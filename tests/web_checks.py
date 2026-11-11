"""Isolated HTTP checks. No production session or account is used."""
import http.client, os, pathlib, subprocess, tempfile, time, sys
root = pathlib.Path(__file__).resolve().parents[1]
php = os.environ.get('PHP_BIN', 'php')
port = 18765
with tempfile.TemporaryDirectory() as sessions:
    server = subprocess.Popen([php, '-d', 'session.save_path=' + sessions, '-S', f'127.0.0.1:{port}', '-t', str(root/'public')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        time.sleep(0.6)
        def request(path, method='GET', body=None, sid=None):
            c = http.client.HTTPConnection('127.0.0.1', port)
            headers = {'Content-Type': 'application/x-www-form-urlencoded'}
            if sid: headers['Cookie'] = 'auth_session=' + sid
            c.request(method, path, body, headers)
            r = c.getresponse()
            data = r.read().decode(errors='replace')
            return r.status, dict(r.getheaders()), data
        def fixture(age=0, role='admin'):
            sid = os.urandom(16).hex()
            code = '''session_name('auth_session'); session_id($argv[1]); session_start(); $_SESSION=['user'=>['id'=>1,'username'=>'<admin>','role'=>$argv[3]], 'last_activity'=>time()-(int)$argv[2], 'csrf'=>str_repeat('a',64)]; session_write_close();'''
            subprocess.run([php, '-d', 'session.save_path='+sessions, '-r', code, sid, str(age), role], check=True)
            return sid
        status, headers, body = request('/dashboard.php')
        assert status == 303 and headers.get('Location') == '/login.php', 'anonymous dashboard must redirect'
        status, headers, body = request('/login.php')
        assert status == 200 and 'name="password"' in body and 'name="captcha"' in body, 'login form missing'
        assert 'no-store' in headers.get('Cache-Control', ''), 'login must not be cached'
        status, headers, body = request('/login.php', 'POST', 'username[]=admin&password[]=x&csrf[]=x')
        assert status == 200 and 'form has expired' in body, 'malformed POST must fail safely'
        sid = fixture()
        status, headers, body = request('/admin.php', sid=sid)
        assert status == 200 and '&lt;admin&gt;' in body, 'authorized dashboard escapes username'
        assert 'no-store' in headers.get('Cache-Control', ''), 'dashboard must not be cached'
        assert request('/logout.php', sid=sid)[0] == 405, 'GET cannot log out'
        assert request('/logout.php', 'POST', 'csrf=bad', sid)[0] == 403, 'logout requires CSRF'
        assert request('/admin.php', sid=sid)[0] == 200, 'bad logout must preserve session'
        assert request('/logout.php', 'POST', 'csrf='+'a'*64, sid)[0] == 303, 'logout redirects'
        assert request('/dashboard.php', sid=sid)[0] == 303, 'logged-out cookie cannot access dashboard'
        expired = fixture(300)
        status, headers, body = request('/dashboard.php', sid=expired)
        assert status == 303 and headers.get('Location') == '/login.php?expired=1', 'expiry redirects with message'
        assert request('/session.php', sid=fixture(300))[0] == 401, 'status rejects expired session'
        assert request('/session.php', 'POST', 'csrf='+'a'*64, fixture(300))[0] == 401, 'activity cannot revive expiry'
        assert request('/session.php', 'POST', 'csrf=bad', fixture())[0] == 403, 'activity requires CSRF'
        assert request('/session.php', sid=fixture())[0] == 200, 'live status succeeds'
        pages = {'super_admin': ('/super-admin.php', 'Super Admin Dashboard'), 'admin': ('/admin.php', 'Admin Dashboard'), 'user': ('/user.php', 'User Dashboard')}
        for role, (path, title) in pages.items():
            role_sid = fixture(role=role)
            status, headers, body = request('/dashboard.php', sid=role_sid)
            assert status == 303 and headers.get('Location') == path, 'router chooses stored role'
            for other_role, (other_path, other_title) in pages.items():
                status, headers, body = request(other_path, sid=role_sid)
                if other_role == role:
                    assert status == 200 and title in body, 'own dashboard available'
                else:
                    assert status == 403 and other_title not in body, 'cross-role dashboard blocked'
            assert request(path)[0] == 303, 'anonymous role page blocked'
        assert request('/dashboard.php', sid=fixture(role=''))[0] == 303, 'legacy session must reauthenticate'
        assert request('/admin.php?role=admin', sid=fixture(role='user'))[0] == 403, 'query cannot escalate role'
        print('PASS: HTTP security checks and full three-role authorization matrix')
    finally:
        server.terminate()
        server.wait()

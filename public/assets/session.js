const token = document.querySelector('meta[name="csrf-token"]').content;
const content = document.getElementById('protected-content');
let deadline = Date.now() + 300000;
let lastSent = 0;
let pending = false;
let activity = false;
function expired() { content.hidden = true; location.replace('/login.php?expired=1'); }
async function check(renew = false) {
  if (pending) return;
  pending = true;
  try {
    const options = {cache: 'no-store', credentials: 'same-origin'};
    if (renew) {
      options.method = 'POST';
      options.headers = {'Content-Type': 'application/x-www-form-urlencoded'};
      options.body = 'csrf=' + encodeURIComponent(token);
    }
    const response = await fetch('/session.php', options);
    if (response.status === 401 || response.status === 403) { expired(); return; }
    if (!response.ok) throw new Error('unavailable');
    const data = await response.json();
    deadline = Date.now() + data.remaining * 1000;
    document.getElementById('connection-status').textContent = '';
    content.hidden = false;
    if (renew) { lastSent = Date.now(); activity = false; }
  } catch {
    document.getElementById('connection-status').textContent = 'Connection interrupted. Your session still expires automatically.';
  } finally { pending = false; }
}
for (const event of ['pointerdown', 'keydown', 'scroll', 'touchstart']) {
  document.addEventListener(event, eventObject => {
    if (!eventObject.isTrusted || document.hidden) return;
    activity = true;
    if (Date.now() - lastSent > 10000) check(true);
  }, {passive: true});
}
setInterval(() => {
  if (Date.now() >= deadline) { expired(); return; }
  if (activity && !document.hidden && Date.now() - lastSent > 10000) check(true);
}, 1000);
setInterval(() => check(), 15000);
window.addEventListener('pagehide', () => { content.hidden = true; });
window.addEventListener('pageshow', () => { content.hidden = true; check(); });
document.addEventListener('visibilitychange', () => { if (!document.hidden) check(); });

document.getElementById('refresh').addEventListener('click', () => {
  document.getElementById('captcha-image').src = '/captcha.php?r=' + Date.now();
  document.getElementById('captcha').value = '';
  document.getElementById('captcha').focus();
});

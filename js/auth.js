// =============================================
// SAKURA AUTH JS
// =============================================

// ─── Page Loader ─────────────────────────────
window.addEventListener('load', () => {
  setTimeout(() => {
    const loader = document.getElementById('pageLoader');
    if (loader) {
      loader.style.opacity = '0';
      loader.style.pointerEvents = 'none';
      setTimeout(() => loader.remove(), 500);
    }
  }, 600);
});

// ─── Tab Switcher ─────────────────────────────
function switchTab(tab) {
  const formLogin    = document.getElementById('formLogin');
  const formRegister = document.getElementById('formRegister');
  const tabLogin     = document.getElementById('tabLogin');
  const tabRegister  = document.getElementById('tabRegister');
  const alertBox     = document.getElementById('alertBox');

  if (!formLogin) return;

  hideAlert();

  if (tab === 'login') {
    formLogin.style.display    = '';
    formRegister.style.display = 'none';
    tabLogin.classList.add('active');
    tabRegister.classList.remove('active');
  } else {
    formLogin.style.display    = 'none';
    formRegister.style.display = '';
    tabLogin.classList.remove('active');
    tabRegister.classList.add('active');
  }
}

// ─── Alert helpers ────────────────────────────
function showAlert(message, type = 'error') {
  const box = document.getElementById('alertBox');
  if (!box) return;
  box.className = `alert alert-${type} show`;
  box.textContent = message;
}
function hideAlert() {
  const box = document.getElementById('alertBox');
  if (box) { box.classList.remove('show'); }
}

// ─── Login (NIS) ──────────────────────────────
async function handleLogin(e) {
  e.preventDefault();
  hideAlert();

  const btn  = document.getElementById('btnLogin');
  const nis  = document.getElementById('loginNis').value.trim();
  const pass = document.getElementById('loginPassword').value;

  btn.disabled    = true;
  btn.textContent = 'Memproses…';

  const fd = new FormData();
  fd.append('action', 'login');
  fd.append('nis', nis);
  fd.append('password', pass);

  try {
    const res  = await fetch('auth.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      showAlert(data.message, 'success');
      btn.textContent = 'Berhasil ✓';
      setTimeout(() => { window.location.href = data.redirect; }, 800);
    } else {
      showAlert(data.message, 'error');
      btn.disabled    = false;
      btn.textContent = 'Masuk 入る';
    }
  } catch (err) {
    showAlert('Terjadi kesalahan. Coba lagi.', 'error');
    btn.disabled    = false;
    btn.textContent = 'Masuk 入る';
  }
}

// ─── Register ─────────────────────────────────
async function handleRegister(e) {
  e.preventDefault();
  hideAlert();

  const btn     = document.getElementById('btnRegister');
  const name    = document.getElementById('regName').value.trim();
  const nis     = document.getElementById('regNis').value.trim();
  const email   = document.getElementById('regEmail').value.trim();
  const pass    = document.getElementById('regPassword').value;
  const confirm = document.getElementById('regConfirm').value;

  if (pass !== confirm) {
    showAlert('Konfirmasi password tidak cocok.', 'error');
    return;
  }

  btn.disabled    = true;
  btn.textContent = 'Memproses…';

  const fd = new FormData();
  fd.append('action',           'register');
  fd.append('name',             name);
  fd.append('nis',              nis);
  fd.append('email',            email);
  fd.append('password',         pass);
  fd.append('confirm_password', confirm);

  try {
    const res  = await fetch('auth.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      showAlert(data.message, 'success');
      btn.textContent = 'Berhasil ✓';
      setTimeout(() => switchTab('login'), 1500);
    } else {
      showAlert(data.message, 'error');
    }
    btn.disabled    = false;
    if (!data.success) btn.textContent = 'Buat Akun 作る';
  } catch (err) {
    showAlert('Terjadi kesalahan. Coba lagi.', 'error');
    btn.disabled    = false;
    btn.textContent = 'Buat Akun 作る';
  }
}

// ─── Logout ───────────────────────────────────
async function handleLogout() {
  const fd = new FormData();
  fd.append('action', 'logout');

  try {
    const res  = await fetch('auth.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.redirect) window.location.href = data.redirect;
  } catch (err) {
    window.location.href = 'index.php';
  }
}
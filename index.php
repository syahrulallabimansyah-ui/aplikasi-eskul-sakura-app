<?php
require_once 'config.php';
startSecureSession();

// Jika sudah login, langsung ke beranda
if (isLoggedIn()) {
    header('Location: beranda.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Masuk</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* Extra subtle floating kanji background */
    .bg-kanji {
      position: fixed;
      font-family: 'Noto Serif JP', serif;
      color: rgba(201,169,110,0.04);
      pointer-events: none;
      z-index: 0;
      user-select: none;
      animation: floatKanji 20s ease-in-out infinite;
    }
    @keyframes floatKanji {
      0%, 100% { transform: translateY(0) rotate(-5deg); }
      50%       { transform: translateY(-20px) rotate(5deg); }
    }
    .side-torii {
      position: fixed;
      top: 0;
      bottom: 0;
      width: 4px;
      background: linear-gradient(180deg, transparent 0%, var(--torii) 20%, var(--torii) 80%, transparent 100%);
      opacity: 0.25;
    }
    .side-torii.left  { left: 28px; }
    .side-torii.right { right: 28px; }

    /* Info / Peraturan */
    .info-content {
      text-align: left;
      max-height: 360px;
      overflow-y: auto;
      padding-right: 6px;
      scrollbar-width: none;       /* Firefox */
      -ms-overflow-style: none;    /* IE/Edge */
    }
    .info-content::-webkit-scrollbar {
      display: none;               /* Chrome, Safari */
    }
    .info-content h3 {
      margin: 0 0 8px;
      font-size: 1rem;
      color: var(--torii);
    }
    .info-content ol,
    .info-content ul {
      margin: 0 0 16px;
      padding-left: 20px;
      font-size: 0.92rem;
      line-height: 1.6;
    }
    .info-content li {
      margin-bottom: 6px;
    }
    .btn-whatsapp {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      width: 100%;
      padding: 12px 16px;
      margin-top: 8px;
      border: none;
      border-radius: 10px;
      background: #25D366;
      color: #fff;
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none;
      transition: filter 0.2s ease, transform 0.2s ease;
    }
    .btn-whatsapp:hover {
      filter: brightness(0.95);
      transform: translateY(-1px);
    }
    .btn-whatsapp svg {
      width: 20px;
      height: 20px;
      fill: #fff;
      flex-shrink: 0;
    }
  </style>
</head>
<body>
  <!-- Loader -->
  <div class="page-loader" id="pageLoader">
    <span class="loader-kanji">桜</span>
  </div>

  <!-- Backgrounds -->
  <div class="asanoha-bg"></div>
  <div class="side-torii left"></div>
  <div class="side-torii right"></div>

  <!-- Floating Kanji decoration -->
  <span class="bg-kanji" style="font-size:12rem; top:5%; left:2%; animation-duration:25s;">和</span>
  <span class="bg-kanji" style="font-size:8rem; bottom:8%; right:4%; animation-duration:18s; animation-delay:-8s;">美</span>
  <span class="bg-kanji" style="font-size:6rem; top:40%; right:6%; animation-duration:22s; animation-delay:-4s;">心</span>

  <!-- Sakura petals -->
  <div id="petals"></div>

  <!-- Theme Toggle (fixed) -->
  <button class="theme-toggle" onclick="toggleTheme()" title="Mode Terang" style="position:fixed; top:20px; right:20px; z-index:10;">☀️</button>

  <!-- Auth Card -->
  <div class="auth-page">
    <div class="auth-card fade-up">
      <div class="torii-accent"></div>

      <!-- Brand -->
      <div class="auth-brand">
        <span class="kanji">桜</span>
        <span class="brand-name">Sakura App</span>
        <div class="brand-divider"></div>
      </div>

      <!-- Tab Switcher -->
      <div class="tab-switcher">
        <button class="tab-btn active" id="tabLogin" onclick="switchTab('login')">Masuk</button>
        <button class="tab-btn" id="tabRegister" onclick="switchTab('register')">Info</button>
      </div>

      <!-- Alert -->
      <div class="alert" id="alertBox"></div>

      <!-- ── FORM LOGIN ── -->
      <form id="formLogin" onsubmit="handleLogin(event)">
        <div class="form-group">
          <label class="form-label" for="loginNis">NIS / Nomor Induk</label>
          <input class="form-input" type="text" id="loginNis" name="nis"
                 placeholder="Contoh: 2025001" required autocomplete="username">
        </div>
        <div class="form-group">
          <label class="form-label" for="loginPassword">Kata Sandi</label>
          <input class="form-input" type="password" id="loginPassword" name="password"
                 placeholder="••••••••" required autocomplete="current-password">
        </div>
        <button class="btn-primary" type="submit" id="btnLogin">
          Masuk 入る
        </button>
      </form>

      <!-- ── PANEL INFO / PERATURAN ── -->
      <div id="formRegister" style="display:none;">
        <div class="info-content">
          <h3>📋 Peraturan Penggunaan</h3>
          <ol>
            <li>Akun hanya dibuat oleh Admin menggunakan NIS resmi anggota.</li>
            <li>Jaga kerahasiaan NIS dan kata sandi, jangan dibagikan ke orang lain.</li>
            <li>Gunakan aplikasi ini dengan sopan dan sesuai tujuan pembelajaran.</li>
            <li>Kerjakan ujian, tugas, dan hafalan secara mandiri dan jujur.</li>
            <li>Segera hubungi Admin jika lupa kata sandi atau menemukan masalah.</li>
          </ol>

          <h3>🆕 Belum Punya Akun?</h3>
          <ul>
            <li>Hubungi Admin untuk pendaftaran anggota baru.</li>
            <li>Siapkan nama lengkap dan data diri yang diperlukan.</li>
            <li>Admin akan membuatkan NIS dan kata sandi untuk Anda.</li>
          </ul>
        </div>

        <!-- Ganti 628XXXXXXXXXX dengan nomor WhatsApp tujuan -->
        <a class="btn-whatsapp" href="https://wa.me/6283829165208?text=Halo%20Admin%2C%20saya%20ingin%20mendaftar%20sebagai%20anggota%20baru%20di%20Sakura%20App."
           target="_blank" rel="noopener noreferrer">
          <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-1.746-.873-2.892-1.555-4.043-3.523-.305-.524.305-.487.873-1.62.099-.198.05-.371-.05-.52-.099-.149-.669-1.612-.917-2.207-.242-.579-.487-.5-.67-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.05 3.13 4.97 4.27 2.92 1.14 2.92.76 3.45.71.53-.05 1.758-.718 2.006-1.413.248-.694.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347z"/>
            <path d="M12 2C6.477 2 2 6.477 2 12c0 1.93.55 3.73 1.5 5.27L2 22l4.83-1.47A9.96 9.96 0 0 0 12 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a8 8 0 0 1-4.27-1.23l-.31-.18-3.18.97.97-3.1-.2-.32A8 8 0 1 1 12 20z"/>
          </svg>
          Hubungi Admin via WhatsApp
        </a>
      </div>

    </div>
  </div>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/petals.js"></script>
</body>
</html>
<?php
require_once 'config.php';
require_once 'exam_helper.php';
requireLogin();

$user = getCurrentUser();
if (!$user) { session_destroy(); header('Location: index.php'); exit; }
if ($user['role'] === 'admin') { header('Location: ujian_admin.php'); exit; }

$initial = strtoupper(mb_substr($user['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Ujian</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-page">

  <div class="page-loader" id="pageLoader">
    <span class="loader-kanji">桜</span>
  </div>

  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <button class="theme-toggle" onclick="toggleTheme()" title="Mode Terang">☀️</button>
    <a href="beranda.php" class="topbar-back" style="border: 2px solid #99711b; padding: 8px 12px; border-radius: 8px; text-decoration: none; display: inline-block;">← Beranda</a>
  </header>

  <main class="dashboard-main">

    <section class="welcome-section fade-up">
      <span class="welcome-kanji">試験</span>
      <h1 class="welcome-title">Ujian Tersedia</h1>
      <p class="welcome-sub">Pilih ujian yang ingin kamu kerjakan. Pastikan kamu sudah memiliki token dari admin.</p>
      <div class="section-divider"></div>
    </section>

    <div id="examListContainer" class="fade-up delay-1">
      <div class="empty-state"><span class="icon">⏳</span>Memuat ujian...</div>
    </div>

    <section class="welcome-section fade-up delay-2" id="expiredSection" style="display:none; margin-top:48px;">
      <span class="welcome-kanji" style="font-size:1.8rem;">⏳</span>
      <h2 class="welcome-title" style="font-size:1.4rem;">Ujian yang Telah Kadaluarsa</h2>
      <p class="welcome-sub">Token untuk ujian berikut sudah melewati batas waktu yang ditentukan admin. Hubungi admin untuk mendapatkan token baru.</p>
      <div class="section-divider"></div>
    </section>

    <div id="examExpiredContainer" class="fade-up delay-2" style="display:none;"></div>

  </main>

  <!-- ═══ MODAL: Token Ujian ═══ -->
  <div class="modal-overlay" id="tokenModal">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal('tokenModal')">×</button>
      <h3>Masukkan Token Ujian</h3>
      <p style="color:var(--mist); font-size:0.88rem; margin-bottom:20px; line-height:1.6;">
        Masukkan token yang diberikan oleh admin untuk mengikuti ujian ini.
      </p>
      <form id="tokenForm">
        <input type="hidden" id="tokenExamId" value="">
        <div class="form-group">
          <label class="form-label">Token Ujian</label>
          <input type="text" class="form-input" id="tokenInput" placeholder="Contoh: A1B2C3" style="text-transform:uppercase; letter-spacing:0.2em; text-align:center; font-family:'Cinzel',serif; font-size:1.1rem;" maxlength="20" required>
        </div>
        <button type="submit" class="btn-primary">Verifikasi Token</button>
      </form>
    </div>
  </div>

  <!-- ═══ MODAL: Motivasi ═══ -->
  <div class="modal-overlay motivation-modal" id="motivationModal">
    <div class="modal-box">
      <div class="motivation-icon">🌸</div>
      <h3>Sebelum Kamu Mulai...</h3>
      <p class="motivation-text" id="motivationText"></p>
      <button class="btn-primary" onclick="proceedToExam()">Mulai Ujian Sekarang</button>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/petals.js"></script>
  <script src="js/ujian_user.js"></script>
</body>
</html>
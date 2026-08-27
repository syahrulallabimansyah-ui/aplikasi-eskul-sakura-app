<?php
require_once 'config.php';
require_once 'exam_helper.php';
requireLogin();

$user = getCurrentUser();
if (!$user) { session_destroy(); header('Location: index.php'); exit; }
if ($user['role'] === 'admin') { header('Location: ujian_admin.php'); exit; }

$examId = (int)($_GET['exam_id'] ?? 0);
if (!$examId) { header('Location: ujian.php'); exit; }

// Pastikan user memiliki attempt yang aktif (atau sudah selesai untuk lihat hasil)
$db = getDB();
$stmt = $db->prepare("SELECT a.*, e.title, e.description, e.duration_minutes, e.status as exam_status FROM exam_attempts a JOIN exams e ON e.id = a.exam_id WHERE a.exam_id = ? AND a.user_id = ?");
$stmt->execute([$examId, $user['id']]);
$attempt = $stmt->fetch();

if (!$attempt || $attempt['exam_status'] !== 'published') {
    header('Location: ujian.php');
    exit;
}

$initial = strtoupper(mb_substr($user['name'], 0, 1));
$isFinished = $attempt['status'] === 'finished';
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — <?= htmlspecialchars($attempt['title']) ?></title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* Mencegah seleksi teks selama ujian berlangsung */
    body.exam-active { user-select: none; -webkit-user-select: none; }
    body.exam-active img { pointer-events: none; }
  </style>
</head>
<body class="dashboard-page <?= $isFinished ? '' : 'exam-active' ?>">

  <div class="page-loader" id="pageLoader">
    <span class="loader-kanji">桜</span>
  </div>

  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <?php if ($isFinished): ?>
    <!-- ═══ HASIL UJIAN ═══ -->
    <header class="topbar">
      <div class="topbar-brand">桜 Sakura</div>
      <button class="theme-toggle" onclick="toggleTheme()" title="Mode Terang">☀️</button>
      <div class="topbar-right">
        <a href="ujian.php" class="nav-link">← Kembali ke Daftar Ujian</a>
      </div>
    </header>

    <main class="dashboard-main">
      <div class="profile-card fade-up">
        <div class="result-card">
          <span class="welcome-kanji" style="display:block; margin-bottom:8px;">結果</span>
          <h1 class="welcome-title"><?= htmlspecialchars($attempt['title']) ?></h1>
          <div class="result-score-label">Nilai Akhir Kamu</div>
          <div class="result-score"><?= htmlspecialchars((string)$attempt['score']) ?></div>
          <div class="result-stats">
            <div class="result-stat-item">
              <div class="result-stat-number"><?= (int)$attempt['total_correct'] ?></div>
              <div class="result-stat-label">Jawaban Benar</div>
            </div>
            <div class="result-stat-item">
              <div class="result-stat-number"><?= (int)$attempt['total_questions'] ?></div>
              <div class="result-stat-label">Total Soal</div>
            </div>
            <div class="result-stat-item">
              <div class="result-stat-number"><?= (int)$attempt['total_questions'] - (int)$attempt['total_correct'] ?></div>
              <div class="result-stat-label">Jawaban Salah</div>
            </div>
          </div>
          <div class="section-divider"></div>
          <a href="ujian.php" class="btn-primary" style="display:inline-block; width:auto; padding:14px 32px; text-decoration:none; margin-top:8px;">Kembali ke Daftar Ujian</a>
        </div>
      </div>
    </main>

  <?php else: ?>
    <!-- ═══ HALAMAN PENGERJAAN UJIAN ═══ -->
    <header class="exam-take-header">
      <div class="exam-take-title"><?= htmlspecialchars($attempt['title']) ?></div>
      <div class="exam-timer" id="examTimer">⏱ --:--</div>
    </header>

    <main class="exam-take-main">
      <div class="exam-question-panel" id="questionPanel">
        <div class="empty-state"><span class="icon">⏳</span>Memuat soal...</div>
      </div>

      <aside class="exam-navigator">
        <h4>Navigasi Soal</h4>
        <div class="nav-grid" id="navGrid"></div>
        <div class="exam-legend">
          <div><span class="legend-dot" style="background:rgba(74,124,89,0.4); border:1px solid var(--bamboo);"></span> Sudah dijawab</div>
          <div><span class="legend-dot" style="background:rgba(201,169,110,0.1); border:1px solid var(--gold);"></span> Sedang dilihat</div>
          <div><span class="legend-dot" style="background:rgba(255,255,255,0.02); border:1px solid rgba(201,169,110,0.2);"></span> Belum dijawab</div>
        </div>
        <button class="btn-primary btn-bamboo" style="margin-top:20px;" onclick="confirmFinishExam()">Selesaikan Ujian</button>
      </aside>
    </main>

    <!-- ═══ MODAL: Konfirmasi Selesai ═══ -->
    <div class="modal-overlay" id="finishModal">
      <div class="modal-box">
        <h3>Selesaikan Ujian?</h3>
        <p style="color:var(--mist); font-size:0.9rem; line-height:1.7; margin-bottom:20px;" id="finishModalText">
          Apakah kamu yakin ingin menyelesaikan ujian ini? Setelah disubmit, jawaban tidak dapat diubah kembali.
        </p>
        <div style="display:flex; gap:12px;">
          <button class="btn-primary btn-outline" style="flex:1;" onclick="closeModal('finishModal')">Batal</button>
          <button class="btn-primary btn-bamboo" style="flex:1;" onclick="submitFinishExam()">Ya, Selesaikan</button>
        </div>
      </div>
    </div>

    <!-- ═══ OVERLAY: Peringatan Anti-Cheat ═══ -->
    <div class="cheat-warning-overlay" id="cheatWarningOverlay">
      <div class="cheat-warning-box">
        <span class="icon">⚠️</span>
        <h3>Peringatan: Jangan Berpindah Tab!</h3>
        <p>Kamu meninggalkan halaman ujian. Tindakan ini tercatat secara otomatis.</p>
        <p>Jumlah peringatan: <span class="cheat-count" id="cheatCount">0</span></p>
        <p style="margin-top:16px;">Klik tombol di bawah untuk melanjutkan ujian.</p>
        <button class="btn-primary" style="margin-top:12px;" onclick="dismissCheatWarning()">Lanjutkan Ujian</button>
      </div>
    </div>

    <div class="image-lightbox" id="imageLightbox" onclick="this.classList.remove('active')">
      <img id="lightboxImg" src="">
    </div>
  <?php endif; ?>

  <div class="toast" id="toast"></div>

  <script src="js/theme.js"></script>
  <script src="js/petals.js"></script>
  <?php if (!$isFinished): ?>
  <script>
    const EXAM_ID = <?= (int)$examId ?>;
  </script>
  <script src="js/ujian_kerjakan.js"></script>
  <?php endif; ?>
  <script>
    setTimeout(() => {
      const loader = document.getElementById('pageLoader');
      if (loader) { loader.style.opacity = '0'; setTimeout(() => loader.style.display = 'none', 500); }
    }, 300);
  </script>
</body>
</html>
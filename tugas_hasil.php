<?php
/**
 * tugas_hasil.php — Lihat Hasil Pengumpulan Tugas (Admin)
 * Sakura App
 */
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header('Location: beranda.php');
    exit;
}

$db = getDB();

$tugasId = isset($_GET['tugas_id']) ? (int)$_GET['tugas_id'] : 0;
if ($tugasId <= 0) {
    header('Location: tugas_admin.php');
    exit;
}

$msg = '';
$err = '';

/* ------------------------------------------------------------------ */
/*  HANDLE PENILAIAN                                                    */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_nilai') {
    $submissionId = (int)($_POST['submission_id'] ?? 0);
    $nilaiRaw     = trim($_POST['nilai'] ?? '');
    $feedback     = trim($_POST['feedback'] ?? '');

    if ($submissionId <= 0) {
        $err = 'Submission tidak valid.';
    } elseif ($nilaiRaw === '' || !is_numeric($nilaiRaw) || $nilaiRaw < 0 || $nilaiRaw > 100) {
        $err = 'Nilai harus berupa angka 0 - 100.';
    } else {
        $stmt = $db->prepare("
            UPDATE tugas_submissions
            SET nilai = ?, feedback = ?, graded_at = NOW()
            WHERE id = ? AND tugas_id = ?
        ");
        $stmt->execute([round((float)$nilaiRaw, 2), $feedback ?: null, $submissionId, $tugasId]);
        $msg = 'Nilai berhasil disimpan.';
    }
}

// Detail tugas
$stmtT = $db->prepare("SELECT * FROM tugas WHERE id = ?");
$stmtT->execute([$tugasId]);
$tugas = $stmtT->fetch();
if (!$tugas) {
    header('Location: tugas_admin.php');
    exit;
}

// Semua user yg sudah submit
$submissions = $db->prepare("
    SELECT s.*, u.name AS user_name, u.email AS user_email
    FROM tugas_submissions s
    JOIN users u ON u.id = s.user_id
    WHERE s.tugas_id = ?
    ORDER BY s.submitted_at DESC
");
$submissions->execute([$tugasId]);
$submissions = $submissions->fetchAll();

// Label helper
function tipeLabel(string $t): string {
    return match($t) {
        'foto'       => '📷 Foto',
        'video'      => '🎥 Video',
        'foto_video' => '📷🎥 Foto & Video',
        default      => $t,
    };
}

// Extension → icon
function fileIcon(?string $path): string {
    if (!$path) return '—';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg','jpeg','png','gif','webp']) ? '🖼' : '🎞';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Hasil Tugas</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .th-wrap { max-width: 960px; margin: 0 auto; padding: 24px 16px 80px; }

    /* info card */
    .th-info-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 18px;
      padding: 24px 26px;
      margin-bottom: 24px;
      box-shadow: var(--card-shadow);
    }
    .th-info-card h2 {
      margin: 0 0 12px; font-size: 1.1rem; color: var(--torii);
      padding-bottom: 14px; border-bottom: 1px solid var(--card-border);
    }
    .th-info-meta { font-size: .82rem; color: var(--text-muted); display: flex; gap: 16px; flex-wrap: wrap; margin-top: 6px; }
    .th-info-desc { margin-top: 12px; font-size: .9rem; line-height: 1.7; color: var(--text-main); white-space: pre-wrap; }

    /* submissions table card */
    .th-table-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 18px;
      overflow: hidden;
      box-shadow: var(--card-shadow);
    }
    .th-table-head {
      padding: 18px 24px;
      border-bottom: 1px solid var(--card-border);
    }
    .th-table-head h2 { margin: 0; font-size: 1rem; }

    table.th-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
    table.th-table th {
      text-align: left; padding: 12px 16px;
      background: rgba(0,0,0,.03);
      color: var(--text-muted); font-size: .76rem; font-weight: 700; letter-spacing: .05em;
      border-bottom: 1px solid var(--card-border);
    }
    table.th-table td { padding: 13px 16px; border-bottom: 1px solid rgba(0,0,0,.05); vertical-align: top; }
    table.th-table tr:last-child td { border-bottom: none; }
    table.th-table tr:hover td { background: rgba(0,0,0,.025); }

    .user-name { font-weight: 700; }
    .user-email { font-size: .78rem; color: var(--text-muted); }

    .file-link {
      display: inline-flex; align-items: center; gap: 5px;
      background: rgba(0,0,0,.06); border-radius: 8px; padding: 5px 12px;
      text-decoration: none; color: var(--text-main); font-size: .8rem;
      transition: background .2s, transform .15s; margin: 2px 0;
    }
    .file-link:hover { background: rgba(183,75,75,.12); color: var(--torii); transform: translateY(-1px); }

    .catatan-text { font-size: .82rem; color: var(--text-muted); font-style: italic; max-width: 200px; }

    .empty-state { text-align: center; padding: 48px 20px; color: var(--text-muted); }
    .empty-state .emo { font-size: 3rem; margin-bottom: 10px; }

    .alert {
      padding: 13px 18px; border-radius: 12px; margin-bottom: 16px;
      font-size: .9rem; font-weight: 600;
      display: flex; align-items: center; gap: 8px;
      border: 1px solid transparent;
    }
    .alert-ok  { background: rgba(74,124,89,.15); color: var(--bamboo); border-color: rgba(74,124,89,.3); }
    .alert-err { background: rgba(183,75,75,.12); color: var(--torii); border-color: rgba(183,75,75,.25); }

    /* nilai form */
    .nilai-form { display: flex; flex-direction: column; gap: 6px; min-width: 160px; }
    .nilai-row { display: flex; gap: 6px; align-items: center; }
    .nilai-row input[type="number"] {
      width: 70px; background: var(--input-bg, rgba(0,0,0,.06));
      border: 1px solid var(--card-border); border-radius: 8px;
      padding: 6px 8px; color: var(--text-main); font-size: .85rem; font-weight: 700;
      text-align: center;
    }
    .nilai-row input[type="number"]:focus {
      outline: none; border-color: var(--torii);
      box-shadow: 0 0 0 3px rgba(0,0,0,.04);
    }
    .nilai-form textarea {
      background: var(--input-bg, rgba(0,0,0,.06));
      border: 1px solid var(--card-border); border-radius: 8px;
      padding: 6px 8px; color: var(--text-main); font-size: .78rem;
      font-family: inherit; resize: vertical; min-height: 36px;
    }
    .nilai-form textarea:focus {
      outline: none; border-color: var(--torii);
      box-shadow: 0 0 0 3px rgba(0,0,0,.04);
    }
    .btn-nilai {
      background: var(--torii); color: #fff; border: none; border-radius: 8px;
      padding: 6px 14px; font-size: .8rem; font-weight: 700; cursor: pointer;
      transition: opacity .2s;
    }
    .btn-nilai:hover { opacity: .9; }
    .nilai-badge {
      display: inline-flex; align-items: center; gap: 4px;
      border-radius: 20px; padding: 4px 12px; font-size: .8rem; font-weight: 800;
      margin-bottom: 6px;
    }
    .nilai-badge.high { background: rgba(74,124,89,.15); color: var(--bamboo); }
    .nilai-badge.mid  { background: rgba(196,160,69,.18); color: var(--gold); }
    .nilai-badge.low  { background: rgba(183,75,75,.12); color: var(--torii); }
    .nilai-empty { font-size: .78rem; color: var(--text-muted); }

    .back-link {
      display: inline-flex; align-items: center; gap: 6px;
      color: var(--torii); text-decoration: none; font-size: .88rem; font-weight: 600;
      margin-bottom: 20px; transition: gap .2s, opacity .2s;
    }
    .back-link:hover { opacity: .7; gap: 10px; }

    .stat-pills { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 10px; }
    .pill { background: rgba(0,0,0,.06); border-radius: 20px; padding: 5px 14px; font-size: .8rem; color: var(--text-muted); font-weight: 600; }
    .pill.green { background: rgba(74,124,89,.15); color: var(--bamboo); }
    .pill.red   { background: rgba(183,75,75,.12); color: var(--torii); }

    /* lightbox */
    #lightbox {
      display: none; position: fixed; inset: 0; background: rgba(0,0,0,.85);
      z-index: 9999; align-items: center; justify-content: center; flex-direction: column;
    }
    #lightbox.open { display: flex; }
    #lightbox img, #lightbox video {
      max-width: 90vw; max-height: 80vh; border-radius: 12px;
      box-shadow: 0 8px 40px rgba(0,0,0,.5);
    }
    #lightbox .lb-close {
      position: absolute; top: 20px; right: 24px;
      color: #fff; font-size: 2rem; cursor: pointer; line-height: 1;
      width: 40px; height: 40px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      background: rgba(255,255,255,.1); transition: background .2s;
    }
    #lightbox .lb-close:hover { background: rgba(255,255,255,.2); }
    #lightbox .lb-caption { color: #fff; margin-top: 12px; font-size: .85rem; opacity: .75; }

    @media(max-width:600px) {
      .th-info-card, .th-table-head { padding-left: 18px; padding-right: 18px; }
      table.th-table th:nth-child(4),
      table.th-table td:nth-child(4) { display: none; }
      table.th-table th:nth-child(5),
      table.th-table td:nth-child(5) { display: none; }
    }
  </style>
</head>
<body class="dashboard-page">
  <div class="page-loader" id="pageLoader"><span class="loader-kanji">桜</span></div>
  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <button class="theme-toggle" onclick="toggleTheme()" title="Mode Terang">☀️</button>
  </header>

  <main class="dashboard-main">
    <section class="welcome-section fade-up">
      <span class="welcome-kanji">結果</span>
      <h1 class="welcome-title">Hasil Pengumpulan Tugas</h1>
      <p class="welcome-sub">Rekap file yang telah dikumpulkan siswa</p>
      <div class="section-divider"></div>
    </section>

    <div class="th-wrap fade-up delay-1">
      <a href="tugas_admin.php" class="back-link">← Kembali ke Kelola Tugas</a>

      <?php if ($msg): ?>
        <div class="alert alert-ok"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
      <?php if ($err): ?>
        <div class="alert alert-err"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>


      <!-- Info Tugas -->
      <div class="th-info-card">
        <h2>📋 <?= htmlspecialchars($tugas['judul']) ?></h2>
        <div class="stat-pills">
          <span class="pill"><?= tipeLabel($tugas['tipe_upload']) ?></span>
          <span class="pill <?= $tugas['status'] === 'published' ? 'green' : 'red' ?>">
            <?= ucfirst($tugas['status']) ?>
          </span>
          <span class="pill green"><?= count($submissions) ?> Pengumpulan</span>
          <?php
            $graded = array_filter($submissions, fn($s) => $s['nilai'] !== null);
            if (!empty($graded)):
              $avgNilai = array_sum(array_map(fn($s) => (float)$s['nilai'], $graded)) / count($graded);
          ?>
            <span class="pill">📝 Rata-rata Nilai: <?= number_format($avgNilai, 1) ?> (<?= count($graded) ?>/<?= count($submissions) ?> dinilai)</span>
          <?php endif; ?>
        </div>
        <?php if ($tugas['deskripsi']): ?>
          <div class="th-info-desc"><?= htmlspecialchars($tugas['deskripsi']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Tabel Hasil -->
      <div class="th-table-card">
        <div class="th-table-head">
          <h2>📥 Pengumpulan User (<?= count($submissions) ?>)</h2>
        </div>

        <?php if (empty($submissions)): ?>
          <div class="empty-state">
            <div class="emo">📭</div>
            <p>Belum ada yang mengumpulkan tugas ini.</p>
          </div>
        <?php else: ?>
          <div style="overflow-x:auto;">
          <table class="th-table">
            <thead>
              <tr>
                <th>#</th>
                <th>USER</th>
                <th>FILE YANG DIKUMPULKAN</th>
                <th>CATATAN</th>
                <th>WAKTU PENGUMPULAN</th>
                <th>PENILAIAN</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($submissions as $i => $s): ?>
              <tr>
                <td><?= $i + 1 ?></td>
                <td>
                  <div class="user-name"><?= htmlspecialchars($s['user_name']) ?></div>
                  <div class="user-email"><?= htmlspecialchars($s['user_email']) ?></div>
                </td>
                <td>
                  <?php if ($s['file_foto']): ?>
                    <a href="#" class="file-link"
                       onclick="openLightbox('<?= htmlspecialchars($s['file_foto']) ?>','foto'); return false;">
                      🖼 Lihat Foto
                    </a><br>
                  <?php endif; ?>
                  <?php if ($s['file_video']): ?>
                    <a href="#" class="file-link"
                       onclick="openLightbox('<?= htmlspecialchars($s['file_video']) ?>','video'); return false;">
                      🎞 Lihat Video
                    </a><br>
                  <?php endif; ?>
                  <?php if (!$s['file_foto'] && !$s['file_video']): ?>
                    <span style="color:var(--text-muted)">—</span>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="catatan-text">
                    <?= $s['catatan'] ? htmlspecialchars(mb_strimwidth($s['catatan'], 0, 80, '…')) : '<em>—</em>' ?>
                  </div>
                </td>
                <td><?= date('d F Y, H:i', strtotime($s['submitted_at'])) ?></td>
                <td>
                  <?php
                    $nilai = $s['nilai'];
                    if ($nilai !== null) {
                      $nClass = $nilai >= 80 ? 'high' : ($nilai >= 60 ? 'mid' : 'low');
                      echo '<span class="nilai-badge ' . $nClass . '">📝 ' . number_format((float)$nilai, 1) . '</span>';
                    } else {
                      echo '<div class="nilai-empty">Belum dinilai</div>';
                    }
                  ?>
                  <form method="POST" class="nilai-form" style="margin-top:8px;">
                    <input type="hidden" name="action" value="save_nilai">
                    <input type="hidden" name="submission_id" value="<?= $s['id'] ?>">
                    <div class="nilai-row">
                      <input type="number" name="nilai" min="0" max="100" step="0.1"
                             placeholder="0-100"
                             value="<?= $nilai !== null ? htmlspecialchars((string)round((float)$nilai,1)) : '' ?>">
                      <button type="submit" class="btn-nilai">Simpan</button>
                    </div>
                    <textarea name="feedback" placeholder="Feedback (opsional)"><?= htmlspecialchars($s['feedback'] ?? '') ?></textarea>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          </div>
        <?php endif; ?>
      </div>
    </div><!-- /th-wrap -->

    <!-- Bottom Action Bar -->
    <div class="bottom-action-bar fade-up delay-3">
      <div class="bab-profile">
        <div class="avatar bab-avatar"><?= strtoupper(mb_substr($user['name'],0,1)) ?></div>
        <div class="bab-user-info">
          <div class="bab-name"><?= htmlspecialchars($user['name']) ?></div>
          <span class="bab-role role-admin">⛩ Administrator</span>
        </div>
      </div>
      <div class="bab-actions">
        <a href="tugas_admin.php" class="bab-btn bab-btn-primary">📋 Kelola Tugas</a>
        <a href="beranda.php"     class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,var(--bamboo),#3a6347);">🏠 Beranda</a>
        <button class="bab-btn bab-btn-logout" onclick="handleLogout()">🚪 Keluar 出る</button>
      </div>
    </div>
  </main>

  <!-- Lightbox -->
  <div id="lightbox">
    <span class="lb-close" onclick="closeLightbox()">✕</span>
    <div id="lb-content"></div>
    <div class="lb-caption" id="lb-caption"></div>
  </div>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/petals.js"></script>
  <script>
    function handleLogout() {
      const fd = new FormData(); fd.append('action', 'logout');
      fetch('auth.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => { if (d.redirect) location.href = d.redirect; });
    }

    function openLightbox(src, type) {
      const box = document.getElementById('lightbox');
      const content = document.getElementById('lb-content');
      const caption = document.getElementById('lb-caption');
      if (type === 'foto') {
        content.innerHTML = `<img src="${src}" alt="Foto tugas">`;
        caption.textContent = 'Foto pengumpulan tugas';
      } else {
        content.innerHTML = `<video src="${src}" controls style="max-width:90vw;max-height:70vh;border-radius:12px;"></video>`;
        caption.textContent = 'Video pengumpulan tugas';
      }
      box.classList.add('open');
    }

    function closeLightbox() {
      document.getElementById('lightbox').classList.remove('open');
      document.getElementById('lb-content').innerHTML = '';
    }

    document.getElementById('lightbox').addEventListener('click', function(e) {
      if (e.target === this) closeLightbox();
    });

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeLightbox();
    });
  </script>
</body>
</html>
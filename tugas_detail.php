<?php
/**
 * tugas_detail.php — Detail Tugas & Form Pengumpulan (User)
 * Sakura App
 */
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (!$user) { session_destroy(); header('Location: index.php'); exit; }
if ($user['role'] === 'admin') { header('Location: tugas_admin.php'); exit; }

$db = getDB();

$tugasId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($tugasId <= 0) { header('Location: tugas.php'); exit; }

// Ambil tugas
$stmt = $db->prepare("SELECT * FROM tugas WHERE id = ? AND status = 'published'");
$stmt->execute([$tugasId]);
$tugas = $stmt->fetch();
if (!$tugas) { header('Location: tugas.php'); exit; }

// Cek submission user
$stmt = $db->prepare("SELECT * FROM tugas_submissions WHERE tugas_id = ? AND user_id = ?");
$stmt->execute([$tugasId, $user['id']]);
$submission = $stmt->fetch();

$msg = '';
$err = '';

/* ------------------------------------------------------------------ */
/*  HANDLE SUBMIT                                                       */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$submission) {
    $catatan    = trim($_POST['catatan'] ?? '');
    $tipe       = $tugas['tipe_upload'];
    $uploadDir  = 'uploads/tugas/';

    // Pastikan folder ada
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

    $pathFoto  = null;
    $pathVideo = null;
    $errUpload = '';

    /* ── Validasi & Upload Foto ── */
    $needFoto  = in_array($tipe, ['foto', 'foto_video']);
    $needVideo = in_array($tipe, ['video', 'foto_video']);

    if ($needFoto) {
        if (empty($_FILES['file_foto']['name'])) {
            $errUpload = 'File foto wajib diunggah.';
        } else {
            $allowedImg = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $mime = mime_content_type($_FILES['file_foto']['tmp_name']);
            if (!in_array($mime, $allowedImg)) {
                $errUpload = 'File foto harus berformat JPG, PNG, GIF, atau WEBP.';
            } elseif ($_FILES['file_foto']['size'] > 10 * 1024 * 1024) {
                $errUpload = 'Ukuran foto maksimal 10 MB.';
            } else {
                $ext = pathinfo($_FILES['file_foto']['name'], PATHINFO_EXTENSION);
                $fname = 'foto_' . $user['id'] . '_' . uniqid() . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['file_foto']['tmp_name'], $uploadDir . $fname)) {
                    $pathFoto = $uploadDir . $fname;
                } else {
                    $errUpload = 'Gagal menyimpan file foto.';
                }
            }
        }
    }

    /* ── Validasi & Upload Video ── */
    if (!$errUpload && $needVideo) {
        if (empty($_FILES['file_video']['name'])) {
            $errUpload = 'File video wajib diunggah.';
        } else {
            $allowedVid = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo'];
            $mime = mime_content_type($_FILES['file_video']['tmp_name']);
            if (!in_array($mime, $allowedVid)) {
                $errUpload = 'File video harus berformat MP4, WEBM, OGG, MOV, atau AVI.';
            } elseif ($_FILES['file_video']['size'] > 100 * 1024 * 1024) {
                $errUpload = 'Ukuran video maksimal 100 MB.';
            } else {
                $ext = pathinfo($_FILES['file_video']['name'], PATHINFO_EXTENSION);
                $fname = 'video_' . $user['id'] . '_' . uniqid() . '.' . strtolower($ext);
                if (move_uploaded_file($_FILES['file_video']['tmp_name'], $uploadDir . $fname)) {
                    $pathVideo = $uploadDir . $fname;
                } else {
                    $errUpload = 'Gagal menyimpan file video.';
                }
            }
        }
    }

    if ($errUpload) {
        // Rollback foto jika video gagal
        if ($pathFoto && file_exists($pathFoto)) unlink($pathFoto);
        $err = $errUpload;
    } else {
        $ins = $db->prepare("
            INSERT INTO tugas_submissions (tugas_id, user_id, catatan, file_foto, file_video)
            VALUES (?, ?, ?, ?, ?)
        ");
        $ins->execute([$tugasId, $user['id'], $catatan ?: null, $pathFoto, $pathVideo]);
        $msg = 'Tugas berhasil dikumpulkan!';

        // Refresh submission
        $stmt2 = $db->prepare("SELECT * FROM tugas_submissions WHERE tugas_id = ? AND user_id = ?");
        $stmt2->execute([$tugasId, $user['id']]);
        $submission = $stmt2->fetch();
    }
}

/* ── Helper ── */
function tipeLabel(string $t): string {
    return match($t) {
        'foto'       => 'Foto',
        'video'      => 'Video',
        'foto_video' => 'Foto & Video',
        default      => $t,
    };
}

// Konversi video URL biasa ke embed
function toEmbedUrl(?string $url): ?string {
    if (!$url) return null;
    // YouTube watch → embed
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    return $url; // sudah embed atau URL lain
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — <?= htmlspecialchars($tugas['judul']) ?></title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .td-wrap { max-width: 800px; margin: 0 auto; padding: 0 16px 80px; }

    /* content card */
    .td-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 18px;
      padding: 28px;
      margin-bottom: 20px;
      box-shadow: var(--card-shadow);
      transition: box-shadow .25s ease;
    }
    .td-card:hover {
      box-shadow: 0 10px 30px -10px rgba(0,0,0,.18), var(--card-shadow);
    }
    .td-card h2 {
      margin: 0 0 16px; font-size: 1.2rem; color: var(--torii);
      display: flex; align-items: center; gap: 8px;
      padding-bottom: 14px; border-bottom: 1px solid var(--card-border);
    }

    /* meta pills */
    .td-meta { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .td-pill {
      background: rgba(0,0,0,.07); border-radius: 20px; padding: 5px 14px;
      font-size: .78rem; color: var(--text-muted); font-weight: 600;
      display: inline-flex; align-items: center; gap: 6px;
      border: 1px solid var(--card-border);
    }

    /* deskripsi */
    .td-desc { font-size: .92rem; line-height: 1.75; color: var(--text-main); white-space: pre-wrap; margin-bottom: 0; }

    /* video embed */
    .td-video-wrap { margin-top: 20px; }
    .td-video-wrap iframe {
      width: 100%; aspect-ratio: 16/9; border-radius: 12px;
      border: none; display: block; box-shadow: var(--card-shadow);
    }

    /* status banner */
    .td-status-banner {
      border-radius: 14px; padding: 18px 22px;
      display: flex; align-items: center; gap: 16px;
      margin-bottom: 20px;
    }
    .td-status-banner.sent   { background: rgba(74,124,89,.12);  border: 1px solid rgba(74,124,89,.3); }
    .td-status-banner.unsent { background: rgba(183,75,75,.08);  border: 1px solid rgba(183,75,75,.2); }
    .td-status-banner .sb-icon {
      flex-shrink: 0; width: 6px; align-self: stretch;
      border-radius: 6px; min-height: 36px;
      background: var(--torii); opacity: .5;
    }
    .td-status-banner.sent .sb-icon { background: var(--bamboo); opacity: .7; }
    .td-status-banner .sb-text h3 { margin: 0 0 2px; font-size: .95rem; }
    .td-status-banner .sb-text p  { margin: 0; font-size: .82rem; color: var(--text-muted); }

    .td-nilai-badge {
      display: inline-flex; align-items: center; gap: 4px;
      background: rgba(74,124,89,.15); color: var(--bamboo);
      border-radius: 20px; padding: 4px 14px; font-size: .85rem; font-weight: 800;
    }
    .td-feedback {
      margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--card-border);
      font-size: .88rem; color: var(--text-muted); display: flex; gap: 10px; align-items: baseline;
    }
    .td-feedback .sub-file-label { border-right: none; padding-right: 0; flex-shrink: 0; font-size: .72rem; font-weight: 700; letter-spacing: .05em; color: var(--text-muted); text-transform: uppercase; }
    .td-feedback em { color: var(--text-main); font-style: italic; }

    /* submission card preview */
    .sub-file-row { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 14px; }
    .sub-file-item {
      background: rgba(0,0,0,.05); border-radius: 12px; padding: 12px 18px;
      font-size: .85rem; display: flex; align-items: center; gap: 10px;
      border: 1px solid var(--card-border);
      transition: background .2s ease;
    }
    .sub-file-item:hover { background: rgba(0,0,0,.08); }
    .sub-file-item a { color: var(--torii); text-decoration: none; font-weight: 600; }
    .sub-file-item a:hover { text-decoration: underline; }
    .sub-file-label {
      font-size: .72rem; font-weight: 700; letter-spacing: .05em;
      color: var(--text-muted); text-transform: uppercase;
      border-right: 1px solid var(--card-border); padding-right: 10px;
    }
    .sub-catatan {
      margin-top: 16px; padding-top: 14px; border-top: 1px solid var(--card-border);
      font-size: .88rem; color: var(--text-muted); display: flex; gap: 10px; align-items: baseline;
    }
    .sub-catatan .sub-file-label { border-right: none; padding-right: 0; flex-shrink: 0; }
    .sub-catatan em { color: var(--text-main); font-style: italic; }

    /* upload form */
    .td-form-card {
      background: var(--card-bg); border: 1px solid var(--card-border);
      border-radius: 18px; padding: 28px; box-shadow: var(--card-shadow);
    }
    .td-form-card h2 {
      margin: 0 0 18px; font-size: 1.05rem; color: var(--text-main);
      display: flex; align-items: center; gap: 8px;
      padding-bottom: 14px; border-bottom: 1px solid var(--card-border);
    }

    .form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 18px; }
    .form-group label { font-size: .82rem; color: var(--text-muted); font-weight: 700; letter-spacing: .03em; }
    .form-group textarea, .form-group input[type="file"] {
      background: var(--input-bg, rgba(0,0,0,.06));
      border: 1px solid var(--card-border);
      border-radius: 10px; padding: 11px 14px;
      color: var(--text-main); font-size: .9rem; font-family: inherit;
      resize: vertical; transition: border-color .2s, box-shadow .2s;
    }
    .form-group input[type="file"] { padding: 9px 12px; cursor: pointer; }
    .form-group textarea:focus,
    .form-group input[type="file"]:focus {
      outline: none; border-color: var(--torii);
      box-shadow: 0 0 0 3px rgba(0,0,0,.04);
    }
    .file-hint { font-size: .75rem; color: var(--text-muted); margin-top: 3px; }

    .btn-submit {
      background: var(--torii); color: #fff; border: none; border-radius: 10px;
      padding: 13px 30px; font-size: .95rem; font-weight: 700; cursor: pointer;
      display: inline-flex; align-items: center; gap: 8px;
      transition: opacity .2s, transform .15s;
      box-shadow: 0 4px 14px -4px rgba(0,0,0,.25);
    }
    .btn-submit:hover { opacity: .9; transform: translateY(-1px); }
    .btn-submit:active { transform: translateY(0); }
    .btn-submit:disabled { opacity: .5; cursor: not-allowed; transform: none; }

    .alert {
      padding: 13px 18px; border-radius: 12px; margin-bottom: 16px;
      font-size: .9rem; font-weight: 600;
      display: flex; align-items: center; gap: 8px;
      border: 1px solid transparent;
    }
    .alert-ok  { background: rgba(74,124,89,.15); color: var(--bamboo); border-color: rgba(74,124,89,.3); }
    .alert-err { background: rgba(183,75,75,.12); color: var(--torii); border-color: rgba(183,75,75,.25); }

    .back-link {
      display: inline-flex; align-items: center; gap: 6px;
      color: var(--torii); text-decoration: none; font-size: .88rem; font-weight: 600;
      margin-bottom: 20px; transition: gap .2s, opacity .2s;
    }
    .back-link:hover { opacity: .7; gap: 10px; }

    /* progress */
    #uploadProgress { display: none; margin-top: 12px; }
    #uploadProgress progress {
      width: 100%; height: 8px; border-radius: 4px; overflow: hidden;
      accent-color: var(--torii);
    }
    #progressText { font-size: .78rem; color: var(--text-muted); margin-top: 4px; }

    /* responsive */
    @media (max-width: 600px) {
      .td-card, .td-form-card { padding: 20px; }
      .td-status-banner { padding: 14px 16px; gap: 12px; }
      .btn-submit { width: 100%; justify-content: center; }
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
    <a href="beranda.php" class="topbar-back" style="border: 2px solid #a1781e; padding: 8px 12px; border-radius: 8px; text-decoration: none; display: inline-block;">← Beranda</a>
  </header>

  <main class="dashboard-main">
    <section class="welcome-section fade-up">
      <span class="welcome-kanji">課題</span>
      <h1 class="welcome-title">Detail Tugas</h1>
      <p class="welcome-sub">Kerjakan dan kumpulkan tugasmu</p>
      <div class="section-divider"></div>
    </section>

    <div class="td-wrap fade-up delay-1">
      <a href="tugas.php" class="back-link">← Kembali ke Daftar Tugas</a>

      <?php if ($msg): ?>
        <div class="alert alert-ok"><?= htmlspecialchars($msg) ?></div>
      <?php endif; ?>
      <?php if ($err): ?>
        <div class="alert alert-err"><?= htmlspecialchars($err) ?></div>
      <?php endif; ?>

      <!-- Status Banner -->
      <?php if ($submission): ?>
        <div class="td-status-banner sent">
          <div class="sb-icon"></div>
          <div class="sb-text">
            <h3 style="color:var(--bamboo)">Tugas Sudah Dikirim</h3>
            <p>Dikumpulkan pada <?= date('d F Y, H:i', strtotime($submission['submitted_at'])) ?></p>
            <?php if ($submission['nilai'] !== null): ?>
              <p style="margin-top:6px;">
                <span class="td-nilai-badge">📝 Nilai: <?= number_format((float)$submission['nilai'], 1) ?></span>
              </p>
            <?php else: ?>
              <p style="margin-top:6px; color:var(--text-muted);">⏳ Menunggu penilaian dari admin.</p>
            <?php endif; ?>
          </div>
        </div>
      <?php else: ?>
        <div class="td-status-banner unsent">
          <div class="sb-icon"></div>
          <div class="sb-text">
            <h3 style="color:var(--torii)">Belum Dikumpulkan</h3>
            <p>Unggah file hasil tugasmu di bawah ini.</p>
          </div>
        </div>
      <?php endif; ?>

      <!-- Konten Tugas -->
      <div class="td-card">
        <h2><?= htmlspecialchars($tugas['judul']) ?></h2>
        <div class="td-meta">
          <span class="td-pill"><?= tipeLabel($tugas['tipe_upload']) ?></span>
          <span class="td-pill">Dibuat <?= date('d F Y', strtotime($tugas['created_at'])) ?></span>
        </div>

        <?php if ($tugas['deskripsi']): ?>
          <div class="td-desc"><?= htmlspecialchars($tugas['deskripsi']) ?></div>
        <?php endif; ?>

        <?php if ($tugas['video_url']): ?>
          <div class="td-video-wrap">
            <iframe
              src="<?= htmlspecialchars(toEmbedUrl($tugas['video_url'])) ?>"
              allowfullscreen
              loading="lazy"
              title="Video Tugas">
            </iframe>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($submission): ?>
        <!-- Preview file yang sudah dikumpulkan -->
        <div class="td-card">
          <h2>File yang Dikumpulkan</h2>
          <div class="sub-file-row">
            <?php if ($submission['file_foto']): ?>
              <div class="sub-file-item">
                <span class="sub-file-label">Foto</span>
                <a href="<?= htmlspecialchars($submission['file_foto']) ?>" target="_blank">Lihat Foto</a>
              </div>
            <?php endif; ?>
            <?php if ($submission['file_video']): ?>
              <div class="sub-file-item">
                <span class="sub-file-label">Video</span>
                <a href="<?= htmlspecialchars($submission['file_video']) ?>" target="_blank">Lihat Video</a>
              </div>
            <?php endif; ?>
          </div>
          <?php if ($submission['catatan']): ?>
            <div class="sub-catatan">
              <span class="sub-file-label">Catatan</span>
              <em><?= htmlspecialchars($submission['catatan']) ?></em>
            </div>
          <?php endif; ?>
          <?php if ($submission['feedback']): ?>
            <div class="td-feedback">
              <span class="sub-file-label">Feedback Admin</span>
              <em><?= htmlspecialchars($submission['feedback']) ?></em>
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <!-- Form Upload -->
        <div class="td-form-card">
          <h2>Kumpulkan Tugas</h2>
          <form method="POST" enctype="multipart/form-data" id="submitForm">

            <?php if (in_array($tugas['tipe_upload'], ['foto', 'foto_video'])): ?>
            <div class="form-group">
              <label>FILE FOTO <span style="color:var(--torii)">*</span></label>
              <input type="file" name="file_foto" id="fotoInput"
                     accept="image/jpeg,image/png,image/gif,image/webp" required>
              <div class="file-hint">Format: JPG, PNG, GIF, WEBP — Maks. 10 MB</div>
              <div id="fotoPreview" style="display:none; margin-top:8px;"></div>
            </div>
            <?php endif; ?>

            <?php if (in_array($tugas['tipe_upload'], ['video', 'foto_video'])): ?>
            <div class="form-group">
              <label>FILE VIDEO <span style="color:var(--torii)">*</span></label>
              <input type="file" name="file_video" id="videoInput"
                     accept="video/mp4,video/webm,video/ogg,video/quicktime,video/x-msvideo" required>
              <div class="file-hint">Format: MP4, WEBM, OGG, MOV, AVI — Maks. 100 MB</div>
              <div id="videoPreview" style="display:none; margin-top:8px;"></div>
            </div>
            <?php endif; ?>

            <div class="form-group">
              <label>CATATAN (opsional)</label>
              <textarea name="catatan" rows="3" placeholder="Tambahkan catatan atau keterangan jika perlu..."></textarea>
            </div>

            <div id="uploadProgress">
              <progress id="progBar" max="100" value="0"></progress>
              <div id="progressText">Mengunggah...</div>
            </div>

            <button type="submit" class="btn-submit" id="submitBtn">
              <span id="submitText">Kirim Tugas</span>
            </button>
          </form>
        </div>
      <?php endif; ?>

    </div><!-- /td-wrap -->

    <!-- Bottom Action Bar -->

  </main>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/petals.js"></script>
  <script>
    function handleLogout() {
      const fd = new FormData(); fd.append('action', 'logout');
      fetch('auth.php', { method: 'POST', body: fd })
        .then(r => r.json()).then(d => { if (d.redirect) location.href = d.redirect; });
    }

    /* ── Foto preview ── */
    const fotoInput = document.getElementById('fotoInput');
    if (fotoInput) {
      fotoInput.addEventListener('change', function () {
        const preview = document.getElementById('fotoPreview');
        const file = this.files[0];
        if (!file) { preview.style.display='none'; return; }
        const url = URL.createObjectURL(file);
        preview.innerHTML = `<img src="${url}" style="max-width:100%;max-height:200px;border-radius:8px;border:1px solid var(--card-border);">`;
        preview.style.display = 'block';
      });
    }

    /* ── Video preview (filename) ── */
    const videoInput = document.getElementById('videoInput');
    if (videoInput) {
      videoInput.addEventListener('change', function () {
        const preview = document.getElementById('videoPreview');
        const file = this.files[0];
        if (!file) { preview.style.display='none'; return; }
        preview.innerHTML = `<div style="font-size:.8rem;color:var(--text-muted);background:rgba(0,0,0,.05);padding:8px 12px;border-radius:6px;">
          ${file.name} (${(file.size/1024/1024).toFixed(2)} MB)</div>`;
        preview.style.display = 'block';
      });
    }

    /* ── Submit with progress ── */
    const form = document.getElementById('submitForm');
    if (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        const btn  = document.getElementById('submitBtn');
        const prog = document.getElementById('uploadProgress');
        const bar  = document.getElementById('progBar');
        const txt  = document.getElementById('progressText');
        const label = document.getElementById('submitText');

        btn.disabled = true;
        label.textContent = 'Mengirim...';
        prog.style.display = 'block';

        const fd = new FormData(form);
        const xhr = new XMLHttpRequest();
        xhr.open('POST', window.location.href, true);

        xhr.upload.onprogress = function(ev) {
          if (ev.lengthComputable) {
            const pct = Math.round((ev.loaded / ev.total) * 100);
            bar.value = pct;
            txt.textContent = `Mengunggah... ${pct}%`;
          }
        };

        xhr.onload = function() {
          // Server memproses dan merespons dengan halaman baru
          document.open();
          document.write(xhr.responseText);
          document.close();
          window.scrollTo(0,0);
        };

        xhr.onerror = function() {
          btn.disabled = false;
          label.textContent = 'Kirim Tugas';
          prog.style.display = 'none';
          alert('Terjadi kesalahan jaringan. Coba lagi.');
        };

        xhr.send(fd);
      });
    }
  </script>
</body>
</html>
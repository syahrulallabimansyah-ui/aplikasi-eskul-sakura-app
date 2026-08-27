<?php
/**
 * tugas.php — Daftar Tugas untuk User
 * Sakura App
 */
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (!$user) { session_destroy(); header('Location: index.php'); exit; }
if ($user['role'] === 'admin') { header('Location: tugas_admin.php'); exit; }

$db = getDB();

// Ambil semua tugas published beserta status submission user
$stmt = $db->prepare("
    SELECT t.*,
           s.id          AS sub_id,
           s.submitted_at AS sub_time,
           s.nilai       AS sub_nilai
    FROM tugas t
    LEFT JOIN tugas_submissions s ON s.tugas_id = t.id AND s.user_id = ?
    WHERE t.status = 'published'
    ORDER BY t.created_at DESC
");
$stmt->execute([$user['id']]);
$tugasList = $stmt->fetchAll();

$total      = count($tugasList);
$sudahKirim = count(array_filter($tugasList, fn($t) => $t['sub_id']));
$belumKirim = $total - $sudahKirim;

function tipeLabel(string $t): string {
    return match($t) {
        'foto'       => 'Foto',
        'video'      => 'Video',
        'foto_video' => 'Foto & Video',
        default      => $t,
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Tugas</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .tg-wrap { max-width: 860px; margin: 0 auto; padding: 0 16px 80px; }

    /* stats mini */
    .tg-stats { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
    .tg-stat {
      flex: 1; min-width: 100px;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 14px; padding: 18px;
      text-align: center; box-shadow: var(--card-shadow);
      transition: box-shadow .2s ease, transform .2s ease;
    }
    .tg-stat:hover { box-shadow: 0 8px 24px -8px rgba(0,0,0,.15); transform: translateY(-2px); }
    .tg-stat .num { font-size: 1.8rem; font-weight: 800; line-height: 1.1; }
    .tg-stat .lbl { font-size: .75rem; color: var(--text-muted); margin-top: 4px; }
    .green { color: var(--bamboo) !important; }
    .red   { color: var(--torii) !important; }

    /* task card */
    .tg-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 20px 22px;
      margin-bottom: 14px;
      box-shadow: var(--card-shadow);
      display: flex; gap: 16px; align-items: flex-start;
      transition: box-shadow .25s ease, transform .25s ease;
    }
    .tg-card:hover { box-shadow: 0 10px 28px -10px rgba(0,0,0,.18); transform: translateY(-2px); }

    .tg-icon {
      flex-shrink: 0;
      width: 6px; align-self: stretch;
      border-radius: 6px;
      background: var(--torii);
      opacity: .4;
    }
    .tg-icon.done { background: var(--bamboo); opacity: .7; }
    .tg-dot { display: none; }

    .tg-body { flex: 1; min-width: 0; }
    .tg-title { font-size: 1rem; font-weight: 700; color: var(--text-main); margin-bottom: 6px; }
    .tg-meta  { font-size: .78rem; color: var(--text-muted); display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 8px; align-items: center; }
    .tg-desc  { font-size: .87rem; color: var(--text-muted); white-space: pre-wrap; line-height: 1.55; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }

    .tg-right { display: flex; flex-direction: column; align-items: flex-end; gap: 10px; flex-shrink: 0; }
    .badge-sent { background: rgba(74,124,89,.15); color: var(--bamboo); }
    .badge-unsent { background: rgba(183,75,75,.12); color: var(--torii); }
    .badge-nilai { background: rgba(74,124,89,.15); color: var(--bamboo); }
    .badge-pending { background: rgba(196,160,69,.18); color: var(--gold); }
    .badge {
      display: inline-flex; align-items: center; gap: 4px;
      border-radius: 20px; padding: 5px 14px; font-size: .76rem; font-weight: 700;
      white-space: nowrap;
    }
    .btn-open {
      background: var(--torii); color: #fff; border: none;
      border-radius: 10px; padding: 9px 18px; font-size: .85rem;
      font-weight: 700; cursor: pointer; text-decoration: none;
      display: inline-flex; align-items: center; gap: 5px;
      transition: opacity .2s, transform .15s;
      box-shadow: 0 4px 12px -4px rgba(0,0,0,.25);
      white-space: nowrap;
    }
    .btn-open.done { background: var(--bamboo); }
    .btn-open:hover { opacity: .9; transform: translateY(-1px); }

    .empty-state { text-align: center; padding: 60px 20px; color: var(--text-muted); }
    .empty-state .emo { font-size: 3rem; margin-bottom: 12px; }

    .tg-chip { background: rgba(0,0,0,.06); border-radius: 20px; padding: 3px 10px; font-size: .75rem; font-weight: 600; }

    @media(max-width:500px) {
      .tg-card { flex-direction: column; }
      .tg-icon { width: 100%; height: 4px; align-self: auto; }
      .tg-right { flex-direction: row; align-items: center; width: 100%; justify-content: space-between; }
      .btn-open { flex: 1; justify-content: center; }
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
    <a href="beranda.php" class="topbar-back" style="border: 2px solid #99711b; padding: 8px 12px; border-radius: 8px; text-decoration: none; display: inline-block;">← Beranda</a>
  </header>

  <main class="dashboard-main">
    <section class="welcome-section fade-up">
      <span class="welcome-kanji">課題</span>
      <h1 class="welcome-title">Daftar Tugas</h1>
      <p class="welcome-sub">Kerjakan dan kumpulkan tugasmu di sini</p>
      <div class="section-divider"></div>
    </section>

    <div class="tg-wrap fade-up delay-1">

      <!-- Stats mini -->
      <div class="tg-stats">
        <div class="tg-stat">
          <div class="num"><?= $total ?></div>
          <div class="lbl">Total Tugas</div>
        </div>
        <div class="tg-stat">
          <div class="num green"><?= $sudahKirim ?></div>
          <div class="lbl">Sudah Dikumpulkan</div>
        </div>
        <div class="tg-stat">
          <div class="num red"><?= $belumKirim ?></div>
          <div class="lbl">Belum Dikumpulkan</div>
        </div>
      </div>

      <?php if (empty($tugasList)): ?>
        <div class="empty-state">
          <p>Belum ada tugas yang tersedia.</p>
        </div>
      <?php else: ?>
        <?php foreach ($tugasList as $t): ?>
        <div class="tg-card">
          <div class="tg-icon <?= $t['sub_id'] ? 'done' : '' ?>"><span class="tg-dot"></span></div>
          <div class="tg-body">
            <div class="tg-title"><?= htmlspecialchars($t['judul']) ?></div>
            <div class="tg-meta">
              <span class="tg-chip"><?= tipeLabel($t['tipe_upload']) ?></span>
              <span>Dibuat <?= date('d F Y', strtotime($t['created_at'])) ?></span>
              <?php if ($t['sub_id']): ?>
                <span style="color:var(--bamboo)">Dikumpulkan <?= date('d F Y, H:i', strtotime($t['sub_time'])) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($t['deskripsi']): ?>
              <div class="tg-desc"><?= htmlspecialchars($t['deskripsi']) ?></div>
            <?php endif; ?>
          </div>
          <div class="tg-right">
            <span class="badge <?= $t['sub_id'] ? 'badge-sent' : 'badge-unsent' ?>">
              <?= $t['sub_id'] ? 'Sudah Dikirim' : 'Belum Dikirim' ?>
            </span>
            <?php if ($t['sub_id']): ?>
              <?php if ($t['sub_nilai'] !== null): ?>
                <span class="badge badge-nilai">📝 Nilai: <?= number_format((float)$t['sub_nilai'], 1) ?></span>
              <?php else: ?>
                <span class="badge badge-pending">⏳ Belum dinilai</span>
              <?php endif; ?>
            <?php endif; ?>
            <a href="tugas_detail.php?id=<?= $t['id'] ?>"
               class="btn-open <?= $t['sub_id'] ? 'done' : '' ?>">
              <?= $t['sub_id'] ? 'Lihat' : 'Kerjakan' ?>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

    </div><!-- /tg-wrap -->

  
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
  </script>
</body>
</html>
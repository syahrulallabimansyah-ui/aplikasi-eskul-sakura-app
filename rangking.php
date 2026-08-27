<?php
require_once 'config.php';
requireLogin();

$user    = getCurrentUser();
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$isAdmin = $user['role'] === 'admin';
$db      = getDB();

// ── Query Rangking: SUM nilai ujian + tugas per user ────────────
// Logika sama dengan overallStats di beranda.php:
//   total_score = SUM semua score ujian (finished) + SUM semua nilai tugas (bernilai)
$sql = "
    SELECT
        u.id,
        u.name,
        u.nis,
        u.avatar,
        COALESCE(e.exam_total, 0)  AS exam_total,
        COALESCE(e.exam_count, 0)  AS exam_count,
        COALESCE(t.tugas_total, 0) AS tugas_total,
        COALESCE(t.tugas_count, 0) AS tugas_count,
        COALESCE(k.kotoba_total, 0) AS kotoba_total,
        COALESCE(k.kotoba_count, 0) AS kotoba_count,
        (
            COALESCE(e.exam_total, 0) +
            COALESCE(t.tugas_total, 0) +
            COALESCE(k.kotoba_total, 0)
        ) AS grand_total,
        (
            COALESCE(e.exam_count, 0) +
            COALESCE(t.tugas_count, 0) +
            COALESCE(k.kotoba_count, 0)
        ) AS total_items
    FROM users u
    LEFT JOIN (
        SELECT user_id,
               SUM(score) AS exam_total,
               COUNT(*)   AS exam_count
        FROM exam_attempts
        WHERE status = 'finished'
        GROUP BY user_id
    ) e ON e.user_id = u.id
    LEFT JOIN (
        SELECT user_id,
               SUM(nilai) AS tugas_total,
               COUNT(*)   AS tugas_count
        FROM tugas_submissions
        WHERE nilai IS NOT NULL
        GROUP BY user_id
    ) t ON t.user_id = u.id
    LEFT JOIN (
        SELECT user_id,
               SUM(score) AS kotoba_total,
               COUNT(*)   AS kotoba_count
        FROM kotoba_quiz_attempts
        WHERE status = 'finished'
        GROUP BY user_id
    ) k ON k.user_id = u.id
    WHERE u.role = 'user'
    ORDER BY grand_total DESC, u.name ASC
";

$rows      = $db->query($sql)->fetchAll();
$myRank    = null;
$myData    = null;

foreach ($rows as $i => $r) {
    if ($r['id'] == $user['id']) {
        $myRank = $i + 1;
        $myData = $r;
    }
}

// Posisi user di antara semua user
$totalUsers = count($rows);
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Peringkat</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { -webkit-overflow-scrolling: touch; overscroll-behavior-y: contain; }
    .topbar { will-change: transform; backface-visibility: hidden; }
    html { scroll-behavior: smooth; }

    /* ── Page wrapper ── */
    .rank-page {
      max-width: 680px;
      margin: 0 auto;
      padding: 16px 16px 100px;
    }

    /* ── Back button ── */
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: 20px;
      background: rgba(183,75,75,.1);
      border: 1.5px solid rgba(183,75,75,.25);
      color: var(--torii, #b74b4b);
      font-size: .85rem;
      font-weight: 700;
      text-decoration: none;
      margin-bottom: 20px;
      transition: background .18s;
    }
    .back-btn:hover { background: rgba(183,75,75,.18); }

    /* ── Header section ── */
    .rank-header {
      text-align: center;
      margin-bottom: 24px;
    }
    .rank-header-kanji {
      font-size: 2.8rem;
      line-height: 1;
      margin-bottom: 4px;
    }
    .rank-header-title {
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--text-main, #1a1a2e);
      margin-bottom: 4px;
    }
    .rank-header-sub {
      font-size: .85rem;
      color: var(--text-muted, #888);
    }

    /* ── Kartu posisi user sendiri ── */
    .my-rank-card {
      background: linear-gradient(135deg, var(--torii, #b74b4b) 0%, #d97070 100%);
      border-radius: 20px;
      padding: 20px 22px;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 18px;
      margin-bottom: 24px;
      box-shadow: 0 6px 24px rgba(183,75,75,.35);
    }
    .my-rank-badge {
      font-size: 2.2rem;
      font-weight: 900;
      line-height: 1;
      min-width: 60px;
      text-align: center;
    }
    .my-rank-badge small {
      display: block;
      font-size: .65rem;
      font-weight: 600;
      opacity: .85;
      margin-top: 2px;
    }
    .my-rank-info { flex: 1; min-width: 0; }
    .my-rank-name {
      font-size: 1.05rem;
      font-weight: 800;
      margin-bottom: 2px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .my-rank-detail {
      font-size: .8rem;
      opacity: .85;
    }
    .my-rank-score {
      text-align: right;
    }
    .my-rank-score-num {
      font-size: 1.7rem;
      font-weight: 900;
      line-height: 1;
    }
    .my-rank-score-label {
      font-size: .72rem;
      opacity: .85;
    }

    /* ── Tabel Rangking ── */
    .rank-list {
      background: var(--card-bg, #fff);
      border-radius: 20px;
      overflow: hidden;
      box-shadow: 0 2px 16px rgba(0,0,0,.08);
      border: 1.5px solid rgba(183,75,75,.12);
    }
    .rank-list-header {
      display: grid;
      grid-template-columns: 48px 1fr 80px 80px 80px;
      gap: 8px;
      padding: 12px 16px;
      background: linear-gradient(135deg, rgba(183,75,75,.08), rgba(183,75,75,.04));
      border-bottom: 1.5px solid rgba(183,75,75,.12);
      font-size: .75rem;
      font-weight: 700;
      color: var(--text-muted, #888);
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .rank-list-header span:not(:first-child) { text-align: right; }

    .rank-row {
      display: grid;
      grid-template-columns: 48px 1fr 80px 80px 80px;
      gap: 8px;
      padding: 13px 16px;
      align-items: center;
      border-bottom: 1px solid rgba(0,0,0,.05);
      transition: background .15s;
    }
    .rank-row:last-child { border-bottom: none; }
    .rank-row:hover { background: rgba(183,75,75,.04); }
    .rank-row.is-me {
      background: linear-gradient(90deg, rgba(183,75,75,.08), rgba(183,75,75,.04));
      border-left: 3px solid var(--torii, #b74b4b);
    }

    /* Posisi / medali */
    .rank-pos {
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .rank-medal {
      font-size: 1.5rem;
      line-height: 1;
    }
    .rank-num {
      font-size: .95rem;
      font-weight: 800;
      color: var(--text-muted, #888);
    }

    /* Nama user */
    .rank-user {
      display: flex;
      align-items: center;
      gap: 10px;
      min-width: 0;
    }
    .rank-avatar {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--torii, #b74b4b), #d97070);
      color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: .95rem; font-weight: 800;
      flex-shrink: 0;
    }
    .rank-avatar.top1 { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .rank-avatar.top2 { background: linear-gradient(135deg, #94a3b8, #64748b); }
    .rank-avatar.top3 { background: linear-gradient(135deg, #cd7c3a, #b05a1a); }
    .rank-user-info { min-width: 0; }
    .rank-user-name {
      font-size: .9rem;
      font-weight: 700;
      color: var(--text-main, #1a1a2e);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .rank-user-nis {
      font-size: .72rem;
      color: var(--text-muted, #888);
    }
    .me-label {
      display: inline-block;
      font-size: .62rem;
      background: var(--torii, #b74b4b);
      color: #fff;
      padding: 1px 6px;
      border-radius: 8px;
      font-weight: 700;
      margin-left: 4px;
      vertical-align: middle;
    }

    /* Nilai kolom */
    .rank-val {
      text-align: right;
      font-size: .85rem;
      font-weight: 600;
      color: var(--text-main, #333);
    }
    .rank-val.grand {
      font-size: .95rem;
      font-weight: 800;
      color: var(--torii, #b74b4b);
    }
    .rank-val .muted {
      font-size: .7rem;
      color: var(--text-muted, #aaa);
      font-weight: 400;
      display: block;
    }

    /* ── Empty state ── */
    .rank-empty {
      text-align: center;
      padding: 48px 24px;
      color: var(--text-muted, #888);
    }
    .rank-empty-icon { font-size: 3rem; margin-bottom: 12px; }
    .rank-empty-text { font-size: .95rem; }

    /* ── Legend ── */
    .rank-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 16px;
      font-size: .78rem;
      color: var(--text-muted, #888);
    }
    .rank-legend-item {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    /* ── Responsive ── */
    @media (max-width: 520px) {
      .rank-list-header,
      .rank-row {
        grid-template-columns: 40px 1fr 68px 68px;
      }
      /* Sembunyikan kolom Tugas di layar kecil, tampilkan Total & Ujian saja */
      .rank-list-header .col-tugas,
      .rank-row .col-tugas {
        display: none;
      }
    }

    /* fade-up */
    .fade-up {
      opacity: 0;
      transform: translateY(24px);
      transition: opacity .45s ease, transform .45s ease;
    }
    .fade-up.is-visible { opacity: 1; transform: translateY(0); }
    .delay-1 { transition-delay: .08s; }
    .delay-2 { transition-delay: .16s; }
    .delay-3 { transition-delay: .24s; }

    /* topbar AI btn */
    .topbar-actions { display: flex; align-items: center; gap: 10px; }
    .ai-btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 7px 14px; border-radius: 20px;
      background: linear-gradient(135deg, #7c3aed, #a855f7);
      color: #fff; font-size: .82rem; font-weight: 700;
      text-decoration: none;
      box-shadow: 0 2px 8px rgba(124,58,237,.35);
      transition: transform .18s, box-shadow .18s, opacity .18s;
    }
    .ai-btn:hover { transform: translateY(-2px); opacity: .93; }
    @media (max-width: 480px) {
      .ai-btn span.ai-btn-label { display: none; }
      .ai-btn { padding: 7px 10px; }
    }
  </style>
</head>
<body>

  <!-- Loading -->
 

  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <!-- ── TOPBAR ── -->
  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <div class="topbar-actions">
      <a href="chatbot.php" class="ai-btn" title="Tanya AI">
        <span>🤖</span>
        <span class="ai-btn-label">Tanya AI</span>
      </a>
      <button class="theme-toggle" onclick="toggleTheme()" title="Ganti Tema">☀️</button>
    </div>
  </header>

  <!-- ── MAIN ── -->
  <main class="dashboard-main">
    <div class="rank-page">

      <!-- Back -->
      <a href="beranda.php" class="back-btn fade-up">← Kembali ke Beranda</a>

      <!-- Header -->
      <div class="rank-header fade-up delay-1">
        <div class="rank-header-kanji">🏆</div>
        <div class="rank-header-title">Papan Peringkat</div>
        <div class="rank-header-sub">
          Urutan berdasarkan total nilai (Ujian + Tugas + Quiz Kotoba)
        </div>
      </div>

      <?php if (!$isAdmin && $myData): ?>
      <!-- Kartu posisi saya -->
      <div class="my-rank-card fade-up delay-2">
        <div class="my-rank-badge">
          <?php
            if ($myRank == 1) echo '🥇';
            elseif ($myRank == 2) echo '🥈';
            elseif ($myRank == 3) echo '🥉';
            else echo '#' . $myRank;
          ?>
          <small>Posisi Anda</small>
        </div>
        <div class="my-rank-info">
          <div class="my-rank-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="my-rank-detail">
            Ujian: <?= number_format($myData['exam_total'], 1) ?> •
            Tugas: <?= number_format($myData['tugas_total'], 1) ?> •
            Kotoba: <?= number_format($myData['kotoba_total'], 1) ?>
          </div>
          <div class="my-rank-detail" style="margin-top:3px; opacity:.8;">
            <?= $myData['total_items'] ?> item dinilai • dari <?= $totalUsers ?> peserta
          </div>
        </div>
        <div class="my-rank-score">
          <div class="my-rank-score-num"><?= number_format($myData['grand_total'], 1) ?></div>
          <div class="my-rank-score-label">Total Poin</div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Tabel rangking -->
      <div class="rank-list fade-up delay-3">
        <?php if (empty($rows)): ?>
          <div class="rank-empty">
            <div class="rank-empty-icon">📋</div>
            <div class="rank-empty-text">Belum ada data nilai untuk ditampilkan.</div>
          </div>
        <?php else: ?>

          <div class="rank-list-header">
            <span>#</span>
            <span>Peserta</span>
            <span class="col-tugas" style="text-align:right;">Ujian</span>
            <span style="text-align:right;">Tugas</span>
            <span style="text-align:right;">Total</span>
          </div>

          <?php foreach ($rows as $i => $r):
            $pos    = $i + 1;
            $isMe   = ($r['id'] == $user['id']);
            $initl  = strtoupper(mb_substr($r['name'], 0, 1));
            $avatarClass = '';
            if ($pos === 1)      $avatarClass = 'top1';
            elseif ($pos === 2)  $avatarClass = 'top2';
            elseif ($pos === 3)  $avatarClass = 'top3';
          ?>
          <div class="rank-row <?= $isMe ? 'is-me' : '' ?>">

            <!-- Posisi -->
            <div class="rank-pos">
              <?php if ($pos === 1): ?>
                <span class="rank-medal">🥇</span>
              <?php elseif ($pos === 2): ?>
                <span class="rank-medal">🥈</span>
              <?php elseif ($pos === 3): ?>
                <span class="rank-medal">🥉</span>
              <?php else: ?>
                <span class="rank-num"><?= $pos ?></span>
              <?php endif; ?>
            </div>

            <!-- Nama -->
            <div class="rank-user">
              <div class="rank-avatar <?= $avatarClass ?>"><?= htmlspecialchars($initl) ?></div>
              <div class="rank-user-info">
                <div class="rank-user-name">
                  <?= htmlspecialchars($r['name']) ?>
                  <?php if ($isMe): ?>
                    <span class="me-label">Kamu</span>
                  <?php endif; ?>
                </div>
                <div class="rank-user-nis"><?= htmlspecialchars($r['nis'] ?? '-') ?></div>
              </div>
            </div>

            <!-- Ujian + Kotoba -->
            <div class="rank-val col-tugas">
              <?= number_format((float)$r['exam_total'] + (float)$r['kotoba_total'], 1) ?>
              <span class="muted"><?= $r['exam_count'] + $r['kotoba_count'] ?>x</span>
            </div>

            <!-- Tugas -->
            <div class="rank-val">
              <?= number_format($r['tugas_total'], 1) ?>
              <span class="muted"><?= $r['tugas_count'] ?>x</span>
            </div>

            <!-- Grand Total -->
            <div class="rank-val grand">
              <?= number_format($r['grand_total'], 1) ?>
            </div>

          </div>
          <?php endforeach; ?>

        <?php endif; ?>
      </div>

      <!-- Keterangan -->
      <div class="rank-legend fade-up">
        <div class="rank-legend-item">📊 <span>Total = Ujian + Tugas + Quiz Kotoba</span></div>
        <div class="rank-legend-item">🔢 <span>Angka kecil = jumlah item dinilai</span></div>
        <div class="rank-legend-item">🌸 <span>Hanya menampilkan member aktif</span></div>
      </div>

    </div>
  </main>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script>
    // Loading screen
    window.addEventListener('load', () => {
      document.getElementById('loadingScreen')?.classList.add('hidden');
    });

    // Fade-up observer
    const observer = new IntersectionObserver(entries => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          e.target.classList.add('is-visible');
          observer.unobserve(e.target);
        }
      });
    }, { threshold: 0.08 });
    document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));

    // Sakura petals (sama seperti beranda)
    (function() {
      const container = document.getElementById('petals');
      if (!container) return;
      const chars = ['🌸','🌺','🌼','✿','❀'];
      let count = 0;
      function spawnPetal() {
        if (count > 18) return;
        const p = document.createElement('span');
        p.textContent = chars[Math.floor(Math.random() * chars.length)];
        p.style.cssText = [
          'position:fixed',
          'top:-30px',
          `left:${Math.random() * 100}vw`,
          `font-size:${12 + Math.random() * 14}px`,
          `opacity:${0.4 + Math.random() * 0.5}`,
          `animation:fall ${4 + Math.random() * 5}s linear forwards`,
          'pointer-events:none',
          'z-index:0',
        ].join(';');
        container.appendChild(p);
        count++;
        p.addEventListener('animationend', () => { p.remove(); count--; });
      }
      // CSS keyframes
      if (!document.getElementById('petalStyle')) {
        const s = document.createElement('style');
        s.id = 'petalStyle';
        s.textContent = '@keyframes fall{to{transform:translateY(110vh) rotate(360deg);opacity:0}}';
        document.head.appendChild(s);
      }
      setInterval(spawnPetal, 700);
    })();
  </script>
</body>
</html>

<?php
require_once 'config.php';
requireLogin();

$user    = getCurrentUser();
$isAdmin = $user['role'] === 'admin';
$db      = getDB();

// ── AJAX: ambil pelajaran per level ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {

    if ($_GET['action'] === 'get_lessons') {
        $levelId = (int)($_GET['level_id'] ?? 0);
        $stmt = $db->prepare("
            SELECT gl.id, gl.judul, gl.deskripsi, gl.urutan,
                   COUNT(gm.id) AS total_materi
            FROM grammar_lesson gl
            LEFT JOIN grammar_materi gm ON gm.lesson_id = gl.id AND gm.is_active = 1
            WHERE gl.level_id = ? AND gl.is_active = 1
            GROUP BY gl.id
            ORDER BY gl.urutan ASC, gl.id ASC
        ");
        $stmt->execute([$levelId]);
        echo json_encode(['ok' => true, 'lessons' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_GET['action'] === 'get_materis') {
        $lessonId = (int)($_GET['lesson_id'] ?? 0);
        $stmt = $db->prepare("
            SELECT id, judul, penjelasan_singkat, urutan
            FROM grammar_materi
            WHERE lesson_id = ? AND is_active = 1
            ORDER BY urutan ASC, id ASC
        ");
        $stmt->execute([$lessonId]);
        echo json_encode(['ok' => true, 'materis' => $stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_GET['action'] === 'get_materi_detail') {
        $materiId = (int)($_GET['materi_id'] ?? 0);
        $stmt = $db->prepare("
            SELECT gm.*, gl.judul AS lesson_judul, lv.kode AS level_kode
            FROM grammar_materi gm
            JOIN grammar_lesson gl ON gl.id = gm.lesson_id
            JOIN grammar_level lv ON lv.id = gl.level_id
            WHERE gm.id = ? AND gm.is_active = 1
        ");
        $stmt->execute([$materiId]);
        $materi = $stmt->fetch();
        if (!$materi) {
            echo json_encode(['ok' => false, 'error' => 'Materi tidak ditemukan']);
            exit;
        }
        echo json_encode(['ok' => true, 'materi' => $materi], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ── Ambil semua level ──────────────────────────────────────
$levels = array_reverse($db->query("SELECT * FROM grammar_level ORDER BY urutan ASC")->fetchAll());

$initial  = strtoupper(mb_substr($user['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Tata Bahasa</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { -webkit-overflow-scrolling: touch; overscroll-behavior-y: contain; }
    html { scroll-behavior: smooth; }

    /* ── TOPBAR ── */
    .topbar-actions { display:flex; align-items:center; gap:10px; }
    .back-btn {
      display:inline-flex; align-items:center; gap:6px;
      padding:7px 14px; border-radius:20px;
      background:rgba(183,75,75,.12);
      color:var(--torii,#b74b4b);
      font-size:.82rem; font-weight:700;
      text-decoration:none;
      border:1px solid rgba(183,75,75,.25);
      transition:background .18s;
    }
    .back-btn:hover { background:rgba(183,75,75,.22); }

    /* ── PAGE HEADER ── */
    .tb-page-header {
      padding: 20px 20px 0;
      text-align: center;
    }
    .tb-page-kanji {
      font-size: 2.2rem; font-weight: 900;
      color: var(--torii,#b74b4b);
      letter-spacing: -1px;
      margin-bottom: 4px;
    }
    .tb-page-title {
      font-size: 1.3rem; font-weight: 800;
      color: var(--text-main,#222);
      margin-bottom: 4px;
    }
    .tb-page-sub {
      font-size: .85rem;
      color: var(--text-muted,#888);
      margin-bottom: 16px;
    }

    /* ── LEVEL TABS ── */
    .level-tabs {
      display: flex;
      gap: 8px;
      padding: 0 20px 16px;
      overflow-x: auto;
      scrollbar-width: none;
      -ms-overflow-style: none;
    }
    .level-tabs::-webkit-scrollbar { display: none; }
    .level-tab {
      flex-shrink: 0;
      padding: 10px 20px;
      border-radius: 24px;
      border: 2px solid var(--card-border,#e0e0e0);
      background: var(--card-bg,#fff);
      color: var(--text-muted,#888);
      font-size: .9rem; font-weight: 700;
      cursor: pointer;
      transition: all .2s;
      white-space: nowrap;
      user-select: none;
    }
    .level-tab:hover { border-color: var(--torii,#b74b4b); color: var(--torii,#b74b4b); }
    .level-tab.active {
      background: linear-gradient(135deg, var(--torii,#b74b4b), #d97070);
      border-color: transparent;
      color: #fff;
      box-shadow: 0 4px 14px rgba(183,75,75,.35);
    }

    /* ── CONTENT AREA ── */
    .tb-content {
      padding: 0 20px 100px;
    }

    /* ── LESSON LIST ── */
    .lessons-container {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .lesson-card {
      background: var(--card-bg,#fff);
      border: 1.5px solid var(--card-border,#e8e8e8);
      border-radius: 16px;
      overflow: hidden;
      cursor: pointer;
      transition: box-shadow .2s, border-color .2s;
    }
    .lesson-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.1); border-color: var(--torii,#b74b4b); }
    .lesson-card-header {
      display: flex; align-items: center; gap: 12px;
      padding: 14px 16px;
    }
    .lesson-num {
      flex-shrink: 0;
      width: 38px; height: 38px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--torii,#b74b4b), #d97070);
      color: #fff;
      font-size: .8rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center;
    }
    .lesson-info { flex: 1; min-width: 0; }
    .lesson-title {
      font-size: .95rem; font-weight: 700;
      color: var(--text-main,#222);
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .lesson-desc {
      font-size: .78rem; color: var(--text-muted,#888);
      margin-top: 2px;
      display: -webkit-box;
      -webkit-line-clamp: 1;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
    .lesson-meta {
      font-size: .73rem;
      color: var(--text-muted,#888);
      display: flex; align-items: center; gap: 4px;
      flex-shrink: 0;
    }
    .lesson-arrow {
      color: var(--text-muted,#aaa);
      font-size: 1rem;
      transition: transform .2s;
    }
    .lesson-card.open .lesson-arrow { transform: rotate(90deg); }

    /* ── MATERI LIST (accordion) ── */
    .materi-list {
      display: none;
      border-top: 1px solid var(--card-border,#e8e8e8);
      padding: 0;
    }
    .lesson-card.open .materi-list { display: block; }
    .materi-loading {
      padding: 14px 16px;
      font-size: .85rem;
      color: var(--text-muted,#888);
      text-align: center;
    }
    .materi-item {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 12px 16px;
      border-bottom: 1px solid var(--card-border,#f0f0f0);
      cursor: pointer;
      transition: background .15s;
    }
    .materi-item:last-child { border-bottom: none; }
    .materi-item:hover { background: rgba(183,75,75,.05); }
    .materi-dot {
      flex-shrink: 0;
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--torii,#b74b4b);
      margin-top: 6px;
    }
    .materi-info { flex: 1; min-width: 0; }
    .materi-title {
      font-size: .88rem; font-weight: 700;
      color: var(--text-main,#222);
    }
    .materi-desc {
      font-size: .76rem; color: var(--text-muted,#888);
      margin-top: 2px;
      line-height: 1.4;
    }
    .materi-arrow {
      color: var(--text-muted,#bbb);
      font-size: .9rem;
      flex-shrink: 0;
      margin-top: 3px;
    }

    /* ── EMPTY STATE ── */
    .empty-state {
      text-align: center;
      padding: 50px 20px;
      color: var(--text-muted,#aaa);
    }
    .empty-state-icon { font-size: 3rem; margin-bottom: 12px; }
    .empty-state-text { font-size: .9rem; }

    /* ── MATERI MODAL ── */
    .materi-modal-overlay {
      position: fixed; inset: 0;
      background: rgba(0,0,0,.5);
      z-index: 9999;
      display: none;
      align-items: flex-end;
      padding: 0;
    }
    .materi-modal-overlay.open { display: flex; }
    .materi-modal {
      background: var(--card-bg,#fff);
      border-radius: 24px 24px 0 0;
      width: 100%; max-height: 90vh;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      padding: 24px 20px 40px;
      animation: slideUpModal .3s cubic-bezier(.22,1,.36,1);
    }
    @keyframes slideUpModal {
      from { transform: translateY(100%); }
      to   { transform: translateY(0); }
    }
    .materi-modal-handle {
      width: 40px; height: 4px;
      background: var(--card-border,#ddd);
      border-radius: 2px;
      margin: 0 auto 18px;
    }
    .materi-modal-breadcrumb {
      font-size: .73rem; color: var(--text-muted,#888);
      margin-bottom: 8px;
      display: flex; align-items: center; gap: 4px;
      flex-wrap: wrap;
    }
    .materi-modal-breadcrumb span { color: var(--torii,#b74b4b); font-weight: 700; }
    .materi-modal-title {
      font-size: 1.2rem; font-weight: 800;
      color: var(--text-main,#222);
      margin-bottom: 10px;
      line-height: 1.35;
    }
    .materi-modal-short {
      font-size: .88rem;
      color: var(--text-muted,#666);
      background: rgba(183,75,75,.06);
      border-left: 3px solid var(--torii,#b74b4b);
      padding: 10px 14px;
      border-radius: 0 10px 10px 0;
      margin-bottom: 18px;
      line-height: 1.5;
      white-space: pre-wrap;
    }
    .materi-modal-konten {
      font-size: .9rem;
      color: var(--text-main,#333);
      line-height: 1.7;
      white-space: pre-wrap;
    }
    .materi-modal-konten p { margin: 0 0 14px; }
    .materi-modal-konten p:last-child { margin-bottom: 0; }
    .materi-modal-konten-empty {
      text-align: center;
      padding: 30px 0;
      color: var(--text-muted,#bbb);
      font-size: .88rem;
    }
    .materi-modal-close {
      position: absolute; top: 16px; right: 16px;
      width: 34px; height: 34px;
      border-radius: 50%;
      background: rgba(0,0,0,.08);
      border: none; cursor: pointer;
      font-size: 1rem;
      display: flex; align-items: center; justify-content: center;
      color: var(--text-main,#333);
    }
    .materi-modal-close:hover { background: rgba(0,0,0,.15); }

    /* Skeleton loader */
    .skeleton {
      background: linear-gradient(90deg, var(--card-border,#e8e8e8) 25%, rgba(255,255,255,.5) 50%, var(--card-border,#e8e8e8) 75%);
      background-size: 200% 100%;
      animation: shimmer 1.4s infinite;
      border-radius: 6px;
      height: 14px; margin-bottom: 8px;
    }
    @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

    /* ── FADE UP ── */
    .fade-up { opacity:0; transform:translateY(20px); transition:opacity .4s ease, transform .4s ease; }
    .fade-up.is-visible { opacity:1; transform:translateY(0); }
  </style>
</head>
<body class="dashboard-page">

  <div class="page-loader" id="pageLoader"><span class="loader-kanji">文</span></div>

  <div class="asanoha-bg"></div>

  <!-- TOPBAR -->
  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <div class="topbar-actions">
      <a href="beranda.php" class="back-btn">← Beranda</a>
      <button class="theme-toggle" onclick="toggleTheme()" title="Ganti Tema">☀️</button>
    </div>
  </header>

  <main class="dashboard-main">

    <!-- Page Header -->
    <section class="tb-page-header fade-up">
      <div class="tb-page-kanji">文法</div>
      <div class="tb-page-title">Tata Bahasa Jepang</div>
      <div class="tb-page-sub">Pilih level JLPT lalu pelajari materi tata bahasa</div>
    </section>

    <!-- Level Tabs -->
    <div class="level-tabs fade-up" id="levelTabs">
      <?php foreach ($levels as $i => $lv): ?>
      <button
        class="level-tab <?= $i === 0 ? 'active' : '' ?>"
        data-level-id="<?= $lv['id'] ?>"
        data-level-kode="<?= htmlspecialchars($lv['kode']) ?>"
        onclick="selectLevel(this)"
      >
        <?= htmlspecialchars($lv['kode']) ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Content -->
    <div class="tb-content">

      <!-- Level Description -->
      <div id="levelDesc" style="margin-bottom:14px; font-size:.83rem; color:var(--text-muted,#888); text-align:center;"></div>

      <!-- Lessons -->
      <div class="lessons-container fade-up" id="lessonsContainer">
        <div class="materi-loading">⏳ Memuat pelajaran...</div>
      </div>

    </div><!-- /tb-content -->

  </main>

  <!-- Materi Detail Modal -->
  <div class="materi-modal-overlay" id="materiModal" onclick="closeMateriModal(event)">
    <div class="materi-modal" id="materiModalContent" style="position:relative;">
      <button class="materi-modal-close" onclick="closeMateriModal(null, true)">✕</button>
      <div class="materi-modal-handle"></div>
      <div id="materiModalInner"></div>
    </div>
  </div>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script>
    // ── Data dari PHP ──
    const levels = <?= json_encode(array_values($levels), JSON_UNESCAPED_UNICODE) ?>;
    let currentLevelId = levels.length ? levels[0].id : 0;

    // ── Fade-up Observer ──
    (function() {
      if (!('IntersectionObserver' in window)) {
        document.querySelectorAll('.fade-up').forEach(el => el.classList.add('is-visible'));
        return;
      }
      const io = new IntersectionObserver(entries => {
        entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('is-visible'); io.unobserve(e.target); } });
      }, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
      document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.fade-up').forEach(el => io.observe(el));
      });
    })();

    // ── Select Level ──
    function selectLevel(btn) {
      document.querySelectorAll('.level-tab').forEach(t => t.classList.remove('active'));
      btn.classList.add('active');
      currentLevelId = parseInt(btn.dataset.levelId);

      // Update deskripsi
      const lv = levels.find(l => l.id === currentLevelId);
      document.getElementById('levelDesc').textContent = lv ? lv.deskripsi : '';

      loadLessons(currentLevelId);
    }

    // ── Load Lessons ──
    async function loadLessons(levelId) {
      const container = document.getElementById('lessonsContainer');
      container.innerHTML = `
        <div style="padding:40px 0; text-align:center; color:var(--text-muted,#888);">
          <div style="font-size:1.4rem; margin-bottom:8px;">⏳</div>
          <div style="font-size:.85rem;">Memuat pelajaran...</div>
        </div>`;

      try {
        const res  = await fetch(`tata_bahasa.php?action=get_lessons&level_id=${levelId}`);
        const data = await res.json();

        if (!data.ok || !data.lessons.length) {
          container.innerHTML = `
            <div class="empty-state">
              <div class="empty-state-icon">📖</div>
              <div class="empty-state-text">Belum ada pelajaran untuk level ini.<br>Admin akan segera menambahkan konten.</div>
            </div>`;
          return;
        }

        container.innerHTML = data.lessons.map((lesson, idx) => `
          <div class="lesson-card" id="lesson-${lesson.id}" onclick="toggleLesson(this, ${lesson.id})">
            <div class="lesson-card-header">
              <div class="lesson-num">${idx + 1}</div>
              <div class="lesson-info">
                <div class="lesson-title">${escHtml(lesson.judul)}</div>
                ${lesson.deskripsi ? `<div class="lesson-desc">${escHtml(lesson.deskripsi)}</div>` : ''}
              </div>
              <div class="lesson-meta">
                <span>${lesson.total_materi} materi</span>
              </div>
              <div class="lesson-arrow">›</div>
            </div>
            <div class="materi-list" id="materi-list-${lesson.id}">
              <div class="materi-loading">Memuat...</div>
            </div>
          </div>
        `).join('');

      } catch(e) {
        container.innerHTML = `<div class="empty-state"><div class="empty-state-icon">⚠️</div><div class="empty-state-text">Gagal memuat data. Coba lagi.</div></div>`;
      }
    }

    // ── Toggle Lesson (accordion) ──
    async function toggleLesson(card, lessonId) {
      const isOpen = card.classList.contains('open');
      // Tutup semua
      document.querySelectorAll('.lesson-card.open').forEach(c => c.classList.remove('open'));
      if (isOpen) return;

      card.classList.add('open');
      const listEl = document.getElementById(`materi-list-${lessonId}`);

      // Jika sudah pernah dimuat, jangan muat ulang
      if (listEl.dataset.loaded) return;

      try {
        const res  = await fetch(`tata_bahasa.php?action=get_materis&lesson_id=${lessonId}`);
        const data = await res.json();
        listEl.dataset.loaded = '1';

        if (!data.ok || !data.materis.length) {
          listEl.innerHTML = `<div class="materi-loading" style="color:var(--text-muted,#aaa);">Belum ada materi di pelajaran ini.</div>`;
          return;
        }

        listEl.innerHTML = data.materis.map(m => `
          <div class="materi-item" onclick="event.stopPropagation(); openMateri(${m.id})">
            <div class="materi-dot"></div>
            <div class="materi-info">
              <div class="materi-title">${escHtml(m.judul)}</div>
              ${m.penjelasan_singkat ? `<div class="materi-desc">${escHtml(m.penjelasan_singkat)}</div>` : ''}
            </div>
            <div class="materi-arrow">›</div>
          </div>
        `).join('');

      } catch(e) {
        listEl.innerHTML = `<div class="materi-loading" style="color:var(--torii,#b74b4b);">Gagal memuat materi.</div>`;
      }
    }

    // ── Open Materi Detail Modal ──
    async function openMateri(materiId) {
      const modal   = document.getElementById('materiModal');
      const inner   = document.getElementById('materiModalInner');
      modal.classList.add('open');
      document.body.style.overflow = 'hidden';

      inner.innerHTML = `
        <div class="skeleton" style="width:40%; height:11px; margin-bottom:14px;"></div>
        <div class="skeleton" style="width:90%; height:20px; margin-bottom:8px;"></div>
        <div class="skeleton" style="width:70%; height:20px; margin-bottom:18px;"></div>
        <div class="skeleton" style="width:100%; height:11px;"></div>
        <div class="skeleton" style="width:100%; height:11px;"></div>
        <div class="skeleton" style="width:85%; height:11px;"></div>`;

      try {
        const res  = await fetch(`tata_bahasa.php?action=get_materi_detail&materi_id=${materiId}`);
        const data = await res.json();

        if (!data.ok) {
          inner.innerHTML = `<div style="text-align:center;padding:30px 0;color:var(--torii,#b74b4b);">⚠️ Materi tidak ditemukan.</div>`;
          return;
        }

        const m = data.materi;
        inner.innerHTML = `
          <div class="materi-modal-breadcrumb">
            <span>${escHtml(m.level_kode)}</span> ›
            <span>${escHtml(m.lesson_judul)}</span>
          </div>
          <div class="materi-modal-title">${escHtml(m.judul)}</div>
          ${m.penjelasan_singkat
            ? `<div class="materi-modal-short">${escHtml(m.penjelasan_singkat)}</div>`
            : ''}
          <div class="materi-modal-konten">
            ${m.konten
              ? m.konten  /* konten bisa berisi HTML dari admin */
              : `<div class="materi-modal-konten-empty">📝 Konten materi sedang disiapkan oleh admin.</div>`}
          </div>`;

      } catch(e) {
        inner.innerHTML = `<div style="text-align:center;padding:30px 0;color:var(--torii,#b74b4b);">⚠️ Gagal memuat materi.</div>`;
      }
    }

    function closeMateriModal(event, force = false) {
      if (force || (event && event.target === document.getElementById('materiModal'))) {
        document.getElementById('materiModal').classList.remove('open');
        document.body.style.overflow = '';
      }
    }

    function escHtml(s) {
      return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    // ── Init ──
    document.addEventListener('DOMContentLoaded', () => {
      const firstTab = document.querySelector('.level-tab.active');
      if (firstTab) {
        const lv = levels.find(l => l.id === parseInt(firstTab.dataset.levelId));
        if (lv) document.getElementById('levelDesc').textContent = lv.deskripsi || '';
      }
      if (currentLevelId) loadLessons(currentLevelId);

      // Page loader
      const loader = document.getElementById('pageLoader');
      if (loader) { loader.style.opacity='0'; setTimeout(()=>loader.remove(),400); }
    });

    // Escape key tutup modal
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeMateriModal(null, true);
    });
  </script>
</body>
</html>

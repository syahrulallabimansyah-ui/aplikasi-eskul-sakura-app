<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if ($user['role'] !== 'admin') {
    header('Location: beranda.php');
    exit;
}

$db = getDB();

// ─────────────────────────────────────────────────────────────────
// AJAX HANDLERS
// ─────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $act = $_POST['action'];

    // ── LESSON: tambah ──
    if ($act === 'add_lesson') {
        $levelId = (int)($_POST['level_id'] ?? 0);
        $judul   = trim($_POST['judul'] ?? '');
        $desk    = trim($_POST['deskripsi'] ?? '');
        $urutan  = (int)($_POST['urutan'] ?? 0);
        if (!$levelId || !$judul) { echo json_encode(['ok'=>false,'error'=>'Data tidak lengkap']); exit; }
        $stmt = $db->prepare("INSERT INTO grammar_lesson (level_id,judul,deskripsi,urutan,created_by) VALUES (?,?,?,?,?)");
        $stmt->execute([$levelId, $judul, $desk, $urutan, $user['id']]);
        echo json_encode(['ok'=>true,'id'=>$db->lastInsertId()]);
        exit;
    }

    // ── LESSON: edit ──
    if ($act === 'edit_lesson') {
        $id     = (int)($_POST['id'] ?? 0);
        $judul  = trim($_POST['judul'] ?? '');
        $desk   = trim($_POST['deskripsi'] ?? '');
        $urutan = (int)($_POST['urutan'] ?? 0);
        if (!$id || !$judul) { echo json_encode(['ok'=>false,'error'=>'Data tidak lengkap']); exit; }
        $db->prepare("UPDATE grammar_lesson SET judul=?,deskripsi=?,urutan=? WHERE id=?")->execute([$judul,$desk,$urutan,$id]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    // ── LESSON: hapus ──
    if ($act === 'delete_lesson') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $db->prepare("DELETE FROM grammar_lesson WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    // ── MATERI: tambah ──
    if ($act === 'add_materi') {
        $lessonId = (int)($_POST['lesson_id'] ?? 0);
        $judul    = trim($_POST['judul'] ?? '');
        $short    = trim($_POST['penjelasan_singkat'] ?? '');
        $konten   = trim($_POST['konten'] ?? '');
        $urutan   = (int)($_POST['urutan'] ?? 0);
        if (!$lessonId || !$judul) { echo json_encode(['ok'=>false,'error'=>'Data tidak lengkap']); exit; }
        $stmt = $db->prepare("INSERT INTO grammar_materi (lesson_id,judul,penjelasan_singkat,konten,urutan,created_by) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$lessonId,$judul,$short,$konten,$urutan,$user['id']]);
        echo json_encode(['ok'=>true,'id'=>$db->lastInsertId()]);
        exit;
    }

    // ── MATERI: edit ──
    if ($act === 'edit_materi') {
        $id     = (int)($_POST['id'] ?? 0);
        $judul  = trim($_POST['judul'] ?? '');
        $short  = trim($_POST['penjelasan_singkat'] ?? '');
        $konten = trim($_POST['konten'] ?? '');
        $urutan = (int)($_POST['urutan'] ?? 0);
        if (!$id || !$judul) { echo json_encode(['ok'=>false,'error'=>'Data tidak lengkap']); exit; }
        $db->prepare("UPDATE grammar_materi SET judul=?,penjelasan_singkat=?,konten=?,urutan=? WHERE id=?")->execute([$judul,$short,$konten,$urutan,$id]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    // ── MATERI: hapus ──
    if ($act === 'delete_materi') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id) $db->prepare("DELETE FROM grammar_materi WHERE id=?")->execute([$id]);
        echo json_encode(['ok'=>true]);
        exit;
    }

    echo json_encode(['ok'=>false,'error'=>'Action tidak dikenal']);
    exit;
}

// ── GET AJAX ──
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {

    if ($_GET['action'] === 'get_lessons') {
        $levelId = (int)($_GET['level_id'] ?? 0);
        $stmt = $db->prepare("
            SELECT gl.*, COUNT(gm.id) AS total_materi
            FROM grammar_lesson gl
            LEFT JOIN grammar_materi gm ON gm.lesson_id = gl.id
            WHERE gl.level_id = ?
            GROUP BY gl.id
            ORDER BY gl.urutan ASC, gl.id ASC
        ");
        $stmt->execute([$levelId]);
        echo json_encode(['ok'=>true,'lessons'=>$stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_GET['action'] === 'get_materis') {
        $lessonId = (int)($_GET['lesson_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM grammar_materi WHERE lesson_id=? ORDER BY urutan ASC, id ASC");
        $stmt->execute([$lessonId]);
        echo json_encode(['ok'=>true,'materis'=>$stmt->fetchAll()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($_GET['action'] === 'get_materi') {
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM grammar_materi WHERE id=?");
        $stmt->execute([$id]);
        $m = $stmt->fetch();
        echo json_encode(['ok'=>(bool)$m,'materi'=>$m], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// ── Load levels ──
$levels = array_reverse($db->query("SELECT * FROM grammar_level ORDER BY urutan ASC")->fetchAll());
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Kelola Tata Bahasa</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    body { -webkit-overflow-scrolling: touch; }
    html { scroll-behavior: smooth; }

    .topbar-actions { display:flex; align-items:center; gap:10px; }
    .back-btn {
      display:inline-flex; align-items:center; gap:6px;
      padding:7px 14px; border-radius:20px;
      background:rgba(183,75,75,.12); color:var(--torii,#b74b4b);
      font-size:.82rem; font-weight:700;
      text-decoration:none; border:1px solid rgba(183,75,75,.25);
      transition:background .18s;
    }
    .back-btn:hover { background:rgba(183,75,75,.22); }

    /* PAGE HEADER */
    .admin-tb-header {
      padding: 20px 20px 0;
    }
    .admin-tb-title {
      font-size: 1.2rem; font-weight: 800; color: var(--text-main,#b74b4b);
    }
    .admin-tb-sub { font-size: .82rem; color: var(--text-muted,#888); margin-top:2px; }

    /* LEVEL TABS */
    .level-tabs {
      display:flex; gap:8px;
      padding: 16px 20px 0;
      overflow-x:auto; scrollbar-width:none;
    }
    .level-tabs::-webkit-scrollbar { display:none; }
    .level-tab {
      flex-shrink:0; padding:9px 18px; border-radius:22px;
      border:2px solid var(--card-border,#e0e0e0);
      background:var(--card-bg,#fff); color:var(--text-muted,#888);
      font-size:.88rem; font-weight:700; cursor:pointer;
      transition:all .2s; white-space:nowrap;
    }
    .level-tab.active {
      background:linear-gradient(135deg,var(--torii,#b74b4b),#d97070);
      border-color:transparent; color:#fff;
      box-shadow:0 4px 14px rgba(183,75,75,.35);
    }

    /* MAIN CONTENT */
    .admin-tb-body { padding: 16px 20px 80px; }

    /* SECTION CARD */
    .section-card {
      background: var(--card-bg,#fff);
      border: 1.5px solid var(--card-border,#e8e8e8);
      border-radius: 18px;
      margin-bottom: 14px;
      overflow: hidden;
    }
    .section-card-header {
      display:flex; align-items:center; justify-content:space-between;
      padding: 14px 16px;
      border-bottom: 1px solid var(--card-border,#f0f0f0);
    }
    .section-card-title {
      font-size:.95rem; font-weight:800; color:var(--text-main,#222);
    }
    .section-card-sub {
      font-size:.75rem; color:var(--text-muted,#888); margin-top:2px;
    }

    /* LESSON ROWS */
    .lesson-row {
      display:flex; align-items:center; gap:10px;
      padding: 12px 16px;
      border-bottom: 1px solid var(--card-border,#f5f5f5);
    }
    .lesson-row:last-child { border-bottom: none; }
    .lesson-row-num {
      flex-shrink:0; width:32px; height:32px; border-radius:50%;
      background:linear-gradient(135deg,var(--torii,#b74b4b),#d97070);
      color:#fff; font-size:.75rem; font-weight:800;
      display:flex; align-items:center; justify-content:center;
    }
    .lesson-row-info { flex:1; min-width:0; }
    .lesson-row-title { font-size:.88rem; font-weight:700; color:var(--text-main,#222); }
    .lesson-row-meta { font-size:.73rem; color:var(--text-muted,#888); margin-top:1px; }
    .lesson-row-actions { display:flex; gap:6px; flex-shrink:0; }

    /* MATERI ROWS (nested) */
    .materi-nested {
      display:none;
      background: rgba(183,75,75,.03);
    }
    .lesson-row.open + .materi-nested { display:block; }
    .materi-toggle-btn {
      background:none; border:none; cursor:pointer;
      color:var(--text-muted,#aaa); font-size:.9rem;
      padding:4px; border-radius:6px; transition:color .15s, transform .2s;
    }
    .lesson-row.open .materi-toggle-btn { color:var(--torii,#b74b4b); transform:rotate(90deg); }
    .materi-row {
      display:flex; align-items:flex-start; gap:8px;
      padding: 10px 16px 10px 56px;
      border-bottom: 1px solid var(--card-border,#f5f5f5);
    }
    .materi-row:last-child { border-bottom: none; }
    .materi-dot-sm {
      flex-shrink:0; width:7px; height:7px; border-radius:50%;
      background:var(--torii,#b74b4b); margin-top:6px;
    }
    .materi-row-info { flex:1; min-width:0; }
    .materi-row-title { font-size:.84rem; font-weight:700; color:var(--text-main,#222); }
    .materi-row-desc { font-size:.73rem; color:var(--text-muted,#888); margin-top:1px; }

    /* BUTTONS */
    .btn-sm {
      padding:5px 10px; border-radius:10px;
      font-size:.75rem; font-weight:700; cursor:pointer;
      border:none; transition:opacity .15s;
      display:inline-flex; align-items:center; gap:4px;
    }
    .btn-sm:hover { opacity:.85; }
    .btn-edit { background:rgba(74,124,89,.12); color:var(--bamboo,#4a7c59); }
    .btn-del  { background:rgba(183,75,75,.10); color:var(--torii,#b74b4b); }
    .btn-add-lesson {
      padding:8px 14px; border-radius:12px;
      background:linear-gradient(135deg,var(--torii,#b74b4b),#d97070);
      color:#fff; font-size:.8rem; font-weight:700;
      border:none; cursor:pointer;
      display:inline-flex; align-items:center; gap:6px;
      transition:opacity .15s;
    }
    .btn-add-lesson:hover { opacity:.88; }
    .btn-add-materi {
      display:inline-flex; align-items:center; gap:5px;
      padding:7px 12px; border-radius:10px;
      background:rgba(74,124,89,.1); color:var(--bamboo,#4a7c59);
      font-size:.75rem; font-weight:700;
      border:1px dashed rgba(74,124,89,.4);
      cursor:pointer; margin:8px 16px;
      transition:background .15s;
    }
    .btn-add-materi:hover { background:rgba(74,124,89,.2); }

    /* EMPTY */
    .empty-row {
      padding:24px 16px; text-align:center;
      color:var(--text-muted,#bbb); font-size:.85rem;
    }

    /* ── MODAL ── */
    .modal-overlay {
      position:fixed; inset:0; background:rgba(0,0,0,.5);
      z-index:9999; display:none;
      align-items:center; justify-content:center; padding:20px;
    }
    .modal-overlay.open { display:flex; }
    .modal-box {
      background:var(--card-bg,#fff); border-radius:22px;
      padding:24px 20px 20px; width:100%; max-width:480px;
      max-height:90vh; overflow-y:auto;
      box-shadow:0 20px 60px rgba(0,0,0,.25);
      animation:slideUpModal .28s cubic-bezier(.22,1,.36,1);
      position:relative;
    }
    @keyframes slideUpModal {
      from{opacity:0;transform:translateY(20px) scale(.97)}
      to{opacity:1;transform:translateY(0) scale(1)}
    }
    .modal-title { font-size:1rem; font-weight:800; color:var(--text-main,#222); margin-bottom:18px; }
    .modal-close {
      position:absolute; top:14px; right:14px;
      width:30px; height:30px; border-radius:50%;
      background:rgba(0,0,0,.08); border:none; cursor:pointer; font-size:.9rem;
    }
    .modal-close:hover { background:rgba(0,0,0,.15); }
    .form-label { font-size:.78rem; font-weight:700; color:var(--text-muted,#888); margin-bottom:5px; display:block; }
    .form-input {
      width:100%; box-sizing:border-box;
      border:1.5px solid var(--card-border,#ddd);
      border-radius:10px; padding:9px 12px;
      font-size:.88rem; color:var(--text-main,#333);
      background:var(--input-bg,#fafafa);
      font-family:inherit; margin-bottom:12px;
      transition:border-color .18s;
    }
    .form-input:focus { outline:none; border-color:var(--torii,#b74b4b); }
    textarea.form-input { resize:vertical; min-height:100px; }
    .form-textarea-konten { min-height:240px; font-size:.85rem; line-height:1.6; }
    .form-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:6px; }
    .btn-modal-save {
      padding:9px 20px; border-radius:12px;
      background:linear-gradient(135deg,var(--torii,#b74b4b),#d97070);
      color:#fff; font-size:.85rem; font-weight:700;
      border:none; cursor:pointer; transition:opacity .15s;
    }
    .btn-modal-save:hover { opacity:.88; }
    .btn-modal-cancel {
      padding:9px 16px; border-radius:12px;
      background:rgba(0,0,0,.07); color:var(--text-main,#444);
      font-size:.85rem; font-weight:700; border:none; cursor:pointer;
    }
    .form-hint { font-size:.73rem; color:var(--text-muted,#aaa); margin-top:-8px; margin-bottom:12px; }

    /* TOAST */
    .toast {
      position:fixed; bottom:24px; left:50%; transform:translateX(-50%);
      background:var(--text-main,#333); color:#fff;
      padding:10px 20px; border-radius:24px;
      font-size:.85rem; font-weight:700;
      z-index:99999; opacity:0;
      transition:opacity .3s;
      white-space:nowrap;
    }
    .toast.show { opacity:1; }

    /* loading */
    .loading-row {
      padding:20px 16px; text-align:center;
      color:var(--text-muted,#aaa); font-size:.85rem;
    }
  </style>
</head>
<body class="dashboard-page">

  <div class="page-loader" id="pageLoader"><span class="loader-kanji">文</span></div>
  <div class="asanoha-bg"></div>

  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <div class="topbar-actions">
      <a href="beranda.php" class="back-btn">← Beranda</a>
      <button class="theme-toggle" onclick="toggleTheme()">☀️</button>
    </div>
  </header>

  <main class="dashboard-main">

    <div class="admin-tb-header">
      <div class="admin-tb-title">⛩ Kelola Tata Bahasa</div>
      <div class="admin-tb-sub">Tambah & edit pelajaran serta materi per level JLPT</div>
    </div>

    <!-- LEVEL TABS -->
    <div class="level-tabs" id="levelTabs">
      <?php foreach ($levels as $i => $lv): ?>
      <button
        class="level-tab <?= $i === 0 ? 'active' : '' ?>"
        data-level-id="<?= $lv['id'] ?>"
        onclick="selectLevel(this)"
      ><?= htmlspecialchars($lv['kode']) ?></button>
      <?php endforeach; ?>
    </div>

    <div class="admin-tb-body">

      <!-- Lesson Section -->
      <div class="section-card">
        <div class="section-card-header">
          <div>
            <div class="section-card-title">📚 Daftar Pelajaran</div>
            <div class="section-card-sub" id="levelSubtitle">Pilih level di atas</div>
          </div>
          <button class="btn-add-lesson" onclick="openLessonModal()">＋ Tambah Pelajaran</button>
        </div>
        <div id="lessonsBody">
          <div class="loading-row">⏳ Memuat...</div>
        </div>
      </div>

    </div>
  </main>

  <!-- ── LESSON MODAL ── -->
  <div class="modal-overlay" id="lessonModal" onclick="handleOverlayClick(event, 'lessonModal')">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal('lessonModal')">✕</button>
      <div class="modal-title" id="lessonModalTitle">Tambah Pelajaran</div>
      <input type="hidden" id="lessonModalId">

      <label class="form-label">Judul Pelajaran *</label>
      <input type="text" id="lessonJudul" class="form-input" placeholder="Contoh: Pelajaran 1 — Kata Kerja Dasar" maxlength="200">

      <label class="form-label">Deskripsi Singkat</label>
      <textarea id="lessonDeskripsi" class="form-input" placeholder="Deskripsi singkat pelajaran ini (opsional)" maxlength="500"></textarea>

      <label class="form-label">Urutan</label>
      <input type="number" id="lessonUrutan" class="form-input" placeholder="0" min="0" value="0" style="max-width:120px;">
      <div class="form-hint">Angka kecil tampil lebih dulu</div>

      <div class="form-actions">
        <button class="btn-modal-cancel" onclick="closeModal('lessonModal')">Batal</button>
        <button class="btn-modal-save" onclick="saveLesson()">💾 Simpan</button>
      </div>
    </div>
  </div>

  <!-- ── MATERI MODAL ── -->
  <div class="modal-overlay" id="materiModal" onclick="handleOverlayClick(event, 'materiModal')">
    <div class="modal-box">
      <button class="modal-close" onclick="closeModal('materiModal')">✕</button>
      <div class="modal-title" id="materiModalTitle">Tambah Materi</div>
      <input type="hidden" id="materiModalId">
      <input type="hidden" id="materiModalLessonId">

      <label class="form-label">Judul Materi *</label>
      <input type="text" id="materiJudul" class="form-input" placeholder="Contoh: Pola ～です (desu)" maxlength="200">

      <label class="form-label">Penjelasan Singkat</label>
      <textarea id="materiShort" class="form-input" placeholder="Penjelasan 1-2 kalimat yang tampil di daftar materi" maxlength="500"></textarea>

      <label class="form-label">Konten Materi</label>
      <textarea id="materiKonten" class="form-input form-textarea-konten" placeholder="Konten lengkap materi... (bisa kosong dulu, isi nanti)"></textarea>
      <div class="form-hint">Tekan Enter untuk baris baru (otomatis tampil sebagai baris baru). Mendukung juga HTML sederhana (&lt;b&gt;, &lt;i&gt;, &lt;p&gt;, dll)</div>

      <label class="form-label">Urutan</label>
      <input type="number" id="materiUrutan" class="form-input" placeholder="0" min="0" value="0" style="max-width:120px;">

      <div class="form-actions">
        <button class="btn-modal-cancel" onclick="closeModal('materiModal')">Batal</button>
        <button class="btn-modal-save" onclick="saveMateri()">💾 Simpan</button>
      </div>
    </div>
  </div>

  <!-- TOAST -->
  <div class="toast" id="toast"></div>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script>
    const levels = <?= json_encode(array_values($levels), JSON_UNESCAPED_UNICODE) ?>;
    let currentLevelId = levels.length ? levels[0].id : 0;

    // ── LEVEL SELECT ──
    function selectLevel(btn) {
      document.querySelectorAll('.level-tab').forEach(t => t.classList.remove('active'));
      btn.classList.add('active');
      currentLevelId = parseInt(btn.dataset.levelId);
      const lv = levels.find(l => l.id === currentLevelId);
      document.getElementById('levelSubtitle').textContent = lv ? `Level ${lv.kode} — ${lv.deskripsi || ''}` : '';
      loadLessons();
    }

    // ── LOAD LESSONS ──
    async function loadLessons() {
      const body = document.getElementById('lessonsBody');
      body.innerHTML = '<div class="loading-row">⏳ Memuat pelajaran...</div>';
      try {
        const res  = await fetch(`admin_tata_bahasa.php?action=get_lessons&level_id=${currentLevelId}`);
        const data = await res.json();
        renderLessons(data.lessons || []);
      } catch(e) {
        body.innerHTML = '<div class="loading-row" style="color:var(--torii);">⚠️ Gagal memuat</div>';
      }
    }

    function renderLessons(lessons) {
      const body = document.getElementById('lessonsBody');
      if (!lessons.length) {
        body.innerHTML = '<div class="empty-row">Belum ada pelajaran. Klik "＋ Tambah Pelajaran" untuk mulai.</div>';
        return;
      }
      body.innerHTML = lessons.map((l, idx) => `
        <div class="lesson-row" id="lrow-${l.id}">
          <div class="lesson-row-num">${idx+1}</div>
          <div class="lesson-row-info">
            <div class="lesson-row-title">${escHtml(l.judul)}</div>
            <div class="lesson-row-meta">${l.total_materi} materi${l.deskripsi ? ' · ' + escHtml(l.deskripsi.substring(0,50)) : ''}</div>
          </div>
          <div class="lesson-row-actions">
            <button class="btn-sm btn-edit" onclick="event.stopPropagation(); editLesson(${l.id},'${escJs(l.judul)}','${escJs(l.deskripsi||'')}',${l.urutan})">✏️</button>
            <button class="btn-sm btn-del" onclick="event.stopPropagation(); deleteLesson(${l.id})">🗑</button>
            <button class="materi-toggle-btn" onclick="toggleMateri(this, ${l.id})" title="Lihat/tambah materi">›</button>
          </div>
        </div>
        <div class="materi-nested" id="mnest-${l.id}">
          <div class="loading-row">Memuat materi...</div>
        </div>
      `).join('');
    }

    // ── TOGGLE MATERI NESTED ──
    async function toggleMateri(btn, lessonId) {
      const row   = document.getElementById(`lrow-${lessonId}`);
      const nest  = document.getElementById(`mnest-${lessonId}`);
      const isOpen = row.classList.contains('open');

      document.querySelectorAll('.lesson-row.open').forEach(r => r.classList.remove('open'));
      if (isOpen) return;

      row.classList.add('open');
      if (nest.dataset.loaded) return;

      try {
        const res  = await fetch(`admin_tata_bahasa.php?action=get_materis&lesson_id=${lessonId}`);
        const data = await res.json();
        nest.dataset.loaded = '1';
        renderMaterisNested(nest, lessonId, data.materis || []);
      } catch(e) {
        nest.innerHTML = '<div class="loading-row" style="color:var(--torii);">⚠️ Gagal memuat</div>';
      }
    }

    function renderMaterisNested(container, lessonId, materis) {
      const rows = materis.map(m => `
        <div class="materi-row">
          <div class="materi-dot-sm"></div>
          <div class="materi-row-info">
            <div class="materi-row-title">${escHtml(m.judul)}</div>
            ${m.penjelasan_singkat ? `<div class="materi-row-desc">${escHtml(m.penjelasan_singkat.substring(0,80))}</div>` : ''}
          </div>
          <div style="display:flex;gap:5px;flex-shrink:0;">
            <button class="btn-sm btn-edit" onclick="editMateri(${m.id})">✏️</button>
            <button class="btn-sm btn-del" onclick="deleteMateri(${m.id}, ${lessonId})">🗑</button>
          </div>
        </div>
      `).join('');

      container.innerHTML = (rows || '<div class="materi-row" style="color:var(--text-muted);">Belum ada materi.</div>') +
        `<div style="padding:4px 16px 10px 16px;">
          <button class="btn-add-materi" onclick="openMateriModal(${lessonId})">＋ Tambah Materi</button>
        </div>`;
    }

    // ── LESSON CRUD ──
    function openLessonModal(id, judul, desk, urutan) {
      document.getElementById('lessonModalId').value = id || '';
      document.getElementById('lessonJudul').value = judul || '';
      document.getElementById('lessonDeskripsi').value = desk || '';
      document.getElementById('lessonUrutan').value = urutan ?? 0;
      document.getElementById('lessonModalTitle').textContent = id ? '✏️ Edit Pelajaran' : '＋ Tambah Pelajaran';
      openModal('lessonModal');
    }
    function editLesson(id, judul, desk, urutan) {
      openLessonModal(id, judul, desk, urutan);
    }

    async function saveLesson() {
      const id     = document.getElementById('lessonModalId').value;
      const judul  = document.getElementById('lessonJudul').value.trim();
      const desk   = document.getElementById('lessonDeskripsi').value.trim();
      const urutan = document.getElementById('lessonUrutan').value;
      if (!judul) { alert('Judul tidak boleh kosong!'); return; }

      const fd = new FormData();
      fd.append('action', id ? 'edit_lesson' : 'add_lesson');
      if (id) fd.append('id', id);
      else    fd.append('level_id', currentLevelId);
      fd.append('judul', judul);
      fd.append('deskripsi', desk);
      fd.append('urutan', urutan);

      try {
        const res  = await fetch('admin_tata_bahasa.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.ok) {
          closeModal('lessonModal');
          showToast(id ? '✅ Pelajaran diperbarui!' : '✅ Pelajaran ditambahkan!');
          loadLessons();
        } else {
          alert('Gagal: ' + (data.error || 'Error'));
        }
      } catch(e) { alert('Terjadi kesalahan jaringan.'); }
    }

    async function deleteLesson(id) {
      if (!confirm('Hapus pelajaran ini? Semua materi di dalamnya juga akan terhapus!')) return;
      const fd = new FormData();
      fd.append('action', 'delete_lesson');
      fd.append('id', id);
      try {
        await fetch('admin_tata_bahasa.php', {method:'POST', body:fd});
        showToast('🗑 Pelajaran dihapus');
        loadLessons();
      } catch(e) { alert('Gagal menghapus.'); }
    }

    // ── MATERI CRUD ──
    function openMateriModal(lessonId, id, judul, short, konten, urutan) {
      document.getElementById('materiModalId').value = id || '';
      document.getElementById('materiModalLessonId').value = lessonId;
      document.getElementById('materiJudul').value = judul || '';
      document.getElementById('materiShort').value = short || '';
      document.getElementById('materiKonten').value = konten || '';
      document.getElementById('materiUrutan').value = urutan ?? 0;
      document.getElementById('materiModalTitle').textContent = id ? '✏️ Edit Materi' : '＋ Tambah Materi';
      openModal('materiModal');
    }

    async function editMateri(id) {
      try {
        const res  = await fetch(`admin_tata_bahasa.php?action=get_materi&id=${id}`);
        const data = await res.json();
        if (data.ok && data.materi) {
          const m = data.materi;
          openMateriModal(m.lesson_id, m.id, m.judul, m.penjelasan_singkat, m.konten, m.urutan);
        }
      } catch(e) { alert('Gagal memuat data materi.'); }
    }

    async function saveMateri() {
      const id       = document.getElementById('materiModalId').value;
      const lessonId = document.getElementById('materiModalLessonId').value;
      const judul    = document.getElementById('materiJudul').value.trim();
      const short    = document.getElementById('materiShort').value.trim();
      const konten   = document.getElementById('materiKonten').value.trim();
      const urutan   = document.getElementById('materiUrutan').value;
      if (!judul) { alert('Judul materi tidak boleh kosong!'); return; }

      const fd = new FormData();
      fd.append('action', id ? 'edit_materi' : 'add_materi');
      if (id) fd.append('id', id);
      else    fd.append('lesson_id', lessonId);
      fd.append('judul', judul);
      fd.append('penjelasan_singkat', short);
      fd.append('konten', konten);
      fd.append('urutan', urutan);

      try {
        const res  = await fetch('admin_tata_bahasa.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.ok) {
          closeModal('materiModal');
          showToast(id ? '✅ Materi diperbarui!' : '✅ Materi ditambahkan!');
          // reload nested materi
          const nest = document.getElementById(`mnest-${lessonId}`);
          if (nest) { delete nest.dataset.loaded; }
          const row = document.getElementById(`lrow-${lessonId}`);
          if (row && row.classList.contains('open')) {
            const toggleBtn = row.querySelector('.materi-toggle-btn');
            if (toggleBtn) { row.classList.remove('open'); toggleMateri(toggleBtn, parseInt(lessonId)); }
          }
          // Update jumlah materi di daftar pelajaran
          loadLessons();
        } else {
          alert('Gagal: ' + (data.error || 'Error'));
        }
      } catch(e) { alert('Terjadi kesalahan jaringan.'); }
    }

    async function deleteMateri(id, lessonId) {
      if (!confirm('Hapus materi ini?')) return;
      const fd = new FormData();
      fd.append('action', 'delete_materi');
      fd.append('id', id);
      try {
        await fetch('admin_tata_bahasa.php', {method:'POST', body:fd});
        showToast('🗑 Materi dihapus');
        const nest = document.getElementById(`mnest-${lessonId}`);
        if (nest) { delete nest.dataset.loaded; }
        loadLessons();
      } catch(e) { alert('Gagal menghapus.'); }
    }

    // ── MODAL HELPERS ──
    function openModal(id) {
      document.getElementById(id).classList.add('open');
      document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
      document.getElementById(id).classList.remove('open');
      document.body.style.overflow = '';
    }
    function handleOverlayClick(e, id) {
      if (e.target === document.getElementById(id)) closeModal(id);
    }
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        closeModal('lessonModal');
        closeModal('materiModal');
      }
    });

    // ── TOAST ──
    let toastTimer;
    function showToast(msg) {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.classList.add('show');
      clearTimeout(toastTimer);
      toastTimer = setTimeout(() => t.classList.remove('show'), 2600);
    }

    // ── ESCAPE JS ──
    function escHtml(s) {
      return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escJs(s) {
      return String(s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/\n/g,' ');
    }

    // ── INIT ──
    document.addEventListener('DOMContentLoaded', () => {
      const lv = levels.find(l => l.id === currentLevelId);
      if (lv) document.getElementById('levelSubtitle').textContent = `Level ${lv.kode} — ${lv.deskripsi||''}`;
      loadLessons();

      const loader = document.getElementById('pageLoader');
      if (loader) { loader.style.opacity='0'; setTimeout(()=>loader.remove(),400); }
    });
  </script>
</body>
</html>

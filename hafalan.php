<?php
require_once 'config.php';
requireLogin();
$user    = getCurrentUser();
$isAdmin = $user && $user['role'] === 'admin';
$initial = strtoupper(mb_substr($user['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Hafalan</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* ── Hafalan Page ── */
    .hafalan-wrap {
      max-width: 860px;
      margin: 0 auto;
      padding: 0 20px 120px;
    }

    /* Kategori Tab Bar */
    .kat-bar {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      margin-bottom: 28px;
    }
    .kat-tab {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 9px 18px;
      border-radius: 30px;
      border: 1px solid rgba(201,169,110,0.2);
      background: rgba(255,255,255,0.03);
      color: var(--mist);
      font-family: 'Cinzel', serif;
      font-size: 0.78rem;
      letter-spacing: 0.08em;
      cursor: pointer;
      transition: var(--transition);
    }
    .kat-tab:hover { border-color: rgba(201,169,110,0.45); color: var(--gold-light); }
    .kat-tab.active {
      background: rgba(201,169,110,0.12);
      border-color: var(--gold);
      color: var(--gold);
    }
    .kat-tab .kat-count {
      background: rgba(201,169,110,0.15);
      color: var(--gold);
      font-size: 0.7rem;
      padding: 1px 7px;
      border-radius: 20px;
      font-family: 'Inter', sans-serif;
    }

    /* Item Grid */
    .item-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 18px;
    }

    /* Item Card */
    .item-card {
      background: rgba(22,33,62,0.9);
      border: 1px solid rgba(201,169,110,0.15);
      border-radius: var(--radius);
      overflow: hidden;
      cursor: pointer;
      transition: var(--transition);
    }
    body.light-mode .item-card {
      background: rgba(255,255,255,0.7);
      border-color: rgba(201,169,110,0.25);
    }
    .item-card:hover {
      border-color: rgba(201,169,110,0.4);
      transform: translateY(-3px);
      box-shadow: var(--shadow-card);
    }

    /* Media preview di card */
    .item-thumb {
      width: 100%;
      height: 150px;
      object-fit: cover;
      display: block;
      background: rgba(0,0,0,0.2);
    }
    .item-thumb-placeholder {
      width: 100%;
      height: 150px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.8rem;
      background: rgba(201,169,110,0.06);
      border-bottom: 1px solid rgba(201,169,110,0.1);
    }

    .item-body {
      padding: 14px 16px;
    }
    .item-type-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: 0.67rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      font-family: 'Cinzel', serif;
      padding: 3px 9px;
      border-radius: 4px;
      margin-bottom: 8px;
    }
    .badge-gambar { background: rgba(74,124,89,0.15);  color: var(--bamboo); }
    .badge-audio  { background: rgba(201,169,110,0.12); color: var(--gold); }
    .badge-video  { background: rgba(155,35,53,0.12);  color: var(--torii-soft); }
    .badge-link   { background: rgba(138,138,158,0.15); color: var(--mist); }

    .item-judul {
      font-family: 'Cinzel', serif;
      font-size: 0.92rem;
      color: var(--cream);
      margin-bottom: 5px;
      line-height: 1.4;
    }
    .item-desc {
      font-size: 0.8rem;
      color: var(--mist);
      line-height: 1.5;
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--mist);
    }
    .empty-state .empty-icon { font-size: 3rem; margin-bottom: 14px; display: block; }
    .empty-state p { font-size: 0.88rem; }

    /* ── MODAL DETAIL ── */
    .detail-media {
      width: 100%;
      border-radius: var(--radius-sm);
      margin-bottom: 18px;
      background: rgba(0,0,0,0.15);
    }
    .detail-media img  { width: 100%; border-radius: var(--radius-sm); display: block; max-height: 400px; object-fit: contain; }
    .detail-media audio { width: 100%; }
    .detail-media video { width: 100%; border-radius: var(--radius-sm); max-height: 360px; }
    .detail-link-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 22px;
      border-radius: var(--radius-sm);
      background: rgba(201,169,110,0.1);
      border: 1px solid rgba(201,169,110,0.3);
      color: var(--gold);
      font-family: 'Cinzel', serif;
      font-size: 0.82rem;
      text-decoration: none;
      transition: var(--transition);
    }
    .detail-link-btn:hover {
      background: rgba(201,169,110,0.18);
      border-color: var(--gold);
    }
    /* embed iframe untuk link video YouTube dll */
    .detail-media iframe {
      width: 100%;
      aspect-ratio: 16/9;
      border: none;
      border-radius: var(--radius-sm);
    }

    .detail-judul {
      font-family: 'Cinzel', serif;
      font-size: 1.05rem;
      color: var(--cream);
      margin-bottom: 10px;
      line-height: 1.5;
    }
    .detail-desc {
      font-size: 0.88rem;
      color: var(--mist);
      line-height: 1.7;
    }

    /* Loading skeleton */
    .skeleton {
      background: linear-gradient(90deg, rgba(255,255,255,0.04) 25%, rgba(255,255,255,0.08) 50%, rgba(255,255,255,0.04) 75%);
      background-size: 200% 100%;
      animation: shimmer 1.4s infinite;
      border-radius: 6px;
    }
    @keyframes shimmer { 0%{background-position:200% 0} 100%{background-position:-200% 0} }

    @media (max-width: 600px) {
      .item-grid { grid-template-columns: 1fr 1fr; gap: 12px; }
      .kat-bar { gap: 8px; }
      .kat-tab { padding: 7px 14px; font-size: 0.74rem; }
    }
    @media (max-width: 380px) {
      .item-grid { grid-template-columns: 1fr; }
    }

    /* ── Tombol Download ── */
    .download-btn {
      display: inline-flex; align-items: center; gap: 8px;
      margin-top: 16px; padding: 10px 22px;
      border-radius: var(--radius-sm);
      background: rgba(201,169,110,0.12); border: 1px solid rgba(201,169,110,0.35);
      color: var(--gold); font-family: 'Cinzel', serif; font-size: 0.82rem;
      text-decoration: none; transition: var(--transition); cursor: pointer;
    }
    .download-btn:hover { background: rgba(201,169,110,0.22); border-color: var(--gold); }

    /* ── Fix modal overlay & z-index ── */
    .modal-overlay { display: none !important; }
    .modal-overlay.active { display: block !important; }
    .modal-box { z-index: 1001 !important; position: fixed !important; }
    * { backdrop-filter: none !important; -webkit-backdrop-filter: none !important; }
  </style>
</head>
<body class="dashboard-page">

  <div class="page-loader" id="pageLoader" style="transition:opacity 0.5s ease;"><span class="loader-kanji">桜</span></div>
  <script>!function(){var l=document.getElementById("pageLoader");if(l){window.addEventListener("load",function(){l.style.opacity="0";l.style.pointerEvents="none";setTimeout(function(){l.style.display="none"},500)});setTimeout(function(){if(l.style.opacity!=="0"){l.style.opacity="0";l.style.pointerEvents="none";setTimeout(function(){l.style.display="none"},500)}},2000)}}</script>
  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <button class="theme-toggle" onclick="toggleTheme()" title="Mode Terang">☀️</button>
    <a href="beranda.php" class="topbar-back" style="border: 2px solid #99711b; padding: 8px 12px; border-radius: 8px; text-decoration: none; display: inline-block;">← Beranda</a>
  </header>

  <main class="dashboard-main">

    <!-- Welcome -->
    <section class="welcome-section fade-up">
      <span class="welcome-kanji">暗記</span>
      <h1 class="welcome-title">Hafalan</h1>
      <p class="welcome-sub">Pilih kategori dan pelajari materi hafalan yang tersedia</p>
      <div class="section-divider"></div>
    </section>

    <div class="hafalan-wrap">

      <!-- Kategori tab -->
      <div class="kat-bar fade-up delay-1" id="katBar">
        <div class="skeleton" style="width:100px;height:38px;border-radius:30px;"></div>
        <div class="skeleton" style="width:80px;height:38px;border-radius:30px;"></div>
        <div class="skeleton" style="width:90px;height:38px;border-radius:30px;"></div>
      </div>

      <!-- Item grid -->
      <div class="item-grid fade-up delay-2" id="itemGrid">
        <!-- loaded via JS -->
      </div>

    </div><!-- /hafalan-wrap -->

 
  </main>

  <!-- Modal Detail -->
  <div class="modal-overlay" id="detailOverlay" style="display:none;" onclick="closeDetail()"></div>
  <div class="modal-box modal-wide" id="detailBox" style="display:none; max-width:560px;">
    <button class="modal-close" onclick="closeDetail()">✕</button>
    <div id="detailContent"></div>
  </div>

  <div class="toast" id="toast"></div>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/petals.js"></script>
  <script>
    let kategoriList = [];
    let activeKatId  = null;

    /* ── Load kategori ── */
    async function loadKategori() {
      const res  = await fetch('hafalan_api.php?action=get_kategori');
      const json = await res.json();
      kategoriList = json.data || [];
      renderKatBar();
      if (kategoriList.length > 0) {
        setActiveKat(kategoriList[0].id);
      } else {
        document.getElementById('itemGrid').innerHTML =
          `<div class="empty-state" style="grid-column:1/-1">
             <span class="empty-icon">📭</span>
             <p>Belum ada kategori hafalan.</p>
           </div>`;
      }
    }

    function renderKatBar() {
      const bar = document.getElementById('katBar');
      if (!kategoriList.length) { bar.innerHTML = ''; return; }
      bar.innerHTML = kategoriList.map(k => `
        <button class="kat-tab ${k.id == activeKatId ? 'active' : ''}"
                onclick="setActiveKat(${k.id})">
          <span>${k.icon}</span>
          <span>${escHtml(k.nama)}</span>
          <span class="kat-count">${k.jumlah_item}</span>
        </button>
      `).join('');
    }

    function setActiveKat(id) {
      activeKatId = id;
      renderKatBar();
      loadItem(id);
    }

    /* ── Load item ── */
    async function loadItem(katId) {
      const grid = document.getElementById('itemGrid');
      grid.innerHTML = Array(4).fill(
        `<div class="item-card"><div class="skeleton" style="height:150px;border-radius:0;"></div>
         <div class="item-body"><div class="skeleton" style="height:14px;margin-bottom:8px;width:60%"></div>
         <div class="skeleton" style="height:12px;width:90%"></div></div></div>`
      ).join('');

      const res  = await fetch(`hafalan_api.php?action=get_item&kategori_id=${katId}`);
      const json = await res.json();
      const items = json.data || [];

      if (!items.length) {
        grid.innerHTML = `<div class="empty-state" style="grid-column:1/-1">
          <span class="empty-icon">🌸</span>
          <p>Belum ada materi di kategori ini.</p>
        </div>`;
        return;
      }

      grid.innerHTML = items.map(it => {
        const thumb = thumbHtml(it);
        const badge = badgeHtml(it.tipe);
        return `
          <div class="item-card" onclick="openDetail(${JSON.stringify(escJson(it)).replace(/"/g,'&quot;')})">
            ${thumb}
            <div class="item-body">
              ${badge}
              <div class="item-judul">${escHtml(it.judul)}</div>
              ${it.deskripsi ? `<div class="item-desc">${escHtml(it.deskripsi)}</div>` : ''}
            </div>
          </div>`;
      }).join('');
    }

    function thumbHtml(it) {
      if (it.tipe === 'gambar' && it.file_path) {
        return `<img class="item-thumb" src="${it.file_path}" alt="${escHtml(it.judul)}" loading="lazy">`;
      }
      const icons = { audio:'🎵', video:'🎬', link:'🔗', gambar:'🖼️' };
      return `<div class="item-thumb-placeholder">${icons[it.tipe] || '📄'}</div>`;
    }

    function badgeHtml(tipe) {
      const map = { gambar:['🖼️','badge-gambar','Gambar'], audio:['🎵','badge-audio','Audio'],
                    video:['🎬','badge-video','Video'], link:['🔗','badge-link','Link'] };
      const [icon, cls, label] = map[tipe] || ['📄','','File'];
      return `<span class="item-type-badge ${cls}">${icon} ${label}</span>`;
    }

    /* ── Modal detail ── */
    function openDetail(item) {
      if (typeof item === 'string') item = JSON.parse(item);
      const overlay = document.getElementById('detailOverlay');
      const box     = document.getElementById('detailBox');
      const content = document.getElementById('detailContent');

      let mediaHtml = '';
      if (item.tipe === 'gambar' && item.file_path) {
        mediaHtml = `<div class="detail-media"><img src="${item.file_path}" alt="${escHtml(item.judul)}"></div>`;
      } else if (item.tipe === 'audio' && item.file_path) {
        mediaHtml = `<div class="detail-media"><audio controls src="${item.file_path}"></audio></div>`;
      } else if (item.tipe === 'video' && item.file_path) {
        mediaHtml = `<div class="detail-media"><video controls src="${item.file_path}"></video></div>`;
      } else if (item.link_url) {
        const embedSrc = tryEmbed(item.link_url);
        if (embedSrc) {
          mediaHtml = `<div class="detail-media"><iframe src="${embedSrc}" allowfullscreen></iframe></div>`;
        } else {
          mediaHtml = `<div style="margin-bottom:18px;">
            <a class="detail-link-btn" href="${item.link_url}" target="_blank" rel="noopener">
              🔗 Buka Link Eksternal
            </a></div>`;
        }
      }

      content.innerHTML = `
        ${badgeHtml(item.tipe)}
        <div class="detail-judul" style="margin-top:10px;">${escHtml(item.judul)}</div>
        ${mediaHtml}
        ${item.deskripsi ? `<div class="detail-desc">${escHtml(item.deskripsi)}</div>` : ''}
        ${item.file_path ? `
          <a class="download-btn" href="${item.file_path}" download="${escHtml(item.judul)}" target="_blank" rel="noopener">
            ⬇️ Unduh ${item.tipe.charAt(0).toUpperCase() + item.tipe.slice(1)}
          </a>` : ''}
      `;

      overlay.style.display = 'block';
      overlay.classList.add('active');
      box.style.display = 'block';
    }

    function closeDetail() {
      const overlay = document.getElementById('detailOverlay');
      overlay.classList.remove('active');
      overlay.style.display = 'none';
      document.getElementById('detailBox').style.display = 'none';
      document.querySelectorAll('#detailBox audio, #detailBox video').forEach(m => m.pause());
    }

    /* Coba konversi URL YouTube/dll ke embed */
    function tryEmbed(url) {
      const ytMatch = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/);
      if (ytMatch) return `https://www.youtube.com/embed/${ytMatch[1]}`;
      if (url.includes('youtube.com/embed/')) return url;
      return null;
    }

    /* ── Utils ── */
    function escHtml(s) {
      return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    function escJson(obj) { return JSON.parse(JSON.stringify(obj)); }

    function handleLogout() {
      const fd = new FormData(); fd.append('action','logout');
      fetch('auth.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.redirect) location.href=d.redirect; });
    }

    /* ── Init ── */

    loadKategori();
  </script>
</body>
</html>
<?php
require_once 'config.php';
requireLogin();
$user    = getCurrentUser();
$isAdmin = $user && $user['role'] === 'admin';
if (!$isAdmin) { header('Location: beranda.php'); exit; }
$initial = strtoupper(mb_substr($user['name'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Kelola Hafalan</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* ── Admin Hafalan Page ── */
    .hafalan-wrap { max-width: 900px; margin: 0 auto; padding: 0 20px 120px; }

    /* Section header */
    .section-header {
      display: flex; align-items: center; justify-content: space-between;
      gap: 14px; flex-wrap: wrap; margin-bottom: 18px;
    }
    .section-header h2 {
      font-family: 'Cinzel', serif; font-size: 1.1rem;
      color: var(--gold-light); letter-spacing: 0.08em;
    }

    /* Kategori list */
    .kat-list { display: flex; flex-direction: column; gap: 12px; margin-bottom: 32px; }
    .kat-row {
      background: rgba(22,33,62,0.85); border: 1px solid rgba(201,169,110,0.15);
      border-radius: var(--radius); padding: 16px 20px;
      display: flex; align-items: center; gap: 14px;
      cursor: pointer; transition: var(--transition);
    }
    body.light-mode .kat-row { background: rgba(255,255,255,0.65); border-color: rgba(201,169,110,0.25); }
    .kat-row:hover { border-color: rgba(201,169,110,0.4); }
    .kat-row.active-kat { border-color: var(--gold); background: rgba(201,169,110,0.07); }
    .kat-icon-big { font-size: 1.6rem; flex-shrink: 0; }
    .kat-info { flex: 1; min-width: 0; }
    .kat-nama { font-family: 'Cinzel', serif; font-size: 0.92rem; color: var(--cream); }
    .kat-desc { font-size: 0.78rem; color: var(--mist); margin-top: 2px; }
    .kat-count-badge {
      background: rgba(201,169,110,0.12); color: var(--gold);
      font-size: 0.72rem; font-family: 'Cinzel', serif;
      padding: 3px 10px; border-radius: 20px; flex-shrink: 0;
    }
    .kat-actions { display: flex; gap: 8px; flex-shrink: 0; }
    .icon-btn {
      width: 34px; height: 34px; border-radius: 8px;
      background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);
      color: var(--mist); font-size: 0.9rem; cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: var(--transition);
    }
    .icon-btn:hover { background: rgba(201,169,110,0.1); border-color: rgba(201,169,110,0.3); color: var(--gold); }
    .icon-btn.danger:hover { background: rgba(155,35,53,0.12); border-color: rgba(155,35,53,0.3); color: var(--torii-soft); }

    /* Item panel */
    .item-panel {
      background: rgba(255,255,255,0.02); border: 1px solid rgba(201,169,110,0.12);
      border-radius: var(--radius); padding: 22px; margin-bottom: 24px;
    }
    body.light-mode .item-panel { background: rgba(255,255,255,0.5); }

    .item-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
    .item-table th, .item-table td {
      padding: 11px 14px; text-align: left;
      border-bottom: 1px solid rgba(201,169,110,0.08); color: var(--cream);
    }
    .item-table th { color: var(--mist); font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.1em; font-family: 'Cinzel', serif; }
    .item-table tr:last-child td { border-bottom: none; }
    .item-table tr:hover td { background: rgba(255,255,255,0.02); }

    .thumb-mini {
      width: 44px; height: 44px; object-fit: cover;
      border-radius: 6px; display: block;
      border: 1px solid rgba(201,169,110,0.15);
    }
    .thumb-mini-placeholder {
      width: 44px; height: 44px; border-radius: 6px;
      background: rgba(201,169,110,0.07); border: 1px solid rgba(201,169,110,0.12);
      display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
    }

    /* Type badge (reuse dari hafalan.php) */
    .item-type-badge {
      display: inline-flex; align-items: center; gap: 4px;
      font-size: 0.67rem; letter-spacing: 0.1em; text-transform: uppercase;
      font-family: 'Cinzel', serif; padding: 3px 9px; border-radius: 4px;
    }
    .badge-gambar { background: rgba(74,124,89,0.15);  color: var(--bamboo); }
    .badge-audio  { background: rgba(201,169,110,0.12); color: var(--gold); }
    .badge-video  { background: rgba(155,35,53,0.12);  color: var(--torii-soft); }
    .badge-link   { background: rgba(138,138,158,0.15); color: var(--mist); }

    /* Form dalam modal */
    .tipe-selector { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 18px; }
    .tipe-btn {
      flex: 1; min-width: 80px; padding: 10px 8px;
      border-radius: var(--radius-sm); border: 1px solid rgba(201,169,110,0.2);
      background: rgba(255,255,255,0.03); color: var(--mist);
      font-family: 'Cinzel', serif; font-size: 0.75rem;
      cursor: pointer; text-align: center; transition: var(--transition);
    }
    .tipe-btn:hover { border-color: rgba(201,169,110,0.4); color: var(--gold-light); }
    .tipe-btn.selected {
      background: rgba(201,169,110,0.12); border-color: var(--gold);
      color: var(--gold); font-weight: 600;
    }
    .tipe-btn .tipe-icon { font-size: 1.3rem; display: block; margin-bottom: 4px; }

    .upload-zone {
      border: 2px dashed rgba(201,169,110,0.25); border-radius: var(--radius-sm);
      padding: 28px 16px; text-align: center; cursor: pointer;
      transition: var(--transition); position: relative; margin-bottom: 4px;
    }
    .upload-zone:hover, .upload-zone.drag-over {
      border-color: var(--gold); background: rgba(201,169,110,0.05);
    }
    .upload-zone input[type="file"] {
      position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%;
    }
    .upload-icon { font-size: 2rem; display: block; margin-bottom: 8px; }
    .upload-hint { font-size: 0.78rem; color: var(--mist); }
    .upload-name { font-size: 0.8rem; color: var(--gold); margin-top: 6px; }

    .form-sep {
      display: flex; align-items: center; gap: 12px; margin: 12px 0;
      color: var(--mist); font-size: 0.75rem;
    }
    .form-sep::before, .form-sep::after {
      content: ''; flex: 1; height: 1px; background: rgba(201,169,110,0.12);
    }

    .empty-state {
      text-align: center; padding: 40px 20px; color: var(--mist);
    }
    .empty-state .empty-icon { font-size: 2.5rem; display: block; margin-bottom: 10px; }
    .empty-state p { font-size: 0.83rem; }

    /* Delete confirm */
    .confirm-box {
      background: rgba(155,35,53,0.08); border: 1px solid rgba(155,35,53,0.25);
      border-radius: var(--radius-sm); padding: 14px 18px; margin-bottom: 20px;
      font-size: 0.85rem; color: var(--mist);
    }
    .confirm-box strong { color: var(--torii-soft); }

    @media (max-width: 600px) {
      .item-table { font-size: 0.78rem; }
      .item-table th, .item-table td { padding: 9px 10px; }
      .section-header h2 { font-size: 0.95rem; }
    }

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
    <a href="beranda.php" class="topbar-back" style="border: 2px solid #a1781e; padding: 8px 12px; border-radius: 8px; text-decoration: none; display: inline-block;">← Beranda</a>
  </header>

  <main class="dashboard-main">

    <section class="welcome-section fade-up">
      <span class="welcome-kanji">管理</span>
      <h1 class="welcome-title">Kelola Hafalan</h1>
      <p class="welcome-sub">Tambah dan kelola kategori serta materi hafalan</p>
      <div class="section-divider"></div>
    </section>

    <div class="hafalan-wrap">

      <!-- ── Kategori ── -->
      <div class="profile-card fade-up delay-1">
        <div class="section-header">
          <h2>⛩ Kategori</h2>
          <button class="btn-primary btn-sm" style="width:auto;padding:10px 20px;" onclick="openModalKat()">
            + Tambah Kategori
          </button>
        </div>
        <div class="kat-list" id="katList">
          <!-- loaded via JS -->
        </div>
      </div>

      <!-- ── Item per Kategori ── -->
      <div class="item-panel fade-up delay-2" id="itemPanel" style="display:none;">
        <div class="section-header">
          <h2 id="itemPanelTitle">🌸 Item Hafalan</h2>
          <button class="btn-primary btn-sm" style="width:auto;padding:10px 20px;" onclick="openModalItem()">
            + Tambah Item
          </button>
        </div>
        <div id="itemTableWrap"><!-- loaded via JS --></div>
      </div>

    </div>

   

  </main>

  <!-- ── MODAL OVERLAY ── -->
  <div class="modal-overlay" id="modalOverlay" style="display:none;" onclick="closeModal()"></div>

  <!-- Modal Kategori -->
  <div class="modal-box" id="modalKat" style="display:none; max-width:460px;">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <h3 class="modal-title" id="modalKatTitle">Tambah Kategori</h3>
    <input type="hidden" id="katEditId">
    <div class="form-group">
      <label class="form-label">Ikon</label>
      <input class="form-input" id="katIcon" value="📚" maxlength="4" style="font-size:1.4rem;width:80px;text-align:center;">
    </div>
    <div class="form-group">
      <label class="form-label">Nama Kategori *</label>
      <input class="form-input" id="katNama" placeholder="mis. Kosakata, Kanji, Grammar">
    </div>
    <div class="form-group">
      <label class="form-label">Deskripsi</label>
      <input class="form-input" id="katDesc" placeholder="Keterangan singkat (opsional)">
    </div>
    <button class="btn-primary" onclick="submitKat()" id="btnSubmitKat">Simpan</button>
  </div>

  <!-- Modal Item -->
  <div class="modal-box modal-wide" id="modalItem" style="display:none; max-width:540px;">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <h3 class="modal-title" id="modalItemTitle">Tambah Item Hafalan</h3>
    <input type="hidden" id="itemEditId">

    <!-- Pilih tipe -->
    <div class="form-group" id="tipeGroup">
      <label class="form-label">Tipe Konten *</label>
      <div class="tipe-selector">
        <button class="tipe-btn" data-tipe="gambar" onclick="selectTipe(this)">
          <span class="tipe-icon">🖼️</span>Gambar
        </button>
        <button class="tipe-btn" data-tipe="audio" onclick="selectTipe(this)">
          <span class="tipe-icon">🎵</span>Audio
        </button>
        <button class="tipe-btn" data-tipe="video" onclick="selectTipe(this)">
          <span class="tipe-icon">🎬</span>Video
        </button>
        <button class="tipe-btn" data-tipe="link" onclick="selectTipe(this)">
          <span class="tipe-icon">🔗</span>Link
        </button>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Judul *</label>
      <input class="form-input" id="itemJudul" placeholder="Judul materi hafalan">
    </div>

    <div class="form-group">
      <label class="form-label">Deskripsi / Keterangan</label>
      <textarea class="form-input" id="itemDesc" rows="3" placeholder="Penjelasan singkat (opsional)" style="resize:vertical;"></textarea>
    </div>

    <!-- Upload file (muncul untuk tipe gambar/audio/video) -->
    <div id="uploadSection" style="display:none;">
      <div class="form-group">
        <label class="form-label">Upload File</label>
        <div class="upload-zone" id="uploadZone">
          <input type="file" id="itemFile" onchange="onFileSelected(this)">
          <span class="upload-icon">📂</span>
          <div class="upload-hint" id="uploadHintText">Klik atau seret file ke sini</div>
          <div class="upload-name" id="uploadFileName"></div>
        </div>
      </div>
      <div class="form-sep">atau tempel link</div>
    </div>

    <!-- Link URL -->
    <div class="form-group" id="linkSection" style="display:none;">
      <label class="form-label" id="linkLabel">URL Link *</label>
      <input class="form-input" id="itemLink" placeholder="https://...">
      <div style="font-size:0.73rem;color:var(--mist);margin-top:5px;" id="linkHint"></div>
    </div>

    <button class="btn-primary" onclick="submitItem()" id="btnSubmitItem">Simpan</button>
  </div>

  <!-- Modal Hapus Konfirmasi -->
  <div class="modal-box" id="modalHapus" style="display:none; max-width:400px;">
    <button class="modal-close" onclick="closeModal()">✕</button>
    <h3 class="modal-title">Konfirmasi Hapus</h3>
    <div class="confirm-box">
      <strong>⚠ Perhatian!</strong><br>
      <span id="hapusMsg">Data ini akan dihapus permanen dan tidak bisa dikembalikan.</span>
    </div>
    <div style="display:flex;gap:10px;">
      <button class="btn-primary" style="background:linear-gradient(135deg,var(--torii),#a03020);" onclick="confirmHapus()" id="btnConfirmHapus">Hapus</button>
      <button class="btn-primary" style="background:rgba(255,255,255,0.06);color:var(--mist);border:1px solid rgba(255,255,255,0.1);" onclick="closeModal()">Batal</button>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/petals.js"></script>
  <script>
    /* ═══════════════════════════════════════
       STATE
    ═══════════════════════════════════════ */
    let kategoriList  = [];
    let activeKatId   = null;
    let selectedTipe  = null;
    let hapusFn       = null; // fungsi yang dipanggil saat konfirmasi hapus

    /* ═══════════════════════════════════════
       MODAL HELPERS
    ═══════════════════════════════════════ */
    function openModal(id) {
      const overlay = document.getElementById('modalOverlay');
      overlay.style.display = 'block';
      overlay.classList.add('active');
      document.getElementById(id).style.display = 'block';
    }
    function closeModal() {
      const overlay = document.getElementById('modalOverlay');
      overlay.classList.remove('active');
      overlay.style.display = 'none';
      ['modalKat','modalItem','modalHapus'].forEach(id => {
        document.getElementById(id).style.display = 'none';
      });
      resetItemForm();
    }

    /* ═══════════════════════════════════════
       TOAST
    ═══════════════════════════════════════ */
    function showToast(msg, type = '') {
      const t = document.getElementById('toast');
      t.textContent = msg;
      t.className   = 'toast show' + (type ? ' ' + type : '');
      clearTimeout(t._t);
      t._t = setTimeout(() => t.classList.remove('show'), 3000);
    }

    /* ═══════════════════════════════════════
       ESCAPE
    ═══════════════════════════════════════ */
    function escHtml(s) {
      return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ═══════════════════════════════════════
       KATEGORI
    ═══════════════════════════════════════ */
    async function loadKategori(keepActive = false) {
      const res  = await fetch('hafalan_api.php?action=get_kategori');
      const json = await res.json();
      kategoriList = json.data || [];
      renderKatList();
      if (!keepActive && kategoriList.length > 0) {
        setActiveKat(kategoriList[0].id, kategoriList[0].nama, kategoriList[0].icon);
      } else if (keepActive && activeKatId) {
        const k = kategoriList.find(k => k.id == activeKatId);
        if (k) setActiveKat(k.id, k.nama, k.icon);
      } else {
        document.getElementById('itemPanel').style.display = 'none';
      }
    }

    function renderKatList() {
      const list = document.getElementById('katList');
      if (!kategoriList.length) {
        list.innerHTML = `<div class="empty-state"><span class="empty-icon">📭</span><p>Belum ada kategori. Tambahkan kategori pertama!</p></div>`;
        return;
      }
      list.innerHTML = kategoriList.map(k => `
        <div class="kat-row ${k.id == activeKatId ? 'active-kat' : ''}" onclick="setActiveKat(${k.id},'${escHtml(k.nama)}','${escHtml(k.icon)}')">
          <span class="kat-icon-big">${escHtml(k.icon)}</span>
          <div class="kat-info">
            <div class="kat-nama">${escHtml(k.nama)}</div>
            ${k.deskripsi ? `<div class="kat-desc">${escHtml(k.deskripsi)}</div>` : ''}
          </div>
          <span class="kat-count-badge">${k.jumlah_item} item</span>
          <div class="kat-actions" onclick="event.stopPropagation()">
            <button class="icon-btn" title="Edit" onclick="openModalKat(${k.id})">✏️</button>
            <button class="icon-btn danger" title="Hapus" onclick="askHapusKat(${k.id},'${escHtml(k.nama)}')">🗑️</button>
          </div>
        </div>
      `).join('');
    }

    function setActiveKat(id, nama, icon) {
      activeKatId = id;
      renderKatList();
      const panel = document.getElementById('itemPanel');
      panel.style.display = 'block';
      document.getElementById('itemPanelTitle').textContent = `${icon||'🌸'} ${nama||'Item Hafalan'}`;
      loadItem(id);
    }

    /* Buka modal tambah/edit kategori */
    function openModalKat(editId = null) {
      document.getElementById('modalKatTitle').textContent = editId ? 'Edit Kategori' : 'Tambah Kategori';
      document.getElementById('katEditId').value = editId || '';
      if (editId) {
        const k = kategoriList.find(k => k.id == editId);
        if (k) {
          document.getElementById('katIcon').value = k.icon || '📚';
          document.getElementById('katNama').value = k.nama;
          document.getElementById('katDesc').value = k.deskripsi || '';
        }
      } else {
        document.getElementById('katIcon').value = '📚';
        document.getElementById('katNama').value = '';
        document.getElementById('katDesc').value = '';
      }
      openModal('modalKat');
      setTimeout(() => document.getElementById('katNama').focus(), 100);
    }

    async function submitKat() {
      const id   = document.getElementById('katEditId').value;
      const nama = document.getElementById('katNama').value.trim();
      const desc = document.getElementById('katDesc').value.trim();
      const icon = document.getElementById('katIcon').value.trim() || '📚';
      if (!nama) { showToast('Nama kategori wajib diisi','error'); return; }

      const fd = new FormData();
      fd.append('action', id ? 'edit_kategori' : 'tambah_kategori');
      if (id) fd.append('id', id);
      fd.append('nama', nama); fd.append('deskripsi', desc); fd.append('icon', icon);

      const btn = document.getElementById('btnSubmitKat');
      btn.disabled = true; btn.textContent = 'Menyimpan…';
      const res  = await fetch('hafalan_api.php', { method:'POST', body:fd });
      const json = await res.json();
      btn.disabled = false; btn.textContent = 'Simpan';

      if (json.success) {
        showToast(json.message, 'success');
        closeModal();
        loadKategori(true);
      } else {
        showToast(json.message || 'Gagal menyimpan','error');
      }
    }

    /* Hapus kategori */
    function askHapusKat(id, nama) {
      document.getElementById('hapusMsg').innerHTML =
        `Kategori <strong>"${escHtml(nama)}"</strong> beserta semua itemnya akan dihapus permanen.`;
      hapusFn = () => doHapus('hapus_kategori', { id });
      openModal('modalHapus');
    }

    /* ═══════════════════════════════════════
       ITEM
    ═══════════════════════════════════════ */
    async function loadItem(katId) {
      const wrap = document.getElementById('itemTableWrap');
      wrap.innerHTML = '<p style="color:var(--mist);font-size:0.83rem;padding:16px 0;">Memuat…</p>';
      const res  = await fetch(`hafalan_api.php?action=get_item&kategori_id=${katId}`);
      const json = await res.json();
      renderItemTable(json.data || []);
    }

    function renderItemTable(items) {
      const wrap = document.getElementById('itemTableWrap');
      if (!items.length) {
        wrap.innerHTML = `<div class="empty-state"><span class="empty-icon">🌸</span><p>Belum ada item. Tambahkan materi hafalan!</p></div>`;
        return;
      }
      const icons = { gambar:'🖼️', audio:'🎵', video:'🎬', link:'🔗' };
      const badgeCls = { gambar:'badge-gambar', audio:'badge-audio', video:'badge-video', link:'badge-link' };
      wrap.innerHTML = `
        <div style="overflow-x:auto;">
        <table class="item-table">
          <thead>
            <tr>
              <th>Media</th>
              <th>Judul</th>
              <th>Tipe</th>
              <th style="text-align:right;">Aksi</th>
            </tr>
          </thead>
          <tbody>
            ${items.map(it => `
              <tr>
                <td>${it.tipe==='gambar'&&it.file_path
                      ? `<img class="thumb-mini" src="${escHtml(it.file_path)}" alt="">`
                      : `<div class="thumb-mini-placeholder">${icons[it.tipe]||'📄'}</div>`
                    }</td>
                <td>
                  <div style="font-family:'Cinzel',serif;font-size:0.85rem;">${escHtml(it.judul)}</div>
                  ${it.deskripsi ? `<div style="font-size:0.73rem;color:var(--mist);margin-top:2px;">${escHtml(it.deskripsi.substring(0,60))}${it.deskripsi.length>60?'…':''}</div>` : ''}
                </td>
                <td><span class="item-type-badge ${badgeCls[it.tipe]||''}">${icons[it.tipe]||''} ${it.tipe}</span></td>
                <td style="text-align:right;">
                  <div style="display:inline-flex;gap:6px;">
                    <button class="icon-btn" title="Edit" onclick="openModalItem(${JSON.stringify(it).replace(/"/g,'&quot;')})">✏️</button>
                    <button class="icon-btn danger" title="Hapus" onclick="askHapusItem(${it.id},'${escHtml(it.judul)}')">🗑️</button>
                  </div>
                </td>
              </tr>`).join('')}
          </tbody>
        </table>
        </div>`;
    }

    /* Buka modal tambah/edit item */
    function openModalItem(item = null) {
      resetItemForm();
      document.getElementById('modalItemTitle').textContent = item ? 'Edit Item' : 'Tambah Item Hafalan';
      document.getElementById('itemEditId').value = item ? item.id : '';

      if (item) {
        document.getElementById('itemJudul').value = item.judul || '';
        document.getElementById('itemDesc').value  = item.deskripsi || '';
        document.getElementById('itemLink').value  = item.link_url || '';
        // Tipe terkunci saat edit
        document.getElementById('tipeGroup').style.display = 'none';
        selectTipeByValue(item.tipe);
      } else {
        document.getElementById('tipeGroup').style.display = 'block';
      }
      openModal('modalItem');
      setTimeout(() => document.getElementById('itemJudul').focus(), 100);
    }

    function resetItemForm() {
      document.getElementById('itemEditId').value = '';
      document.getElementById('itemJudul').value  = '';
      document.getElementById('itemDesc').value   = '';
      document.getElementById('itemLink').value   = '';
      document.getElementById('uploadFileName').textContent = '';
      document.getElementById('itemFile').value   = '';
      document.getElementById('uploadSection').style.display = 'none';
      document.getElementById('linkSection').style.display   = 'none';
      document.getElementById('tipeGroup').style.display     = 'block';
      document.querySelectorAll('.tipe-btn').forEach(b => b.classList.remove('selected'));
      selectedTipe = null;
    }

    function selectTipe(btn) {
      document.querySelectorAll('.tipe-btn').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      selectTipeByValue(btn.dataset.tipe);
    }

    function selectTipeByValue(tipe) {
      selectedTipe = tipe;
      // Tandai btn jika ada
      document.querySelectorAll('.tipe-btn').forEach(b => {
        if (b.dataset.tipe === tipe) b.classList.add('selected');
      });
      const up = document.getElementById('uploadSection');
      const lk = document.getElementById('linkSection');
      const lkLabel = document.getElementById('linkLabel');
      const lkHint  = document.getElementById('linkHint');

      if (tipe === 'link') {
        up.style.display = 'none';
        lk.style.display = 'block';
        lkLabel.textContent = 'URL Link *';
        lkHint.textContent  = 'Mendukung embed YouTube secara otomatis.';
      } else if (['gambar','audio','video'].includes(tipe)) {
        const hints = { gambar:'JPG, PNG, GIF, WebP', audio:'MP3, WAV, OGG', video:'MP4, WebM, OGG' };
        document.getElementById('uploadHintText').textContent = `Upload file (${hints[tipe]})`;
        up.style.display = 'block';
        lk.style.display = 'block';
        lkLabel.textContent = 'Atau tempel URL ' + tipe;
        lkHint.textContent  = 'Jika diisi, link ini digunakan sebagai media (tidak perlu upload file).';
      }
    }

    function onFileSelected(input) {
      const fn = document.getElementById('uploadFileName');
      fn.textContent = input.files[0] ? input.files[0].name : '';
    }

    async function submitItem() {
      const isEdit = !!document.getElementById('itemEditId').value;
      const judul  = document.getElementById('itemJudul').value.trim();
      const desc   = document.getElementById('itemDesc').value.trim();
      const link   = document.getElementById('itemLink').value.trim();
      const file   = document.getElementById('itemFile').files[0];

      if (!judul) { showToast('Judul wajib diisi','error'); return; }
      if (!isEdit && !selectedTipe) { showToast('Pilih tipe konten','error'); return; }
      if (!isEdit && selectedTipe !== 'link' && !file && !link) {
        showToast('Upload file atau isi URL','error'); return;
      }
      if (!isEdit && selectedTipe === 'link' && !link) {
        showToast('URL link wajib diisi','error'); return;
      }

      const fd = new FormData();
      fd.append('action', isEdit ? 'edit_item' : 'tambah_item');
      if (isEdit) {
        fd.append('id', document.getElementById('itemEditId').value);
      } else {
        fd.append('kategori_id', activeKatId);
        fd.append('tipe', selectedTipe);
      }
      fd.append('judul', judul);
      fd.append('deskripsi', desc);
      if (link) fd.append('link_url', link);
      if (file) fd.append('file', file);

      const btn = document.getElementById('btnSubmitItem');
      btn.disabled = true; btn.textContent = 'Menyimpan…';
      const res  = await fetch('hafalan_api.php', { method:'POST', body:fd });
      const json = await res.json();
      btn.disabled = false; btn.textContent = 'Simpan';

      if (json.success) {
        showToast(json.message, 'success');
        closeModal();
        loadItem(activeKatId);
        loadKategori(true);
      } else {
        showToast(json.message || 'Gagal menyimpan','error');
      }
    }

    function askHapusItem(id, judul) {
      document.getElementById('hapusMsg').innerHTML =
        `Item <strong>"${escHtml(judul)}"</strong> akan dihapus permanen (termasuk file media jika ada).`;
      hapusFn = () => doHapus('hapus_item', { id });
      openModal('modalHapus');
    }

    /* ═══════════════════════════════════════
       HAPUS GENERIK
    ═══════════════════════════════════════ */
    async function doHapus(action, params) {
      const fd = new FormData();
      fd.append('action', action);
      for (const [k,v] of Object.entries(params)) fd.append(k, v);
      const btn = document.getElementById('btnConfirmHapus');
      btn.disabled = true; btn.textContent = 'Menghapus…';
      const res  = await fetch('hafalan_api.php', { method:'POST', body:fd });
      const json = await res.json();
      btn.disabled = false; btn.textContent = 'Hapus';
      if (json.success) {
        showToast(json.message, 'success');
        closeModal();
        if (action === 'hapus_kategori') {
          activeKatId = null;
          document.getElementById('itemPanel').style.display = 'none';
          loadKategori(false);
        } else {
          loadItem(activeKatId);
          loadKategori(true);
        }
      } else {
        showToast(json.message || 'Gagal menghapus','error');
      }
    }

    function confirmHapus() { if (hapusFn) hapusFn(); }

    /* ═══════════════════════════════════════
       LOGOUT
    ═══════════════════════════════════════ */
    function handleLogout() {
      const fd = new FormData(); fd.append('action','logout');
      fetch('auth.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.redirect) location.href=d.redirect; });
    }

    /* ── Init ── */

    loadKategori();
  </script>
</body>
</html>
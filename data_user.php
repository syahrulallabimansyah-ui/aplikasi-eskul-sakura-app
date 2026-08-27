<?php
require_once 'config.php';
requireAdmin();

$user    = getCurrentUser();
$initial = strtoupper(mb_substr($user['name'], 0, 1));
$db      = getDB();

$message     = '';
$messageType = '';

/* ─────────────────────────────────────────
   HANDLE ACTIONS (POST)
───────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $targetId  = (int)($_POST['user_id'] ?? 0);

    // Jangan boleh aksi ke diri sendiri
    if ($targetId === (int)$user['id'] && in_array($action, ['delete_user'])) {
        $message     = 'Tidak dapat menghapus akun Anda sendiri.';
        $messageType = 'error';

    } elseif ($action === 'delete_user' && $targetId > 0) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $message     = 'Pengguna berhasil dihapus.';
        $messageType = 'success';

    } elseif ($action === 'edit_user' && $targetId > 0) {
        $newNis  = sanitize($_POST['nis'] ?? '');
        $newPass = $_POST['password'] ?? '';

        $errors = [];

        // Validasi NIS unik (jika diisi)
        if ($newNis !== '') {
            $chk = $db->prepare("SELECT id FROM users WHERE nis = ? AND id != ? LIMIT 1");
            $chk->execute([$newNis, $targetId]);
            if ($chk->fetch()) {
                $errors[] = 'NIS sudah digunakan oleh pengguna lain.';
            }
        }

        // Validasi panjang password (jika diisi)
        if ($newPass !== '' && strlen($newPass) < 6) {
            $errors[] = 'Kata sandi minimal 6 karakter.';
        }

        if ($errors) {
            $message     = implode(' ', $errors);
            $messageType = 'error';
        } else {
            if ($newPass !== '') {
                $hash = password_hash($newPass, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $db->prepare("UPDATE users SET nis = ?, password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newNis ?: null, $hash, $targetId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET nis = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$newNis ?: null, $targetId]);
            }
            $message     = 'Data pengguna berhasil diperbarui.';
            $messageType = 'success';
        }
    }
}

/* ─────────────────────────────────────────
   AMBIL SEMUA USER
───────────────────────────────────────── */
$users = $db->query("SELECT id, name, nis, email, role, created_at, hiragana_status, katakana_status, hiragana_exam_score, katakana_exam_score FROM users ORDER BY role ASC, name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Data Pengguna</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* ── Layout ── */
    .du-page {
      min-height: 100vh;
      padding: 80px 16px 40px;
      max-width: 1000px;
      margin: 0 auto;
    }

    /* ── Topbar ── */
    .topbar { position: fixed; top: 0; left: 0; right: 0; z-index: 100; }

    /* ── Page header ── */
    .du-header {
      text-align: center;
      margin-bottom: 28px;
    }
    .du-header .kanji-title {
      font-size: 2rem;
      color: var(--torii);
      display: block;
      margin-bottom: 4px;
    }
    .du-header h1 {
      font-size: 1.4rem;
      font-weight: 800;
      color: var(--text);
      margin: 0 0 6px;
    }
    .du-header p {
      font-size: .88rem;
      color: var(--text-muted);
      margin: 0;
    }

    /* ── Alert ── */
    .du-alert {
      padding: 12px 18px;
      border-radius: 10px;
      margin-bottom: 18px;
      font-size: .9rem;
      font-weight: 600;
      border: 1px solid transparent;
    }
    .du-alert.success {
      background: rgba(74,124,89,.12);
      border-color: rgba(74,124,89,.35);
      color: var(--bamboo);
    }
    .du-alert.error {
      background: rgba(183,75,75,.10);
      border-color: rgba(183,75,75,.35);
      color: var(--torii);
    }

    /* ── Table card ── */
    .du-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 4px 24px rgba(0,0,0,.08);
    }
    .du-card-header {
      padding: 18px 22px 14px;
      border-bottom: 1px solid var(--card-border);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .du-card-header-icon {
      font-size: 1.3rem;
    }
    .du-card-header-title {
      font-size: 1rem;
      font-weight: 700;
      color: var(--text);
    }
    .du-card-header-count {
      margin-left: auto;
      background: rgba(183,75,75,.12);
      color: var(--torii);
      font-size: .78rem;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
    }

    /* ── Scrollable table wrapper ── */
    .du-table-wrap {
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }
    table.du-table {
      width: 100%;
      border-collapse: collapse;
      font-size: .88rem;
    }
    .du-table thead tr {
      background: rgba(183,75,75,.06);
    }
    .du-table th {
      padding: 12px 14px;
      text-align: left;
      font-size: .78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: var(--text-muted);
      white-space: nowrap;
      border-bottom: 1px solid var(--card-border);
    }
    .du-table td {
      padding: 13px 14px;
      color: var(--text);
      border-bottom: 1px solid var(--card-border);
      vertical-align: middle;
    }
    .du-table tbody tr:last-child td { border-bottom: none; }
    .du-table tbody tr:hover { background: rgba(183,75,75,.04); }

    /* ── Role badge ── */
    .role-pill {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: .75rem;
      font-weight: 700;
    }
    .role-pill.admin {
      background: rgba(183,75,75,.12);
      color: var(--torii);
    }
    .role-pill.user {
      background: rgba(74,124,89,.12);
      color: var(--bamboo);
    }

    /* ── Kana understanding badge ── */
    .kana-pill-stack {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }
    .kana-pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      width: fit-content;
      padding: 2px 9px;
      border-radius: 20px;
      font-size: .72rem;
      font-weight: 700;
      white-space: nowrap;
      cursor: default;
    }
    .kana-pill-belum {
      background: rgba(150,150,150,.14);
      color: #888;
    }
    .kana-pill-paham {
      background: rgba(74,124,89,.12);
      color: var(--bamboo);
    }
    .kana-pill-lulus {
      background: rgba(183,75,75,.12);
      color: var(--torii);
    }

    /* ── Action buttons in table ── */
    .du-btn {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 5px 11px;
      border-radius: 8px;
      font-size: .8rem;
      font-weight: 700;
      border: none;
      cursor: pointer;
      transition: opacity .18s, transform .15s;
      white-space: nowrap;
    }
    .du-btn:hover { opacity: .82; transform: translateY(-1px); }
    .du-btn-edit {
      background: rgba(196,147,38,.18);
      color: var(--gold);
      border: 1px solid rgba(196,147,38,.35);
    }
    .du-btn-delete {
      background: rgba(183,75,75,.12);
      color: var(--torii);
      border: 1px solid rgba(183,75,75,.3);
    }
    .du-actions { display: flex; gap: 6px; flex-wrap: wrap; }

    /* ── Modal overlay ── */
    .du-modal-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.5);
      z-index: 500;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .du-modal-overlay.open { display: flex; }
    .du-modal {
      background: #ffffff;
      border: 2px solid #d4b8b8;
      border-radius: 18px;
      width: 100%;
      max-width: 420px;
      padding: 28px 26px 24px;
      box-shadow: 0 12px 48px rgba(0,0,0,.45);
      animation: modalIn .2s ease;
      position: relative;
      z-index: 501;
    }
    [data-theme="dark"] .du-modal,
    body.dark .du-modal {
      background: #1e1a1a;
      border-color: #3a2e2e;
      color: #f0e8e8;
    }
    @keyframes modalIn {
      from { transform: translateY(20px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }
    .du-modal-title {
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--text);
      color: brown;
      margin-bottom: 18px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .du-modal label {
      display: block;
      font-size: .82rem;
      font-weight: 700;
      color: var(--text-muted);
      margin-bottom: 5px;
    }
    .du-modal input {
      width: 100%;
      padding: 10px 13px;
      border-radius: 10px;
      border: 1px solid var(--card-border);
      background: var(--input-bg, var(--card-bg));
      color: var(--text);
      font-size: .92rem;
      box-sizing: border-box;
      margin-bottom: 14px;
      transition: border-color .18s;
    }
    .du-modal input:focus {
      outline: none;
      border-color: var(--torii);
    }
    .du-modal-note {
      font-size: .78rem;
      color: var(--text-muted);
      margin-top: -10px;
      margin-bottom: 14px;
    }
    .du-modal-actions {
      display: flex;
      gap: 10px;
      margin-top: 6px;
    }
    .du-modal-btn {
      flex: 1;
      padding: 10px;
      border-radius: 10px;
      border: none;
      font-size: .9rem;
      font-weight: 700;
      cursor: pointer;
      transition: opacity .18s;
    }
    .du-modal-btn:hover { opacity: .85; }
    .du-modal-btn-save {
      background: var(--torii);
      color: #fff;
    }
    .du-modal-btn-cancel {
      background: var(--card-border);
      color: var(--text);
    }

    /* Confirm modal */
    .du-confirm-text {
      font-size: .95rem;
      color: var(--text);
      color: #c09d00;
      margin-bottom: 20px;
      line-height: 1.6;
    }
    .du-confirm-text strong { color: var(--torii); }
    .du-modal-btn-danger {
      background: var(--torii);
      color: #fff;
    }

    /* ── Back button (di dalam topbar) ── */
    .du-back-btn {
      color: var(--torii);
      text-decoration: none;
      font-size: .875rem;
      font-weight: 700;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 6px 14px;
      border-radius: 20px;
      border: 1.5px solid rgba(183,75,75,.35);
      background: rgba(183,75,75,.08);
      transition: background .18s, gap .18s;
      white-space: nowrap;
    }
    .du-back-btn:hover {
      background: rgba(183,75,75,.18);
      gap: 8px;
    }

    /* ── Empty state ── */
    .du-empty {
      text-align: center;
      padding: 40px 20px;
      color: var(--text-muted);
      font-size: .92rem;
    }

    /* ── Search bar ── */
    .du-search-wrap {
      padding: 14px 22px;
      border-bottom: 1px solid var(--card-border);
    }
    .du-search-inner {
      display: flex;
      align-items: center;
      gap: 10px;
      background: var(--input-bg, var(--card-bg));
      border: 1px solid var(--card-border);
      border-radius: 10px;
      padding: 8px 14px;
      transition: border-color .18s;
    }
    .du-search-inner:focus-within {
      border-color: var(--torii);
    }
    .du-search-icon {
      font-size: 1rem;
      color: var(--text-muted);
      flex-shrink: 0;
    }
    .du-search-input {
      flex: 1;
      border: none;
      background: transparent;
      color: var(--text);
      font-size: .9rem;
      outline: none;
    }
    .du-search-input::placeholder {
      color: var(--text-muted);
    }
    .du-search-clear {
      background: none;
      border: none;
      cursor: pointer;
      color: var(--text-muted);
      font-size: 1rem;
      padding: 0 2px;
      display: none;
      line-height: 1;
    }
    .du-search-clear.visible { display: block; }
    .du-no-results {
      text-align: center;
      padding: 32px 20px;
      color: var(--text-muted);
      font-size: .92rem;
      display: none;
    }

    /* ════════════════════════════════════════════
       RESPONSIVE / MOBILE FIXES
    ════════════════════════════════════════════ */
    @media(max-width: 720px) {
      .du-page { padding: 76px 12px 32px; }
      .du-back-btn { font-size: .8rem; padding: 5px 11px; }

      .du-card-header {
        padding: 14px 14px 10px;
        flex-wrap: wrap;
        gap: 8px;
      }
      .du-card-header-title { font-size: .9rem; }

      .du-search-wrap { padding: 10px 12px; }

      /* ── Tabel berubah jadi tampilan "kartu" di HP ── */
      .du-table-wrap { overflow-x: hidden; padding: 10px; }
      .du-table thead { display: none; }
      .du-table,
      .du-table tbody,
      .du-table tr,
      .du-table td { display: block; width: 100%; box-sizing: border-box; }

      .du-table tbody tr {
        border: 1px solid var(--card-border);
        border-radius: 14px;
        padding: 4px 14px;
        margin-bottom: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,.05);
      }
      .du-table tbody tr:last-child { margin-bottom: 0; }
      .du-table tbody tr:hover { background: transparent; }

      .du-table td {
        padding: 9px 0;
        border-bottom: 1px dashed var(--card-border);
      }
      .du-table td:last-child { border-bottom: none; }

      /* Nomor urut disembunyikan di mobile */
      .du-table td:first-child { display: none; }

      .du-table td::before {
        content: attr(data-label);
        display: block;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 5px;
      }

      .du-actions { gap: 8px; }
      .du-btn { padding: 7px 13px; font-size: .82rem; }
    }

    @media(max-width: 420px) {
      .du-page { padding: 72px 10px 28px; }
      .du-back-btn { font-size: .75rem; padding: 4px 10px; }
      .du-header .kanji-title { font-size: 1.7rem; }
      .du-header h1 { font-size: 1.15rem; }

      .du-modal { padding: 22px 16px 20px; }
      .du-modal-actions { flex-direction: column; }

      .du-card-header-count { font-size: .73rem; }
    }
  </style>
</head>
<body class="dashboard-page">

  <!-- Backgrounds -->
  <div class="asanoha-bg"></div>

  <!-- Topbar -->
  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <div style="display:flex; align-items:center; gap:10px;">
      <a href="beranda.php" class="du-back-btn" title="Kembali ke Beranda">← Beranda</a>
    </div>
  </header>

  <!-- Main -->
  <main class="du-page fade-up">

    <!-- Header -->
    <div class="du-header">
      <span class="kanji-title">会員</span>
      <h1>Data Pengguna</h1>
      <p>Kelola semua akun anggota dan administrator Sakura App</p>
    </div>

    <!-- Alert -->
    <?php if ($message): ?>
      <div class="du-alert <?= $messageType ?>">
        <?= $messageType === 'success' ? '✅' : '⚠️' ?>
        <?= htmlspecialchars($message) ?>
      </div>
    <?php endif; ?>

    <!-- Table Card -->
    <div class="du-card">
      <div class="du-card-header">
        <span class="du-card-header-icon">👥</span>
        <span class="du-card-header-title">Daftar Pengguna</span>
        <span class="du-card-header-count" id="userCount"><?= count($users) ?> akun</span>
      </div>

      <?php if (empty($users)): ?>
        <div class="du-empty">Belum ada pengguna terdaftar.</div>
      <?php else: ?>

      <!-- Search Bar -->
      <div class="du-search-wrap">
        <div class="du-search-inner">
          <span class="du-search-icon">🔍</span>
          <input
            type="text"
            class="du-search-input"
            id="userSearch"
            placeholder="Cari nama, NIS, email, atau peran…"
            autocomplete="off"
            oninput="filterUsers(this)"
          >
          <button class="du-search-clear" id="searchClear" onclick="clearSearch()" title="Hapus pencarian">✕</button>
        </div>
      </div>
      <div class="du-no-results" id="noResults">
        🌸 Tidak ada pengguna yang cocok dengan pencarian.
      </div>
      <div class="du-table-wrap">
        <table class="du-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Nama</th>
              <th>NIS</th>
              <th>Email</th>
              <th>Peran</th>
              <th>Pemahaman Kana</th>
              <th>Bergabung</th>
              <th>Aksi</th>
            </tr>
          </thead>
          <tbody id="userTableBody">
            <?php foreach ($users as $i => $u): ?>
            <tr
              data-name="<?= strtolower(htmlspecialchars($u['name'])) ?>"
              data-nis="<?= strtolower(htmlspecialchars($u['nis'] ?? '')) ?>"
              data-email="<?= strtolower(htmlspecialchars($u['email'])) ?>"
              data-role="<?= strtolower($u['role']) ?>"
            >
              <td data-label="No." style="color:var(--text-muted); font-size:.8rem;"><?= $i + 1 ?></td>
              <td data-label="Nama">
                <strong><?= htmlspecialchars($u['name']) ?></strong>
              </td>
              <td data-label="NIS">
                <?= $u['nis'] ? htmlspecialchars($u['nis']) : '<span style="color:var(--text-muted);font-style:italic;">—</span>' ?>
              </td>
              <td data-label="Email" style="font-size:.83rem; color:var(--text-muted);">
                <?= htmlspecialchars($u['email']) ?>
              </td>
              <td data-label="Peran">
                <span class="role-pill <?= $u['role'] ?>">
                  <?= $u['role'] === 'admin' ? '⛩ Admin' : '🌸 Member' ?>
                </span>
              </td>
              <td data-label="Pemahaman Kana">
                <?php if ($u['role'] === 'admin'): ?>
                  <span style="color:var(--text-muted); font-style:italic; font-size:.78rem;">—</span>
                <?php else:
                  $kanaPillMap = [
                      'belum_dijawab' => ['❔ Belum', 'kana-pill-belum'],
                      'sudah_paham'   => ['✅ Paham', 'kana-pill-paham'],
                      'lulus_ujian'   => ['🏅 Lulus', 'kana-pill-lulus'],
                  ];
                  $hStat = $u['hiragana_status'] ?? 'belum_dijawab';
                  $kStat = $u['katakana_status'] ?? 'belum_dijawab';
                  [$hText, $hCls] = $kanaPillMap[$hStat] ?? $kanaPillMap['belum_dijawab'];
                  [$kText, $kCls] = $kanaPillMap[$kStat] ?? $kanaPillMap['belum_dijawab'];
                ?>
                <div class="kana-pill-stack">
                  <span class="kana-pill <?= $hCls ?>" title="Hiragana<?= $u['hiragana_exam_score'] !== null ? ' — skor ujian terakhir: ' . htmlspecialchars($u['hiragana_exam_score']) . '%' : '' ?>">ひ <?= $hText ?></span>
                  <span class="kana-pill <?= $kCls ?>" title="Katakana<?= $u['katakana_exam_score'] !== null ? ' — skor ujian terakhir: ' . htmlspecialchars($u['katakana_exam_score']) . '%' : '' ?>">カ <?= $kText ?></span>
                </div>
                <?php endif; ?>
              </td>
              <td data-label="Bergabung" style="font-size:.8rem; color:var(--text-muted); white-space:nowrap;">
                <?= date('d M Y', strtotime($u['created_at'])) ?>
              </td>
              <td data-label="Aksi">
                <div class="du-actions">
                  <button class="du-btn du-btn-edit"
                    onclick="openEdit(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>', '<?= htmlspecialchars(addslashes($u['nis'] ?? '')) ?>')">
                    ✏️ Edit
                  </button>
                  <?php if ((int)$u['id'] !== (int)$user['id']): ?>
                  <button class="du-btn du-btn-delete"
                    onclick="openDelete(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['name'])) ?>')">
                    🗑 Hapus
                  </button>
                  <?php else: ?>
                  <span style="font-size:.75rem; color:var(--text-muted); font-style:italic;">Anda</span>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </main>

  <!-- ── MODAL EDIT ── -->
  <div class="du-modal-overlay" id="editModal">
    <div class="du-modal">
      <div class="du-modal-title">✏️ Edit Pengguna — <span id="editName" style="color:var(--torii);"></span></div>
      <form method="POST" action="data_user.php">
        <input type="hidden" name="action" value="edit_user">
        <input type="hidden" name="user_id" id="editUserId">

        <label for="editNis">NIS / Nomor Induk</label>
        <input type="text" id="editNis" name="nis" placeholder="Kosongkan untuk tidak mengubah">

        <label for="editPassword">Kata Sandi Baru</label>
        <input type="password" id="editPassword" name="password" placeholder="Kosongkan untuk tidak mengubah">
        <p class="du-modal-note">Minimal 6 karakter. Kosongkan jika tidak ingin mengganti sandi.</p>

        <div class="du-modal-actions">
          <button type="button" class="du-modal-btn du-modal-btn-cancel" onclick="closeModal('editModal')">Batal</button>
          <button type="submit" class="du-modal-btn du-modal-btn-save">Simpan 保存</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── MODAL KONFIRMASI HAPUS ── -->
  <div class="du-modal-overlay" id="deleteModal">
    <div class="du-modal">
      <div class="du-modal-title">🗑 Hapus Pengguna</div>
      <p class="du-confirm-text">
        Yakin ingin menghapus pengguna <strong id="deleteName"></strong>?<br>
        Semua data terkait (ujian, tugas, quiz) juga akan dihapus. Tindakan ini <strong>tidak dapat dibatalkan</strong>.
      </p>
      <form method="POST" action="data_user.php">
        <input type="hidden" name="action" value="delete_user">
        <input type="hidden" name="user_id" id="deleteUserId">
        <div class="du-modal-actions">
          <button type="button" class="du-modal-btn du-modal-btn-cancel" onclick="closeModal('deleteModal')">Batal</button>
          <button type="submit" class="du-modal-btn du-modal-btn-danger">Hapus 削除</button>
        </div>
      </form>
    </div>
  </div>

  <script src="js/theme.js"></script>
  <script>
    /* ── Open/Close modals ── */
    function openEdit(id, name, nis) {
      document.getElementById('editUserId').value = id;
      document.getElementById('editName').textContent = name;
      document.getElementById('editNis').value = nis;
      document.getElementById('editPassword').value = '';
      document.getElementById('editModal').classList.add('open');
    }

    function openDelete(id, name) {
      document.getElementById('deleteUserId').value = id;
      document.getElementById('deleteName').textContent = name;
      document.getElementById('deleteModal').classList.add('open');
    }

    function closeModal(id) {
      document.getElementById(id).classList.remove('open');
    }

    // Tutup modal kalau klik di luar
    document.querySelectorAll('.du-modal-overlay').forEach(overlay => {
      overlay.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
      });
    });

    /* ── Search / filter ── */
    const totalUsers = <?= count($users) ?>;

    function filterUsers(input) {
      const q = input.value.trim().toLowerCase();
      const rows = document.querySelectorAll('#userTableBody tr');
      const clearBtn = document.getElementById('searchClear');
      const noResults = document.getElementById('noResults');
      const countEl = document.getElementById('userCount');

      clearBtn.classList.toggle('visible', q.length > 0);

      let visible = 0;
      rows.forEach(row => {
        const match =
          row.dataset.name.includes(q)  ||
          row.dataset.nis.includes(q)   ||
          row.dataset.email.includes(q) ||
          row.dataset.role.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) visible++;
      });

      // Renumber visible rows
      let num = 1;
      rows.forEach(row => {
        if (row.style.display !== 'none') {
          row.querySelector('td:first-child').textContent = num++;
        }
      });

      noResults.style.display = visible === 0 ? 'block' : 'none';
      countEl.textContent = q ? visible + ' / ' + totalUsers + ' akun' : totalUsers + ' akun';
    }

    function clearSearch() {
      const input = document.getElementById('userSearch');
      input.value = '';
      filterUsers(input);
      input.focus();
    }

    // Shortcut: tekan "/" untuk fokus ke search
    document.addEventListener('keydown', function(e) {
      if (e.key === '/' && document.activeElement.tagName !== 'INPUT') {
        e.preventDefault();
        document.getElementById('userSearch').focus();
      }
      if (e.key === 'Escape') {
        document.querySelectorAll('.du-modal-overlay.open').forEach(m => m.classList.remove('open'));
      }
    });
  </script>
</body>
</html>
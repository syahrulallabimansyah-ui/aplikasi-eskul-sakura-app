<?php
/**
 * tugas_admin.php — Manajemen Tugas (Admin)
 * Sakura App — UI Revamp v2
 * Disesuaikan dengan struktur DB: sakura_app
 */
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header('Location: beranda.php');
    exit;
}

$db  = getDB();
$msg = '';
$err = '';

/* ------------------------------------------------------------------ */
/*  HANDLE POST ACTIONS                                                 */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── Buat / Edit Tugas ── */
    if ($action === 'save_tugas') {
        $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $judul       = trim($_POST['judul']       ?? '');
        $deskripsi   = trim($_POST['deskripsi']   ?? '');
        $video_url   = trim($_POST['video_url']   ?? '');
        $tipe_upload = $_POST['tipe_upload']      ?? 'foto';
        $status      = $_POST['status']           ?? 'draft';

        $allowed_tipe   = ['foto', 'video', 'foto_video'];
        $allowed_status = ['draft', 'published', 'closed'];

        if ($judul === '') {
            $err = 'Judul tugas tidak boleh kosong.';
        } elseif (!in_array($tipe_upload, $allowed_tipe)) {
            $err = 'Tipe upload tidak valid.';
        } elseif (!in_array($status, $allowed_status)) {
            $err = 'Status tidak valid.';
        } else {
            if ($id > 0) {
                $stmt = $db->prepare("
                    UPDATE tugas
                    SET judul=?, deskripsi=?, video_url=?, tipe_upload=?, status=?, updated_at=NOW()
                    WHERE id=?
                ");
                $stmt->execute([$judul, $deskripsi ?: null, $video_url ?: null, $tipe_upload, $status, $id]);
                $msg = 'Tugas berhasil diperbarui.';
            } else {
                $stmt = $db->prepare("
                    INSERT INTO tugas (judul, deskripsi, video_url, tipe_upload, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$judul, $deskripsi ?: null, $video_url ?: null, $tipe_upload, $status, $user['id']]);
                $msg = 'Tugas berhasil dibuat.';
            }
        }
    }

    /* ── Quick Status Change ── */
    if ($action === 'quick_status') {
        $id     = (int)($_POST['id']   ?? 0);
        $status = $_POST['status']     ?? '';
        $allowed = ['draft', 'published', 'closed'];
        if ($id > 0 && in_array($status, $allowed)) {
            $db->prepare("UPDATE tugas SET status=?, updated_at=NOW() WHERE id=?")->execute([$status, $id]);
            $msg = 'Status tugas berhasil diubah.';
        }
    }

    /* ── Duplikat Tugas ── */
    if ($action === 'duplicate_tugas') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $orig = $db->prepare("SELECT * FROM tugas WHERE id=?");
            $orig->execute([$id]);
            $t = $orig->fetch();
            if ($t) {
                $stmt = $db->prepare("
                    INSERT INTO tugas (judul, deskripsi, video_url, tipe_upload, status, created_by)
                    VALUES (?, ?, ?, ?, 'draft', ?)
                ");
                $stmt->execute(['[Salinan] '.$t['judul'], $t['deskripsi'], $t['video_url'], $t['tipe_upload'], $user['id']]);
                $msg = 'Tugas berhasil diduplikat sebagai Draft.';
            }
        }
    }

    /* ── Hapus Tugas ── */
    if ($action === 'delete_tugas') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            // Hapus file fisik submission dulu
            $subs = $db->prepare("SELECT file_foto, file_video FROM tugas_submissions WHERE tugas_id = ?");
            $subs->execute([$id]);
            foreach ($subs->fetchAll() as $s) {
                if ($s['file_foto']  && file_exists($s['file_foto']))  unlink($s['file_foto']);
                if ($s['file_video'] && file_exists($s['file_video'])) unlink($s['file_video']);
            }
            $db->prepare("DELETE FROM tugas WHERE id=?")->execute([$id]);
            $msg = 'Tugas berhasil dihapus.';
        }
    }
}

/* ------------------------------------------------------------------ */
/*  DATA                                                                */
/* ------------------------------------------------------------------ */
// Ambil daftar tugas + nama pembuat + jumlah submission
$tugasList = $db->query("
    SELECT t.*,
           u.name  AS creator_name,
           (SELECT COUNT(*) FROM tugas_submissions ts WHERE ts.tugas_id = t.id) AS total_submissions,
           (SELECT COUNT(*) FROM tugas_submissions ts WHERE ts.tugas_id = t.id AND ts.nilai IS NOT NULL) AS total_graded
    FROM tugas t
    JOIN users u ON u.id = t.created_by
    ORDER BY t.created_at DESC
")->fetchAll();

// Statistik ringkas
$stats = [
    'total'     => count($tugasList),
    'published' => count(array_filter($tugasList, fn($x) => $x['status'] === 'published')),
    'draft'     => count(array_filter($tugasList, fn($x) => $x['status'] === 'draft')),
    'closed'    => count(array_filter($tugasList, fn($x) => $x['status'] === 'closed')),
    'total_sub' => array_sum(array_column($tugasList, 'total_submissions')),
];

// Edit mode
$editTugas = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM tugas WHERE id=?");
    $stmt->execute([(int)$_GET['edit']]);
    $editTugas = $stmt->fetch();
}

/* ── Helper functions ── */
function tipeLabel(string $t): string {
    return match($t) {
        'foto'       => 'Foto',
        'video'      => 'Video',
        'foto_video' => 'Foto & Video',
        default      => $t,
    };
}
function tipeIcon(string $t): string {
    return match($t) {
        'foto'       => '📷',
        'video'      => '🎬',
        'foto_video' => '📷🎬',
        default      => '📄',
    };
}
function statusLabel(string $s): string {
    return match($s) {
        'draft'     => 'Draft',
        'published' => 'Aktif',
        'closed'    => 'Ditutup',
        default     => $s,
    };
}
function statusClass(string $s): string {
    return match($s) {
        'draft'     => 'badge-draft',
        'published' => 'badge-pub',
        'closed'    => 'badge-closed',
        default     => '',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Kelola Tugas</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* ════════════════════════════════════════════
       TUGAS ADMIN v2 — Sakura App
    ════════════════════════════════════════════ */
    :root {
      --anim: .22s ease;
      --card-r: 16px;
    }

    /* ── Layout 2 kolom ── */
    .admin-layout {
      display: grid;
      grid-template-columns: 360px 1fr;
      gap: 24px;
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 16px 80px;
      align-items: start;
    }
    @media(max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; }
    }

    /* ── Stat Bar ── */
    .stat-bar {
      max-width: 1200px;
      margin: 0 auto 24px;
      padding: 0 16px;
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 12px;
    }
    @media(max-width: 700px) { .stat-bar { grid-template-columns: repeat(3, 1fr); } }
    @media(max-width: 400px) { .stat-bar { grid-template-columns: repeat(2, 1fr); } }

    .stat-card {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 14px;
      padding: 16px 14px 14px;
      text-align: center;
      box-shadow: var(--card-shadow);
      transition: transform var(--anim);
      cursor: default;
    }
    .stat-card:hover { transform: translateY(-2px); }
    .stat-num {
      font-size: 1.9rem; font-weight: 800; line-height: 1;
      color: var(--torii); font-variant-numeric: tabular-nums;
    }
    .stat-lbl {
      font-size: .71rem; color: var(--text-muted);
      font-weight: 600; letter-spacing: .04em; margin-top: 5px;
    }
    .stat-card.s-pub  .stat-num { color: var(--bamboo); }
    .stat-card.s-tot  .stat-num { color: var(--gold); }
    .stat-card.s-sub  .stat-num { color: #5558af; }

    /* ── Alert ── */
    .alert-wrap {
      max-width: 1200px; margin: 0 auto 16px; padding: 0 16px;
    }
    .alert-inner {
      padding: 12px 18px; border-radius: 12px;
      font-size: .9rem; font-weight: 600;
      display: flex; align-items: center; gap: 8px;
      border: 1px solid transparent; animation: slideDown .3s ease;
    }
    @keyframes slideDown {
      from { opacity: 0; transform: translateY(-8px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .alert-ok  .alert-inner { background: rgba(74,124,89,.12); color: var(--bamboo); border-color: rgba(74,124,89,.25); }
    .alert-err .alert-inner { background: rgba(183,75,75,.1);  color: var(--torii);  border-color: rgba(183,75,75,.2); }
    .alert-close {
      margin-left: auto; background: transparent; border: none;
      cursor: pointer; font-size: 1.1rem; color: inherit; opacity: .65;
      padding: 0 2px; line-height: 1; flex-shrink: 0;
    }

    /* ── Panel Kiri (Form) ── */
    .form-panel {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: var(--card-r);
      box-shadow: var(--card-shadow);
      overflow: hidden;
      position: sticky;
      top: 80px;
    }
    .form-panel-header {
      background: linear-gradient(135deg, var(--torii) 0%, #c96060 100%);
      padding: 18px 22px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .form-panel-header h2 {
      margin: 0; color: #fff; font-size: 1rem; font-weight: 700;
      display: flex; align-items: center; gap: 9px;
    }
    .panel-icon {
      width: 30px; height: 30px; background: rgba(255,255,255,.2);
      border-radius: 8px; display: flex; align-items: center;
      justify-content: center; font-size: .95rem; flex-shrink: 0;
    }
    .form-panel-body { padding: 20px 22px 4px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 15px; }
    .form-group label {
      font-size: .74rem; color: var(--text-muted);
      font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    }
    .field-hint { font-size: .72rem; color: var(--text-muted); margin-top: 3px; opacity: .8; }
    .form-group input,
    .form-group select,
    .form-group textarea {
      background: var(--input-bg, rgba(0,0,0,.05));
      border: 1.5px solid var(--card-border);
      border-radius: 10px; padding: 10px 13px;
      color: var(--text-main); font-size: .9rem;
      font-family: inherit; resize: vertical;
      transition: border-color var(--anim), box-shadow var(--anim);
      width: 100%; box-sizing: border-box;
    }
    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none; border-color: var(--torii);
      box-shadow: 0 0 0 3px rgba(183,75,75,.1);
    }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

    .form-actions {
      display: flex; gap: 8px;
      padding: 15px 22px 18px;
    }
    .btn-save {
      flex: 1; background: var(--torii); color: #fff;
      border: none; border-radius: 10px; padding: 11px 16px;
      font-weight: 700; cursor: pointer; font-size: .9rem;
      transition: opacity var(--anim), transform .15s;
      display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .btn-save:hover { opacity: .87; transform: translateY(-1px); }
    .btn-cancel {
      background: transparent; color: var(--text-muted);
      border: 1.5px solid var(--card-border); border-radius: 10px;
      padding: 11px 16px; cursor: pointer; font-size: .9rem;
      text-decoration: none; display: inline-flex; align-items: center; gap: 5px;
      transition: background var(--anim);
    }
    .btn-cancel:hover { background: rgba(0,0,0,.05); }

    /* ── Panel Kanan (Tabel) ── */
    .table-panel {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: var(--card-r);
      box-shadow: var(--card-shadow);
      overflow: hidden;
    }
    .table-panel-header {
      padding: 16px 22px; border-bottom: 1px solid var(--card-border);
      display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
    }
    .table-panel-header h2 {
      margin: 0; font-size: .98rem; font-weight: 700; color: var(--text-main);
      display: flex; align-items: center; gap: 7px;
    }
    /* Search */
    .search-box { position: relative; }
    .search-box input {
      background: var(--input-bg, rgba(0,0,0,.05));
      border: 1.5px solid var(--card-border); border-radius: 10px;
      padding: 8px 12px 8px 33px; color: var(--text-main);
      font-size: .85rem; font-family: inherit; width: 190px;
      transition: border-color var(--anim), width var(--anim);
    }
    .search-box input:focus { outline: none; border-color: var(--torii); width: 230px; }
    .search-ico { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: .82rem; pointer-events: none; }

    /* Filter Pills */
    .filter-bar {
      padding: 11px 22px; border-bottom: 1px solid var(--card-border);
      display: flex; gap: 7px; flex-wrap: wrap; align-items: center;
    }
    .filter-lbl { font-size: .73rem; color: var(--text-muted); font-weight: 700; letter-spacing: .03em; }
    .filter-pill {
      border: 1.5px solid var(--card-border); background: transparent;
      border-radius: 20px; padding: 5px 13px; font-size: .78rem; font-weight: 600;
      cursor: pointer; color: var(--text-muted);
      transition: all var(--anim); font-family: inherit;
    }
    .filter-pill:hover { border-color: var(--torii); color: var(--torii); }
    .filter-pill.active { border-color: var(--torii); color: var(--torii); background: rgba(183,75,75,.08); font-weight: 700; }

    /* Tabel */
    .table-scroll { overflow-x: auto; }
    .tugas-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .tugas-table th {
      text-align: left; padding: 11px 16px;
      background: rgba(0,0,0,.03); color: var(--text-muted);
      font-weight: 700; font-size: .72rem; letter-spacing: .06em;
      text-transform: uppercase; border-bottom: 1px solid var(--card-border); white-space: nowrap;
    }
    .tugas-table td {
      padding: 13px 16px; border-bottom: 1px solid rgba(0,0,0,.04); vertical-align: middle;
    }
    .tugas-table tr:last-child td { border-bottom: none; }
    .tugas-table tr:hover td { background: rgba(0,0,0,.018); }
    .tugas-table tr.hidden-row { display: none; }

    /* Kolom Judul */
    .task-title { font-weight: 700; color: var(--text-main); margin-bottom: 3px; }
    .task-meta  { font-size: .75rem; color: var(--text-muted); display: flex; gap: 7px; flex-wrap: wrap; align-items: center; }

    /* Badge */
    .badge {
      display: inline-flex; align-items: center; gap: 5px;
      border-radius: 20px; padding: 3px 11px;
      font-size: .73rem; font-weight: 700; border: 1px solid transparent; white-space: nowrap;
    }
    .badge::before { content: ''; display: block; width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0; }
    .badge-draft  { background: rgba(180,180,180,.15); color: var(--text-muted); border-color: rgba(180,180,180,.28); }
    .badge-draft::before  { background: #aaa; }
    .badge-pub    { background: rgba(74,124,89,.14);   color: var(--bamboo);     border-color: rgba(74,124,89,.28); }
    .badge-pub::before    { background: var(--bamboo); }
    .badge-closed { background: rgba(183,75,75,.1);    color: var(--torii);      border-color: rgba(183,75,75,.2); }
    .badge-closed::before { background: var(--torii); }

    /* Chip tipe */
    .chip {
      font-size: .73rem; background: rgba(0,0,0,.05);
      border-radius: 20px; padding: 3px 10px;
      font-weight: 600; border: 1px solid var(--card-border); white-space: nowrap;
    }

    /* Submission bar */
    .sub-info { display: flex; flex-direction: column; gap: 3px; }
    .sub-count {
      font-weight: 700; color: #5558af; font-size: .9rem;
      display: flex; align-items: center; gap: 4px;
    }
    .sub-count small { font-size: .72rem; color: var(--text-muted); font-weight: 500; }
    .graded-bar {
      height: 4px; background: rgba(0,0,0,.07); border-radius: 4px;
      overflow: hidden; width: 72px;
    }
    .graded-fill {
      height: 100%; background: var(--bamboo); border-radius: 4px;
      transition: width .4s ease;
    }
    .graded-txt { font-size: .7rem; color: var(--text-muted); }

    /* Tombol aksi */
    .ta-actions { display: flex; gap: 6px; align-items: center; flex-wrap: nowrap; }
    .btn-sm {
      border: none; border-radius: 8px; padding: 7px 11px;
      font-size: .78rem; font-weight: 600; cursor: pointer;
      text-decoration: none; display: inline-flex; align-items: center; gap: 4px;
      transition: opacity var(--anim), transform .15s; white-space: nowrap; font-family: inherit;
    }
    .btn-edit { background: rgba(74,124,89,.14);  color: var(--bamboo); }
    .btn-del  { background: rgba(183,75,75,.1);   color: var(--torii); }
    .btn-view { background: rgba(85,88,175,.12);  color: #5558af; }
    .btn-sm:hover { opacity: .75; transform: translateY(-1px); }

    /* Dropdown ⋯ */
    .action-menu { position: relative; display: inline-block; }
    .action-menu-btn {
      border: 1.5px solid var(--card-border); background: transparent;
      border-radius: 8px; padding: 6px 10px; cursor: pointer;
      font-size: .88rem; color: var(--text-muted);
      transition: all var(--anim); line-height: 1; font-family: inherit;
    }
    .action-menu-btn:hover { background: rgba(0,0,0,.05); color: var(--text-main); }
    .action-dropdown {
      position: absolute; right: 0; top: calc(100% + 5px);
      background: var(--card-bg); border: 1px solid var(--card-border);
      border-radius: 12px; box-shadow: 0 8px 28px rgba(0,0,0,.13);
      padding: 6px; min-width: 155px; z-index: 200; display: none;
    }
    .action-menu.open .action-dropdown { display: block; }
    .dropdown-item {
      display: flex; align-items: center; gap: 8px;
      padding: 8px 12px; border-radius: 8px;
      font-size: .85rem; font-weight: 600; cursor: pointer;
      text-decoration: none; color: var(--text-main);
      width: 100%; border: none; background: transparent;
      font-family: inherit; transition: background var(--anim);
    }
    .dropdown-item:hover { background: rgba(0,0,0,.05); }
    .dropdown-item.danger { color: var(--torii); }
    .dropdown-item.danger:hover { background: rgba(183,75,75,.07); }
    .dropdown-divider { height: 1px; background: var(--card-border); margin: 4px 0; }

    /* Empty State */
    .empty-state { text-align: center; padding: 52px 24px; color: var(--text-muted); }
    .empty-state .emo { font-size: 3rem; margin-bottom: 12px; }
    .empty-state p { margin: 0; font-size: .9rem; }

    /* ── Modal ── */
    .modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,.46);
      z-index: 1000; display: flex; align-items: center; justify-content: center;
      opacity: 0; pointer-events: none; transition: opacity .2s;
    }
    .modal-overlay.show { opacity: 1; pointer-events: all; }
    .modal-box {
      background: var(--card-bg); border-radius: 20px;
      padding: 30px 26px 24px; max-width: 370px; width: 92%;
      box-shadow: 0 20px 60px rgba(0,0,0,.22);
      transform: scale(.92); transition: transform .22s;
      text-align: center;
    }
    .modal-overlay.show .modal-box { transform: scale(1); }
    .modal-icon  { font-size: 2.6rem; margin-bottom: 10px; }
    .modal-title { font-size: 1.08rem; font-weight: 800; margin-bottom: 7px; color: var(--text-main); }
    .modal-desc  { font-size: .88rem; color: var(--text-muted); margin-bottom: 22px; }
    .modal-actions { display: flex; gap: 10px; }
    .modal-actions .btn-cancel { flex: 1; justify-content: center; }
    .btn-del-confirm {
      flex: 1; background: var(--torii); color: #fff;
      border: none; border-radius: 10px; padding: 11px 16px;
      font-weight: 700; cursor: pointer; font-size: .9rem; font-family: inherit;
    }

    /* Quick Status */
    .qs-options { display: flex; flex-direction: column; gap: 7px; margin-bottom: 18px; }
    .qs-opt {
      border: 1.5px solid var(--card-border); background: transparent;
      border-radius: 10px; padding: 11px 15px; cursor: pointer;
      font-size: .9rem; font-weight: 600; display: flex; align-items: center; gap: 10px;
      text-align: left; width: 100%; font-family: inherit; color: var(--text-main);
      transition: all var(--anim);
    }
    .qs-opt:hover { border-color: var(--torii); background: rgba(183,75,75,.04); }
    .qs-opt.selected { border-color: var(--torii); background: rgba(183,75,75,.08); }
    .qs-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }

    /* ════════════════════════════════════════════
       RESPONSIVE / MOBILE FIXES
    ════════════════════════════════════════════ */

    /* Saat layout berubah jadi 1 kolom, panel form JANGAN sticky —
       supaya tidak menutupi/menimpa daftar tugas saat di-scroll di HP */
    @media(max-width: 900px) {
      .form-panel { position: static; top: auto; }
    }

    @media(max-width: 720px) {
      /* Header panel tabel: judul & kotak cari ditumpuk vertikal, full width */
      .table-panel-header {
        flex-direction: column;
        align-items: stretch;
        gap: 10px;
      }
      .table-panel-header h2 { font-size: .95rem; }
      .search-box { width: 100%; }
      .search-box input,
      .search-box input:focus {
        width: 100%;
        box-sizing: border-box;
      }

      /* Filter pills: digeser horizontal, tidak makan banyak baris */
      .filter-bar {
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
      }
      .filter-pill { flex-shrink: 0; }

      /* ── Tabel berubah jadi tampilan "kartu" di HP ── */
      .table-scroll { overflow-x: hidden; padding: 12px; }
      .tugas-table thead { display: none; }
      .tugas-table,
      .tugas-table tbody,
      .tugas-table tr,
      .tugas-table td { display: block; width: 100%; box-sizing: border-box; }

      .tugas-table tr {
        border: 1px solid var(--card-border);
        border-radius: 14px;
        padding: 4px 14px;
        margin-bottom: 12px;
        box-shadow: var(--card-shadow);
      }
      .tugas-table tr:last-child { margin-bottom: 0; }
      .tugas-table tr:hover td { background: transparent; }

      .tugas-table td {
        padding: 9px 0;
        border-bottom: 1px dashed rgba(0,0,0,.08);
      }
      .tugas-table td:last-child { border-bottom: none; }

      .tugas-table td::before {
        content: attr(data-label);
        display: block;
        font-size: .68rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        color: var(--text-muted);
        margin-bottom: 6px;
      }

      /* Baris "tidak ditemukan" tidak perlu bingkai kartu */
      .tugas-table tr#noResultRow {
        border: none; box-shadow: none; margin-bottom: 0; padding: 0;
      }
      .tugas-table tr#noResultRow td { border-bottom: none; }
      .tugas-table tr#noResultRow td::before { content: none; }

      /* Tombol aksi: boleh wrap, jangan terpaksa sebaris & terpotong */
      .ta-actions {
        flex-wrap: wrap !important;
        justify-content: flex-start !important;
      }
    }

    @media(max-width: 480px) {
      .stat-bar { gap: 8px; padding: 0 12px; }
      .stat-card { padding: 12px 8px 10px; }
      .stat-num { font-size: 1.5rem; }
      .stat-lbl { font-size: .63rem; }

      .admin-layout { padding: 0 12px 60px; }

      .form-panel-header { padding: 16px 16px; }
      .form-panel-body { padding: 16px 16px 2px; }
      .form-actions { padding: 13px 16px 16px; flex-wrap: wrap; }
      .form-row { grid-template-columns: 1fr; }

      .table-panel-header,
      .filter-bar { padding-left: 16px; padding-right: 16px; }

      .modal-box { padding: 24px 16px 18px; width: 90%; }

      .btn-sm, .action-menu-btn { padding: 8px 12px; }
    }
  </style>
</head>
<body class="dashboard-page">

  <div class="page-loader" id="pageLoader"><span class="loader-kanji">桜</span></div>
  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <!-- ── Modal Hapus ── -->
  <div class="modal-overlay" id="deleteModal">
    <div class="modal-box">
      <div class="modal-icon">🗑️</div>
      <div class="modal-title">Hapus Tugas?</div>
      <div class="modal-desc" id="deleteDesc">Semua file pengumpulan juga akan dihapus permanen. Aksi ini tidak bisa dibatalkan.</div>
      <div class="modal-actions">
        <button class="btn-cancel" onclick="closeModal('deleteModal')">Batal</button>
        <form method="POST" id="deleteForm" style="flex:1;display:flex;">
          <input type="hidden" name="action" value="delete_tugas">
          <input type="hidden" name="id" id="deleteId">
          <button type="submit" class="btn-del-confirm" style="width:100%;">Ya, Hapus</button>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Modal Quick Status ── -->
  <div class="modal-overlay" id="statusModal">
    <div class="modal-box">
      <div class="modal-icon">🔄</div>
      <div class="modal-title">Ubah Status Tugas</div>
      <div class="modal-desc" id="statusModalJudul" style="font-weight:700;color:var(--text-main);margin-bottom:14px;"></div>
      <form method="POST" id="statusForm">
        <input type="hidden" name="action" value="quick_status">
        <input type="hidden" name="id"     id="statusTugasId">
        <input type="hidden" name="status" id="selectedStatus">
        <div class="qs-options">
          <button type="button" class="qs-opt" data-val="draft" onclick="pickStatus(this)">
            <span class="qs-dot" style="background:#aaa;"></span>
            <span>Draft</span>
            <small style="margin-left:auto;font-weight:400;color:var(--text-muted);">Belum dipublish</small>
          </button>
          <button type="button" class="qs-opt" data-val="published" onclick="pickStatus(this)">
            <span class="qs-dot" style="background:var(--bamboo);"></span>
            <span>Aktif</span>
            <small style="margin-left:auto;font-weight:400;color:var(--text-muted);">Siswa bisa kumpul</small>
          </button>
          <button type="button" class="qs-opt" data-val="closed" onclick="pickStatus(this)">
            <span class="qs-dot" style="background:var(--torii);"></span>
            <span>Ditutup</span>
            <small style="margin-left:auto;font-weight:400;color:var(--text-muted);">Tidak menerima file</small>
          </button>
        </div>
        <div class="modal-actions">
          <button type="button" class="btn-cancel" onclick="closeModal('statusModal')">Batal</button>
          <button type="submit" class="btn-save" id="statusSubmitBtn"
                  style="flex:1;opacity:.45;" disabled>Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <!-- ── Topbar ── -->
  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <button class="theme-toggle" onclick="toggleTheme()" title="Ganti Tema">☀️</button>
    <a href="beranda.php" class="topbar-back"
       style="border:2px solid var(--gold);padding:7px 13px;border-radius:8px;text-decoration:none;
              display:inline-flex;align-items:center;gap:5px;font-size:.87rem;font-weight:600;color:var(--gold);">
      ← Beranda
    </a>
  </header>

  <main class="dashboard-main">

    <!-- Welcome -->
    <section class="welcome-section fade-up">
      <span class="welcome-kanji">課題</span>
      <h1 class="welcome-title">Kelola Tugas</h1>
      <p class="welcome-sub">Buat, edit, dan pantau pengumpulan tugas siswa</p>
      <div class="section-divider"></div>
    </section>

    <!-- Stat Bar -->
    <div class="stat-bar fade-up delay-1">
      <div class="stat-card s-tot">
        <div class="stat-num"><?= $stats['total'] ?></div>
        <div class="stat-lbl">Total Tugas</div>
      </div>
      <div class="stat-card s-pub">
        <div class="stat-num"><?= $stats['published'] ?></div>
        <div class="stat-lbl">Aktif</div>
      </div>
      <div class="stat-card">
        <div class="stat-num"><?= $stats['draft'] ?></div>
        <div class="stat-lbl">Draft</div>
      </div>
      <div class="stat-card">
        <div class="stat-num"><?= $stats['closed'] ?></div>
        <div class="stat-lbl">Ditutup</div>
      </div>
      <div class="stat-card s-sub">
        <div class="stat-num"><?= $stats['total_sub'] ?></div>
        <div class="stat-lbl">Total Dikumpulkan</div>
      </div>
    </div>

    <!-- Alert -->
    <?php if ($msg): ?>
    <div class="alert-wrap alert-ok fade-up">
      <div class="alert-inner">✅ <?= htmlspecialchars($msg) ?>
        <button class="alert-close" onclick="this.closest('.alert-wrap').remove()">✕</button>
      </div>
    </div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="alert-wrap alert-err fade-up">
      <div class="alert-inner">⚠️ <?= htmlspecialchars($err) ?>
        <button class="alert-close" onclick="this.closest('.alert-wrap').remove()">✕</button>
      </div>
    </div>
    <?php endif; ?>

    <!-- Main Layout -->
    <div class="admin-layout fade-up delay-1">

      <!-- ═══ FORM PANEL (Kiri) ═══ -->
      <div class="form-panel">
        <div class="form-panel-header">
          <h2>
            <div class="panel-icon"><?= $editTugas ? '✏️' : '＋' ?></div>
            <?= $editTugas ? 'Edit Tugas' : 'Buat Tugas Baru' ?>
          </h2>
          <?php if ($editTugas): ?>
            <a href="tugas_admin.php"
               style="color:rgba(255,255,255,.75);font-size:.82rem;text-decoration:none;font-weight:600;">
              ✕ Batal
            </a>
          <?php endif; ?>
        </div>

        <form method="POST">
          <input type="hidden" name="action" value="save_tugas">
          <?php if ($editTugas): ?>
            <input type="hidden" name="id" value="<?= $editTugas['id'] ?>">
          <?php endif; ?>

          <div class="form-panel-body">

            <div class="form-group">
              <label>Judul Tugas *</label>
              <input type="text" name="judul" required
                     placeholder="Contoh: Latihan Menulis Hiragana"
                     value="<?= htmlspecialchars($editTugas['judul'] ?? '') ?>">
            </div>

            <div class="form-group">
              <label>Deskripsi / Instruksi</label>
              <textarea name="deskripsi" rows="4"
                        placeholder="Tulis instruksi tugas, materi, atau penjelasan untuk siswa..."><?= htmlspecialchars($editTugas['deskripsi'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
              <label>URL Video (opsional)</label>
              <input type="url" name="video_url"
                     placeholder="https://www.youtube.com/embed/..."
                     value="<?= htmlspecialchars($editTugas['video_url'] ?? '') ?>">
              <div class="field-hint">💡 Gunakan link embed YouTube. Contoh: /embed/dQw4w9WgXcQ</div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label>Jenis File</label>
                <select name="tipe_upload">
                  <?php
                  $tipeOptions = ['foto' => '📷 Foto', 'video' => '🎬 Video', 'foto_video' => '📷🎬 Keduanya'];
                  foreach ($tipeOptions as $v => $l):
                  ?>
                    <option value="<?= $v ?>"
                      <?= ($editTugas['tipe_upload'] ?? 'foto') === $v ? 'selected' : '' ?>>
                      <?= $l ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label>Status</label>
                <select name="status">
                  <?php
                  $statusOptions = ['draft' => 'Draft', 'published' => '✅ Aktif', 'closed' => '🔒 Ditutup'];
                  foreach ($statusOptions as $v => $l):
                  ?>
                    <option value="<?= $v ?>"
                      <?= ($editTugas['status'] ?? 'draft') === $v ? 'selected' : '' ?>>
                      <?= $l ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

          </div><!-- /form-panel-body -->

          <div class="form-actions">
            <button type="submit" class="btn-save">
              <?= $editTugas ? '💾 Simpan Perubahan' : '＋ Buat Tugas' ?>
            </button>
            <?php if ($editTugas): ?>
              <a href="tugas_admin.php" class="btn-cancel">Batal</a>
            <?php endif; ?>
          </div>
        </form>
      </div><!-- /form-panel -->

      <!-- ═══ TABLE PANEL (Kanan) ═══ -->
      <div class="table-panel">

        <div class="table-panel-header">
          <h2>📋 Daftar Tugas
            <span style="font-size:.78rem;font-weight:500;color:var(--text-muted);">(<?= count($tugasList) ?>)</span>
          </h2>
          <div class="search-box">
            <span class="search-ico">🔍</span>
            <input type="text" id="searchInput" placeholder="Cari tugas..." oninput="filterTable()">
          </div>
        </div>

        <!-- Filter Pills -->
        <div class="filter-bar">
          <span class="filter-lbl">Filter:</span>
          <button class="filter-pill active" onclick="setFilter('all',this)">Semua (<?= $stats['total'] ?>)</button>
          <button class="filter-pill" onclick="setFilter('published',this)">✅ Aktif (<?= $stats['published'] ?>)</button>
          <button class="filter-pill" onclick="setFilter('draft',this)">📝 Draft (<?= $stats['draft'] ?>)</button>
          <button class="filter-pill" onclick="setFilter('closed',this)">🔒 Ditutup (<?= $stats['closed'] ?>)</button>
        </div>

        <?php if (empty($tugasList)): ?>
          <div class="empty-state">
            <div class="emo">📭</div>
            <p>Belum ada tugas. Buat tugas pertama di panel kiri!</p>
          </div>
        <?php else: ?>
          <div class="table-scroll">
          <table class="tugas-table" id="tugasTable">
            <thead>
              <tr>
                <th>Tugas</th>
                <th>Tipe & Status</th>
                <th>Pengumpulan</th>
                <th>Dibuat</th>
                <th style="text-align:center;">Aksi</th>
              </tr>
            </thead>
            <tbody id="tugasBody">
              <?php foreach ($tugasList as $t):
                $pct = $t['total_submissions'] > 0
                     ? round(($t['total_graded'] / $t['total_submissions']) * 100)
                     : 0;
              ?>
              <tr data-status="<?= $t['status'] ?>"
                  data-title="<?= strtolower(htmlspecialchars($t['judul'])) ?>">

                <!-- Kolom Judul -->
                <td data-label="Tugas">
                  <div class="task-title"><?= htmlspecialchars($t['judul']) ?></div>
                  <div class="task-meta">
                    <span>oleh <?= htmlspecialchars($t['creator_name']) ?></span>
                    <span>·</span>
                    <span><?= date('d M Y', strtotime($t['created_at'])) ?></span>
                  </div>
                </td>

                <!-- Tipe & Status -->
                <td data-label="Tipe & Status">
                  <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-start;">
                    <span class="chip"><?= tipeIcon($t['tipe_upload']) ?> <?= tipeLabel($t['tipe_upload']) ?></span>
                    <span class="badge <?= statusClass($t['status']) ?>"><?= statusLabel($t['status']) ?></span>
                  </div>
                </td>

                <!-- Pengumpulan -->
                <td data-label="Pengumpulan">
                  <div class="sub-info">
                    <div class="sub-count">
                      <?= (int)$t['total_submissions'] ?>
                      <small>/ dikumpulkan</small>
                    </div>
                    <?php if ($t['total_submissions'] > 0): ?>
                    <div class="graded-bar" title="<?= $t['total_graded'] ?>/<?= $t['total_submissions'] ?> dinilai">
                      <div class="graded-fill" style="width:<?= $pct ?>%;"></div>
                    </div>
                    <div class="graded-txt"><?= $t['total_graded'] ?> dinilai</div>
                    <?php endif; ?>
                  </div>
                </td>

                <!-- Dibuat -->
                <td data-label="Dibuat" style="white-space:nowrap;font-size:.82rem;color:var(--text-muted);">
                  <?= date('d M Y', strtotime($t['created_at'])) ?>
                </td>

                <!-- Aksi -->
                <td data-label="Aksi">
                  <div class="ta-actions" style="justify-content:center;">
                    <a href="tugas_hasil.php?tugas_id=<?= $t['id'] ?>" class="btn-sm btn-view" title="Lihat Hasil">
                      📊 Hasil
                    </a>
                    <a href="tugas_admin.php?edit=<?= $t['id'] ?>" class="btn-sm btn-edit" title="Edit Tugas">
                      ✏️
                    </a>
                    <!-- Dropdown ⋯ -->
                    <div class="action-menu" id="menu_<?= $t['id'] ?>">
                      <button class="action-menu-btn"
                              onclick="toggleMenu(<?= $t['id'] ?>)" title="Aksi lain">⋯</button>
                      <div class="action-dropdown">
                        <button class="dropdown-item"
                                onclick="openStatusModal(<?= $t['id'] ?>, '<?= addslashes(htmlspecialchars($t['judul'])) ?>', '<?= $t['status'] ?>')">
                          🔄 Ubah Status
                        </button>
                        <form method="POST" style="margin:0;">
                          <input type="hidden" name="action" value="duplicate_tugas">
                          <input type="hidden" name="id"     value="<?= $t['id'] ?>">
                          <button type="submit" class="dropdown-item">📋 Duplikat</button>
                        </form>
                        <div class="dropdown-divider"></div>
                        <button class="dropdown-item danger"
                                onclick="openDeleteModal(<?= $t['id'] ?>, '<?= addslashes(htmlspecialchars($t['judul'])) ?>')">
                          🗑️ Hapus
                        </button>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
              <!-- Row "tidak ditemukan" -->
              <tr id="noResultRow" style="display:none;">
                <td colspan="5"
                    style="text-align:center;padding:36px;color:var(--text-muted);font-size:.9rem;">
                  🔍 Tidak ada tugas yang cocok dengan pencarian
                </td>
              </tr>
            </tbody>
          </table>
          </div>
        <?php endif; ?>

      </div><!-- /table-panel -->
    </div><!-- /admin-layout -->
  </main>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/petals.js"></script>
  <script>
    /* ── Alert auto-dismiss (4 detik) ── */
    setTimeout(() => {
      document.querySelectorAll('.alert-wrap').forEach(el => {
        el.style.transition = 'opacity .4s';
        el.style.opacity    = '0';
        setTimeout(() => el.remove(), 400);
      });
    }, 4000);

    /* ── Modal ── */
    function openModal(id)  {
      document.getElementById(id).classList.add('show');
      document.body.style.overflow = 'hidden';
    }
    function closeModal(id) {
      document.getElementById(id).classList.remove('show');
      document.body.style.overflow = '';
    }
    document.querySelectorAll('.modal-overlay').forEach(m => {
      m.addEventListener('click', e => { if (e.target === m) closeModal(m.id); });
    });

    /* ── Delete Modal ── */
    function openDeleteModal(id, judul) {
      document.getElementById('deleteId').value = id;
      document.getElementById('deleteDesc').textContent =
        'Hapus tugas "' + judul + '"? Semua file pengumpulan juga ikut terhapus permanen.';
      openModal('deleteModal');
      closeAllMenus();
    }

    /* ── Quick Status Modal ── */
    function openStatusModal(id, judul, currentStatus) {
      document.getElementById('statusTugasId').value = id;
      document.getElementById('statusModalJudul').textContent  = judul;
      document.getElementById('selectedStatus').value          = '';
      const btn = document.getElementById('statusSubmitBtn');
      btn.disabled     = true;
      btn.style.opacity = '.45';
      // Reset & pre-select
      document.querySelectorAll('.qs-opt').forEach(o => {
        o.classList.remove('selected');
        if (o.dataset.val === currentStatus) o.classList.add('selected');
      });
      openModal('statusModal');
      closeAllMenus();
    }
    function pickStatus(el) {
      document.querySelectorAll('.qs-opt').forEach(o => o.classList.remove('selected'));
      el.classList.add('selected');
      document.getElementById('selectedStatus').value = el.dataset.val;
      const btn = document.getElementById('statusSubmitBtn');
      btn.disabled      = false;
      btn.style.opacity = '1';
    }

    /* ── Dropdown ⋯ ── */
    function toggleMenu(id) {
      const menu   = document.getElementById('menu_' + id);
      const isOpen = menu.classList.contains('open');
      closeAllMenus();
      if (!isOpen) menu.classList.add('open');
    }
    function closeAllMenus() {
      document.querySelectorAll('.action-menu.open').forEach(m => m.classList.remove('open'));
    }
    document.addEventListener('click', e => {
      if (!e.target.closest('.action-menu')) closeAllMenus();
    });

    /* ── Search & Filter ── */
    let activeFilter = 'all';

    function filterTable() {
      const q = document.getElementById('searchInput').value.toLowerCase().trim();
      let visible = 0;
      document.querySelectorAll('#tugasBody tr[data-status]').forEach(row => {
        const matchTitle  = row.dataset.title.includes(q);
        const matchFilter = activeFilter === 'all' || row.dataset.status === activeFilter;
        const show        = matchTitle && matchFilter;
        row.classList.toggle('hidden-row', !show);
        if (show) visible++;
      });
      const noRes = document.getElementById('noResultRow');
      if (noRes) noRes.style.display = visible === 0 ? '' : 'none';
    }

    function setFilter(filter, btn) {
      activeFilter = filter;
      document.querySelectorAll('.filter-pill').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      filterTable();
    }

    /* logout helper */
    function handleLogout() {
      const fd = new FormData();
      fd.append('action', 'logout');
      fetch('auth.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => { if (d.redirect) location.href = d.redirect; });
    }
  </script>
</body>
</html>
<?php
require_once 'config.php';
requireLogin();

// ── AJAX: jawab pertanyaan onboarding paham hiragana/katakana ──
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'kana_set_paham') {
    $db    = getDB();
    $cuser = getCurrentUser();
    $jenis = $_POST['jenis'] ?? ''; // 'hiragana' | 'katakana'
    // FIX BUG: dulu kalau "belum paham" tidak ada apapun yg disimpan ke server,
    // jadi modal ini akan muncul lagi terus-menerus setiap halaman dibuka karena
    // status tetap 'belum_dijawab' selamanya. Sekarang kita selalu terima flag
    // `paham` dari client supaya kita bisa mencatat "pertanyaan ini sudah pernah
    // dijawab" terlepas dari hasil jawabannya.
    $paham = ($_POST['paham'] ?? '1') === '1';
    if (!$cuser || !in_array($jenis, ['hiragana', 'katakana'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Permintaan tidak valid']);
        exit;
    }
    if ($paham) {
        // Hanya kalau jawabannya "sudah paham" status enum diubah.
        // Kalau "belum paham", status TETAP 'belum_dijawab' (supaya tombol
        // ujian pemahaman tetap tampil di kana.php) — ini sudah benar dari awal.
        $col   = $jenis . '_status';
        $colTs = $jenis . '_updated_at';
        $stmt = $db->prepare("UPDATE users SET `$col` = 'sudah_paham', `$colTs` = NOW() WHERE id = ?");
        $stmt->execute([$cuser['id']]);
    }
    // INI KUNCI PERBAIKANNYA: tandai modal onboarding sudah pernah ditampilkan &
    // dijawab (apapun jawabannya), sekali saja, sehingga TIDAK akan muncul lagi
    // di kunjungan/login berikutnya dan tidak lagi mengunci akses ke konten lain.
    $stmtSeen = $db->prepare("UPDATE users SET kana_onboarding_asked_at = NOW() WHERE id = ? AND kana_onboarding_asked_at IS NULL");
    $stmtSeen->execute([$cuser['id']]);
    echo json_encode(['ok' => true, 'status' => $paham ? 'sudah_paham' : 'belum_dijawab']);
    exit;
}

// ── AJAX: simpan profil club (admin only) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'save_club_profile') {
    $cuser = getCurrentUser();
    if (($cuser['role'] ?? '') !== 'admin') {
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $type  = in_array($_POST['profile_type'] ?? '', ['ketua','guru']) ? $_POST['profile_type'] : '';
    $name  = trim($_POST['profile_name'] ?? '');
    $photo = trim($_POST['profile_photo'] ?? '');
    if ($type && $name) {
        $clubFile = __DIR__ . '/data/club_profiles.json';
        @mkdir(dirname($clubFile), 0755, true);
        $profiles = [];
        if (file_exists($clubFile)) {
            $profiles = json_decode(file_get_contents($clubFile), true) ?: [];
        }
        $profiles[$type] = ['name' => $name, 'photo' => $photo];
        file_put_contents($clubFile, json_encode($profiles, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Nama tidak boleh kosong']);
    }
    exit;
}

// ── AJAX: tandai pengumuman sudah dibaca ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action'])
    && $_POST['action'] === 'mark_announcement_read') {
    $db  = getDB();
    $uid = getCurrentUser()['id'] ?? 0;
    $aid = (int)($_POST['ann_id'] ?? 0);
    if ($uid && $aid) {
        try {
            $db->prepare("INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES (?,?)")
               ->execute([$aid, $uid]);
        } catch (\Exception $e) { /* ignore duplicate */ }
    }
    echo json_encode(['ok' => true]);
    exit;
}

// ── AJAX: polling pengumuman baru (user) ────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['action'])
    && $_GET['action'] === 'poll_announcements') {
    $db   = getDB();
    $cuser = getCurrentUser();
    $uid  = $cuser['id'] ?? 0;
    $isAdminPoll = ($cuser['role'] ?? '') === 'admin';

    if ($isAdminPoll) {
        // Admin: kirim riwayat pengumuman terbaru
        $rows = $db->query(
            "SELECT a.*, u.name AS sender_name
             FROM announcements a
             JOIN users u ON u.id = a.created_by
             WHERE a.is_active = 1
             ORDER BY a.created_at DESC
             LIMIT 5"
        )->fetchAll();
        echo json_encode(['ok' => true, 'announcements' => $rows], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // User: ambil pengumuman aktif yang belum dibaca
    $stmt = $db->prepare("
        SELECT a.id, a.message, a.created_at
        FROM announcements a
        LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
        WHERE a.is_active = 1 AND ar.id IS NULL
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$uid]);
    $rows = $stmt->fetchAll();
    echo json_encode(['ok' => true, 'announcements' => $rows, 'unread' => count($rows)], JSON_UNESCAPED_UNICODE);
    exit;
}
// ── END AJAX ────────────────────────────────────────────────

$user = getCurrentUser();
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$isAdmin = $user['role'] === 'admin';
$initial = strtoupper(mb_substr($user['name'], 0, 1));

// ── Status pemahaman Hiragana/Katakana (untuk modal onboarding) ──
// Catatan: tidak bergantung pada apa yang dikembalikan getCurrentUser() —
// query langsung ke DB supaya tetap akurat walau getCurrentUser() di config.php
// memakai SELECT kolom eksplisit (belum tentu menyertakan kolom baru ini).
$hiraganaStatus      = 'belum_dijawab';
$katakanaStatus      = 'belum_dijawab';
$kanaOnboardingAsked = null; // null = modal onboarding INI belum pernah dijawab sama sekali
if (array_key_exists('hiragana_status', $user) && array_key_exists('katakana_status', $user) && array_key_exists('kana_onboarding_asked_at', $user)) {
    $hiraganaStatus      = $user['hiragana_status'] ?? 'belum_dijawab';
    $katakanaStatus      = $user['katakana_status'] ?? 'belum_dijawab';
    $kanaOnboardingAsked = $user['kana_onboarding_asked_at'] ?? null;
} else {
    $dbKana = getDB();
    $stmtKana = $dbKana->prepare("SELECT hiragana_status, katakana_status, kana_onboarding_asked_at FROM users WHERE id = ?");
    $stmtKana->execute([$user['id']]);
    $rowKana = $stmtKana->fetch();
    if ($rowKana) {
        $hiraganaStatus      = $rowKana['hiragana_status'] ?? 'belum_dijawab';
        $katakanaStatus      = $rowKana['katakana_status'] ?? 'belum_dijawab';
        $kanaOnboardingAsked = $rowKana['kana_onboarding_asked_at'] ?? null;
    }
}
// FIX BUG: sebelumnya modal ini muncul TERUS-MENERUS di SETIAP halaman dimuat
// selama status masih 'belum_dijawab' (yang memang sengaja tidak diubah saat
// user memilih "Belum, Saya Akan Belajar") — ini yang membuat user serasa
// "terkunci" dan tidak bisa akses konten lain.
//
// Sekarang modal hanya ditampilkan jika `kana_onboarding_asked_at` masih NULL,
// artinya modal ini BENAR-BENAR belum pernah dijawab sama sekali (sekali tampil
// & dijawab, kolom ini langsung terisi dan modal tidak akan muncul lagi).
// User lama (yang sudah ada sebelum perbaikan ini) ditandai sudah "pernah
// ditanya" lewat migrasi DB, sehingga modal ini ke depannya hanya akan muncul
// untuk user yang benar-benar baru pertama kali login.
$showKanaOnboarding = !$isAdmin
    && $kanaOnboardingAsked === null
    && ($hiraganaStatus === 'belum_dijawab' || $katakanaStatus === 'belum_dijawab');
$joinDate = date('d F Y', strtotime($user['created_at']));

// Untuk admin: hitung total user
$totalUsers = 0;
$totalAdmins = 0;
if ($isAdmin) {
    $db = getDB();
    $totalUsers  = $db->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
    $totalAdmins = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $totalAll    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
}

// Hitung notifikasi ujian baru untuk user (yang belum dikerjakan)
$pendingExams = 0;
$pendingTugas = 0;
$pendingKotoba = 0;
if (!$isAdmin) {
    $db = getDB();
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM exams e
        LEFT JOIN exam_attempts a ON a.exam_id = e.id AND a.user_id = ?
        WHERE e.status = 'published' AND (a.id IS NULL OR a.status != 'finished')
    ");
    $stmt->execute([$user['id']]);
    $pendingExams = (int)$stmt->fetchColumn();

    // Tugas yang belum dikumpulkan
    $stmt2 = $db->prepare("
        SELECT COUNT(*) FROM tugas t
        LEFT JOIN tugas_submissions s ON s.tugas_id = t.id AND s.user_id = ?
        WHERE t.status = 'published' AND s.id IS NULL
    ");
    $stmt2->execute([$user['id']]);
    $pendingTugas = (int)$stmt2->fetchColumn();

    // Quiz kotoba yang belum dikerjakan/diselesaikan
    // (pakai NOT EXISTS, bukan LEFT JOIN+IS NULL, supaya tidak salah hitung
    //  kalau ada lebih dari satu baris attempt untuk quiz+user yang sama —
    //  notif hanya hilang jika BENAR-BENAR ada attempt yang statusnya 'finished')
    $stmt3 = $db->prepare("
        SELECT COUNT(*) FROM kotoba_quiz q
        WHERE q.status = 'published'
          AND NOT EXISTS (
              SELECT 1 FROM kotoba_quiz_attempts a
              WHERE a.quiz_id = q.id AND a.user_id = ? AND a.status = 'finished'
          )
    ");
    $stmt3->execute([$user['id']]);
    $pendingKotoba = (int)$stmt3->fetchColumn();
}

// ── PENGUMUMAN ──────────────────────────────────────────────
$db = getDB();

// Admin: kirim pengumuman baru
$announceSuccess = '';
$announceError   = '';
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send_announcement') {
    $msg = trim($_POST['announcement_message'] ?? '');
    if (strlen($msg) < 3) {
        $announceError = 'Pesan terlalu pendek (minimal 3 karakter).';
    } elseif (strlen($msg) > 500) {
        $announceError = 'Pesan terlalu panjang (maksimal 500 karakter).';
    } else {
        $stmt = $db->prepare("INSERT INTO announcements (message, created_by) VALUES (?, ?)");
        $stmt->execute([$msg, $user['id']]);
        $announceSuccess = 'Pengumuman berhasil dikirim ke semua user! 📢';
    }
}

// Admin: hapus pengumuman
if ($isAdmin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_announcement') {
    $delId = (int)($_POST['ann_id'] ?? 0);
    if ($delId > 0) {
        $db->prepare("UPDATE announcements SET is_active=0 WHERE id=?")->execute([$delId]);
    }
    header('Location: beranda.php');
    exit;
}

// Ambil 5 pengumuman terbaru (aktif) untuk admin
$recentAnnouncements = [];
if ($isAdmin) {
    $recentAnnouncements = $db->query(
        "SELECT a.*, u.name AS sender_name
         FROM announcements a
         JOIN users u ON u.id = a.created_by
         WHERE a.is_active = 1
         ORDER BY a.created_at DESC
         LIMIT 5"
    )->fetchAll();
}

// User: ambil pengumuman yang belum dibaca
$newAnnouncements = [];
if (!$isAdmin) {
    $stmt = $db->prepare("
        SELECT a.id, a.message, a.created_at
        FROM announcements a
        LEFT JOIN announcement_reads ar ON ar.announcement_id = a.id AND ar.user_id = ?
        WHERE a.is_active = 1 AND ar.id IS NULL
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $newAnnouncements = $stmt->fetchAll();

    // Tandai semua sebagai sudah dibaca sekarang
    // (dilakukan via JS/AJAX setelah user melihat bubble, tapi kita siapkan juga endpoint)
}

// Hitung total pengumuman belum dibaca (untuk badge)
$unreadCount = count($newAnnouncements);
// ── END PENGUMUMAN ───────────────────────────────────────────

// Statistik nilai ujian untuk user
$examStats = [
    'total_done'  => 0,
    'avg_score'   => 0,
    'highest'     => 0,
    'lowest'      => 0,
    'history'     => [],
    'chart'       => [],
];
if (!$isAdmin) {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT COUNT(*) AS total_done,
               AVG(score) AS avg_score,
               MAX(score) AS highest,
               MIN(score) AS lowest
        FROM exam_attempts
        WHERE user_id = ? AND status = 'finished'
    ");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if ($row && (int)$row['total_done'] > 0) {
        $examStats['total_done'] = (int)$row['total_done'];
        $examStats['avg_score']  = round((float)$row['avg_score'], 1);
        $examStats['highest']    = round((float)$row['highest'], 1);
        $examStats['lowest']     = round((float)$row['lowest'], 1);
    }

    // Riwayat ujian terbaru (5 terakhir)
    $stmt = $db->prepare("
        SELECT e.title, a.score, a.total_correct, a.total_questions, a.finished_at
        FROM exam_attempts a
        JOIN exams e ON e.id = a.exam_id
        WHERE a.user_id = ? AND a.status = 'finished'
        ORDER BY a.finished_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $examStats['history'] = $stmt->fetchAll();

    // Data untuk grafik (kronologis, maksimal 10 ujian terakhir)
    $stmt = $db->prepare("
        SELECT e.title, a.score, a.finished_at
        FROM exam_attempts a
        JOIN exams e ON e.id = a.exam_id
        WHERE a.user_id = ? AND a.status = 'finished'
        ORDER BY a.finished_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $chartRows = $stmt->fetchAll();
    $examStats['chart'] = array_reverse($chartRows);
}

// Statistik nilai tugas untuk user
$tugasStats = [
    'total_done'  => 0,
    'avg_score'   => 0,
    'highest'     => 0,
    'lowest'      => 0,
    'history'     => [],
];
if (!$isAdmin) {
    $db = getDB();

    $stmt = $db->prepare("
        SELECT COUNT(*) AS total_done,
               AVG(nilai) AS avg_score,
               MAX(nilai) AS highest,
               MIN(nilai) AS lowest
        FROM tugas_submissions
        WHERE user_id = ? AND nilai IS NOT NULL
    ");
    $stmt->execute([$user['id']]);
    $row = $stmt->fetch();

    if ($row && (int)$row['total_done'] > 0) {
        $tugasStats['total_done'] = (int)$row['total_done'];
        $tugasStats['avg_score']  = round((float)$row['avg_score'], 1);
        $tugasStats['highest']    = round((float)$row['highest'], 1);
        $tugasStats['lowest']     = round((float)$row['lowest'], 1);
    }

    // Riwayat tugas yang sudah dinilai (5 terbaru)
    $stmt = $db->prepare("
        SELECT t.judul, s.nilai, s.feedback, s.graded_at
        FROM tugas_submissions s
        JOIN tugas t ON t.id = s.tugas_id
        WHERE s.user_id = ? AND s.nilai IS NOT NULL
        ORDER BY s.graded_at DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id']]);
    $tugasStats['history'] = $stmt->fetchAll();
}

// Statistik nilai keseluruhan (ujian + tugas) untuk user
$overallStats = [
    'total_items'   => 0,
    'avg_score'     => 0,
    'total_score'   => 0,
    'exam_count'    => 0,
    'exam_total'    => 0,
    'tugas_count'   => 0,
    'tugas_total'   => 0,
];
if (!$isAdmin) {
    $overallStats['exam_count']  = $examStats['total_done'];
    $overallStats['tugas_count'] = $tugasStats['total_done'];
    $overallStats['total_items'] = $overallStats['exam_count'] + $overallStats['tugas_count'];

    if ($overallStats['exam_count'] > 0) {
        $overallStats['exam_total'] = round($examStats['avg_score'] * $overallStats['exam_count'], 1);
    }
    if ($overallStats['tugas_count'] > 0) {
        $overallStats['tugas_total'] = round($tugasStats['avg_score'] * $overallStats['tugas_count'], 1);
    }

    $overallStats['total_score'] = round($overallStats['exam_total'] + $overallStats['tugas_total'], 1);

    if ($overallStats['total_items'] > 0) {
        $overallStats['avg_score'] = round($overallStats['total_score'] / $overallStats['total_items'], 1);
    }
}

// ── CLUB PROFILE: Load data ──────────────────────────────
$clubProfileFile = __DIR__ . '/data/club_profiles.json';
$clubProfiles = ['ketua' => ['name' => '', 'photo' => ''], 'guru' => ['name' => '', 'photo' => '']];
if (file_exists($clubProfileFile)) {
    $loaded = json_decode(file_get_contents($clubProfileFile), true) ?: [];
    $clubProfiles = array_merge($clubProfiles, $loaded);
}
// ── END CLUB PROFILE ─────────────────────────────────────

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Beranda</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* ── Scroll Performance Optimizations ────────────────── */
    /* Native smooth scrolling on mobile with momentum */
    body {
      -webkit-overflow-scrolling: touch;
      overscroll-behavior-y: contain;
    }
    /* Hint browser this area only scrolls vertically — skip hit-test overhead */
    .dashboard-main {
      touch-action: pan-y;
    }
    /* Promote topbar to its own GPU layer so it doesn't repaint during scroll */
    .topbar {
      will-change: transform;
      backface-visibility: hidden;
    }
    /* Isolate each card into its own stacking context to reduce paint area */
    .profile-card,
    .stats-card-exam,
    .bottom-action-bar {
      contain: layout style;
      backface-visibility: hidden;
    }
    /* fade-up: use transform+opacity only (compositor-thread friendly) */
    .fade-up {
      will-change: transform, opacity;
      opacity: 0;
      transform: translateY(24px);
      transition: opacity 0.45s ease, transform 0.45s ease;
    }
    .fade-up.is-visible {
      opacity: 1;
      transform: translateY(0);
      will-change: auto; /* lepas will-change setelah animasi selesai */
    }
    /* Fixed/sticky announcement bubble: own GPU layer */
    .ann-bubble {
      backface-visibility: hidden;
    }
    /* Reduce background complexity on scroll — defer petals pointer events */
    #petals {
      pointer-events: none;
    }
    /* Smooth scroll at OS level */
    html {
      scroll-behavior: smooth;
    }
    /* Prevent layout thrash from dynamic content resizing */
    .exam-chart-bars {
      contain: strict;
      height: 120px; /* match actual rendered height */
    }

    /* Topbar AI Button */
    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .ai-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border-radius: 20px;
      background: linear-gradient(135deg, #7c3aed, #a855f7);
      color: #fff;
      font-size: .82rem;
      font-weight: 700;
      text-decoration: none;
      box-shadow: 0 2px 8px rgba(124,58,237,.35);
      transition: transform .18s, box-shadow .18s, opacity .18s;
      white-space: nowrap;
      letter-spacing: .01em;
    }
    .ai-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(124,58,237,.5);
      opacity: .93;
    }
    .ai-btn-icon { font-size: 1rem; }
    .ai-btn-label { display: inline; }
    @media (max-width: 480px) {
      .ai-btn-label { display: none; }
      .ai-btn { padding: 7px 10px; }
    }

    /* ── Announcement Bubble ─────────────────────────────── */
    .ann-bubble-overlay {
      position: fixed;
      inset: 0;
      z-index: 9000;
      pointer-events: none;
    }
    .ann-bubble {
      position: fixed;
      bottom: 80px;
      right: 20px;
      max-width: 340px;
      min-width: 260px;
      background: var(--card-bg, #fff);
      border: 1.5px solid rgba(183,75,75,0.25);
      border-radius: 20px 20px 4px 20px;
      box-shadow: 0 8px 32px rgba(0,0,0,.18), 0 2px 8px rgba(183,75,75,.12);
      padding: 16px 18px 14px;
      z-index: 9100;
      pointer-events: all;
      animation: annSlideIn .4s cubic-bezier(.22,1,.36,1) both;
      transform-origin: bottom right;
    }
    @keyframes annSlideIn {
      from { opacity: 0; transform: scale(.85) translateY(20px); }
      to   { opacity: 1; transform: scale(1)  translateY(0); }
    }
    .ann-bubble-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 8px;
    }
    .ann-bubble-avatar {
      width: 34px; height: 34px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--torii, #b74b4b), #d97070);
      color: #fff;
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem; font-weight: 700;
      flex-shrink: 0;
    }
    .ann-bubble-meta { flex: 1; min-width: 0; }
    .ann-bubble-sender { font-weight: 700; font-size: .82rem; color: var(--text, #333); }
    .ann-bubble-time   { font-size: .73rem; color: var(--mist, #888); }
    .ann-bubble-close {
      background: none; border: none; cursor: pointer;
      color: var(--mist, #888); font-size: 1.1rem; line-height: 1;
      padding: 2px 4px; border-radius: 6px; transition: background .15s;
      flex-shrink: 0;
    }
    .ann-bubble-close:hover { background: rgba(0,0,0,.08); }
    .ann-bubble-msg {
      font-size: .9rem;
      color: var(--text, #333);
      line-height: 1.5;
      word-break: break-word;
    }
    .ann-bubble-footer {
      margin-top: 10px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
    }
    .ann-bubble-counter {
      font-size: .75rem; color: var(--mist, #888);
    }
    .ann-bubble-btn {
      font-size: .78rem; padding: 5px 12px;
      border-radius: 12px; border: none; cursor: pointer;
      background: linear-gradient(135deg, var(--torii, #b74b4b), #d97070);
      color: #fff; font-weight: 700; transition: opacity .15s;
    }
    .ann-bubble-btn:hover { opacity: .85; }
    /* Badge di topbar */
    .ann-badge {
      position: absolute; top: -4px; right: -4px;
      background: var(--torii, #b74b4b); color: #fff;
      border-radius: 50%; width: 18px; height: 18px;
      font-size: .68rem; font-weight: 800;
      display: flex; align-items: center; justify-content: center;
      line-height: 1; border: 2px solid var(--card-bg, #fff);
    }
    .ann-bell-btn {
      position: relative;
      display: inline-flex; align-items: center; justify-content: center;
      width: 38px; height: 38px;
      border-radius: 50%;
      background: rgba(183,75,75,.1);
      border: 1.5px solid rgba(183,75,75,.25);
      cursor: pointer; transition: background .18s;
      font-size: 1.1rem;
      text-decoration: none;
    }
    .ann-bell-btn:hover { background: rgba(183,75,75,.18); }

    /* ── Announcement Form (admin) ───────────────────────── */
    .ann-form-card {
      background: var(--card-bg, #fff);
      border: 1.5px solid rgba(183,75,75,.2);
      border-radius: 18px;
      padding: 22px 22px 18px;
      margin-bottom: 0;
    }
    .ann-form-header {
      display: flex; align-items: center; gap: 10px; margin-bottom: 14px;
    }
    .ann-form-icon { font-size: 1.4rem; }
    .ann-form-title { font-size: 1rem; font-weight: 700; color: var(--text, #333); }
    .ann-form-sub   { font-size: .78rem; color: var(--mist, #888); }
    .ann-textarea {
      width: 100%; box-sizing: border-box;
      border: 1.5px solid var(--card-border, #ddd);
      border-radius: 12px;
      padding: 12px 14px;
      font-size: .9rem;
      line-height: 1.5;
      resize: vertical; min-height: 80px;
      background: var(--input-bg, #fafafa);
      color: var(--text, #333);
      transition: border-color .18s;
      font-family: inherit;
    }
    .ann-textarea:focus { outline: none; border-color: var(--torii, #b74b4b); }
    .ann-char-count { font-size: .73rem; color: var(--mist, #888); text-align: right; margin-top: 4px; }
    .ann-send-btn {
      display: inline-flex; align-items: center; gap: 7px;
      padding: 9px 20px;
      border-radius: 14px; border: none; cursor: pointer;
      background: linear-gradient(135deg, var(--torii, #b74b4b), #d97070);
      color: #fff; font-size: .88rem; font-weight: 700;
      transition: transform .18s, box-shadow .18s;
      margin-top: 10px;
    }
    .ann-send-btn:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(183,75,75,.4); }
    .ann-alert {
      padding: 10px 14px; border-radius: 10px; font-size: .85rem;
      margin-bottom: 10px; font-weight: 600;
    }
    .ann-alert-success { background: rgba(74,124,89,.12); color: var(--bamboo, #4a7c59); border: 1px solid rgba(74,124,89,.25); }
    .ann-alert-error   { background: rgba(183,75,75,.10); color: var(--torii, #b74b4b); border: 1px solid rgba(183,75,75,.25); }
    /* Riwayat pengumuman */
    .ann-history-item {
      display: flex; gap: 10px; align-items: flex-start;
      padding: 10px 0;
      border-bottom: 1px solid var(--card-border, #eee);
    }
    .ann-history-item:last-child { border-bottom: none; }
    .ann-history-dot {
      width: 8px; height: 8px; border-radius: 50%;
      background: var(--torii, #b74b4b);
      flex-shrink: 0; margin-top: 6px;
    }
    .ann-history-msg  { font-size: .86rem; color: var(--text, #333); line-height: 1.4; flex: 1; }
    .ann-history-time { font-size: .72rem; color: var(--mist, #888); margin-top: 2px; }
    .ann-del-btn {
      background: none; border: none; cursor: pointer;
      color: var(--mist, #aaa); font-size: .85rem; padding: 2px 5px;
      border-radius: 6px; transition: color .15s, background .15s;
      flex-shrink: 0;
    }
    .ann-del-btn:hover { color: var(--torii, #b74b4b); background: rgba(183,75,75,.08); }

    .overall-score-card .overall-total-box {
      text-align: center;
      margin: 18px 0 22px;
      padding: 22px;
      border-radius: 16px;
      background: linear-gradient(135deg, rgba(183,75,75,.10), rgba(74,124,89,.10));
      border: 1px solid var(--card-border);
    }
    .overall-score-card .overall-total-number {
      font-size: 2.6rem;
      font-weight: 800;
      line-height: 1.1;
      color: var(--torii);
    }
    .overall-score-card .overall-total-label {
      font-size: .82rem;
      color: var(--mist);
      margin-top: 6px;
      font-weight: 600;
    }

    /* ── content-visibility: skip off-screen rendering ─── */
    /* Cards below the fold are skipped until scrolled into view */
    .stats-card-exam {
      content-visibility: auto;
      contain-intrinsic-size: 0 400px; /* estimated height to prevent layout shift */
    }
    .overall-score-card {
      content-visibility: auto;
      contain-intrinsic-size: 0 260px;
    }

    /* ── Club Profile Section ─────────────────────────── */
    .club-section {
      margin-top: 10px;
      margin-bottom: 0;
    }
    .club-section-header {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 16px;
      padding: 0 2px;
    }
    .club-section-title {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--text, #333);
      letter-spacing: .01em;
    }
    .club-section-divider {
      flex: 1;
      height: 1.5px;
      background: linear-gradient(90deg, rgba(183,75,75,.35), transparent);
      border-radius: 2px;
    }
    .club-profiles-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }
    @media (max-width: 420px) {
      .club-profiles-grid { grid-template-columns: 1fr; }
    }
    .club-profile-card {
      background: var(--card-bg, #fff);
      border: 1.5px solid rgba(183,75,75,.18);
      border-radius: 20px;
      padding: 22px 16px 18px;
      text-align: center;
      position: relative;
      overflow: hidden;
      transition: transform .2s, box-shadow .2s;
      contain: layout style;
    }
    .club-profile-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 28px rgba(183,75,75,.18);
    }
    .club-profile-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--torii, #b74b4b), #d97070, var(--gold, #c9a96e));
    }
    .club-profile-card.guru-card::before {
      background: linear-gradient(90deg, var(--bamboo, #4a7c59), #6aab7a, var(--gold, #c9a96e));
    }
    .club-profile-photo-wrap {
      position: relative;
      display: inline-block;
      margin-bottom: 12px;
    }
    .club-profile-photo {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid rgba(183,75,75,.3);
      display: block;
      background: linear-gradient(135deg, var(--torii,#b74b4b), #d97070);
    }
    .guru-card .club-profile-photo {
      border-color: rgba(74,124,89,.35);
    }
    .club-profile-photo-placeholder {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--torii,#b74b4b), #d97070);
      border: 3px solid rgba(183,75,75,.3);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
      font-weight: 800;
      color: #fff;
      margin: 0 auto;
    }
    .guru-card .club-profile-photo-placeholder {
      background: linear-gradient(135deg, var(--bamboo,#4a7c59), #6aab7a);
      border-color: rgba(74,124,89,.35);
    }
    .club-profile-badge {
      position: absolute;
      bottom: 0; right: 0;
      width: 24px; height: 24px;
      border-radius: 50%;
      background: var(--torii, #b74b4b);
      color: #fff;
      font-size: .7rem;
      display: flex; align-items: center; justify-content: center;
      border: 2px solid var(--card-bg, #fff);
      font-weight: 800;
    }
    .guru-card .club-profile-badge {
      background: var(--bamboo, #4a7c59);
    }
    .club-profile-name {
      font-size: 1rem;
      font-weight: 800;
      color: var(--text, #333);
      margin-bottom: 5px;
      line-height: 1.3;
    }
    .club-profile-role {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: .75rem;
      font-weight: 700;
      background: rgba(183,75,75,.1);
      color: var(--torii, #b74b4b);
      border: 1px solid rgba(183,75,75,.2);
      letter-spacing: .02em;
    }
    .guru-card .club-profile-role {
      background: rgba(74,124,89,.1);
      color: var(--bamboo, #4a7c59);
      border-color: rgba(74,124,89,.2);
    }
    /* Edit Button (admin only) */
    .club-edit-btn {
      position: absolute;
      top: 10px; right: 10px;
      width: 28px; height: 28px;
      border-radius: 50%;
      background: rgba(183,75,75,.12);
      border: 1px solid rgba(183,75,75,.25);
      color: var(--torii, #b74b4b);
      font-size: .8rem;
      display: flex; align-items: center; justify-content: center;
      cursor: pointer;
      transition: background .18s, transform .18s;
      text-decoration: none;
    }
    .club-edit-btn:hover {
      background: rgba(183,75,75,.22);
      transform: rotate(15deg) scale(1.1);
    }
    /* Modal Edit */
    .club-modal-overlay {
      position: fixed; inset: 0;
      background: rgba(0,0,0,.45);
      z-index: 10000;
      display: flex; align-items: center; justify-content: center;
      padding: 20px;
      animation: fadeInOverlay .2s ease;
    }
    @keyframes fadeInOverlay { from { opacity:0; } to { opacity:1; } }
    .club-modal {
      background: var(--card-bg, #fff);
      border-radius: 22px;
      padding: 26px 24px 22px;
      width: 100%; max-width: 400px;
      box-shadow: 0 20px 60px rgba(0,0,0,.25);
      animation: slideUpModal .3s cubic-bezier(.22,1,.36,1);
      position: relative;
    }
    @keyframes slideUpModal {
      from { opacity:0; transform:translateY(24px) scale(.97); }
      to   { opacity:1; transform:translateY(0) scale(1); }
    }

    /* ── Onboarding Kana Modal ── */
    .kana-onb-overlay {
      position: fixed; inset: 0;
      background: rgba(0,0,0,.5);
      z-index: 10001;
      display: none;
      align-items: center; justify-content: center;
      padding: 20px;
      animation: fadeInOverlay .25s ease;
    }
    .kana-onb-overlay.open { display: flex; }
    .kana-onb-modal {
      background: var(--card-bg, #5e5d5d);
      border-radius: 24px;
      padding: 32px 26px 26px;
      width: 100%; max-width: 420px;
      box-shadow: 0 24px 70px rgba(0,0,0,.3);
      animation: slideUpModal .32s cubic-bezier(.22,1,.36,1);
      text-align: center;
      position: relative;
    }
    .kana-onb-step { display: none; }
    .kana-onb-step.active { display: block; }
    .kana-onb-kanji {
      font-size: 2.6rem;
      color: var(--torii, #C0392B);
      line-height: 1;
      margin-bottom: 10px;
      display: block;
    }
    .kana-onb-title {
      font-size: 1.15rem;
      font-weight: 800;
      color: var(--text);
      margin-bottom: 8px;
    }
    .kana-onb-sub {
      font-size: .87rem;
      color: var(--mist, #000000);
      line-height: 1.55;
      margin-bottom: 24px;
    }
    .kana-onb-actions {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .kana-onb-btn {
      width: 100%;
      box-sizing: border-box;
      padding: 13px 18px;
      border-radius: 13px;
      font-size: .92rem;
      font-weight: 700;
      cursor: pointer;
      border: 1.5px solid;
      font-family: inherit;
      transition: transform .15s, background .2s, filter .15s;
    }
    .kana-onb-btn:active { transform: scale(0.98); }
    .kana-onb-btn-yes {
      background: var(--bamboo, #4a7c59);
      color: #fff;
      border-color: var(--bamboo, #4a7c59);
    }
    .kana-onb-btn-yes:hover { filter: brightness(0.95); }
    .kana-onb-btn-no {
      background: transparent;
      color: var(--text);
      border-color: var(--card-border, #ddd);
    }
    .kana-onb-btn-no:hover { border-color: var(--torii, #C0392B); color: var(--torii, #C0392B); }
    .kana-onb-progress {
      display: flex;
      gap: 6px;
      justify-content: center;
      margin-bottom: 18px;
    }
    .kana-onb-dot {
      width: 7px; height: 7px;
      border-radius: 50%;
      background: var(--card-border, #ddd);
    }
    .kana-onb-dot.active { background: var(--torii, #C0392B); width: 18px; border-radius: 4px; }
    .kana-onb-link {
      display: inline-block;
      margin-top: 14px;
      font-size: .82rem;
      color: var(--torii, #C0392B);
      text-decoration: underline;
      cursor: pointer;
      background: none;
      border: none;
      font-family: inherit;
    }
    .club-modal-title {
      font-size: 1.05rem; font-weight: 800;
      color: var(--text, #333);
      margin-bottom: 18px;
      display: flex; align-items: center; gap: 8px;
    }
    .club-modal-close {
      position: absolute; top: 16px; right: 16px;
      background: none; border: none; cursor: pointer;
      font-size: 1.1rem; color: var(--mist, #888);
      padding: 4px 6px; border-radius: 8px;
      transition: background .15s;
    }
    .club-modal-close:hover { background: rgba(0,0,0,.08); }
    .club-modal-label {
      font-size: .8rem; font-weight: 700;
      color: var(--mist, #888);
      margin-bottom: 5px;
      display: block;
    }
    .club-modal-input {
      width: 100%; box-sizing: border-box;
      border: 1.5px solid var(--card-border, #ddd);
      border-radius: 12px;
      padding: 10px 13px;
      font-size: .9rem;
      color: var(--text, #333);
      background: var(--input-bg, #fafafa);
      transition: border-color .18s;
      font-family: inherit;
      margin-bottom: 14px;
    }
    .club-modal-input:focus {
      outline: none;
      border-color: var(--torii, #b74b4b);
    }
    .club-modal-photo-preview {
      width: 70px; height: 70px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid rgba(183,75,75,.3);
      margin: 0 auto 10px;
      display: block;
      background: linear-gradient(135deg, var(--torii,#b74b4b), #d97070);
    }
    .club-modal-photo-hint {
      font-size: .75rem; color: var(--mist, #888);
      text-align: center; margin-bottom: 14px;
    }
    .club-modal-save-btn {
      width: 100%;
      padding: 11px;
      border-radius: 14px;
      border: none;
      background: linear-gradient(135deg, var(--torii, #b74b4b), #d97070);
      color: #fff;
      font-size: .92rem;
      font-weight: 800;
      cursor: pointer;
      transition: transform .18s, box-shadow .18s;
    }
    .club-modal-save-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(183,75,75,.4);
    }
    .club-modal-save-btn:disabled {
      opacity: .6; cursor: not-allowed; transform: none;
    }
  </style>
</head>
<body class="dashboard-page">

  <!-- Page Loader -->
  <div class="page-loader" id="pageLoader">
    <span class="loader-kanji">桜</span>
  </div>

  <!-- Backgrounds -->
  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <!-- ── TOPBAR ── -->
  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <div class="topbar-actions">
      <?php if (!$isAdmin): ?>
      <a class="ann-bell-btn" id="annBellBtn" title="Pengumuman" onclick="openAnnBubble(); return false;" href="#">
        🔔
        <span class="ann-badge" id="annBadge" style="<?= $unreadCount > 0 ? '' : 'display:none;' ?>"><?= $unreadCount ?></span>
      </a>
      <?php endif; ?>
      <a href="chatbot.php" class="ai-btn" title="Tanya AI">
        <span class="ai-btn-icon">🤖</span>
        <span class="ai-btn-label">Tanya AI</span>
      </a>
      <button class="theme-toggle" onclick="toggleTheme()" title="Mode Terang">☀️</button>
    </div>
  </header>

  <!-- ── MAIN CONTENT ── -->
  <main class="dashboard-main">

    <!-- Welcome -->
    <section class="welcome-section fade-up">
      <span class="welcome-kanji"><?= $isAdmin ? '管理' : 'ようこそ' ?></span>
      <h1 class="welcome-title">Selamat Datang, <?= htmlspecialchars($user['name']) ?></h1>
      <p class="welcome-sub">
        <?= $isAdmin
          ? 'Anda masuk sebagai Administrator — pengelola sistem Sakura App'
          : 'Nikmati pengalaman bersama Sakura App' ?>
      </p>
      <div class="section-divider"></div>
    </section>

    <?php if (!$isAdmin && $overallStats['total_items'] > 0): ?>
    <!-- Overall Score Summary Card -->
    <div class="profile-card fade-up delay-1 overall-score-card">
      <div class="stats-header">
        <span class="stats-header-icon">🏆</span>
        <div>
          <div class="stats-header-title">Total Nilai Keseluruhan</div>
          <div class="stats-header-sub">Gabungan nilai ujian dan tugas yang sudah dinilai</div>
        </div>
      </div>

      <div class="overall-total-box">
        <div class="overall-total-number"><?= number_format($overallStats['total_score'], 1) ?></div>
        <div class="overall-total-label">Total Poin (dari <?= $overallStats['total_items'] ?> item dinilai)</div>
      </div>

      <div class="stats-row exam-stats-row">
        <div class="stat-card">
          <div class="stat-number"><?= number_format($overallStats['avg_score'], 1) ?></div>
          <div class="stat-label">Rata-rata Keseluruhan</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?= number_format($overallStats['exam_total'], 1) ?></div>
          <div class="stat-label">Total Nilai Ujian (<?= $overallStats['exam_count'] ?>)</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?= number_format($overallStats['tugas_total'], 1) ?></div>
          <div class="stat-label">Total Nilai Tugas (<?= $overallStats['tugas_count'] ?>)</div>
        </div>
      </div>
    </div><!-- /overall-score-card -->
    <?php endif; ?>

    <!-- Profile Card -->
    <div class="profile-card <?= $isAdmin ? 'admin-card' : '' ?> fade-up delay-1">

      <!-- Profile Header -->
      <div class="profile-header">
        <div class="profile-avatar-lg">
          <?= htmlspecialchars($initial) ?>
          <div class="role-badge <?= $isAdmin ? 'badge-admin' : 'badge-user' ?>">
            <?= $isAdmin ? '⛩' : '🌸' ?>
          </div>
        </div>
        <div class="profile-meta">
          <div class="profile-name"><?= htmlspecialchars($user['name']) ?></div>
          <div class="profile-email"><?= htmlspecialchars($user['email']) ?></div>
          <span class="profile-role-tag <?= $isAdmin ? 'tag-admin' : 'tag-user' ?>">
            <?= $isAdmin ? 'Administrator' : 'Member' ?>
          </span>
        </div>
      </div>

      <!-- Info Grid -->
      <div class="profile-grid">
        <div class="info-item">
          <div class="info-label">ID Akun</div>
          <div class="info-value">#<?= str_pad($user['id'], 5, '0', STR_PAD_LEFT) ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Peran</div>
          <div class="info-value"><?= $isAdmin ? 'Administrator' : 'User' ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Bergabung</div>
          <div class="info-value"><?= $joinDate ?></div>
        </div>
        <div class="info-item">
          <div class="info-label">Status</div>
          <div class="info-value" style="color: var(--bamboo);">● Aktif</div>
        </div>
      </div>

      <!-- Bio -->
      <?php if (!empty($user['bio'])): ?>
      <div class="profile-bio">
        "<?= htmlspecialchars($user['bio']) ?>"
      </div>
      <?php endif; ?>

      <?php if ($isAdmin): ?>
      <!-- Admin Stats -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-number"><?= $totalAll ?></div>
          <div class="stat-label">Total Pengguna</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?= $totalUsers ?></div>
          <div class="stat-label">Member</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?= $totalAdmins ?></div>
          <div class="stat-label">Admin</div>
        </div>
      </div>

      <!-- Admin notice -->
      <div class="admin-panel-note">
        <span class="icon">⛩</span>
        <p>Anda memiliki akses <strong>Administrator</strong>. Panel manajemen pengguna, konten, dan konfigurasi sistem tersedia untuk Anda.</p>
      </div>

      <!-- ── FORM PENGUMUMAN (Admin) ── -->
      <div class="ann-form-card" style="margin-top:18px;">
        <div class="ann-form-header">
          <span class="ann-form-icon">📢</span>
          <div>
            <div class="ann-form-title">Kirim Pengumuman</div>
            <div class="ann-form-sub">Pesan akan muncul sebagai notifikasi gelembung ke semua user</div>
          </div>
        </div>

        <?php if ($announceSuccess): ?>
          <div class="ann-alert ann-alert-success">✅ <?= htmlspecialchars($announceSuccess) ?></div>
        <?php endif; ?>
        <?php if ($announceError): ?>
          <div class="ann-alert ann-alert-error">⚠️ <?= htmlspecialchars($announceError) ?></div>
        <?php endif; ?>

        <form method="POST" action="beranda.php">
          <input type="hidden" name="action" value="send_announcement">
          <textarea
            name="announcement_message"
            class="ann-textarea"
            placeholder="Tulis pesan pengumuman di sini... (maks. 500 karakter)"
            maxlength="500"
            id="annTextarea"
            oninput="updateCharCount(this)"
          ></textarea>
          <div class="ann-char-count"><span id="annCharCount">0</span>/500</div>
          <button type="submit" class="ann-send-btn">
            <span>📤</span> Kirim ke Semua User
          </button>
        </form>

        <?php if (!empty($recentAnnouncements)): ?>
        <div style="margin-top:18px;" id="annHistoryWrap">
          <div class="exam-history-title" style="margin-bottom:8px;">📋 Riwayat Pengumuman Terakhir</div>
          <div id="annHistoryList">
          <?php foreach ($recentAnnouncements as $ann): ?>
          <div class="ann-history-item">
            <div class="ann-history-dot"></div>
            <div style="flex:1; min-width:0;">
              <div class="ann-history-msg"><?= htmlspecialchars($ann['message']) ?></div>
              <div class="ann-history-time">
                📅 <?= date('d M Y, H:i', strtotime($ann['created_at'])) ?>
                &nbsp;· oleh <?= htmlspecialchars($ann['sender_name']) ?>
              </div>
            </div>
            <form method="POST" action="beranda.php" style="margin:0;">
              <input type="hidden" name="action" value="delete_announcement">
              <input type="hidden" name="ann_id" value="<?= $ann['id'] ?>">
              <button type="submit" class="ann-del-btn" title="Hapus pengumuman" onclick="return confirm('Hapus pengumuman ini?')">🗑</button>
            </form>
          </div>
          <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>
      </div>
      <!-- ── END FORM PENGUMUMAN ── -->

      <?php else: ?>
      <!-- User note -->
      <div class="admin-panel-note" style="background:rgba(74,124,89,0.08); border-color:rgba(74,124,89,0.25);">
        <span class="icon">🌸</span>
        <p>Selamat datang di <strong style="color:var(--bamboo)">Sakura App</strong>. Jelajahi fitur-fitur yang tersedia untuk kamu.</p>
      </div>

      <!-- Catatan status pemahaman Hiragana & Katakana -->
      <?php
        $kanaBadgeMap = [
            'belum_dijawab' => ['❔ Belum Dijawab', '#999', 'rgba(150,150,150,0.12)'],
            'sudah_paham'   => ['✅ Sudah Paham',   'var(--bamboo)', 'rgba(74,124,89,0.12)'],
            'lulus_ujian'   => ['🏅 Lulus Ujian (100%)', 'var(--torii)', 'rgba(192,57,43,0.10)'],
        ];
        [$hLabel, $hColor, $hBg] = $kanaBadgeMap[$hiraganaStatus] ?? $kanaBadgeMap['belum_dijawab'];
        [$kLabel, $kColor, $kBg] = $kanaBadgeMap[$katakanaStatus] ?? $kanaBadgeMap['belum_dijawab'];
      ?>
      <div class="kana-status-note" style="margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;">
        <div style="flex:1; min-width:150px; background:<?= $hBg ?>; border:1px solid <?= $hColor ?>33; border-radius:12px; padding:12px 14px;">
          <div style="font-size:.72rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--mist,#888);">ひ Hiragana</div>
          <div style="font-size:.85rem; font-weight:700; color:<?= $hColor ?>; margin-top:4px;"><?= $hLabel ?></div>
        </div>
        <div style="flex:1; min-width:150px; background:<?= $kBg ?>; border:1px solid <?= $kColor ?>33; border-radius:12px; padding:12px 14px;">
          <div style="font-size:.72rem; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:var(--mist,#888);">カ Katakana</div>
          <div style="font-size:.85rem; font-weight:700; color:<?= $kColor ?>; margin-top:4px;"><?= $kLabel ?></div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /profile-card -->

    <?php if (!$isAdmin): ?>
    <!-- Exam Statistics Card -->
    <div class="profile-card fade-up delay-2 stats-card-exam">
      <div class="stats-header">
        <span class="stats-header-icon">🌸</span>
        <div>
          <div class="stats-header-title">Statistik Ujian</div>
          <div class="stats-header-sub">Rekap nilai dari ujian yang sudah kamu kerjakan</div>
        </div>
      </div>

      <?php if ($examStats['total_done'] === 0): ?>
        <div class="admin-panel-note" style="background:rgba(74,124,89,0.08); border-color:rgba(74,124,89,0.25); margin-top: 20px;">
          <span class="icon">📊</span>
          <p>Belum ada riwayat ujian. Statistik akan muncul setelah kamu menyelesaikan ujian pertama.</p>
        </div>
      <?php else: ?>

      <div class="stats-row exam-stats-row<?= $examStats['total_done'] === 1 ? ' exam-stats-row-3' : '' ?>">
        <div class="stat-card">
          <div class="stat-number"><?= $examStats['total_done'] ?></div>
          <div class="stat-label">Ujian Selesai</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?= number_format($examStats['avg_score'], 1) ?></div>
          <div class="stat-label">Rata-rata Nilai</div>
        </div>
        <div class="stat-card">
          <div class="stat-number" style="color: var(--bamboo);"><?= number_format($examStats['highest'], 1) ?></div>
          <div class="stat-label"><?= $examStats['total_done'] === 1 ? 'Nilai Ujian' : 'Nilai Tertinggi' ?></div>
        </div>
        <?php if ($examStats['total_done'] > 1): ?>
        <div class="stat-card">
          <div class="stat-number" style="color: var(--torii);"><?= number_format($examStats['lowest'], 1) ?></div>
          <div class="stat-label">Nilai Terendah</div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Grafik Nilai -->
      <div class="exam-chart">
        <div class="exam-history-title">Grafik Perkembangan Nilai</div>
        <div class="exam-chart-bars">
          <?php foreach ($examStats['chart'] as $c):
            $score = (float)$c['score'];
            $heightPct = max(4, min(100, $score));
            $barColor = $score >= 80 ? 'var(--bamboo)' : ($score >= 60 ? 'var(--gold)' : 'var(--torii)');
          ?>
            <div class="exam-chart-col">
              <div class="exam-chart-value"><?= number_format($score, 1) ?></div>
              <div class="exam-chart-bar-track">
                <div class="exam-chart-bar" style="height: <?= $heightPct ?>%; background: <?= $barColor ?>;"></div>
              </div>
              <div class="exam-chart-label" title="<?= htmlspecialchars($c['title']) ?>">
                <?= htmlspecialchars(mb_strimwidth($c['title'], 0, 12, '…')) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="exam-chart-legend">
          <span><span class="legend-dot" style="background: var(--bamboo); border: none;"></span> ≥ 80</span>
          <span><span class="legend-dot" style="background: var(--gold); border: none;"></span> 60–79</span>
          <span><span class="legend-dot" style="background: var(--torii); border: none;"></span> &lt; 60</span>
        </div>
      </div>

      <!-- Riwayat ujian -->
      <div class="exam-history">
        <div class="exam-history-title">Riwayat Terbaru</div>
        <?php foreach ($examStats['history'] as $h): ?>
          <div class="exam-history-item">
            <div class="exam-history-main">
              <div class="exam-history-name"><?= htmlspecialchars($h['title']) ?></div>
              <div class="exam-history-date">
                <?= $h['finished_at'] ? date('d F Y, H:i', strtotime($h['finished_at'])) : '-' ?>
              </div>
            </div>
            <div class="exam-history-side">
              <div class="exam-history-score"><?= number_format((float)$h['score'], 1) ?></div>
              <div class="exam-history-correct"><?= (int)$h['total_correct'] ?>/<?= (int)$h['total_questions'] ?> benar</div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php endif; ?>
    </div><!-- /stats-card-exam -->

    <!-- Tugas Statistics Card -->
    <div class="profile-card fade-up delay-2 stats-card-exam">
      <div class="stats-header">
        <span class="stats-header-icon">課題</span>
        <div>
          <div class="stats-header-title">Statistik Tugas</div>
          <div class="stats-header-sub">Rekap nilai tugas yang sudah dikoreksi admin</div>
        </div>
      </div>

      <?php if ($tugasStats['total_done'] === 0): ?>
        <div class="admin-panel-note" style="background:rgba(74,124,89,0.08); border-color:rgba(74,124,89,0.25); margin-top: 20px;">
          <span class="icon">📝</span>
          <p>Belum ada tugas yang dinilai. Statistik akan muncul setelah admin memberi nilai.</p>
        </div>
      <?php else: ?>

      <div class="stats-row exam-stats-row<?= $tugasStats['total_done'] === 1 ? ' exam-stats-row-3' : '' ?>">
        <div class="stat-card">
          <div class="stat-number"><?= $tugasStats['total_done'] ?></div>
          <div class="stat-label">Tugas Dinilai</div>
        </div>
        <div class="stat-card">
          <div class="stat-number"><?= number_format($tugasStats['avg_score'], 1) ?></div>
          <div class="stat-label">Rata-rata Nilai</div>
        </div>
        <div class="stat-card">
          <div class="stat-number" style="color: var(--bamboo);"><?= number_format($tugasStats['highest'], 1) ?></div>
          <div class="stat-label"><?= $tugasStats['total_done'] === 1 ? 'Nilai Tugas' : 'Nilai Tertinggi' ?></div>
        </div>
        <?php if ($tugasStats['total_done'] > 1): ?>
        <div class="stat-card">
          <div class="stat-number" style="color: var(--torii);"><?= number_format($tugasStats['lowest'], 1) ?></div>
          <div class="stat-label">Nilai Terendah</div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Riwayat tugas dinilai -->
      <div class="exam-history">
        <div class="exam-history-title">Riwayat Penilaian Terbaru</div>
        <?php foreach ($tugasStats['history'] as $h): ?>
          <div class="exam-history-item">
            <div class="exam-history-main">
              <div class="exam-history-name"><?= htmlspecialchars($h['judul']) ?></div>
              <div class="exam-history-date">
                <?= $h['graded_at'] ? date('d F Y, H:i', strtotime($h['graded_at'])) : '-' ?>
              </div>
              <?php if ($h['feedback']): ?>
                <div class="exam-history-date" style="margin-top:2px; font-style:italic;">"<?= htmlspecialchars(mb_strimwidth($h['feedback'], 0, 60, '…')) ?>"</div>
              <?php endif; ?>
            </div>
            <div class="exam-history-side">
              <?php
                $tn = (float)$h['nilai'];
                $tColor = $tn >= 80 ? 'var(--bamboo)' : ($tn >= 60 ? 'var(--gold)' : 'var(--torii)');
              ?>
              <div class="exam-history-score" style="color:<?= $tColor ?>;"><?= number_format($tn, 1) ?></div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php endif; ?>
    </div><!-- /stats-card-tugas -->
    <?php endif; ?>

    <!-- ── ACTION BAR ── -->
    <div class="bottom-action-bar fade-up delay-3">
      <!-- Profil ringkas -->
      <div class="bab-profile">
        <div class="avatar bab-avatar"><?= htmlspecialchars($initial) ?></div>
        <div class="bab-user-info">
          <div class="bab-name"><?= htmlspecialchars($user['name']) ?></div>
          <span class="bab-role <?= $isAdmin ? 'role-admin' : 'role-user' ?>">
            <?= $isAdmin ? '⛩ Administrator' : '🌸 Member' ?>
          </span>
        </div>
      </div>

      <!-- Tombol aksi -->
      <div class="bab-actions">
        <?php if ($isAdmin): ?>
          <a href="ujian_admin.php" class="bab-btn bab-btn-primary">
            <span class="bab-btn-icon">⛩</span>
            <span>Kelola Ujian</span>
          </a>
          <a href="hafalan_admin.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,var(--bamboo),#3a6347);">
            <span class="bab-btn-icon">暗記</span>
            <span>Kelola Hafalan</span>
          </a>
          <a href="tugas_admin.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#5558af,#3a3d8a);">
            <span class="bab-btn-icon">課題</span>
            <span>Kelola Tugas</span>
          </a>
          <a href="kotoba_admin.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#7c3aed,#a855f7);">
            <span class="bab-btn-icon">言葉</span>
            <span>Kelola Quiz Kotoba</span>
          </a>
          <a href="admin_tata_bahasa.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#0f7490,#0c5a6e);">
            <span class="bab-btn-icon">文法</span>
            <span>Kelola Tata Bahasa</span>
          </a>
          <a href="admin_membaca.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#b5651d,#8a4a13);">
            <span class="bab-btn-icon">読書</span>
            <span>Kelola Membaca</span>
          </a>
          <a href="tambah_anggota.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#c9a96e,#8a6d3b);">
            <span class="bab-btn-icon">👤</span>
            <span>Tambah Anggota</span>
          </a>
          <a href="data_user.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#0f7490,#0c5a6e);">
            <span class="bab-btn-icon">会員</span>
            <span>Data Pengguna</span>
          </a>
          <a href="rangking.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#e07b00,#c96000);">
            <span class="bab-btn-icon">🏆</span>
            <span>Peringkat</span>
          </a>
        <?php else: ?>
          <a href="ujian.php" class="bab-btn bab-btn-primary">
            <span class="bab-btn-icon">🌸</span>
            <span>Mulai Ujian<?php if ($pendingExams > 0): ?> <span class="nav-badge" style="position:static; display:inline-flex; margin-left:6px; vertical-align:middle;"><?= $pendingExams ?></span><?php endif; ?></span>
          </a>
          <a href="hafalan.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,var(--bamboo),#3a6347);">
            <span class="bab-btn-icon">暗記</span>
            <span>Hafalan</span>
          </a>
          <a href="kana.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#b05a1a,#8a3d0e);">
            <span class="bab-btn-icon">仮名</span>
            <span>Kana</span>
          </a>
          <a href="tugas.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#5558af,#3a3d8a);">
            <span class="bab-btn-icon">課題</span>
            <span>Tugas<?php if ($pendingTugas > 0): ?> <span class="nav-badge" style="position:static; display:inline-flex; margin-left:6px; vertical-align:middle;"><?= $pendingTugas ?></span><?php endif; ?></span>
          </a>
          <a href="kotoba.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#7c3aed,#a855f7);">
            <span class="bab-btn-icon">言葉</span>
            <span>Quiz Kotoba<?php if ($pendingKotoba > 0): ?> <span class="nav-badge" style="position:static; display:inline-flex; margin-left:6px; vertical-align:middle;"><?= $pendingKotoba ?></span><?php endif; ?></span>
          </a>
          <a href="tata_bahasa.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#0f7490,#0c5a6e);">
            <span class="bab-btn-icon">文法</span>
            <span>Tata Bahasa</span>
          </a>
          <a href="membaca.php" class="bab-btn bab-btn-primary" style="background:linear-gradient(135deg,#b5651d,#8a4a13);">
            <span class="bab-btn-icon">読書</span>
            <span>Membaca</span>
          </a>
        <?php endif; ?>
        <button class="bab-btn bab-btn-logout" onclick="handleLogout()">
          <span class="bab-btn-icon">🚪</span>
          <span style="color: brown">Keluar 出る</span>
        </button>
      </div>
    </div>

  <!-- ── CLUB PROFILE SECTION (visible to all) ── -->
    <div class="club-section fade-up delay-3">
      <div class="club-section-header">
        <span style="font-size:1.2rem;">🌸</span>
        <div class="club-section-title">Profil Club 桜 Sakura</div>
        <div class="club-section-divider"></div>
      </div>

      <div class="club-profiles-grid">

        <!-- Ketua Club -->
        <div class="club-profile-card" id="clubCardKetua">
          <?php if ($isAdmin): ?>
          <button class="club-edit-btn" onclick="openClubModal('ketua')" title="Edit Profil Ketua">✏️</button>
          <?php endif; ?>
          <div class="club-profile-photo-wrap">
            <?php if (!empty($clubProfiles['ketua']['photo'])): ?>
              <img
                src="<?= htmlspecialchars($clubProfiles['ketua']['photo']) ?>"
                alt="Foto Ketua Club"
                class="club-profile-photo"
                id="clubPhotoKetua"
                onerror="this.style.display='none'; document.getElementById('clubPlaceholderKetua').style.display='flex';"
              >
              <div class="club-profile-photo-placeholder" id="clubPlaceholderKetua" style="display:none;">
                <?= strtoupper(mb_substr($clubProfiles['ketua']['name'] ?: 'K', 0, 1)) ?>
              </div>
            <?php else: ?>
              <div class="club-profile-photo-placeholder" id="clubPlaceholderKetua">
                <?= strtoupper(mb_substr($clubProfiles['ketua']['name'] ?: '🌸', 0, 1)) ?>
              </div>
            <?php endif; ?>
            <div class="club-profile-badge">⛩</div>
          </div>
          <div class="club-profile-name" id="clubNameKetua">
            <?= !empty($clubProfiles['ketua']['name']) ? htmlspecialchars($clubProfiles['ketua']['name']) : '— Nama Ketua —' ?>
          </div>
          <div class="club-profile-role">🌸 Ketua Club</div>
        </div>

        <!-- Guru Klub -->
        <div class="club-profile-card guru-card" id="clubCardGuru">
          <?php if ($isAdmin): ?>
          <button class="club-edit-btn" onclick="openClubModal('guru')" title="Edit Profil Guru" style="color:var(--bamboo,#4a7c59); border-color:rgba(74,124,89,.3); background:rgba(74,124,89,.1);">✏️</button>
          <?php endif; ?>
          <div class="club-profile-photo-wrap">
            <?php if (!empty($clubProfiles['guru']['photo'])): ?>
              <img
                src="<?= htmlspecialchars($clubProfiles['guru']['photo']) ?>"
                alt="Foto Guru Klub"
                class="club-profile-photo"
                id="clubPhotoGuru"
                onerror="this.style.display='none'; document.getElementById('clubPlaceholderGuru').style.display='flex';"
              >
              <div class="club-profile-photo-placeholder guru-card" id="clubPlaceholderGuru" style="display:none;">
                <?= strtoupper(mb_substr($clubProfiles['guru']['name'] ?: 'G', 0, 1)) ?>
              </div>
            <?php else: ?>
              <div class="club-profile-photo-placeholder guru-card" id="clubPlaceholderGuru">
                <?= strtoupper(mb_substr($clubProfiles['guru']['name'] ?: '先', 0, 1)) ?>
              </div>
            <?php endif; ?>
            <div class="club-profile-badge" style="background:var(--bamboo,#4a7c59);">先</div>
          </div>
          <div class="club-profile-name" id="clubNameGuru">
            <?= !empty($clubProfiles['guru']['name']) ? htmlspecialchars($clubProfiles['guru']['name']) : '— Nama Guru —' ?>
          </div>
          <div class="club-profile-role">🍃 Guru Klub</div>
        </div>

      </div><!-- /club-profiles-grid -->
    </div><!-- /club-section -->
  </main>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script>
    // ── Optimisasi animasi fade-up dengan IntersectionObserver ──
    // Lebih ringan dari scroll event karena berjalan di luar main thread
    (function() {
      if (!('IntersectionObserver' in window)) return;
      var io = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) {
          if (e.isIntersecting) {
            e.target.classList.add('is-visible');
            io.unobserve(e.target); // berhenti observe setelah visible
          }
        });
      }, { threshold: 0.08, rootMargin: '0px 0px -40px 0px' });

      // Observe semua elemen fade-up setelah DOM siap
      document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.fade-up').forEach(function(el) {
          io.observe(el);
        });
      });
    })();
  </script>
  <!-- petals dimuat lazy setelah halaman idle agar tidak mengganggu scroll -->
  <script>
    (function() {
      // Gunakan requestIdleCallback jika tersedia, fallback ke setTimeout
      function loadPetals() {
        var s = document.createElement('script');
        s.src = 'js/petals.js';
        s.defer = true;
        document.body.appendChild(s);
      }
      if ('requestIdleCallback' in window) {
        requestIdleCallback(loadPetals, { timeout: 3000 });
      } else {
        setTimeout(loadPetals, 2000);
      }
    })();
  </script>
  <?php if (!$isAdmin): ?>
  <!-- ── ANNOUNCEMENT BUBBLES (User) ── -->
  <div id="annBubbleContainer"></div>

  <script>
    let announcements = <?= json_encode(array_values($newAnnouncements), JSON_UNESCAPED_UNICODE) ?>;
    let annIndex = 0;
    let annDismissed = [];
    let annBubbleOpen = false;

    function timeAgo(dateStr) {
      const d = new Date(dateStr.replace(' ', 'T'));
      const diff = Math.floor((Date.now() - d.getTime()) / 1000);
      if (diff < 60)   return 'Baru saja';
      if (diff < 3600) return Math.floor(diff/60) + ' menit lalu';
      if (diff < 86400) return Math.floor(diff/3600) + ' jam lalu';
      return Math.floor(diff/86400) + ' hari lalu';
    }

    function showBubble(idx) {
      if (idx >= announcements.length) { annBubbleOpen = false; return; }
      annBubbleOpen = true;
      const ann = announcements[idx];
      const container = document.getElementById('annBubbleContainer');
      const total = announcements.length;

      // hapus bubble sebelumnya
      const old = document.getElementById('annBubble');
      if (old) old.remove();

      const bubble = document.createElement('div');
      bubble.id = 'annBubble';
      bubble.className = 'ann-bubble';
      bubble.innerHTML = `
        <div class="ann-bubble-header">
          <div class="ann-bubble-avatar">⛩</div>
          <div class="ann-bubble-meta">
            <div class="ann-bubble-sender">Admin • Pengumuman</div>
            <div class="ann-bubble-time">${timeAgo(ann.created_at)}</div>
          </div>
          <button class="ann-bubble-close" onclick="closeBubble()" title="Tutup">✕</button>
        </div>
        <div class="ann-bubble-msg">${escHtml(ann.message)}</div>
        <div class="ann-bubble-footer">
          <span class="ann-bubble-counter">${idx+1} dari ${total} pengumuman</span>
          ${idx+1 < total
            ? `<button class="ann-bubble-btn" onclick="nextBubble()">Berikutnya →</button>`
            : `<button class="ann-bubble-btn" onclick="closeBubble()">Tutup ✓</button>`
          }
        </div>
      `;
      container.appendChild(bubble);

      // Mark as read (AJAX)
      markRead(ann.id);
    }

    function escHtml(s) {
      return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function closeBubble() {
      const b = document.getElementById('annBubble');
      if (b) {
        b.style.animation = 'annSlideIn .25s cubic-bezier(.22,1,.36,1) reverse both';
        setTimeout(() => b.remove(), 240);
      }
      annBubbleOpen = false;
      // Update badge
      updateBadge(0);
    }

    function nextBubble() {
      annIndex++;
      showBubble(annIndex);
    }

    function openAnnBubble() {
      annIndex = 0;
      showBubble(annIndex);
    }

    function markRead(id) {
      const fd = new FormData();
      fd.append('action', 'mark_announcement_read');
      fd.append('ann_id', id);
      fetch('beranda.php', { method: 'POST', body: fd })
        .catch(() => {}); // silent fail ok
    }

    function updateBadge(count) {
      const badge = document.getElementById('annBadge');
      if (!badge) return;
      if (count > 0) {
        badge.textContent = count;
        badge.style.display = '';
      } else {
        badge.style.display = 'none';
      }
    }

    // ── Polling realtime pengumuman baru ──
    let lastAnnIds = new Set(announcements.map(a => a.id));

    async function pollAnnouncements() {
      try {
        const res = await fetch('beranda.php?action=poll_announcements', {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (!data.ok) return;

        const fresh = data.announcements || [];
        const freshIds = new Set(fresh.map(a => a.id));

        // Cek apakah ada pengumuman baru yang belum pernah ditampilkan
        const hasNew = fresh.some(a => !lastAnnIds.has(a.id));

        announcements = fresh;
        updateBadge(data.unread || 0);

        if (hasNew && !annBubbleOpen) {
          annIndex = 0;
          showBubble(0);
        }

        lastAnnIds = freshIds;
      } catch (e) { /* silent */ }
    }

    // Auto tampil bubble saat halaman dimuat (delay sedikit agar smooth)
    window.addEventListener('DOMContentLoaded', () => {
      if (announcements.length > 0) {
        setTimeout(() => showBubble(0), 900);
      }
      // Polling tiap 15 detik — berhenti otomatis saat tab tidak aktif
      let pollTimer = setInterval(pollAnnouncements, 15000);
      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          clearInterval(pollTimer);
        } else {
          pollTimer = setInterval(pollAnnouncements, 15000);
        }
      });
    });
  </script>
  <?php endif; ?>

  <script>
    // Logout handler
    function handleLogout() {
      const fd = new FormData();
      fd.append('action', 'logout');
      fetch('auth.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          if (data.redirect) window.location.href = data.redirect;
        });
    }

    // Char counter pengumuman
    function updateCharCount(el) {
      const cnt = document.getElementById('annCharCount');
      if (cnt) cnt.textContent = el.value.length;
    }
  </script>

  <?php if ($showKanaOnboarding): ?>
  <script>
    // ═══════════════════════════════════════════════════════
    //  ONBOARDING: Apakah paham Hiragana/Katakana?
    // ═══════════════════════════════════════════════════════
    const onbNeeds = {
      hiragana: <?= json_encode($hiraganaStatus === 'belum_dijawab') ?>,
      katakana: <?= json_encode($katakanaStatus === 'belum_dijawab') ?>,
    };
    let onbSteps = [];
    if (onbNeeds.hiragana) onbSteps.push('hiragana');
    if (onbNeeds.katakana) onbSteps.push('katakana');
    let onbCurrentIdx = 0;

    function onbShowStep(idx) {
      document.querySelectorAll('.kana-onb-step').forEach(el => el.classList.remove('active'));
      const stepName = onbSteps[idx];
      if (!stepName) { onbCloseModal(); return; }
      document.getElementById('onbStep' + capitalizeOnb(stepName)).classList.add('active');

      // Update progress dots (hanya tampil kalau ada 2 langkah)
      const dotsWrap = document.querySelector('.kana-onb-progress');
      if (onbSteps.length <= 1) {
        dotsWrap.style.display = 'none';
      } else {
        dotsWrap.style.display = 'flex';
        document.getElementById('onbDot1').classList.toggle('active', idx === 0);
        document.getElementById('onbDot2').classList.toggle('active', idx === 1);
      }
    }

    function capitalizeOnb(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    async function onbAnswer(jenis, paham) {
      // FIX BUG: dulu kalau jawabannya "belum paham", TIDAK ADA request ke server
      // sama sekali — akibatnya server tidak pernah tahu pertanyaan ini sudah
      // ditanyakan, dan modal ini akan muncul lagi di setiap kunjungan berikutnya.
      // Sekarang kita selalu kirim ke server (jawaban apapun) supaya backend bisa
      // mencatat "modal ini sudah pernah dijawab" lewat kana_onboarding_asked_at.
      try {
        await fetch('beranda.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'action=kana_set_paham&jenis=' + encodeURIComponent(jenis) + '&paham=' + (paham ? '1' : '0')
        });
      } catch (e) { /* lanjut walau gagal, akan tersinkron lain kali */ }
      // Catatan: jika "belum paham", status hiragana/katakana TETAP 'belum_dijawab'
      // (supaya tombol ujian pemahaman tetap muncul di kana.php) — hanya modal
      // onboarding ini saja yang ditandai sudah pernah ditampilkan.

      onbCurrentIdx++;
      if (onbCurrentIdx >= onbSteps.length) {
        onbCloseModal();
        if (!paham) {
          // Arahkan ke halaman Kana agar user bisa langsung memilih ujian pemahaman
          setTimeout(() => { window.location.href = 'kana.php?onboarding=1'; }, 250);
        }
      } else {
        onbShowStep(onbCurrentIdx);
      }
    }

    function onbCloseModal() {
      document.getElementById('kanaOnbOverlay').classList.remove('open');
    }

    // Tampilkan modal setelah halaman siap
    document.addEventListener('DOMContentLoaded', function() {
      if (onbSteps.length > 0) {
        onbShowStep(0);
        setTimeout(() => {
          document.getElementById('kanaOnbOverlay').classList.add('open');
        }, 400);
      }
    });
  </script>
  <?php endif; ?>
  <?php if ($isAdmin): ?>
  <script>
    // ── Realtime update riwayat pengumuman (Admin) ──
    function renderAnnHistory(list) {
      const wrap = document.getElementById('annHistoryList');
      if (!wrap) return;
      if (!list || list.length === 0) {
        wrap.innerHTML = '<div style="font-size:.85rem;color:var(--mist,#888);">Belum ada pengumuman.</div>';
        return;
      }
      wrap.innerHTML = list.map(ann => `
        <div class="ann-history-item">
          <div class="ann-history-dot"></div>
          <div style="flex:1; min-width:0;">
            <div class="ann-history-msg">${escHtmlAdmin(ann.message)}</div>
            <div class="ann-history-time">
              📅 ${formatDateAdmin(ann.created_at)}
              &nbsp;· oleh ${escHtmlAdmin(ann.sender_name)}
            </div>
          </div>
          <form method="POST" action="beranda.php" style="margin:0;" onsubmit="return submitDeleteAnn(event, ${ann.id})">
            <input type="hidden" name="action" value="delete_announcement">
            <input type="hidden" name="ann_id" value="${ann.id}">
            <button type="submit" class="ann-del-btn" title="Hapus pengumuman" onclick="return confirm('Hapus pengumuman ini?')">🗑</button>
          </form>
        </div>
      `).join('');
    }

    function escHtmlAdmin(s) {
      return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function formatDateAdmin(dateStr) {
      const d = new Date(dateStr.replace(' ', 'T'));
      const pad = n => String(n).padStart(2, '0');
      const months = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
      return `${pad(d.getDate())} ${months[d.getMonth()]} ${d.getFullYear()}, ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    }

    async function pollAnnHistory() {
      try {
        const res = await fetch('beranda.php?action=poll_announcements', {
          headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.ok) renderAnnHistory(data.announcements || []);
      } catch (e) { /* silent */ }
    }

    async function submitDeleteAnn(ev, annId) {
      ev.preventDefault();
      const fd = new FormData();
      fd.append('action', 'delete_announcement');
      fd.append('ann_id', annId);
      try {
        await fetch('beranda.php', { method: 'POST', body: fd });
        await pollAnnHistory();
      } catch (e) { /* silent */ }
      return false;
    }

    // Kirim pengumuman via AJAX agar tidak reload halaman
    document.addEventListener('DOMContentLoaded', () => {
      const sendForm = document.querySelector('.ann-form-card form[action="beranda.php"]');
      if (sendForm) {
        sendForm.addEventListener('submit', async (ev) => {
          ev.preventDefault();
          const fd = new FormData(sendForm);
          try {
            const res = await fetch('beranda.php', { method: 'POST', body: fd });
            await res.text(); // abaikan HTML hasil, kita refresh via polling
            sendForm.reset();
            updateCharCount(document.getElementById('annTextarea'));
            await pollAnnHistory();
          } catch (e) { /* silent */ }
        });
      }
      // Polling tiap 15 detik — berhenti saat tab tidak aktif
      let adminPollTimer = setInterval(pollAnnHistory, 15000);
      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          clearInterval(adminPollTimer);
        } else {
          adminPollTimer = setInterval(pollAnnHistory, 15000);
        }
      });
    });
  </script>
  <?php endif; ?>

  <!-- ── MODAL ONBOARDING: Paham Hiragana/Katakana? (untuk USER, bukan admin) ── -->
  <?php if ($showKanaOnboarding): ?>
  <div id="kanaOnbOverlay" class="kana-onb-overlay">
    <div class="kana-onb-modal">

      <div class="kana-onb-progress">
        <span class="kana-onb-dot" id="onbDot1"></span>
        <span class="kana-onb-dot" id="onbDot2"></span>
      </div>

      <!-- Step 1: Hiragana -->
      <div class="kana-onb-step" id="onbStepHiragana">
        <span class="kana-onb-kanji">ひ</span>
        <div class="kana-onb-title">Apakah kamu sudah paham Hiragana?</div>
        <p class="kana-onb-sub">
          Jawaban ini membantu kami menyesuaikan materi belajarmu.
          Kamu tetap bisa membuktikannya lewat ujian pemahaman nanti di halaman Kana.
        </p>
        <div class="kana-onb-actions">
          <button class="kana-onb-btn kana-onb-btn-yes" onclick="onbAnswer('hiragana', true)">✅ Ya, Saya Sudah Paham</button>
          <button class="kana-onb-btn kana-onb-btn-no" onclick="onbAnswer('hiragana', false)">📖 Belum, Saya Akan Belajar</button>
        </div>
      </div>

      <!-- Step 2: Katakana -->
      <div class="kana-onb-step" id="onbStepKatakana">
        <span class="kana-onb-kanji">カ</span>
        <div class="kana-onb-title">Apakah kamu sudah paham dan hafal Katakana?</div>
        <p class="kana-onb-sub">
          Sama seperti Hiragana, kamu bisa membuktikan jawabanmu lewat ujian pemahaman kapan saja di halaman Kana.
        </p>
        <div class="kana-onb-actions">
          <button class="kana-onb-btn kana-onb-btn-yes" onclick="onbAnswer('katakana', true)">✅ Ya, Saya Sudah Paham</button>
          <button class="kana-onb-btn kana-onb-btn-no" onclick="onbAnswer('katakana', false)">📖 Belum, Saya Akan Belajar</button>
        </div>
      </div>

    </div>
  </div>
  <?php endif; ?>

  <?php if ($isAdmin): ?>
  <!-- ── CLUB PROFILE EDIT MODAL (Admin) ── -->
  <div id="clubModalOverlay" class="club-modal-overlay" style="display:none;" onclick="closeClubModalOnBg(event)">
    <div class="club-modal">
      <button class="club-modal-close" onclick="closeClubModal()">✕</button>
      <div class="club-modal-title" id="clubModalTitle">✏️ Edit Profil</div>

      <!-- Preview Foto -->
      <div style="text-align:center; margin-bottom:10px;">
        <div style="position:relative; display:inline-block; cursor:pointer;" onclick="document.getElementById('clubFileInput').click()">
          <img id="clubModalPhotoPreview" class="club-modal-photo-preview"
            src="" alt="Preview Foto"
            onerror="this.style.display='none'; document.getElementById('clubModalPlaceholder').style.display='flex';"
            style="display:none; cursor:pointer;"
          >
          <div id="clubModalPlaceholder" class="club-profile-photo-placeholder" style="margin:0 auto; display:flex; cursor:pointer;">?</div>
          <div style="
            position:absolute; inset:0; border-radius:50%;
            background:rgba(0,0,0,.35);
            display:flex; align-items:center; justify-content:center;
            opacity:0; transition:opacity .18s;
            font-size:1.2rem; color:#fff; font-weight:800;
          " id="clubPhotoOverlay">📷</div>
        </div>
        <div style="font-size:.75rem; color:var(--mist,#888); margin-top:8px;">Klik foto untuk ganti gambar</div>
      </div>

      <!-- Hidden file input -->
      <input type="file" id="clubFileInput" accept="image/*" style="display:none;" onchange="handleClubFileUpload(this)">

      <label class="club-modal-label">Nama</label>
      <input type="text" id="clubModalName" class="club-modal-input" placeholder="Masukkan nama..." maxlength="80">

      <!-- Hidden: simpan base64 -->
      <input type="hidden" id="clubModalPhoto" value="">

      <button class="club-modal-save-btn" onclick="saveClubProfile()" id="clubModalSaveBtn">💾 Simpan Profil</button>
    </div>
  </div>

  <script>
    let _clubModalType = '';

    // Hover overlay pada foto preview
    document.addEventListener('DOMContentLoaded', function() {
      const photoWrap = document.querySelector('#clubModalPhotoPreview')?.parentElement;
      const overlay   = document.getElementById('clubPhotoOverlay');
      if (photoWrap && overlay) {
        photoWrap.addEventListener('mouseenter', () => overlay.style.opacity = '1');
        photoWrap.addEventListener('mouseleave', () => overlay.style.opacity = '0');
      }
    });

    function openClubModal(type) {
      _clubModalType = type;
      const isKetua = type === 'ketua';
      document.getElementById('clubModalTitle').textContent = isKetua ? '✏️ Edit Profil Ketua Club' : '✏️ Edit Profil Guru Klub';

      // Reset file input
      document.getElementById('clubFileInput').value = '';
      document.getElementById('clubModalPhoto').value = '';

      // Nama
      const currentName = document.getElementById(isKetua ? 'clubNameKetua' : 'clubNameGuru').textContent.trim().replace(/^—\s*|\s*—$/g, '').trim();
      document.getElementById('clubModalName').value = currentName.includes('Nama') ? '' : currentName;

      // Foto: tampilkan foto kartu saat ini
      const currentImgEl = document.getElementById(isKetua ? 'clubPhotoKetua' : 'clubPhotoGuru');
      const preview = document.getElementById('clubModalPhotoPreview');
      const ph      = document.getElementById('clubModalPlaceholder');
      if (currentImgEl && currentImgEl.style.display !== 'none' && currentImgEl.src && !currentImgEl.src.includes('beranda.php')) {
        preview.src = currentImgEl.src;
        preview.style.display = 'block';
        ph.style.display = 'none';
        // Simpan foto lama ke hidden input agar tetap tersimpan jika tidak diubah
        document.getElementById('clubModalPhoto').value = currentImgEl.src;
      } else {
        preview.style.display = 'none';
        preview.src = '';
        ph.style.display = 'flex';
        const n = document.getElementById('clubModalName').value;
        ph.textContent = n ? n[0].toUpperCase() : '?';
      }

      document.getElementById('clubModalOverlay').style.display = 'flex';
      document.getElementById('clubModalName').focus();
    }

    function closeClubModal() {
      document.getElementById('clubModalOverlay').style.display = 'none';
    }

    function closeClubModalOnBg(e) {
      if (e.target === document.getElementById('clubModalOverlay')) closeClubModal();
    }

    function handleClubFileUpload(input) {
      const file = input.files[0];
      if (!file) return;

      if (file.size > 3 * 1024 * 1024) {
        alert('Ukuran foto maksimal 3MB. Pilih foto yang lebih kecil.');
        input.value = '';
        return;
      }

      const btn = document.getElementById('clubModalSaveBtn');
      btn.disabled = true;
      btn.textContent = '⏳ Memproses foto...';

      const reader = new FileReader();
      reader.onload = function(ev) {
        const img = new Image();
        img.onload = function() {
          // Resize ke max 400x400
          const MAX = 400;
          let w = img.width, h = img.height;
          if (w > MAX || h > MAX) {
            if (w > h) { h = Math.round(h * MAX / w); w = MAX; }
            else       { w = Math.round(w * MAX / h); h = MAX; }
          }
          const canvas = document.createElement('canvas');
          canvas.width = w; canvas.height = h;
          canvas.getContext('2d').drawImage(img, 0, 0, w, h);
          const compressed = canvas.toDataURL('image/jpeg', 0.82);

          document.getElementById('clubModalPhoto').value = compressed;

          const preview = document.getElementById('clubModalPhotoPreview');
          const ph      = document.getElementById('clubModalPlaceholder');
          preview.src   = compressed;
          preview.style.display = 'block';
          ph.style.display      = 'none';

          btn.disabled = false;
          btn.textContent = '💾 Simpan Profil';
        };
        img.src = ev.target.result;
      };
      reader.readAsDataURL(file);
    }

    document.addEventListener('DOMContentLoaded', function() {
      document.getElementById('clubModalName').addEventListener('input', function() {
        const ph      = document.getElementById('clubModalPlaceholder');
        const preview = document.getElementById('clubModalPhotoPreview');
        if (preview.style.display === 'none' || !preview.src) {
          ph.textContent = this.value ? this.value[0].toUpperCase() : '?';
        }
      });
    });

    async function saveClubProfile() {
      const btn   = document.getElementById('clubModalSaveBtn');
      const name  = document.getElementById('clubModalName').value.trim();
      const photo = document.getElementById('clubModalPhoto').value.trim();
      if (!name) { alert('Nama tidak boleh kosong!'); return; }

      btn.disabled = true;
      btn.textContent = '⏳ Menyimpan...';

      const fd = new FormData();
      fd.append('action', 'save_club_profile');
      fd.append('profile_type', _clubModalType);
      fd.append('profile_name', name);
      fd.append('profile_photo', photo);

      try {
        const res  = await fetch('beranda.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.ok) {
          const isKetua       = _clubModalType === 'ketua';
          const cardId        = isKetua ? 'clubPhotoKetua'      : 'clubPhotoGuru';
          const placeholderId = isKetua ? 'clubPlaceholderKetua': 'clubPlaceholderGuru';

          document.getElementById(isKetua ? 'clubNameKetua' : 'clubNameGuru').textContent = name;

          let imgEl = document.getElementById(cardId);
          let phEl  = document.getElementById(placeholderId);

          if (photo) {
            if (!imgEl) {
              const wrap = document.querySelector(isKetua ? '#clubCardKetua .club-profile-photo-wrap' : '#clubCardGuru .club-profile-photo-wrap');
              imgEl = document.createElement('img');
              imgEl.id        = cardId;
              imgEl.className = 'club-profile-photo';
              imgEl.alt       = isKetua ? 'Foto Ketua Club' : 'Foto Guru Klub';
              imgEl.onerror   = function() { this.style.display='none'; phEl && (phEl.style.display='flex'); };
              wrap.insertBefore(imgEl, wrap.firstChild);
            }
            imgEl.src = photo;
            imgEl.style.display = 'block';
            if (phEl) phEl.style.display = 'none';
          } else {
            if (imgEl) imgEl.style.display = 'none';
            if (phEl)  { phEl.style.display = 'flex'; phEl.textContent = name[0].toUpperCase(); }
          }

          closeClubModal();
          const card = document.getElementById(isKetua ? 'clubCardKetua' : 'clubCardGuru');
          card.style.boxShadow = '0 0 0 3px rgba(74,124,89,.5)';
          setTimeout(() => card.style.boxShadow = '', 1400);
        } else {
          alert('Gagal menyimpan: ' + (data.error || 'Error'));
        }
      } catch (e) {
        alert('Terjadi kesalahan jaringan.');
      }

      btn.disabled = false;
      btn.textContent = '💾 Simpan Profil';
    }

    document.addEventListener('keydown', function(e) {
      const overlay = document.getElementById('clubModalOverlay');
      if (!overlay || overlay.style.display === 'none') return;
      if (e.key === 'Escape') closeClubModal();
      if (e.key === 'Enter' && document.activeElement.id === 'clubModalName') saveClubProfile();
    });
  </script>
  <?php endif; ?>
</body>
</html>
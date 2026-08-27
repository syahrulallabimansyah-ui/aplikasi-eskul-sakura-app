<?php
require_once 'config.php';

requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header('Location: beranda.php');
    exit;
}

$db = getDB();
$message = '';
$messageType = '';

// ============================================================
// AJAX / POST HANDLERS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ---------- Buat quiz baru ----------
    if ($action === 'create_quiz') {
        $judul = trim($_POST['judul'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $duration = max(0, (int)($_POST['duration_minutes'] ?? 15));
        $token = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));

        if ($judul === '') {
            $message = 'Judul quiz tidak boleh kosong.';
            $messageType = 'error';
        } else {
            $stmt = $db->prepare("INSERT INTO kotoba_quiz (judul, deskripsi, token, duration_minutes, status, created_by) VALUES (?,?,?,?, 'draft', ?)");
            $stmt->execute([$judul, $deskripsi, $token, $duration, $user['id']]);
            $newId = $db->lastInsertId();
            header('Location: kotoba_admin.php?quiz=' . $newId . '&created=1');
            exit;
        }
    }

    // ---------- Update info quiz ----------
    if ($action === 'update_quiz') {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $judul = trim($_POST['judul'] ?? '');
        $deskripsi = trim($_POST['deskripsi'] ?? '');
        $duration = max(0, (int)($_POST['duration_minutes'] ?? 15));
        $status = in_array($_POST['status'] ?? '', ['draft','published','closed']) ? $_POST['status'] : 'draft';

        $stmt = $db->prepare("UPDATE kotoba_quiz SET judul=?, deskripsi=?, duration_minutes=?, status=? WHERE id=?");
        $stmt->execute([$judul, $deskripsi, $duration, $status, $quizId]);

        header('Location: kotoba_admin.php?quiz=' . $quizId . '&updated=1');
        exit;
    }

    // ---------- Hapus quiz ----------
    if ($action === 'delete_quiz') {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM kotoba_quiz WHERE id=?");
        $stmt->execute([$quizId]);
        header('Location: kotoba_admin.php?deleted=1');
        exit;
    }

    // ---------- Tambah / Update soal manual ----------
    if ($action === 'save_soal') {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $soalId = (int)($_POST['soal_id'] ?? 0);
        $kotoba = trim($_POST['kotoba'] ?? '');
        $caraBaca = trim($_POST['cara_baca'] ?? '');
        $arti = trim($_POST['arti'] ?? '');
        $optA = trim($_POST['option_a'] ?? '');
        $optB = trim($_POST['option_b'] ?? '');
        $optC = trim($_POST['option_c'] ?? '');
        $optD = trim($_POST['option_d'] ?? '');
        $correct = in_array($_POST['correct_option'] ?? '', ['a','b','c','d']) ? $_POST['correct_option'] : 'a';

        if ($kotoba === '' || $arti === '' || $optA === '' || $optB === '' || $optC === '' || $optD === '') {
            $message = 'Semua field soal wajib diisi.';
            $messageType = 'error';
        } else {
            if ($soalId > 0) {
                $stmt = $db->prepare("UPDATE kotoba_quiz_soal SET kotoba=?, cara_baca=?, arti=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=? WHERE id=? AND quiz_id=?");
                $stmt->execute([$kotoba, $caraBaca, $arti, $optA, $optB, $optC, $optD, $correct, $soalId, $quizId]);
            } else {
                $maxOrder = (int)$db->prepare("SELECT COALESCE(MAX(question_order),0) FROM kotoba_quiz_soal WHERE quiz_id=?") ;
                $stmtOrder = $db->prepare("SELECT COALESCE(MAX(question_order),0) FROM kotoba_quiz_soal WHERE quiz_id=?");
                $stmtOrder->execute([$quizId]);
                $nextOrder = (int)$stmtOrder->fetchColumn() + 1;

                $stmt = $db->prepare("INSERT INTO kotoba_quiz_soal (quiz_id, kotoba, cara_baca, arti, option_a, option_b, option_c, option_d, correct_option, question_order) VALUES (?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([$quizId, $kotoba, $caraBaca, $arti, $optA, $optB, $optC, $optD, $correct, $nextOrder]);
            }
        }

        header('Location: kotoba_admin.php?quiz=' . $quizId . '&soal_saved=1');
        exit;
    }

    // ---------- Hapus soal ----------
    if ($action === 'delete_soal') {
        $quizId = (int)($_POST['quiz_id'] ?? 0);
        $soalId = (int)($_POST['soal_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM kotoba_quiz_soal WHERE id=? AND quiz_id=?");
        $stmt->execute([$soalId, $quizId]);
        header('Location: kotoba_admin.php?quiz=' . $quizId . '&soal_deleted=1');
        exit;
    }

    // ---------- Import dari Excel ----------
    if ($action === 'import_excel') {
        $quizId = (int)($_POST['quiz_id'] ?? 0);

        if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
            header('Location: kotoba_admin.php?quiz=' . $quizId . '&import_error=' . urlencode('File tidak ditemukan atau gagal diunggah.'));
            exit;
        }

        $tmpPath = $_FILES['excel_file']['tmp_name'];
        $originalName = $_FILES['excel_file']['name'];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['csv'])) {
            header('Location: kotoba_admin.php?quiz=' . $quizId . '&import_error=' . urlencode('Format file harus .csv (export dari Excel: File > Save As > CSV)'));
            exit;
        }

        try {
            $rows = [];
            $handle = fopen($tmpPath, 'r');
            if ($handle === false) {
                throw new Exception('Tidak dapat membuka file.');
            }

            // Deteksi BOM UTF-8 dan lewati jika ada
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            // Deteksi delimiter (koma atau titik koma) dari baris pertama
            $firstLine = fgets($handle);
            $delimiter = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';
            rewind($handle);
            if ($bom === "\xEF\xBB\xBF") {
                fseek($handle, 3);
            }

            while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
                // Lewati baris kosong (semua sel kosong/null)
                $nonEmpty = array_filter($data, fn($v) => trim((string)$v) !== '');
                if (empty($nonEmpty)) continue;
                $rows[] = $data;
            }
            fclose($handle);

            // Format kolom: A=kotoba, B=cara_baca, C=arti, D=option_a, E=option_b, F=option_c, G=option_d, H=correct_option
            $stmtOrder = $db->prepare("SELECT COALESCE(MAX(question_order),0) FROM kotoba_quiz_soal WHERE quiz_id=?");
            $stmtOrder->execute([$quizId]);
            $order = (int)$stmtOrder->fetchColumn();

            $insertStmt = $db->prepare("INSERT INTO kotoba_quiz_soal (quiz_id, kotoba, cara_baca, arti, option_a, option_b, option_c, option_d, correct_option, question_order) VALUES (?,?,?,?,?,?,?,?,?,?)");

            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($rows as $i => $row) {
                // Lewati baris pertama jika berisi header (deteksi sederhana)
                if ($i === 0) {
                    $firstCell = trim((string)($row[0] ?? ''));
                    if (strcasecmp($firstCell, 'kotoba') === 0 || strcasecmp($firstCell, 'kata') === 0) {
                        continue;
                    }
                }

                $kotoba   = trim((string)($row[0] ?? ''));
                $caraBaca = trim((string)($row[1] ?? ''));
                $arti     = trim((string)($row[2] ?? ''));
                $optA     = trim((string)($row[3] ?? ''));
                $optB     = trim((string)($row[4] ?? ''));
                $optC     = trim((string)($row[5] ?? ''));
                $optD     = trim((string)($row[6] ?? ''));
                $correct  = strtolower(trim((string)($row[7] ?? '')));

                // Lewati baris kosong total
                if ($kotoba === '' && $arti === '' && $optA === '') {
                    continue;
                }

                if ($kotoba === '' || $arti === '' || $optA === '' || $optB === '' || $optC === '' || $optD === '') {
                    $skipped++;
                    $errors[] = "Baris " . ($i + 1) . ": data tidak lengkap, dilewati.";
                    continue;
                }

                if (!in_array($correct, ['a','b','c','d'])) {
                    $skipped++;
                    $errors[] = "Baris " . ($i + 1) . ": kolom jawaban benar harus berisi a/b/c/d, dilewati.";
                    continue;
                }

                $order++;
                $insertStmt->execute([$quizId, $kotoba, $caraBaca, $arti, $optA, $optB, $optC, $optD, $correct, $order]);
                $imported++;
            }

            $params = [
                'quiz' => $quizId,
                'imported' => $imported,
                'skipped' => $skipped,
            ];
            if (!empty($errors)) {
                $_SESSION['kotoba_import_errors'] = array_slice($errors, 0, 20);
            }
            header('Location: kotoba_admin.php?' . http_build_query($params) . '#soal-section');
            exit;

        } catch (Exception $e) {
            header('Location: kotoba_admin.php?quiz=' . $quizId . '&import_error=' . urlencode('Gagal membaca file: ' . $e->getMessage()));
            exit;
        }
    }
}

// ============================================================
// DATA UNTUK TAMPILAN
// ============================================================
$quizList = $db->query("
    SELECT q.*, 
           (SELECT COUNT(*) FROM kotoba_quiz_soal s WHERE s.quiz_id = q.id) AS total_soal,
           (SELECT COUNT(*) FROM kotoba_quiz_attempts a WHERE a.quiz_id = q.id AND a.status='finished') AS total_attempts
    FROM kotoba_quiz q
    ORDER BY q.created_at DESC
")->fetchAll();

$activeQuiz = null;
$soalList = [];
$selectedQuizId = (int)($_GET['quiz'] ?? 0);

if ($selectedQuizId > 0) {
    $stmt = $db->prepare("SELECT * FROM kotoba_quiz WHERE id = ?");
    $stmt->execute([$selectedQuizId]);
    $activeQuiz = $stmt->fetch();

    if ($activeQuiz) {
        $stmt = $db->prepare("SELECT * FROM kotoba_quiz_soal WHERE quiz_id = ? ORDER BY question_order ASC, id ASC");
        $stmt->execute([$selectedQuizId]);
        $soalList = $stmt->fetchAll();
    }
}

$importErrors = $_SESSION['kotoba_import_errors'] ?? [];
unset($_SESSION['kotoba_import_errors']);

$baseUrl = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Kelola Quiz Kotoba</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* ======================================================
       LAYOUT UTAMA
    ====================================================== */
    .kotoba-layout {
      display: grid;
      grid-template-columns: 300px 1fr;
      gap: 22px;
      align-items: start;
    }
    @media (max-width: 960px) {
      .kotoba-layout { grid-template-columns: 1fr; }
    }

    /* ======================================================
       NAVIGASI KEMBALI
    ====================================================== */
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border-radius: 20px;
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      color: var(--text-main);
      font-size: .82rem;
      font-weight: 700;
      text-decoration: none;
      transition: border-color .15s, color .15s, transform .15s;
      white-space: nowrap;
    }
    .back-btn:hover {
      border-color: var(--torii);
      color: var(--torii);
      transform: translateY(-1px);
    }
    @media (max-width: 480px) {
      .back-btn .ai-btn-label { display: none; }
      .back-btn { padding: 7px 10px; }
    }

    /* ======================================================
       ALERT / NOTIFIKASI
    ====================================================== */
    .alert-box {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      padding: 12px 16px;
      border-radius: 12px;
      margin-bottom: 16px;
      font-size: .88rem;
      line-height: 1.5;
      border: 1px solid transparent;
    }
    .alert-box .alert-icon { font-size: 1.1rem; flex-shrink: 0; margin-top: 1px; }
    .alert-success { background: rgba(74,124,89,.10); border-color: rgba(74,124,89,.35); color: var(--bamboo); }
    .alert-error   { background: rgba(183,75,75,.10); border-color: rgba(183,75,75,.30); color: var(--torii); }
    .alert-info    { background: rgba(212,160,23,.10); border-color: rgba(212,160,23,.30); color: var(--gold); }
    .alert-box ul  { margin: 6px 0 0 16px; padding: 0; }
    .alert-box li  { margin-bottom: 3px; }

    /* ======================================================
       SIDEBAR — DAFTAR QUIZ
    ====================================================== */
    .sidebar-card {
      border: 1px solid var(--card-border);
      border-radius: 18px;
      background: var(--card-bg);
      box-shadow: 0 2px 10px rgba(0,0,0,.04);
      overflow: hidden;
    }
    .sidebar-header {
      padding: 18px 18px 14px;
      border-bottom: 1px solid var(--card-border);
      display: flex;
      align-items: center;
      gap: 12px;
    }
    .sidebar-header-icon {
      font-size: 1.5rem;
      width: 40px;
      height: 40px;
      border-radius: 12px;
      background: rgba(183,75,75,.10);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .sidebar-header-title { font-weight: 800; font-size: 1rem; }
    .sidebar-header-sub   { font-size: .78rem; color: var(--text-muted); margin-top: 1px; }

    .quiz-list-wrap { padding: 14px; }

    .quiz-list-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      padding: 11px 13px;
      border-radius: 12px;
      border: 1px solid var(--card-border);
      margin-bottom: 8px;
      text-decoration: none;
      color: inherit;
      transition: background .15s, border-color .15s, box-shadow .15s;
      flex-wrap: wrap;
    }
    .quiz-list-item:last-child { margin-bottom: 0; }
    .quiz-list-item:hover  { background: rgba(183,75,75,.05); border-color: var(--torii); box-shadow: 0 2px 8px rgba(0,0,0,.06); }
    .quiz-list-item.active { background: rgba(183,75,75,.09); border-color: var(--torii); }
    .quiz-list-info  { min-width: 0; flex: 1 1 110px; }
    .quiz-list-title { font-weight: 700; font-size: .92rem; word-break: break-word; }
    .quiz-list-meta  { font-size: .75rem; color: var(--text-muted); margin-top: 3px; }

    .status-pill {
      font-size: .70rem;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 20px;
      white-space: nowrap;
      flex-shrink: 0;
      letter-spacing: .02em;
    }
    .status-draft     { background: rgba(150,150,150,.16); color: #888; }
    .status-published { background: rgba(74,124,89,.16);   color: var(--bamboo); }
    .status-closed    { background: rgba(183,75,75,.14);   color: var(--torii); }

    /* Form buat quiz baru di sidebar */
    .create-quiz-wrap {
      padding: 14px;
      border-top: 1px solid var(--card-border);
      background: rgba(124,58,237,.03);
    }
    .create-quiz-title {
      font-size: .80rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: .06em;
      margin-bottom: 12px;
    }

    /* ======================================================
       FORM UMUM
    ====================================================== */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 14px;
    }
    @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }

    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label {
      font-size: .80rem;
      font-weight: 600;
      color: var(--text-muted);
      letter-spacing: .01em;
    }
    .form-group input,
    .form-group textarea,
    .form-group select {
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid var(--card-border);
      background: var(--card-bg);
      color: var(--text-main);
      font-size: .9rem;
      font-family: inherit;
      width: 100%;
      box-sizing: border-box;
      transition: border-color .15s, box-shadow .15s;
    }
    .form-group input:focus,
    .form-group textarea:focus,
    .form-group select:focus {
      outline: none;
      border-color: var(--torii);
      box-shadow: 0 0 0 3px rgba(183,75,75,.12);
    }
    .form-group textarea { resize: vertical; min-height: 70px; }
    .form-hint { font-size: .75rem; color: var(--text-muted); margin-top: 3px; }
    .form-hint.hint-green { color: var(--bamboo); }

    .form-actions {
      display: flex;
      gap: 10px;
      margin-top: 18px;
      flex-wrap: wrap;
    }
    @media (max-width: 480px) {
      .form-actions .bab-btn { flex: 1 1 140px; justify-content: center; }
    }

    /* ======================================================
       SECTION KARTU DETAIL QUIZ
    ====================================================== */
    .detail-card {
      border: 1px solid var(--card-border);
      border-radius: 18px;
      background: var(--card-bg);
      box-shadow: 0 2px 10px rgba(0,0,0,.04);
      margin-bottom: 20px;
      overflow: hidden;
    }
    .detail-card-header {
      padding: 18px 22px 14px;
      border-bottom: 1px solid var(--card-border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
    }
    .detail-card-title-wrap { display: flex; align-items: center; gap: 12px; }
    .detail-card-icon {
      font-size: 1.3rem;
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: rgba(183,75,75,.09);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .detail-card-title { font-weight: 800; font-size: 1rem; }
    .detail-card-sub   { font-size: .78rem; color: var(--text-muted); margin-top: 2px; }
    .detail-card-body  { padding: 20px 22px; }

    /* Badge token */
    .token-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      font-family: monospace;
      font-size: .83rem;
      letter-spacing: .12em;
      background: rgba(124,58,237,.09);
      color: #7c3aed;
      padding: 4px 12px;
      border-radius: 8px;
      font-weight: 700;
      border: 1px solid rgba(124,58,237,.18);
    }

    /* ======================================================
       TABS (Import / Manual)
    ====================================================== */
    .tabs {
      display: flex;
      gap: 6px;
      margin-bottom: 18px;
      border-bottom: 2px solid var(--card-border);
      padding-bottom: 0;
    }
    .tab-btn {
      padding: 8px 16px;
      border-radius: 10px 10px 0 0;
      border: 1px solid transparent;
      border-bottom: none;
      background: transparent;
      cursor: pointer;
      font-size: .85rem;
      font-weight: 600;
      color: var(--text-muted);
      transition: background .15s, color .15s;
      margin-bottom: -2px;
    }
    .tab-btn:hover { background: rgba(183,75,75,.06); color: var(--text-main); }
    .tab-btn.active {
      background: var(--card-bg);
      color: var(--torii);
      border-color: var(--card-border);
      border-bottom-color: var(--card-bg);
      box-shadow: 0 -2px 0 var(--torii) inset;
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    /* ======================================================
       IMPORT BOX
    ====================================================== */
    .import-box {
      border: 2px dashed var(--card-border);
      border-radius: 14px;
      padding: 22px 20px;
      text-align: center;
      background: rgba(74,124,89,.02);
      transition: border-color .2s, background .2s;
    }
    .import-box:hover { border-color: var(--bamboo); background: rgba(74,124,89,.04); }
    .import-box-icon  { font-size: 2.4rem; margin-bottom: 8px; }
    .import-box-title { font-weight: 800; font-size: 1rem; margin-bottom: 6px; }
    .import-box-desc  { font-size: .82rem; color: var(--text-muted); line-height: 1.6; }
    .import-box input[type=file] {
      display: block;
      margin: 14px auto 0;
      max-width: 100%;
      font-size: .85rem;
    }
    .import-col-table {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 6px;
      margin: 12px 0;
      text-align: left;
    }
    @media (max-width: 480px) { .import-col-table { grid-template-columns: 1fr 1fr; } }
    .import-col-item {
      background: var(--card-bg);
      border: 1px solid var(--card-border);
      border-radius: 8px;
      padding: 7px 10px;
      font-size: .75rem;
    }
    .import-col-item strong { display: block; color: var(--torii); font-size: .80rem; }

    /* ======================================================
       SOAL CARDS
    ====================================================== */
    .soal-list-wrap { margin-top: 22px; }
    .soal-list-divider {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-bottom: 14px;
    }
    .soal-list-divider-label {
      font-size: .80rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: .06em;
      white-space: nowrap;
    }
    .soal-list-divider-line {
      flex: 1;
      height: 1px;
      background: var(--card-border);
    }

    .soal-card {
      border: 1px solid var(--card-border);
      border-radius: 14px;
      padding: 16px 18px;
      margin-bottom: 12px;
      background: var(--card-bg);
      transition: border-color .15s, box-shadow .15s;
    }
    .soal-card:hover { border-color: rgba(183,75,75,.30); box-shadow: 0 3px 10px rgba(0,0,0,.06); }
    .soal-card:last-child { margin-bottom: 0; }

    .soal-card-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 10px;
      margin-bottom: 12px;
      flex-wrap: wrap;
    }
    .soal-card-info  { min-width: 0; flex: 1 1 180px; }
    .soal-num        { font-size: .72rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: .06em; margin-bottom: 4px; }
    .soal-kotoba     { font-size: 1.2rem; font-weight: 800; word-break: break-word; }
    .soal-cara-baca  { font-size: .85rem; color: var(--text-muted); margin-top: 2px; }
    .soal-arti       { font-size: .88rem; color: var(--bamboo); font-weight: 600; margin-top: 4px; }

    .soal-actions { display: flex; gap: 7px; flex-shrink: 0; align-items: flex-start; }
    .icon-btn {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      border: 1px solid var(--card-border);
      background: var(--card-bg);
      border-radius: 8px;
      padding: 6px 11px;
      cursor: pointer;
      font-size: .82rem;
      font-weight: 600;
      white-space: nowrap;
      transition: background .15s, border-color .15s, color .15s;
    }
    .icon-btn:hover        { background: rgba(183,75,75,.06); border-color: var(--torii); color: var(--torii); }
    .icon-btn.danger       { color: var(--torii); border-color: rgba(183,75,75,.35); }
    .icon-btn.danger:hover { background: rgba(183,75,75,.08); }

    @media (max-width: 480px) {
      .soal-actions { flex-direction: column; gap: 5px; width: 100%; }
      .icon-btn     { justify-content: center; font-size: .80rem; }
    }

    .soal-options {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 7px;
    }
    @media (max-width: 540px) { .soal-options { grid-template-columns: 1fr; } }

    .soal-option {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      border-radius: 9px;
      border: 1px solid var(--card-border);
      font-size: .84rem;
      word-break: break-word;
      background: var(--card-bg);
    }
    .soal-option-letter {
      font-weight: 800;
      font-size: .75rem;
      color: var(--text-muted);
      flex-shrink: 0;
      width: 18px;
    }
    .soal-option.correct {
      border-color: rgba(74,124,89,.40);
      background: rgba(74,124,89,.08);
      font-weight: 700;
    }
    .soal-option.correct .soal-option-letter { color: var(--bamboo); }

    /* ======================================================
       SOAL FORM ACTIONS
    ====================================================== */
    .soal-form-actions {
      display: flex;
      gap: 10px;
      margin-top: 16px;
      flex-wrap: wrap;
    }
    @media (max-width: 480px) {
      .soal-form-actions .bab-btn { flex: 1 1 130px; justify-content: center; }
    }

    /* ======================================================
       EMPTY STATE
    ====================================================== */
    .empty-state {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      padding: 32px 20px;
      border-radius: 14px;
      border: 2px dashed var(--card-border);
      background: rgba(74,124,89,.02);
    }
    .empty-state-icon  { font-size: 2.2rem; margin-bottom: 10px; }
    .empty-state-title { font-weight: 700; font-size: .95rem; margin-bottom: 5px; }
    .empty-state-sub   { font-size: .82rem; color: var(--text-muted); }

    /* ======================================================
       PLACEHOLDER (belum pilih quiz)
    ====================================================== */
    .placeholder-card {
      border: 1px solid var(--card-border);
      border-radius: 18px;
      background: var(--card-bg);
      box-shadow: 0 2px 10px rgba(0,0,0,.04);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      text-align: center;
      padding: 48px 28px;
      min-height: 220px;
    }
    .placeholder-icon  { font-size: 3rem; margin-bottom: 14px; opacity: .5; }
    .placeholder-title { font-weight: 700; font-size: 1rem; color: var(--text-muted); }
    .placeholder-sub   { font-size: .84rem; color: var(--text-muted); margin-top: 6px; }
  </style>
</head>
<body class="dashboard-page">

  <div class="page-loader" id="pageLoader"><span class="loader-kanji">桜</span></div>
  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <div class="topbar-actions">
      <a href="beranda.php" class="topbar-back" style="border: 2px solid #a1781e; padding: 8px 12px; border-radius: 8px; text-decoration: none; display: inline-block;">← Beranda</a>
    </div>
  </header>

  <main class="dashboard-main">

    <section class="welcome-section fade-up">
      <span class="welcome-kanji">言葉</span>
      <h1 class="welcome-title">Kelola Quiz Kotoba</h1>
      <p class="welcome-sub">Buat, edit, dan import soal kosakata bahasa Jepang untuk siswa</p>
      <div class="section-divider"></div>
    </section>

    <!-- ============ NOTIFIKASI ============ -->
    <?php if (isset($_GET['created'])): ?>
      <div class="alert-box alert-success"><span class="alert-icon">✅</span><span>Quiz baru berhasil dibuat. Tambahkan soal melalui panel kanan.</span></div>
    <?php endif; ?>
    <?php if (isset($_GET['updated'])): ?>
      <div class="alert-box alert-success"><span class="alert-icon">✅</span><span>Perubahan quiz berhasil disimpan.</span></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
      <div class="alert-box alert-success"><span class="alert-icon">✅</span><span>Quiz berhasil dihapus.</span></div>
    <?php endif; ?>
    <?php if (isset($_GET['soal_saved'])): ?>
      <div class="alert-box alert-success"><span class="alert-icon">✅</span><span>Soal berhasil disimpan.</span></div>
    <?php endif; ?>
    <?php if (isset($_GET['soal_deleted'])): ?>
      <div class="alert-box alert-success"><span class="alert-icon">✅</span><span>Soal berhasil dihapus.</span></div>
    <?php endif; ?>
    <?php if (isset($_GET['imported'])): ?>
      <div class="alert-box alert-success">
        <span class="alert-icon">📥</span>
        <span>
          Import selesai — <strong><?= (int)$_GET['imported'] ?></strong> soal berhasil ditambahkan<?php if ((int)($_GET['skipped'] ?? 0) > 0): ?>, <strong><?= (int)$_GET['skipped'] ?></strong> baris dilewati<?php endif; ?>.
        </span>
      </div>
      <?php if (!empty($importErrors)): ?>
        <div class="alert-box alert-info">
          <span class="alert-icon">⚠️</span>
          <div>
            <strong>Baris yang dilewati:</strong>
            <ul><?php foreach ($importErrors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (isset($_GET['import_error'])): ?>
      <div class="alert-box alert-error"><span class="alert-icon">❌</span><span><?= htmlspecialchars($_GET['import_error']) ?></span></div>
    <?php endif; ?>
    <?php if ($message): ?>
      <div class="alert-box alert-<?= $messageType ?>"><span class="alert-icon"><?= $messageType === 'error' ? '❌' : '✅' ?></span><span><?= htmlspecialchars($message) ?></span></div>
    <?php endif; ?>

    <div class="kotoba-layout">

      <!-- ============================================
           SIDEBAR: DAFTAR QUIZ + BUAT BARU
      ============================================ -->
      <div class="sidebar-card fade-up delay-1">
        <div class="sidebar-header">
          <div class="sidebar-header-icon">📚</div>
          <div>
            <div class="sidebar-header-title">Daftar Quiz</div>
            <div class="sidebar-header-sub"><?= count($quizList) ?> quiz tersedia</div>
          </div>
        </div>

        <div class="quiz-list-wrap">
          <?php if (empty($quizList)): ?>
            <div class="empty-state" style="padding:20px;">
              <div class="empty-state-icon">📭</div>
              <div class="empty-state-title">Belum ada quiz</div>
              <div class="empty-state-sub">Buat quiz baru di bawah untuk mulai.</div>
            </div>
          <?php else: ?>
            <?php foreach ($quizList as $q): ?>
              <a href="kotoba_admin.php?quiz=<?= $q['id'] ?>" class="quiz-list-item <?= $selectedQuizId === (int)$q['id'] ? 'active' : '' ?>">
                <div class="quiz-list-info">
                  <div class="quiz-list-title"><?= htmlspecialchars($q['judul']) ?></div>
                  <div class="quiz-list-meta">
                    <?= (int)$q['total_soal'] ?> soal
                    &middot; <?= (int)$q['total_attempts'] ?> pengerjaan
                    &middot; <?= (int)$q['duration_minutes'] > 0 ? (int)$q['duration_minutes'].' mnt' : '♾' ?>
                  </div>
                </div>
                <span class="status-pill status-<?= $q['status'] ?>">
                  <?= $q['status'] === 'draft' ? 'Draft' : ($q['status'] === 'published' ? 'Aktif' : 'Tutup') ?>
                </span>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- Form buat quiz baru -->
        <div class="create-quiz-wrap">
          <div class="create-quiz-title">➕ Buat Quiz Baru</div>
          <form method="POST" style="display:flex; flex-direction:column; gap:10px;">
            <input type="hidden" name="action" value="create_quiz">
            <div class="form-group">
              <label>Judul Quiz</label>
              <input type="text" name="judul" placeholder="Contoh: Kotoba Bab 3" required>
            </div>
            <div class="form-group">
              <label>Deskripsi <span style="font-weight:400;">(opsional)</span></label>
              <textarea name="deskripsi" placeholder="Deskripsi singkat..." style="min-height:56px;"></textarea>
            </div>
            <div class="form-group">
              <label>Durasi (menit)</label>
              <input type="number" name="duration_minutes" value="15" min="0" required>
              <span class="form-hint">Isi 0 untuk tanpa batas waktu</span>
            </div>
            <button type="submit" class="bab-btn bab-btn-primary" style="justify-content:center;">
              <span class="bab-btn-icon">📚</span>
              <span>Buat Quiz</span>
            </button>
          </form>
        </div>
      </div><!-- /sidebar -->

      <!-- ============================================
           PANEL KANAN: DETAIL & SOAL
      ============================================ -->
      <?php if ($activeQuiz): ?>
      <div class="fade-up delay-2">

        <!-- Info & Edit Quiz -->
        <div class="detail-card">
          <div class="detail-card-header">
            <div class="detail-card-title-wrap">
              <div class="detail-card-icon">⚙️</div>
              <div>
                <div class="detail-card-title"><?= htmlspecialchars($activeQuiz['judul']) ?></div>
                <div class="detail-card-sub">
                  Token: <span class="token-badge"><?= htmlspecialchars($activeQuiz['token']) ?></span>
                </div>
              </div>
            </div>
          </div>
          <div class="detail-card-body">
            <form method="POST">
              <input type="hidden" name="action" value="update_quiz">
              <input type="hidden" name="quiz_id" value="<?= $activeQuiz['id'] ?>">
              <div class="form-grid">
                <div class="form-group">
                  <label>Judul Quiz</label>
                  <input type="text" name="judul" value="<?= htmlspecialchars($activeQuiz['judul']) ?>" required>
                </div>
                <div class="form-group">
                  <label>Durasi (menit)</label>
                  <input type="number" name="duration_minutes" value="<?= (int)$activeQuiz['duration_minutes'] ?>" min="0" required id="durationInput">
                  <div id="durationHint" class="form-hint"></div>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                  <label>Deskripsi</label>
                  <textarea name="deskripsi"><?= htmlspecialchars($activeQuiz['deskripsi'] ?? '') ?></textarea>
                </div>
                <div class="form-group">
                  <label>Status</label>
                  <select name="status">
                    <option value="draft"     <?= $activeQuiz['status']==='draft'     ? 'selected' : '' ?>>Draft — tidak terlihat siswa</option>
                    <option value="published" <?= $activeQuiz['status']==='published' ? 'selected' : '' ?>>Aktif — terlihat & bisa dikerjakan</option>
                    <option value="closed"    <?= $activeQuiz['status']==='closed'    ? 'selected' : '' ?>>Tutup — tidak bisa dikerjakan</option>
                  </select>
                </div>
              </div>
              <div class="form-actions">
                <button type="submit" class="bab-btn bab-btn-primary">
                  <span class="bab-btn-icon">💾</span><span>Simpan Perubahan</span>
                </button>
                <button type="button" class="bab-btn bab-btn-logout" onclick="confirmDeleteQuiz(<?= $activeQuiz['id'] ?>)">
                  <span class="bab-btn-icon">🗑</span><span>Hapus Quiz</span>
                </button>
              </div>
            </form>
            <form id="deleteQuizForm" method="POST" style="display:none;">
              <input type="hidden" name="action" value="delete_quiz">
              <input type="hidden" name="quiz_id" value="<?= $activeQuiz['id'] ?>">
            </form>
          </div>
        </div>

        <!-- Manajemen Soal -->
        <div class="detail-card" id="soal-section">
          <div class="detail-card-header">
            <div class="detail-card-title-wrap">
              <div class="detail-card-icon">📝</div>
              <div>
                <div class="detail-card-title">Soal Kotoba</div>
                <div class="detail-card-sub"><?= count($soalList) ?> soal tersimpan</div>
              </div>
            </div>
          </div>
          <div class="detail-card-body">

            <!-- Tabs -->
            <div class="tabs">
              <button class="tab-btn active" onclick="switchTab('import')" id="tabBtnImport">📥 Import CSV</button>
              <button class="tab-btn" onclick="switchTab('manual')" id="tabBtnManual">✍️ Tambah Manual</button>
            </div>

            <!-- Tab: Import CSV -->
            <div class="tab-content active" id="tabImport">
              <div class="import-box">
                <div class="import-box-icon">📊</div>
                <div class="import-box-title">Import Soal dari File CSV</div>
                <div class="import-box-desc">
                  Siapkan file CSV dengan urutan kolom berikut:
                </div>
                <div class="import-col-table">
                  <div class="import-col-item"><strong>A</strong> Kotoba</div>
                  <div class="import-col-item"><strong>B</strong> Cara Baca</div>
                  <div class="import-col-item"><strong>C</strong> Arti</div>
                  <div class="import-col-item"><strong>D</strong> Opsi A</div>
                  <div class="import-col-item"><strong>E</strong> Opsi B</div>
                  <div class="import-col-item"><strong>F</strong> Opsi C</div>
                  <div class="import-col-item"><strong>G</strong> Opsi D</div>
                  <div class="import-col-item"><strong>H</strong> Jawaban (a/b/c/d)</div>
                </div>
                <form method="POST" enctype="multipart/form-data">
                  <input type="hidden" name="action" value="import_excel">
                  <input type="hidden" name="quiz_id" value="<?= $activeQuiz['id'] ?>">
                  <input type="file" name="excel_file" accept=".csv" required>
                  <div style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap; justify-content:center;">
                    <button type="submit" class="bab-btn bab-btn-primary">
                      <span class="bab-btn-icon">📤</span><span>Upload & Import</span>
                    </button>
                    <a href="kotoba_template.php" class="bab-btn" style="background:rgba(74,124,89,.12); color:var(--bamboo); border:1px solid rgba(74,124,89,.3);">
                      <span class="bab-btn-icon">⬇️</span><span>Unduh Template</span>
                    </a>
                  </div>
                </form>
                <p style="font-size:.76rem; color:var(--text-muted); margin-top:14px; margin-bottom:0;">
                  💡 Di Excel/Google Sheets: <strong>File → Save As → CSV</strong> sebelum diupload. Baris header pertama akan dilewati otomatis.
                </p>
              </div>
            </div>

            <!-- Tab: Tambah Manual -->
            <div class="tab-content" id="tabManual">
              <form method="POST">
                <input type="hidden" name="action" value="save_soal">
                <input type="hidden" name="quiz_id" value="<?= $activeQuiz['id'] ?>">
                <input type="hidden" name="soal_id" value="0" id="soalIdField">
                <div class="form-grid">
                  <div class="form-group">
                    <label>Kotoba <span style="font-size:.85rem; font-style:italic; font-weight:400;">(kata Jepang)</span></label>
                    <input type="text" name="kotoba" id="f_kotoba" placeholder="例: 食べる" required>
                  </div>
                  <div class="form-group">
                    <label>Cara Baca <span style="font-weight:400;">(opsional)</span></label>
                    <input type="text" name="cara_baca" id="f_cara_baca" placeholder="たべる">
                  </div>
                  <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Arti (Indonesia)</label>
                    <input type="text" name="arti" id="f_arti" placeholder="Makan" required>
                  </div>
                  <div class="form-group">
                    <label>Opsi A</label>
                    <input type="text" name="option_a" id="f_option_a" required>
                  </div>
                  <div class="form-group">
                    <label>Opsi B</label>
                    <input type="text" name="option_b" id="f_option_b" required>
                  </div>
                  <div class="form-group">
                    <label>Opsi C</label>
                    <input type="text" name="option_c" id="f_option_c" required>
                  </div>
                  <div class="form-group">
                    <label>Opsi D</label>
                    <input type="text" name="option_d" id="f_option_d" required>
                  </div>
                  <div class="form-group">
                    <label>Jawaban Benar</label>
                    <select name="correct_option" id="f_correct_option">
                      <option value="a">Opsi A</option>
                      <option value="b">Opsi B</option>
                      <option value="c">Opsi C</option>
                      <option value="d">Opsi D</option>
                    </select>
                  </div>
                </div>
                <div class="soal-form-actions">
                  <button type="submit" class="bab-btn bab-btn-primary" id="saveSoalBtn">
                    <span class="bab-btn-icon">💾</span><span>Simpan Soal</span>
                  </button>
                  <button type="button" class="bab-btn bab-btn-logout" onclick="resetForm()" style="display:none;" id="cancelEditBtn">
                    <span class="bab-btn-icon">✖</span><span>Batal Edit</span>
                  </button>
                </div>
              </form>
            </div>

            <!-- Daftar Soal -->
            <div class="soal-list-wrap">
              <div class="soal-list-divider">
                <span class="soal-list-divider-label">Daftar Soal</span>
                <div class="soal-list-divider-line"></div>
                <span class="soal-list-divider-label"><?= count($soalList) ?> soal</span>
              </div>

              <?php if (empty($soalList)): ?>
                <div class="empty-state">
                  <div class="empty-state-icon">📭</div>
                  <div class="empty-state-title">Belum ada soal</div>
                  <div class="empty-state-sub">Tambah soal secara manual atau import dari file CSV.</div>
                </div>
              <?php else: ?>
                <?php foreach ($soalList as $idx => $s): ?>
                  <div class="soal-card">
                    <div class="soal-card-head">
                      <div class="soal-card-info">
                        <div class="soal-num">Soal <?= $idx + 1 ?></div>
                        <div class="soal-kotoba"><?= htmlspecialchars($s['kotoba']) ?></div>
                        <?php if (!empty($s['cara_baca'])): ?>
                          <div class="soal-cara-baca"><?= htmlspecialchars($s['cara_baca']) ?></div>
                        <?php endif; ?>
                        <div class="soal-arti">🇮🇩 <?= htmlspecialchars($s['arti']) ?></div>
                      </div>
                      <div class="soal-actions">
                        <button class="icon-btn" onclick='editSoal(<?= json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT) ?>)'>✏️ Edit</button>
                        <form method="POST" onsubmit="return confirm('Hapus soal ini?')" style="display:inline;">
                          <input type="hidden" name="action" value="delete_soal">
                          <input type="hidden" name="quiz_id" value="<?= $activeQuiz['id'] ?>">
                          <input type="hidden" name="soal_id" value="<?= $s['id'] ?>">
                          <button class="icon-btn danger" type="submit">🗑 Hapus</button>
                        </form>
                      </div>
                    </div>
                    <div class="soal-options">
                      <?php foreach (['a','b','c','d'] as $opt): ?>
                        <div class="soal-option <?= $s['correct_option']===$opt ? 'correct' : '' ?>">
                          <span class="soal-option-letter"><?= strtoupper($opt) ?></span>
                          <span><?= htmlspecialchars($s['option_'.$opt]) ?><?= $s['correct_option']===$opt ? ' ✓' : '' ?></span>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

          </div>
        </div><!-- /soal-section -->

      </div><!-- /panel kanan -->
      <?php else: ?>
        <div class="placeholder-card fade-up delay-2">
          <div class="placeholder-icon">👈</div>
          <div class="placeholder-title">Pilih quiz untuk mulai</div>
          <div class="placeholder-sub">Pilih dari daftar quiz di sebelah kiri, atau buat quiz baru.</div>
        </div>
      <?php endif; ?>

    </div><!-- /kotoba-layout -->

  </main>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/petals.js"></script>
  <script>
    function confirmDeleteQuiz() {
      if (confirm('Hapus quiz ini? Semua soal dan riwayat pengerjaan akan ikut terhapus.')) {
        document.getElementById('deleteQuizForm').submit();
      }
    }

    function switchTab(tab) {
      document.getElementById('tabImport').classList.toggle('active', tab === 'import');
      document.getElementById('tabManual').classList.toggle('active', tab === 'manual');
      document.getElementById('tabBtnImport').classList.toggle('active', tab === 'import');
      document.getElementById('tabBtnManual').classList.toggle('active', tab === 'manual');
    }

    function editSoal(soal) {
      switchTab('manual');
      document.getElementById('soalIdField').value        = soal.id;
      document.getElementById('f_kotoba').value           = soal.kotoba;
      document.getElementById('f_cara_baca').value        = soal.cara_baca || '';
      document.getElementById('f_arti').value             = soal.arti;
      document.getElementById('f_option_a').value         = soal.option_a;
      document.getElementById('f_option_b').value         = soal.option_b;
      document.getElementById('f_option_c').value         = soal.option_c;
      document.getElementById('f_option_d').value         = soal.option_d;
      document.getElementById('f_correct_option').value   = soal.correct_option;
      document.getElementById('cancelEditBtn').style.display = 'inline-flex';
      document.getElementById('saveSoalBtn').querySelector('span:last-child').textContent = 'Update Soal';
      const sec = document.getElementById('soal-section');
      if (sec) window.scrollTo({ top: sec.offsetTop - 80, behavior: 'smooth' });
    }

    function resetForm() {
      document.getElementById('soalIdField').value = '0';
      ['f_kotoba','f_cara_baca','f_arti','f_option_a','f_option_b','f_option_c','f_option_d']
        .forEach(id => document.getElementById(id).value = '');
      document.getElementById('f_correct_option').value = 'a';
      document.getElementById('cancelEditBtn').style.display = 'none';
      document.getElementById('saveSoalBtn').querySelector('span:last-child').textContent = 'Simpan Soal';
    }

    // Duration hint
    const durationInput = document.getElementById('durationInput');
    const durationHint  = document.getElementById('durationHint');
    function updateDurationHint() {
      if (!durationInput || !durationHint) return;
      const val = parseInt(durationInput.value, 10);
      if (val === 0) {
        durationHint.innerHTML = '<span style="color:var(--bamboo);">♾ Tanpa batas waktu</span>';
        durationHint.className = 'form-hint hint-green';
      } else if (val > 0) {
        durationHint.innerHTML = '⏱ Siswa punya <strong>' + val + ' menit</strong> untuk menyelesaikan';
        durationHint.className = 'form-hint';
      } else {
        durationHint.textContent = '';
      }
    }
    if (durationInput) {
      durationInput.addEventListener('input', updateDurationHint);
      updateDurationHint();
    }
  </script>
</body>
</html>
<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}
if ($user['role'] === 'admin') {
    header('Location: kotoba_admin.php');
    exit;
}

$db = getDB();
$mode = $_GET['mode'] ?? 'list'; // list | take | finish
$quizId = (int)($_GET['quiz'] ?? 0);
$soalIndex = (int)($_GET['q'] ?? 0); // indeks soal saat ini (0-based)

// ============================================================
// POST: mulai / jawab soal / selesai
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Mulai quiz (atau mulai ulang) ---
    if ($action === 'start_quiz') {
        $quizId = (int)$_POST['quiz_id'];

        $stmt = $db->prepare("SELECT * FROM kotoba_quiz WHERE id=? AND status='published'");
        $stmt->execute([$quizId]);
        $quiz = $stmt->fetch();
        if (!$quiz) { header('Location: kotoba.php'); exit; }

        // Cek attempt yang sudah ada
        $stmt = $db->prepare("SELECT * FROM kotoba_quiz_attempts WHERE quiz_id=? AND user_id=?");
        $stmt->execute([$quizId, $user['id']]);
        $attempt = $stmt->fetch();

        $totalSoal = (int)$db->query("SELECT COUNT(*) FROM kotoba_quiz_soal WHERE quiz_id=" . $quizId)->fetchColumn();
        $startedAt = date('Y-m-d H:i:s');

        // duration_minutes = 0 berarti tanpa batas waktu (ends_at = NULL)
        $endsAt = null;
        if ((int)$quiz['duration_minutes'] > 0) {
            $endsAt = date('Y-m-d H:i:s', strtotime("+{$quiz['duration_minutes']} minutes"));
        }

        if (!$attempt) {
            // Buat attempt baru
            $stmt = $db->prepare("INSERT INTO kotoba_quiz_attempts (quiz_id, user_id, started_at, ends_at, status, total_questions) VALUES (?,?,?,?,'in_progress',?)");
            $stmt->execute([$quizId, $user['id'], $startedAt, $endsAt, $totalSoal]);
        } elseif ($attempt['status'] === 'finished') {
            // Reset attempt agar bisa quiz ulang
            $db->prepare("DELETE FROM kotoba_quiz_answers WHERE attempt_id=?")->execute([$attempt['id']]);
            $stmt = $db->prepare("UPDATE kotoba_quiz_attempts SET started_at=?, ends_at=?, finished_at=NULL, score=0, total_correct=0, total_questions=?, status='in_progress' WHERE id=?");
            $stmt->execute([$startedAt, $endsAt, $totalSoal, $attempt['id']]);
        } else {
            // Lanjutkan yang sedang berjalan
        }

        header('Location: kotoba.php?mode=take&quiz=' . $quizId . '&q=0');
        exit;
    }

    // --- Keluar dari quiz (hentikan attempt) ---
    if ($action === 'quit_quiz' && (int)($_POST['quiz_id'] ?? 0) > 0) {
        $quitQuizId = (int)$_POST['quiz_id'];
        $stmt = $db->prepare("SELECT * FROM kotoba_quiz_attempts WHERE quiz_id=? AND user_id=? AND status='in_progress'");
        $stmt->execute([$quitQuizId, $user['id']]);
        $quitAttempt = $stmt->fetch();
        if ($quitAttempt) {
            // Hitung jawaban yang sudah masuk
            $totalCorrect = (int)$db->query("SELECT COUNT(*) FROM kotoba_quiz_answers WHERE attempt_id={$quitAttempt['id']} AND is_correct=1")->fetchColumn();
            $totalQDone   = (int)$db->query("SELECT COUNT(*) FROM kotoba_quiz_soal WHERE quiz_id=$quitQuizId")->fetchColumn();
            $stmt2 = $db->prepare("UPDATE kotoba_quiz_attempts SET finished_at=NOW(), total_correct=?, total_questions=?, status='finished' WHERE id=?");
            $stmt2->execute([$totalCorrect, $totalQDone, $quitAttempt['id']]);
        }
        header('Location: kotoba.php');
        exit;
    }


    if ($action === 'answer_soal') {
        $quizId   = (int)$_POST['quiz_id'];
        $soalId   = (int)$_POST['soal_id'];
        $nextIdx  = (int)$_POST['next_idx'];
        $total    = (int)$_POST['total'];

        $stmt = $db->prepare("SELECT * FROM kotoba_quiz_attempts WHERE quiz_id=? AND user_id=?");
        $stmt->execute([$quizId, $user['id']]);
        $attempt = $stmt->fetch();

        if (!$attempt || $attempt['status'] === 'finished') {
            header('Location: kotoba.php?mode=finish&quiz=' . $quizId);
            exit;
        }

        // Cek waktu (hanya jika ada batas waktu)
        if ($attempt['ends_at'] && strtotime($attempt['ends_at']) < time()) {
            // auto-finish karena waktu habis
            $stmt = $db->prepare("UPDATE kotoba_quiz_attempts SET finished_at=NOW(), score=0, total_correct=0, total_questions=?, status='finished' WHERE id=?");
            $stmt->execute([$total, $attempt['id']]);
            header('Location: kotoba.php?mode=finish&quiz=' . $quizId . '&timeout=1');
            exit;
        }

        $selected = $_POST['pilihan'] ?? null;
        $selected = in_array($selected, ['a','b','c','d']) ? $selected : null;

        // Simpan jawaban
        $stmt = $db->prepare("SELECT correct_option FROM kotoba_quiz_soal WHERE id=?");
        $stmt->execute([$soalId]);
        $soal = $stmt->fetch();
        $isCorrect = ($selected !== null && $soal && $selected === $soal['correct_option']) ? 1 : 0;

        $insertAns = $db->prepare("
            INSERT INTO kotoba_quiz_answers (attempt_id, soal_id, selected_option, is_correct)
            VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE selected_option=VALUES(selected_option), is_correct=VALUES(is_correct)
        ");
        $insertAns->execute([$attempt['id'], $soalId, $selected, $isCorrect]);

        // Kalau ini soal terakhir, selesaikan
        if ($nextIdx >= $total) {
            $totalCorrect = (int)$db->query("SELECT COUNT(*) FROM kotoba_quiz_answers WHERE attempt_id={$attempt['id']} AND is_correct=1")->fetchColumn();
            $stmt = $db->prepare("UPDATE kotoba_quiz_attempts SET finished_at=NOW(), score=0, total_correct=?, total_questions=?, status='finished' WHERE id=?");
            $stmt->execute([$totalCorrect, $total, $attempt['id']]);
            header('Location: kotoba.php?mode=finish&quiz=' . $quizId);
            exit;
        }

        header('Location: kotoba.php?mode=take&quiz=' . $quizId . '&q=' . $nextIdx);
        exit;
    }
}

// ============================================================
// MODE: list
// ============================================================
if ($mode === 'list') {
    $stmt = $db->prepare("
        SELECT q.*,
               (SELECT COUNT(*) FROM kotoba_quiz_soal s WHERE s.quiz_id = q.id) AS total_soal,
               a.status AS attempt_status
        FROM kotoba_quiz q
        LEFT JOIN kotoba_quiz_attempts a ON a.quiz_id = q.id AND a.user_id = ?
        WHERE q.status = 'published'
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$user['id']]);
    $quizzes = $stmt->fetchAll();

    // Hitung berapa quiz yang sudah diselesaikan
    $totalQuizzes = count($quizzes);
    $doneCount = 0;
    foreach ($quizzes as $q) {
        if ($q['attempt_status'] === 'finished') $doneCount++;
    }
    $allDone = ($totalQuizzes > 0 && $doneCount === $totalQuizzes);
}

// ============================================================
// MODE: take — tampilkan satu soal
// ============================================================
if ($mode === 'take') {
    $stmt = $db->prepare("SELECT * FROM kotoba_quiz WHERE id=? AND status='published'");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();
    if (!$quiz) { header('Location: kotoba.php'); exit; }

    $stmt = $db->prepare("SELECT * FROM kotoba_quiz_attempts WHERE quiz_id=? AND user_id=?");
    $stmt->execute([$quizId, $user['id']]);
    $attempt = $stmt->fetch();

    if (!$attempt || $attempt['status'] === 'finished') {
        header('Location: kotoba.php?mode=finish&quiz=' . $quizId);
        exit;
    }

    // Cek waktu habis (hanya jika ada batas waktu)
    if ($attempt['ends_at'] && strtotime($attempt['ends_at']) < time()) {
        $totalQ = (int)$db->query("SELECT COUNT(*) FROM kotoba_quiz_soal WHERE quiz_id=$quizId")->fetchColumn();
        $stmt = $db->prepare("UPDATE kotoba_quiz_attempts SET finished_at=NOW(), score=0, total_correct=0, total_questions=?, status='finished' WHERE id=?");
        $stmt->execute([$totalQ, $attempt['id']]);
        header('Location: kotoba.php?mode=finish&quiz=' . $quizId . '&timeout=1');
        exit;
    }

    $stmt = $db->prepare("SELECT * FROM kotoba_quiz_soal WHERE quiz_id=? ORDER BY question_order ASC, id ASC");
    $stmt->execute([$quizId]);
    $soalList = $stmt->fetchAll();
    $totalSoal = count($soalList);

    if ($soalIndex < 0 || $soalIndex >= $totalSoal) {
        header('Location: kotoba.php?mode=take&quiz=' . $quizId . '&q=0');
        exit;
    }

    $soalKini = $soalList[$soalIndex];

    // Hitung sisa waktu — null jika tanpa batas waktu
    $remainingSeconds = null;
    $hasTimer = !empty($attempt['ends_at']);
    if ($hasTimer) {
        $remainingSeconds = max(0, strtotime($attempt['ends_at']) - time());
    }

    // Cek apakah soal ini sudah dijawab
    $stmt = $db->prepare("SELECT selected_option FROM kotoba_quiz_answers WHERE attempt_id=? AND soal_id=?");
    $stmt->execute([$attempt['id'], $soalKini['id']]);
    $existingAnswer = $stmt->fetch();
    $existingSelected = $existingAnswer ? $existingAnswer['selected_option'] : null;
}

// ============================================================
// MODE: finish — selesai tanpa skor
// ============================================================
if ($mode === 'finish') {
    $stmt = $db->prepare("SELECT * FROM kotoba_quiz WHERE id=?");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();
    if (!$quiz) { header('Location: kotoba.php'); exit; }

    $stmt = $db->prepare("SELECT * FROM kotoba_quiz_attempts WHERE quiz_id=? AND user_id=? AND status='finished'");
    $stmt->execute([$quizId, $user['id']]);
    $attempt = $stmt->fetch();
    if (!$attempt) { header('Location: kotoba.php'); exit; }

    // Cek apakah semua quiz sudah selesai
    $stmtAll = $db->prepare("
        SELECT COUNT(*) FROM kotoba_quiz q
        WHERE q.status = 'published'
    ");
    $stmtAll->execute();
    $totalPublished = (int)$stmtAll->fetchColumn();

    $stmtDone = $db->prepare("
        SELECT COUNT(*) FROM kotoba_quiz q
        JOIN kotoba_quiz_attempts a ON a.quiz_id = q.id AND a.user_id = ? AND a.status = 'finished'
        WHERE q.status = 'published'
    ");
    $stmtDone->execute([$user['id']]);
    $totalDone = (int)$stmtDone->fetchColumn();

    $allDoneFinish = ($totalPublished > 0 && $totalDone >= $totalPublished);
    $isTimeout = isset($_GET['timeout']);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Quiz Kotoba</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
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
      transition: background .15s, border-color .15s, transform .15s;
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

    /* ---- All Done Banner ---- */
    .all-done-banner {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px 20px;
      border-radius: 14px;
      background: linear-gradient(135deg, rgba(74,124,89,.15), rgba(212,160,23,.12));
      border: 1.5px solid var(--bamboo);
      margin-bottom: 18px;
      animation: slideDown .4s ease;
    }
    .all-done-banner .banner-icon { font-size: 2.2rem; flex-shrink: 0; }
    .all-done-banner .banner-text { flex: 1; }
    .all-done-banner .banner-title { font-weight: 800; font-size: 1rem; color: var(--bamboo); }
    .all-done-banner .banner-sub { font-size: .82rem; color: var(--text-muted); margin-top: 2px; }
    @keyframes slideDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

    /* ---- Quiz List ---- */
    .quiz-card-item {
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 16px 18px;
      margin-bottom: 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
      background: var(--card-bg);
      box-shadow: 0 2px 10px rgba(0,0,0,.05);
      transition: border-color .15s, box-shadow .15s, transform .15s;
    }
    .quiz-card-item:hover {
      border-color: var(--torii);
      box-shadow: 0 4px 16px rgba(0,0,0,.08);
      transform: translateY(-2px);
    }
    .quiz-card-item.card-done {
      border-color: rgba(74,124,89,.4);
      background: rgba(74,124,89,.04);
    }
    .quiz-card-item:last-child { margin-bottom: 0; }
    .quiz-card-info { min-width: 0; flex: 1 1 200px; }
    .quiz-card-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; flex-shrink: 0; }
    .quiz-card-title { font-weight: 700; font-size: 1rem; word-break: break-word; }
    .quiz-card-meta { font-size: .82rem; color: var(--text-muted); margin-top: 4px; }
    .quiz-status-tag {
      font-size: .75rem;
      font-weight: 700;
      padding: 4px 10px;
      border-radius: 20px;
      white-space: nowrap;
      border: 1px solid transparent;
    }
    .tag-done { background: rgba(74,124,89,.15); color: var(--bamboo); border-color: rgba(74,124,89,.25); }
    .tag-progress { background: rgba(212,160,23,.15); color: var(--gold); border-color: rgba(212,160,23,.25); }
    .tag-new { background: rgba(124,58,237,.12); color: #7c3aed; border-color: rgba(124,58,237,.22); }

    /* Durasi badge */
    .duration-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: .75rem;
      color: var(--text-muted);
    }
    .duration-badge.no-limit { color: var(--bamboo); }

    @media (max-width: 480px) {
      .quiz-card-item { flex-direction: column; align-items: flex-start; }
      .quiz-card-actions { width: 100%; justify-content: flex-end; }
    }

    /* ---- Status card (timer + progress) ---- */
    .quiz-status-card {
      border: 1px solid var(--card-border);
      border-radius: 16px;
      background: var(--card-bg);
      padding: 16px 18px;
      margin-bottom: 18px;
      box-shadow: 0 2px 10px rgba(0,0,0,.05);
    }

    /* ---- Timer ---- */
    .quiz-timer {
      position: sticky;
      top: 70px;
      z-index: 5;
      text-align: center;
      font-size: 1.4rem;
      font-weight: 800;
      padding-bottom: 10px;
      margin-bottom: 10px;
      border-bottom: 1px solid var(--card-border);
      transition: color .2s;
    }
    .quiz-timer.no-limit {
      color: var(--bamboo);
      font-size: 1rem;
      font-weight: 600;
    }
    .quiz-timer.warning { color: var(--torii); animation: timerPulse 1s infinite; }
    @keyframes timerPulse { 0%,100% { opacity: 1; } 50% { opacity: .55; } }
    @media (max-width: 480px) {
      .quiz-timer { font-size: 1.1rem; top: 60px; }
    }

    /* ---- Progress bar ---- */
    .quiz-progress-wrap { margin-bottom: 0; }
    .quiz-progress-label {
      font-size: .82rem;
      color: var(--text-muted);
      margin-bottom: 6px;
      display: flex;
      justify-content: space-between;
    }
    .quiz-progress-bar {
      height: 8px;
      border-radius: 99px;
      background: var(--card-border);
      overflow: hidden;
    }
    .quiz-progress-fill {
      height: 100%;
      border-radius: 99px;
      background: var(--torii);
      transition: width .3s ease;
    }

    /* ---- Soal Take ---- */
    .soal-take-card {
      border: 1px solid var(--card-border);
      border-radius: 16px;
      padding: 24px;
      margin-bottom: 18px;
      background: var(--card-bg);
      box-shadow: 0 2px 10px rgba(0,0,0,.05);
    }
    .soal-take-kotoba { font-size: 1.9rem; font-weight: 800; margin-bottom: 4px; word-break: break-word; line-height: 1.2; }
    .soal-take-cara-baca { font-size: .95rem; color: var(--text-muted); }
    .soal-question-box {
      border: 1px solid var(--card-border);
      border-radius: 12px;
      padding: 20px;
      margin-bottom: 20px;
      text-align: center;
      background: linear-gradient(135deg, rgba(183,75,75,.10), rgba(74,124,89,.08));
    }
    .soal-take-options { display: flex; flex-direction: column; gap: 10px; }
    .option-label {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 16px;
      border-radius: 12px;
      border: 1.5px solid var(--card-border);
      cursor: pointer;
      color: var(--text-main);
      transition: background .15s, border-color .15s, transform .1s;
    }
    .option-label:hover { background: rgba(183,75,75,.06); border-color: var(--torii); transform: translateY(-1px); }
    .option-label input { accent-color: var(--torii); flex-shrink: 0; width: 17px; height: 17px; }
    .option-label span { word-break: break-word; font-weight: 600; }
    .option-label.selected { border-color: var(--torii); background: rgba(183,75,75,.09); box-shadow: 0 0 0 1px var(--torii) inset; }

    /* ---- Soal nav wrap (tombol keluar / lanjut) ---- */
    .soal-nav-wrap {
      display: flex;
      gap: 12px;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      margin-top: 6px;
    }
    .soal-nav-wrap .bab-btn { font-size: 1rem; padding: 13px 26px; }
    .soal-nav-wrap .bab-btn-outline {
      color: var(--torii);
      border-color: rgba(183,75,75,.35);
      background: transparent;
    }
    .soal-nav-wrap .bab-btn-outline:hover {
      background: rgba(183,75,75,.07);
    }
    @media (max-width: 480px) {
      .soal-nav-wrap { flex-direction: column-reverse; }
      .soal-nav-wrap .bab-btn { width: 100%; justify-content: center; }
    }

    @media (max-width: 480px) {
      .soal-take-card { padding: 14px; }
      .soal-take-kotoba { font-size: 1.4rem; }
      .soal-question-box { padding: 14px 12px; }
      .option-label { padding: 10px 12px; }
    }

    /* ---- Finish page ---- */
    .finish-box {
      text-align: center;
      padding: 40px 20px;
      border-radius: 18px;
      background: linear-gradient(135deg, rgba(183,75,75,.08), rgba(74,124,89,.08));
      border: 1px solid var(--card-border);
      margin-bottom: 24px;
      box-shadow: 0 2px 8px rgba(0,0,0,.04);
    }
    .finish-box.timeout-box {
      background: linear-gradient(135deg, rgba(183,75,75,.12), rgba(212,160,23,.08));
      border-color: var(--torii);
    }
    .finish-kanji { font-size: 3.5rem; display: block; margin-bottom: 12px; }
    .finish-title { font-size: 1.6rem; font-weight: 800; margin-bottom: 8px; }
    .finish-sub { color: var(--text-muted); font-size: .95rem; }

    .finish-all-done {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 16px 20px;
      border-radius: 14px;
      background: linear-gradient(135deg, rgba(74,124,89,.15), rgba(212,160,23,.10));
      border: 1.5px solid var(--bamboo);
      margin-bottom: 18px;
      text-align: left;
    }
    .finish-all-done .fad-icon { font-size: 2rem; flex-shrink: 0; }
    .finish-all-done .fad-title { font-weight: 800; color: var(--bamboo); font-size: .95rem; }
    .finish-all-done .fad-sub { font-size: .82rem; color: var(--text-muted); margin-top: 2px; }

    .finish-actions {
      display: flex;
      gap: 10px;
      justify-content: center;
      flex-wrap: wrap;
    }
    @media (max-width: 480px) {
      .finish-kanji { font-size: 2.6rem; }
      .finish-title { font-size: 1.3rem; }
      .finish-actions .bab-btn { flex: 1 1 140px; justify-content: center; }
    }

    /* ---- Quit confirm dialog ---- */
    .quit-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,.55);
      backdrop-filter: blur(2px);
      z-index: 500;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .quit-overlay.open { display: flex; }
    .quit-dialog {
      background: var(--card-bg);
      border: 2px solid var(--card-border);
      border-top: 4px solid var(--torii);
      border-radius: 18px;
      width: 100%;
      max-width: 380px;
      padding: 26px 24px 22px;
      box-shadow: 0 12px 48px rgba(0,0,0,.4);
      animation: quitIn .2s ease;
    }
    @keyframes quitIn {
      from { transform: translateY(16px); opacity: 0; }
      to   { transform: translateY(0);    opacity: 1; }
    }
    .quit-dialog-title { font-size: 1.1rem; font-weight: 800; margin-bottom: 10px; color: var(--text-main); }
    .quit-dialog-body  { font-size: .9rem; color: var(--text-muted); margin-bottom: 22px; line-height: 1.55; }
    .quit-dialog-actions { display: flex; gap: 10px; }
    .quit-dialog-actions button {
      flex: 1;
      padding: 11px;
      border-radius: 10px;
      font-size: .9rem;
      font-weight: 700;
      cursor: pointer;
      transition: opacity .18s, transform .12s;
    }
    .quit-dialog-actions button:hover { opacity: .85; transform: translateY(-1px); }
    .btn-stay {
      background: transparent;
      border: 1.5px solid var(--card-border);
      color: var(--text-main);
    }
    .btn-quit-confirm {
      background: var(--torii);
      border: 1.5px solid var(--torii);
      color: #fff;
    }
    [data-theme="dark"] .quit-dialog { background: #1e1a1a; border-color: #3a2e2e; }
    [data-theme="dark"] .btn-stay { border-color: #4a3a3a; color: #f1e9e9; }

    /* ---- Score Summary ---- */
    .score-summary {
      display: flex;
      gap: 14px;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    .score-card {
      flex: 1 1 120px;
      border-radius: 14px;
      padding: 18px 14px;
      text-align: center;
      border: 1.5px solid transparent;
      box-shadow: 0 2px 8px rgba(0,0,0,.05);
    }
    .score-card.correct {
      background: rgba(74,124,89,.10);
      border-color: rgba(74,124,89,.35);
    }
    .score-card.wrong {
      background: rgba(183,75,75,.10);
      border-color: rgba(183,75,75,.30);
    }
    .score-card.total {
      background: rgba(212,160,23,.09);
      border-color: rgba(212,160,23,.30);
    }
    .score-card-icon { font-size: 2rem; display: block; margin-bottom: 6px; }
    .score-card-num {
      font-size: 2.2rem;
      font-weight: 900;
      line-height: 1;
      margin-bottom: 4px;
    }
    .score-card.correct .score-card-num { color: var(--bamboo); }
    .score-card.wrong   .score-card-num { color: var(--torii); }
    .score-card.total   .score-card-num { color: var(--gold); }
    .score-card-label {
      font-size: .78rem;
      font-weight: 700;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: .04em;
    }
    .score-pct-bar-wrap {
      margin-bottom: 20px;
    }
    .score-pct-label {
      font-size: .85rem;
      color: var(--text-muted);
      margin-bottom: 6px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .score-pct-label strong { color: var(--text-main); font-size: 1rem; }
    .score-pct-bar {
      height: 10px;
      border-radius: 99px;
      background: var(--card-border);
      overflow: hidden;
    }
    .score-pct-fill {
      height: 100%;
      border-radius: 99px;
      background: linear-gradient(90deg, var(--bamboo), #6bbf80);
      transition: width .6s ease;
    }
    @media (max-width: 480px) {
      .score-card { padding: 14px 10px; }
      .score-card-num { font-size: 1.8rem; }
    }
  </style>
</head>
<body class="dashboard-page">

  <div class="page-loader" id="pageLoader"><span class="loader-kanji">桜</span></div>
  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <div class="topbar-actions">
      <a href="beranda.php" class="topbar-back" style="border: 2px solid #99711b; padding: 8px 12px; border-radius: 8px; text-decoration: none; display: inline-block;">← Beranda</a>
    </div>
  </header>

  <main class="dashboard-main">

    <?php if ($mode === 'list'): ?>
      <section class="welcome-section fade-up">
        <span class="welcome-kanji">言葉</span>
        <h1 class="welcome-title">Quiz Kotoba</h1>
        <p class="welcome-sub">Uji kosakata bahasa Jepang yang sudah kamu pelajari</p>
        <div class="section-divider"></div>
      </section>

      <!-- Banner: semua quiz sudah dikerjakan -->
      <?php if ($allDone): ?>
        <div class="all-done-banner fade-up">
          <div class="banner-icon">🏆</div>
          <div class="banner-text">
            <div class="banner-title">Semua Quiz Sudah Dikerjakan! おめでとう！</div>
            <div class="banner-sub">Kamu telah menyelesaikan semua <?= $totalQuizzes ?> quiz yang tersedia. Kamu bisa mengerjakan ulang quiz manapun kapan saja.</div>
          </div>
        </div>
      <?php elseif ($totalQuizzes > 0): ?>
        <div style="text-align:right; font-size:.82rem; color:var(--text-muted); margin-bottom:12px;">
          ✅ <?= $doneCount ?> / <?= $totalQuizzes ?> quiz selesai
        </div>
      <?php endif; ?>

      <div class="profile-card fade-up delay-1">
        <?php if (empty($quizzes)): ?>
          <div class="admin-panel-note" style="background:rgba(74,124,89,0.08); border-color:rgba(74,124,89,0.25);">
            <span class="icon">📭</span>
            <p>Belum ada quiz kotoba yang dipublikasikan. Cek kembali nanti.</p>
          </div>
        <?php else: ?>
          <?php foreach ($quizzes as $q): ?>
            <div class="quiz-card-item <?= $q['attempt_status'] === 'finished' ? 'card-done' : '' ?>">
              <div class="quiz-card-info">
                <div class="quiz-card-title"><?= htmlspecialchars($q['judul']) ?></div>
                <div class="quiz-card-meta">
                  <?= (int)$q['total_soal'] ?> soal &nbsp;&middot;&nbsp;
                  <?php if ((int)$q['duration_minutes'] > 0): ?>
                    <span class="duration-badge">⏱ <?= (int)$q['duration_minutes'] ?> menit</span>
                  <?php else: ?>
                    <span class="duration-badge no-limit">♾ Tanpa batas waktu</span>
                  <?php endif; ?>
                  <?php if (!empty($q['deskripsi'])): ?>
                    <br><?= htmlspecialchars($q['deskripsi']) ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="quiz-card-actions">
                <?php if ($q['attempt_status'] === 'finished'): ?>
                  <span class="quiz-status-tag tag-done">✅ Sudah Dikerjakan</span>
                  <form method="POST">
                    <input type="hidden" name="action" value="start_quiz">
                    <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                    <button type="submit" class="bab-btn bab-btn-outline">🔄 Kerjakan Lagi</button>
                  </form>
                <?php elseif ($q['attempt_status'] === 'in_progress'): ?>
                  <span class="quiz-status-tag tag-progress">Sedang Dikerjakan</span>
                  <form method="POST">
                    <input type="hidden" name="action" value="start_quiz">
                    <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                    <button type="submit" class="bab-btn bab-btn-primary">Lanjutkan</button>
                  </form>
                <?php else: ?>
                  <span class="quiz-status-tag tag-new">Baru</span>
                  <form method="POST">
                    <input type="hidden" name="action" value="start_quiz">
                    <input type="hidden" name="quiz_id" value="<?= $q['id'] ?>">
                    <button type="submit" class="bab-btn bab-btn-primary">Mulai</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    <?php elseif ($mode === 'take'): ?>
      <section class="welcome-section fade-up">
        <span class="welcome-kanji">言葉</span>
        <h1 class="welcome-title"><?= htmlspecialchars($quiz['judul']) ?></h1>
        <div class="section-divider"></div>
      </section>

      <!-- Timer + Progress -->
      <div class="quiz-status-card fade-up">
        <?php if ($hasTimer): ?>
          <div class="quiz-timer" id="quizTimer" data-remaining="<?= $remainingSeconds ?>">
            ⏱ <span id="timerDisplay">--:--</span>
          </div>
        <?php else: ?>
          <div class="quiz-timer no-limit" id="quizTimer" data-remaining="-1">
            ♾ Tanpa Batas Waktu
          </div>
        <?php endif; ?>
        <div class="quiz-progress-wrap">
          <div class="quiz-progress-label">
            <span>Soal <?= $soalIndex + 1 ?> dari <?= $totalSoal ?></span>
            <span><?= $soalIndex ?> / <?= $totalSoal ?> selesai</span>
          </div>
          <div class="quiz-progress-bar">
            <div class="quiz-progress-fill" style="width:<?= ($soalIndex / max(1,$totalSoal)) * 100 ?>%"></div>
          </div>
        </div>
      </div>

      <!-- Satu soal -->
      <form method="POST" id="soalForm">
        <input type="hidden" name="action" value="answer_soal">
        <input type="hidden" name="quiz_id" value="<?= $quiz['id'] ?>">
        <input type="hidden" name="soal_id" value="<?= $soalKini['id'] ?>">
        <input type="hidden" name="next_idx" value="<?= $soalIndex + 1 ?>">
        <input type="hidden" name="total" value="<?= $totalSoal ?>">

        <div class="soal-take-card fade-up">
          <div class="soal-question-box">
            <div class="soal-take-kotoba"><?= htmlspecialchars($soalKini['kotoba']) ?></div>
            <?php if (!empty($soalKini['cara_baca'])): ?>
              <div class="soal-take-cara-baca"><?= htmlspecialchars($soalKini['cara_baca']) ?></div>
            <?php endif; ?>
          </div>
          <div class="soal-take-options">
            <?php foreach (['a','b','c','d'] as $opt): ?>
              <?php if (!empty($soalKini['option_'.$opt])): ?>
              <label class="option-label <?= $existingSelected === $opt ? 'selected' : '' ?>">
                <input type="radio" name="pilihan" value="<?= $opt ?>"
                  onclick="markSelected(this)"
                  <?= $existingSelected === $opt ? 'checked' : '' ?>>
                <span><?= strtoupper($opt) ?>. <?= htmlspecialchars($soalKini['option_'.$opt]) ?></span>
              </label>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>

        <?php $isLast = ($soalIndex + 1 >= $totalSoal); ?>
        <div class="soal-nav-wrap">
          <!-- Tombol keluar: hanya membuka dialog konfirmasi, BUKAN form bersarang -->
          <button type="button" class="bab-btn bab-btn-outline" id="quitBtn" onclick="confirmQuit()">
            <span class="bab-btn-icon">✖</span>
            <span>Keluar Quiz</span>
          </button>
          <button type="submit" class="bab-btn bab-btn-primary" id="nextBtn">
            <span class="bab-btn-icon"><?= $isLast ? '✅' : '➡' ?></span>
            <span><?= $isLast ? 'Selesai' : 'Soal Berikutnya' ?></span>
          </button>
        </div>
      </form>

      <!-- Form keluar quiz: sengaja DIPISAH (sibling), bukan nested di dalam #soalForm,
           karena <form> tidak boleh bersarang — itu sebabnya tombol "Soal Berikutnya"
           sebelumnya bisa gagal/loncat balik ke daftar quiz. -->
      <form method="POST" id="quitForm" style="display:none;">
        <input type="hidden" name="action" value="quit_quiz">
        <input type="hidden" name="quiz_id" value="<?= $quiz['id'] ?>">
      </form>


    <?php elseif ($mode === 'finish'): ?>
      <section class="welcome-section fade-up">
        <span class="welcome-kanji"><?= $isTimeout ? '⏰' : '完了' ?></span>
        <h1 class="welcome-title"><?= $isTimeout ? 'Waktu Habis!' : 'Quiz Selesai!' ?></h1>
        <div class="section-divider"></div>
      </section>

      <div class="profile-card fade-up delay-1">
        <div class="finish-box <?= $isTimeout ? 'timeout-box' : '' ?>">
          <span class="finish-kanji"><?= $isTimeout ? '⏰' : '🎉' ?></span>
          <div class="finish-title">
            <?= $isTimeout ? 'Waktu Pengerjaan Habis' : 'Kamu sudah menyelesaikan quiz!' ?>
          </div>
          <div class="finish-sub">
            <?php if ($isTimeout): ?>
              Waktu untuk quiz <strong><?= htmlspecialchars($quiz['judul']) ?></strong> telah habis.<br>
              Jangan khawatir, kamu bisa mengerjakan ulang quiz ini kapan saja!
            <?php else: ?>
              Quiz <strong><?= htmlspecialchars($quiz['judul']) ?></strong> telah berhasil dikerjakan.<br>
              Terus berlatih untuk meningkatkan kosakata bahasa Jepangmu!
            <?php endif; ?>
          </div>
        </div>

        <!-- Ringkasan Jawaban -->
        <?php if (!$isTimeout): ?>
          <?php
            $totalCorrect  = (int)$attempt['total_correct'];
            $totalQ        = (int)$attempt['total_questions'];
            $totalWrong    = $totalQ - $totalCorrect;
            $pct           = $totalQ > 0 ? round($totalCorrect / $totalQ * 100) : 0;
          ?>
          <div class="score-summary">
            <div class="score-card correct">
              <span class="score-card-icon">✅</span>
              <div class="score-card-num"><?= $totalCorrect ?></div>
              <div class="score-card-label">Benar</div>
            </div>
            <div class="score-card wrong">
              <span class="score-card-icon">❌</span>
              <div class="score-card-num"><?= $totalWrong ?></div>
              <div class="score-card-label">Salah</div>
            </div>
            <div class="score-card total">
              <span class="score-card-icon">📝</span>
              <div class="score-card-num"><?= $totalQ ?></div>
              <div class="score-card-label">Total Soal</div>
            </div>
          </div>
          <div class="score-pct-bar-wrap">
            <div class="score-pct-label">
              <span>Persentase Benar</span>
              <strong><?= $pct ?>%</strong>
            </div>
            <div class="score-pct-bar">
              <div class="score-pct-fill" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
        <?php endif; ?>

        <!-- Notifikasi semua quiz selesai -->
        <?php if ($allDoneFinish): ?>
          <div class="finish-all-done">
            <div class="fad-icon">🏆</div>
            <div>
              <div class="fad-title">Semua Quiz Selesai! おめでとう！</div>
              <div class="fad-sub">Kamu telah menyelesaikan semua quiz yang tersedia. Kamu masih bisa mengerjakan ulang quiz manapun.</div>
            </div>
          </div>
        <?php endif; ?>

        <div class="finish-actions">
          <!-- Kerjakan lagi quiz ini -->
          <form method="POST">
            <input type="hidden" name="action" value="start_quiz">
            <input type="hidden" name="quiz_id" value="<?= $quiz['id'] ?>">
            <button type="submit" class="bab-btn bab-btn-outline">
              <span class="bab-btn-icon">🔄</span><span>Kerjakan Lagi</span>
            </button>
          </form>
          <!-- Kembali ke daftar -->
          <a href="kotoba.php" class="bab-btn bab-btn-primary">
            <span class="bab-btn-icon">📚</span><span>Daftar Quiz</span>
          </a>
        </div>
      </div>
    <?php endif; ?>

  </main>

  <?php if ($mode === 'take'): ?>
  <!-- Dialog konfirmasi keluar quiz -->
  <div class="quit-overlay" id="quitOverlay">
    <div class="quit-dialog">
      <div class="quit-dialog-title">⚠️ Keluar dari Quiz?</div>
      <div class="quit-dialog-body">
        Kemajuan soal yang sudah kamu jawab akan tetap tersimpan, namun quiz akan dianggap <strong>selesai</strong> dan timer dihentikan.<br><br>
        Yakin ingin keluar?
      </div>
      <div class="quit-dialog-actions">
        <button class="btn-stay" onclick="closeQuit()">Lanjutkan Quiz</button>
        <button class="btn-quit-confirm" onclick="doQuit()">Ya, Keluar</button>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/petals.js"></script>
  <?php if ($mode === 'take'): ?>
  <script>
    function markSelected(input) {
      const card = input.closest('.soal-take-card');
      card.querySelectorAll('.option-label').forEach(l => l.classList.remove('selected'));
      input.closest('.option-label').classList.add('selected');
    }

    // ── Quit dialog ──
    let timerInterval = null;
    let isSubmitting = false;

    function confirmQuit() {
      document.getElementById('quitOverlay').classList.add('open');
    }
    function closeQuit() {
      document.getElementById('quitOverlay').classList.remove('open');
    }
    function doQuit() {
      if (isSubmitting) return;
      isSubmitting = true;
      // Hentikan timer JS supaya tidak terus berjalan setelah keluar
      if (timerInterval) clearInterval(timerInterval);
      // Submit form keluar (terpisah dari form jawaban) ke server, ini akan
      // menghentikan attempt di sisi server (status='finished').
      document.getElementById('quitForm').submit();
    }
    // Tutup overlay kalau klik backdrop
    document.getElementById('quitOverlay').addEventListener('click', function(e) {
      if (e.target === this) closeQuit();
    });
    // Escape juga tutup dialog
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeQuit();
    });
    // Cegah submit ganda pada form jawaban (klik dobel / enter berulang)
    document.getElementById('soalForm').addEventListener('submit', function(e) {
      if (isSubmitting) { e.preventDefault(); return; }
      isSubmitting = true;
      const nextBtn = document.getElementById('nextBtn');
      if (nextBtn) nextBtn.disabled = true;
    });

    // ── Countdown timer — hanya jika ada batas waktu ──
    const timerBox = document.getElementById('quizTimer');
    const remaining = parseInt(timerBox.dataset.remaining, 10);

    <?php if ($hasTimer): ?>
    let sisa = remaining;
    const timerDisplay = document.getElementById('timerDisplay');

    function renderTimer() {
      const m = Math.floor(sisa / 60);
      const s = sisa % 60;
      timerDisplay.textContent = String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0');
      if (sisa <= 60) timerBox.classList.add('warning');
    }
    renderTimer();

    timerInterval = setInterval(() => {
      sisa--;
      if (sisa <= 0) {
        clearInterval(timerInterval);
        if (!isSubmitting) {
          isSubmitting = true;
          document.getElementById('soalForm').submit();
        }
        return;
      }
      renderTimer();
    }, 1000);
    <?php endif; ?>
  </script>
  <?php endif; ?>
</body>
</html>
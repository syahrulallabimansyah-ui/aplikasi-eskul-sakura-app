<?php
require_once 'config.php';
requireLogin();

$user    = getCurrentUser();
if (!$user) { session_destroy(); header('Location: index.php'); exit; }
$initial = strtoupper(mb_substr($user['name'], 0, 1));

// Status pemahaman kana — fallback query DB langsung jika getCurrentUser()
// tidak menyertakan kolom baru ini (lihat catatan yang sama di beranda.php).
$hiraganaStatusInit = 'belum_dijawab';
$katakanaStatusInit = 'belum_dijawab';
$hiraganaScoreInit  = null;
$katakanaScoreInit  = null;
if (
    array_key_exists('hiragana_status', $user) && array_key_exists('katakana_status', $user) &&
    array_key_exists('hiragana_exam_score', $user) && array_key_exists('katakana_exam_score', $user)
) {
    $hiraganaStatusInit = $user['hiragana_status'] ?? 'belum_dijawab';
    $katakanaStatusInit = $user['katakana_status'] ?? 'belum_dijawab';
    $hiraganaScoreInit  = $user['hiragana_exam_score'] ?? null;
    $katakanaScoreInit  = $user['katakana_exam_score'] ?? null;
} else {
    $dbInit = getDB();
    $stmtInit = $dbInit->prepare("SELECT hiragana_status, katakana_status, hiragana_exam_score, katakana_exam_score FROM users WHERE id = ?");
    $stmtInit->execute([$user['id']]);
    $rowInit = $stmtInit->fetch();
    if ($rowInit) {
        $hiraganaStatusInit = $rowInit['hiragana_status'] ?? 'belum_dijawab';
        $katakanaStatusInit = $rowInit['katakana_status'] ?? 'belum_dijawab';
        $hiraganaScoreInit  = $rowInit['hiragana_exam_score'] ?? null;
        $katakanaScoreInit  = $rowInit['katakana_exam_score'] ?? null;
    }
}

// Syarat buka kunci tombol "Sudah Paham": pernah ujian dengan nilai > 90
$hiraganaCanPahamInit = ($hiraganaScoreInit !== null && (float)$hiraganaScoreInit > 90);
$katakanaCanPahamInit = ($katakanaScoreInit !== null && (float)$katakanaScoreInit > 90);

// ── AJAX: user menjawab "sudah paham" langsung (tanpa ujian) ───────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'kana_set_paham') {
    $db   = getDB();
    $jenis = $_POST['jenis'] ?? ''; // 'hiragana' | 'katakana'
    if (!in_array($jenis, ['hiragana', 'katakana'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Jenis tidak valid']);
        exit;
    }

    // Kunci: tombol "Sudah Paham" hanya boleh dipakai kalau user sudah
    // pernah ujian pemahaman dengan nilai > 90. Cek ulang di server supaya
    // tidak bisa dibypass lewat request langsung (bukan cuma dikunci di UI).
    $colScoreCheck = $jenis . '_exam_score';
    $stmtCheck = $db->prepare("SELECT `$colScoreCheck` AS score FROM users WHERE id = ?");
    $stmtCheck->execute([$user['id']]);
    $rowCheck = $stmtCheck->fetch();
    $currentScore = $rowCheck ? $rowCheck['score'] : null;
    if ($currentScore === null || (float)$currentScore <= 90) {
        echo json_encode(['ok' => false, 'error' => 'Selesaikan ujian pemahaman dengan nilai di atas 90 dulu untuk membuka tombol ini.']);
        exit;
    }

    $col    = $jenis . '_status';
    $colTs  = $jenis . '_updated_at';
    $stmt = $db->prepare("UPDATE users SET `$col` = 'sudah_paham', `$colTs` = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    echo json_encode(['ok' => true, 'status' => 'sudah_paham']);
    exit;
}

// ── AJAX: submit hasil ujian pemahaman hiragana/katakana ───────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'kana_submit_exam') {
    $db      = getDB();
    $jenis   = $_POST['jenis'] ?? ''; // 'hiragana' | 'katakana'
    $correct = (int)($_POST['correct'] ?? 0);
    $total   = (int)($_POST['total'] ?? 0);

    if (!in_array($jenis, ['hiragana', 'katakana'], true) || $total <= 0) {
        echo json_encode(['ok' => false, 'error' => 'Data ujian tidak valid']);
        exit;
    }

    $correct = max(0, min($correct, $total));
    $score   = round(($correct / $total) * 100, 2);
    $passed  = ($score >= 100);

    $colStatus = $jenis . '_status';
    $colTs     = $jenis . '_updated_at';
    $colScore  = $jenis . '_exam_score';

    if ($passed) {
        $stmt = $db->prepare("UPDATE users SET `$colStatus` = 'lulus_ujian', `$colTs` = NOW(), `$colScore` = ? WHERE id = ?");
        $stmt->execute([$score, $user['id']]);
    } else {
        // Belum lulus 100% — simpan skor terakhir saja, status tetap belum paham
        $stmt = $db->prepare("UPDATE users SET `$colScore` = ? WHERE id = ?");
        $stmt->execute([$score, $user['id']]);
    }

    echo json_encode([
        'ok'     => true,
        'passed' => $passed,
        'score'  => $score,
        'status' => $passed ? 'lulus_ujian' : ($jenis . '_status_unchanged'),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Hiragana & Katakana</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    /* ═══ KANA PAGE STYLES ═══ */

    /* Tab bar */
    .kana-tabs {
      display: flex;
      gap: clamp(3px, 1.4vw, 6px);
      margin-bottom: 20px;
      background: var(--card-bg, #fff);
      border: 1px solid var(--card-border, #e8d5d5);
      border-radius: 14px;
      padding: clamp(3px, 1vw, 5px);
    }
    .kana-tab {
      flex: 1;
      min-width: 0; /* fix: tanpa ini, flex item tidak bisa menyusut lebih kecil
                       dari lebar teksnya (min-width default = auto), sehingga di
                       layar sempit tombol paling kanan ("Ujian") terdorong keluar
                       area yang terlihat/ter-klik dan tampak "hilang" */
      padding: clamp(7px, 2.6vw, 10px) clamp(1px, 1vw, 4px);
      border: none;
      border-radius: 10px;
      background: transparent;
      color: var(--mist, #999);
      /* fluid: menyesuaikan halus antara layar kecil (min) dan besar (max),
         jadi teks selalu proporsional dan tidak perlu terpotong ellipsis */
      font-size: clamp(0.64rem, 2.7vw, 0.85rem);
      font-weight: 700;
      cursor: pointer;
      transition: background 0.2s, color 0.2s, box-shadow 0.2s;
      font-family: inherit;
      letter-spacing: clamp(0em, 0.3vw, 0.02em);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis; /* jaring pengaman terakhir kalau masih kurang muat */
    }
    .kana-tab.active {
      background: var(--torii, #C0392B);
      color: #fff;
      box-shadow: 0 2px 10px rgba(192,57,43,0.3);
    }
    .kana-tab:not(.active):hover {
      background: rgba(192,57,43,0.08);
      color: var(--torii, #C0392B);
    }

    /* Search + filter bar */
    .kana-toolbar {
      display: flex;
      gap: 8px;
      align-items: center;
      margin-bottom: 20px;
      flex-wrap: wrap;
    }
    /* Row 1: search full-width on mobile */
    .kana-search-wrap {
      position: relative;
      flex: 1 1 100%;
      min-width: 0;
    }
    .kana-search {
      width: 100%;
      box-sizing: border-box;
      padding: 10px 14px 10px 38px;
      border: 1.5px solid var(--card-border, #e8d5d5);
      border-radius: 10px;
      background: var(--card-bg, #fff);
      color: var(--text);
      font-size: 0.9rem;
      font-family: inherit;
      outline: none;
      transition: border-color 0.2s;
    }
    .kana-search:focus { border-color: var(--torii, #C0392B); }
    .kana-search-icon {
      position: absolute;
      left: 11px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 1rem;
      pointer-events: none;
      color: var(--mist, #aaa);
    }
    /* Row 2: filter buttons + quiz toggle */
    .kana-filter-row {
      display: flex;
      gap: 6px;
      align-items: center;
      flex-wrap: wrap;
      width: 100%;
    }
    .kana-filter-btn {
      padding: 8px 12px;
      border: 1.5px solid var(--card-border, #e8d5d5);
      border-radius: 10px;
      background: var(--card-bg, #fff);
      color: #111;
      font-size: 0.8rem;
      font-weight: 600;
      cursor: pointer;
      transition: border-color 0.2s, background 0.2s, color 0.2s;
      font-family: inherit;
      white-space: nowrap;
    }
    .kana-filter-btn.active {
      background: var(--torii, #C0392B);
      color: #fff;
      border-color: var(--torii, #C0392B);
    }
    .kana-filter-btn:not(.active):hover {
      border-color: var(--torii, #C0392B);
      color: var(--torii, #C0392B);
    }
    /* Push quiz toggle to the right */
    .quiz-toggle-wrap {
      margin-left: auto;
      display: flex;
      align-items: center;
      gap: 7px;
      font-size: 0.82rem;
      color: var(--text);
      font-weight: 600;
      white-space: nowrap;
    }

    /* Section group label */
    .kana-group-label {
      font-size: 0.72rem;
      font-weight: 800;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--mist, #aaa);
      margin: 22px 0 10px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .kana-group-label::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--card-border, #e8d5d5);
    }

    /* Grid */
    .kana-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
      gap: 10px;
    }

    /* Kana card */
    .kana-card {
      background: var(--card-bg, #fff);
      border: 1.5px solid var(--card-border, #e8d5d5);
      border-radius: 14px;
      padding: 14px 8px 10px;
      text-align: center;
      cursor: pointer;
      transition: transform 0.18s, box-shadow 0.18s, border-color 0.18s, background 0.18s;
      position: relative;
      user-select: none;
      -webkit-tap-highlight-color: transparent;
    }
    .kana-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 6px 20px rgba(192,57,43,0.13);
      border-color: var(--torii, #C0392B);
    }
    .kana-card.flipped {
      background: linear-gradient(135deg, rgba(192,57,43,0.06), rgba(192,57,43,0.02));
      border-color: var(--torii, #C0392B);
    }
    .kana-card.memorized {
      border-color: var(--bamboo, #4a7c59);
      background: linear-gradient(135deg, rgba(74,124,89,0.07), rgba(74,124,89,0.02));
    }
    .kana-card.memorized .kana-char { color: var(--bamboo, #4a7c59); }

    /* Kana character */
    .kana-char {
      font-size: 2rem;
      line-height: 1.1;
      color: var(--torii, #C0392B);
      font-weight: 400;
      display: block;
      transition: opacity 0.15s;
    }
    .kana-card.flipped .kana-char { opacity: 0.25; font-size: 1rem; }

    /* Romaji — shown always below, or big when flipped */
    .kana-romaji {
      font-size: 0.78rem;
      font-weight: 700;
      letter-spacing: 0.06em;
      margin-top: 5px;
      color: #111 !important;
      display: block;
      transition: font-size 0.15s;
    }
    .kana-card.flipped .kana-romaji {
      font-size: 1.3rem;
      color: var(--torii, #C0392B);
    }

    /* Memorized tick */
    .kana-check {
      position: absolute;
      top: 6px;
      right: 7px;
      font-size: 0.75rem;
      opacity: 0;
      transition: opacity 0.2s;
      color: var(--bamboo, #4a7c59);
    }
    .kana-card.memorized .kana-check { opacity: 1; }

    /* Quiz mode overlay */
    .kana-card.quiz-mode .kana-romaji { opacity: 0; }
    .kana-card.quiz-mode.flipped .kana-romaji { opacity: 1; }

    /* Progress bar */
    .kana-progress-wrap {
      margin-bottom: 20px;
    }
    .kana-progress-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 0.8rem;
      color: var(--mist);
      margin-bottom: 6px;
      gap: 8px;
      flex-wrap: wrap;
    }
    .kana-progress-row strong { color: var(--bamboo, #4a7c59); }
    .kana-progress-bar {
      height: 6px;
      border-radius: 10px;
      background: var(--card-border, #e8d5d5);
      overflow: hidden;
    }
    .kana-progress-fill {
      height: 100%;
      border-radius: 10px;
      background: linear-gradient(90deg, var(--bamboo, #4a7c59), #6aaf83);
      transition: width 0.4s ease;
    }

    /* Empty state */
    .kana-empty {
      text-align: center;
      padding: 40px 20px;
      color: var(--mist);
      font-size: 0.9rem;
    }
    .kana-empty .icon { font-size: 2rem; display: block; margin-bottom: 8px; }

    /* Tooltip / detail panel */
    .kana-detail-panel {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: var(--card-bg, #fff);
      border-top: 2px solid var(--card-border, #e8d5d5);
      border-radius: 20px 20px 0 0;
      padding: 20px 24px 30px;
      box-shadow: 0 -8px 30px rgba(0,0,0,0.12);
      transform: translateY(100%);
      transition: transform 0.3s cubic-bezier(.32,.72,0,1);
      z-index: 200;
      max-width: 600px;
      margin: 0 auto;
      box-sizing: border-box;
    }
    .kana-detail-panel.open { transform: translateY(0); }
    .kana-detail-panel-handle {
      width: 40px; height: 4px;
      border-radius: 4px;
      background: var(--card-border, #ddd);
      margin: 0 auto 16px;
    }
    .kana-detail-big { font-size: 5rem; text-align: center; color: var(--torii, #C0392B); line-height: 1; margin-bottom: 6px; }
    .kana-detail-romaji { font-size: 1.6rem; text-align: center; font-weight: 800; color: var(--text); letter-spacing: 0.06em; }
    .kana-detail-pair {
      display: flex;
      gap: 12px;
      margin-top: 16px;
      justify-content: center;
    }
    .kana-detail-pair-card {
      flex: 1;
      max-width: 130px;
      background: var(--card-bg2, #f9f0f0);
      border: 1px solid var(--card-border, #e8d5d5);
      border-radius: 12px;
      padding: 12px;
      text-align: center;
    }
    .kana-detail-pair-label { font-size: 0.68rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--mist); margin-bottom: 6px; }
    .kana-detail-pair-char { font-size: 2.4rem; color: var(--torii, #C0392B); line-height: 1.1; }
    .kana-detail-close {
      position: absolute;
      top: 16px; right: 18px;
      background: none; border: none;
      font-size: 1.4rem; cursor: pointer; color: var(--mist);
      line-height: 1;
      padding: 4px 8px;
    }
    .kana-detail-actions {
      display: flex;
      gap: 10px;
      margin-top: 18px;
      justify-content: center;
    }
    .kana-action-btn {
      flex: 1;
      max-width: 160px;
      padding: 11px 16px;
      border-radius: 10px;
      font-size: 0.85rem;
      font-weight: 700;
      cursor: pointer;
      border: 1.5px solid;
      font-family: inherit;
      transition: background 0.2s, color 0.2s;
    }
    .btn-memorize {
      background: var(--bamboo, #4a7c59);
      color: #fff;
      border-color: var(--bamboo, #4a7c59);
    }
    .btn-memorize.active {
      background: transparent;
      color: var(--bamboo, #4a7c59);
    }
    .btn-close-panel {
      background: transparent;
      color: var(--mist);
      border-color: var(--card-border, #ddd);
    }

    /* Backdrop */
    .kana-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.35);
      z-index: 199;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.25s;
    }
    .kana-backdrop.open { opacity: 1; pointer-events: all; }

    /* Reset progress button */
    .kana-reset-btn {
      background: none;
      border: none;
      font-size: 0.75rem;
      color: var(--mist);
      cursor: pointer;
      padding: 0;
      text-decoration: underline;
      font-family: inherit;
      flex-shrink: 0;
    }
    .kana-reset-btn:hover { color: var(--torii, #C0392B); }

    /* Quiz toggle */
    .quiz-toggle {
      position: relative;
      width: 38px; height: 22px;
      cursor: pointer;
      flex-shrink: 0;
    }
    .quiz-toggle input { opacity: 0; width: 0; height: 0; }
    .quiz-slider {
      position: absolute;
      inset: 0;
      background: var(--card-border, #ddd);
      border-radius: 22px;
      transition: background 0.2s;
    }
    .quiz-slider::before {
      content: '';
      position: absolute;
      left: 3px; top: 3px;
      width: 16px; height: 16px;
      border-radius: 50%;
      background: #fff;
      transition: transform 0.2s;
    }
    .quiz-toggle input:checked + .quiz-slider { background: var(--torii, #C0392B); }
    .quiz-toggle input:checked + .quiz-slider::before { transform: translateX(16px); }

    /* ═══ UJIAN PEMAHAMAN STYLES ═══ */
    .ujian-status-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 14px;
    }
    .ujian-status-card {
      background: var(--card-bg, #fff);
      border: 1.5px solid var(--card-border, #e8d5d5);
      border-radius: 16px;
      padding: 18px;
    }
    .ujian-status-head {
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 14px;
    }
    .ujian-status-kanji {
      font-size: 2.2rem;
      color: var(--torii, #C0392B);
      line-height: 1;
      flex-shrink: 0;
    }
    .ujian-status-title {
      font-size: 1rem;
      font-weight: 800;
      color: var(--text);
      margin-bottom: 4px;
    }
    .ujian-status-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: 0.74rem;
      font-weight: 700;
      padding: 3px 10px;
      border-radius: 20px;
      background: rgba(150,150,150,0.15);
      color: var(--mist, #888);
    }
    .ujian-status-badge.badge-belum {
      background: rgba(231,76,60,0.12);
      color: #e74c3c;
    }
    .ujian-status-badge.badge-paham {
      background: rgba(74,124,89,0.14);
      color: var(--bamboo, #4a7c59);
    }
    .ujian-status-badge.badge-lulus {
      background: linear-gradient(135deg, rgba(192,57,43,0.14), rgba(192,57,43,0.06));
      color: var(--torii, #C0392B);
    }
    .ujian-status-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .ujian-action-btn {
      width: 100%;
      box-sizing: border-box;
      padding: 11px 14px;
      border-radius: 11px;
      font-size: 0.85rem;
      font-weight: 700;
      cursor: pointer;
      border: 1.5px solid;
      font-family: inherit;
      transition: background 0.2s, color 0.2s, transform 0.12s;
      text-align: center;
      white-space: normal;
      word-break: break-word;
      line-height: 1.35;
    }
    .ujian-action-btn:active { transform: scale(0.98); }
    .ujian-action-btn:disabled {
      background: rgba(150,150,150,0.18) !important;
      border-color: rgba(150,150,150,0.3) !important;
      color: var(--mist, #999) !important;
      cursor: not-allowed;
      opacity: 0.85;
    }
    .ujian-action-btn:disabled:active { transform: none; }
    .ujian-btn-paham {
      background: var(--bamboo, #4a7c59);
      color: #fff;
      border-color: var(--bamboo, #4a7c59);
    }
    .ujian-btn-paham:hover { background: #3d6b4a; }
    .ujian-btn-ujian {
      background: var(--torii, #C0392B);
      color: #fff;
      border-color: var(--torii, #C0392B);
    }
    .ujian-btn-ujian:hover { background: #a93226; }
    .ujian-btn-ghost {
      background: transparent;
      color: var(--text);
      border-color: var(--card-border, #ddd);
    }
    .ujian-btn-ghost:hover { border-color: var(--torii, #C0392B); color: var(--torii, #C0392B); }
    .ujian-status-note {
      font-size: 0.74rem;
      color: var(--mist);
      margin-top: 2px;
    }

    /* Exam running */
    .exam-top-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 10px;
    }
    .exam-progress-label {
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--mist);
    }
    .exam-progress-bar-wrap {
      height: 6px;
      border-radius: 10px;
      background: var(--card-border, #e8d5d5);
      overflow: hidden;
      margin-bottom: 24px;
    }
    .exam-progress-bar-fill {
      height: 100%;
      border-radius: 10px;
      background: linear-gradient(90deg, var(--torii, #C0392B), #e07b6b);
      transition: width 0.3s ease;
    }
    .exam-question-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      color: #a1781e;
      gap: 6px;
      padding: 10px 0 24px;
    }
    .exam-question-kana {
      font-size: 5rem;
      color: var(--torii, #C0392B);
      line-height: 1.1;
      margin-bottom: 4px;
    }
    .exam-question-hint {
      font-size: 0.82rem;
      color: var(--mist);
      margin-bottom: 18px;
    }
    .exam-options-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
      width: 100%;
      max-width: 380px;
    }
    .exam-option-btn {
      padding: 14px 10px;
      border-radius: 12px;
      border: 1.5px solid var(--card-border, #e8d5d5);
      background: var(--card-bg, #fff);
      color: var(--text);
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      transition: border-color 0.15s, background 0.15s, transform 0.1s, color 0.15s;
    }
    .exam-option-btn:hover:not(:disabled) {
      border-color: var(--torii, #C0392B);
      transform: translateY(-2px);
    }
    .exam-option-btn:disabled { cursor: default; }
    .exam-option-btn.opt-correct {
      background: rgba(74,124,89,0.14);
      border-color: var(--bamboo, #4a7c59);
      color: var(--bamboo, #4a7c59);
    }
    .exam-option-btn.opt-wrong {
      background: rgba(231,76,60,0.12);
      border-color: #e74c3c;
      color: #e74c3c;
    }

    /* Exam result */
    .exam-result-card {
      text-align: center;
      padding: 30px 10px;
    }
    .exam-result-emoji { font-size: 3.6rem; display: block; margin-bottom: 12px; }
    .exam-result-title { font-size: 1.4rem; font-weight: 800; color: var(--text); margin-bottom: 6px; }
    .exam-result-sub { font-size: 0.9rem; color: var(--mist); margin-bottom: 18px; }
    .exam-result-score {
      font-size: 2.4rem;
      font-weight: 900;
      color: var(--torii, #C0392B);
      margin-bottom: 4px;
    }
    .exam-result-detail {
      font-size: 0.85rem;
      color: var(--mist);
      margin-bottom: 24px;
    }
    .exam-result-actions {
      display: flex;
      gap: 12px;
      justify-content: center;
      flex-wrap: wrap;
    }

    /* Confirm modal (sudah paham) */
    .confirm-modal {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -45%) scale(0.96);
      background: var(--card-bg, #fff);
      border-radius: 20px;
      padding: 28px 26px 24px;
      box-shadow: 0 12px 40px rgba(0,0,0,0.18);
      width: 90%;
      max-width: 360px;
      text-align: center;
      z-index: 210;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.22s ease, transform 0.22s ease;
    }
    .confirm-modal.open {
      opacity: 1;
      pointer-events: all;
      transform: translate(-50%, -50%) scale(1);
    }
    .confirm-modal-icon { font-size: 2.6rem; margin-bottom: 10px; }
    .confirm-modal-title { font-size: 1.1rem; font-weight: 800; color: var(--text); margin-bottom: 8px; }
    .confirm-modal-text { font-size: 0.86rem; color: var(--mist); line-height: 1.5; margin: 0 0 20px; }
    .confirm-modal-actions { display: flex; gap: 10px; justify-content: center; }

    /* ── Responsive breakpoints ── */

    /* Small phones (≤ 360px) */
    @media (max-width: 360px) {
      .kana-grid { grid-template-columns: repeat(auto-fill, minmax(62px, 1fr)); gap: 6px; }
      .kana-char { font-size: 1.55rem; }
      .kana-card { padding: 10px 4px 7px; border-radius: 10px; }
      .kana-detail-big { font-size: 3.5rem; }
      .kana-action-btn { font-size: 0.8rem; padding: 9px 10px; }
      .exam-question-kana { font-size: 3.8rem; }
      .exam-options-grid { gap: 8px; }
      .exam-option-btn { padding: 12px 6px; font-size: 0.9rem; }
      /* Tombol & kartu ujian pemahaman — diperkecil agar tidak sempit/terpotong */
      .ujian-status-grid { grid-template-columns: 1fr; gap: 10px; }
      .ujian-status-card { padding: 14px 12px; }
      .ujian-status-head { gap: 10px; margin-bottom: 10px; }
      .ujian-status-kanji { font-size: 1.8rem; }
      .ujian-status-title { font-size: 0.92rem; }
      .ujian-action-btn { font-size: 0.78rem; padding: 10px 10px; }
    }

    /* Typical phones (≤ 480px) */
    @media (max-width: 480px) {
      .kana-grid { grid-template-columns: repeat(auto-fill, minmax(68px, 1fr)); gap: 7px; }
      .kana-char { font-size: 1.7rem; }
      .kana-card { padding: 12px 6px 8px; border-radius: 11px; }
      .kana-detail-big { font-size: 4rem; }
      .kana-detail-panel { padding: 18px 16px 28px; }
      .kana-detail-actions { flex-direction: row; }
      /* Tombol & kartu ujian pemahaman — 1 kolom penuh, lebih nyaman di HP */
      .ujian-status-grid { grid-template-columns: 1fr; gap: 12px; }
      .ujian-status-card { padding: 16px 14px; }
      .ujian-action-btn { font-size: 0.82rem; padding: 10px 12px; }
    }

    /* Tablet and up (≥ 640px): search + filters on same row */
    @media (min-width: 640px) {
      .kana-search-wrap { flex: 1 1 auto; }
      .kana-filter-row { flex-wrap: nowrap; }
    }

    /* ═══ FLASH CARD STYLES ═══ */
    .fc-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 20px;
      padding: 10px 0 20px;
    }

    /* Deck selector */
    .fc-deck-row {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      justify-content: center;
      width: 100%;
    }
    .fc-deck-btn {
      padding: 7px 14px;
      border: 1.5px solid var(--card-border, #e8d5d5);
      border-radius: 10px;
      background: var(--card-bg, #fff);
      color: #111;
      font-size: 0.78rem;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      transition: border-color 0.2s, background 0.2s, color 0.2s;
      white-space: nowrap;
    }
    .fc-deck-btn.active {
      background: var(--torii, #C0392B);
      color: #fff;
      border-color: var(--torii, #C0392B);
    }
    .fc-deck-btn:not(.active):hover {
      border-color: var(--torii, #C0392B);
      color: var(--torii, #C0392B);
    }

    /* Score bar */
    .fc-score-row {
      display: flex;
      gap: 16px;
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--mist);
    }
    .fc-score-row .fc-correct { color: var(--bamboo, #4a7c59); }
    .fc-score-row .fc-wrong   { color: var(--torii, #C0392B); }
    .fc-score-row .fc-remain  { color: #555; }

    /* Card wrapper – 3D flip */
    .fc-card-wrap {
      perspective: 900px;
      width: 100%;
      max-width: 340px;
      height: 220px;
      cursor: pointer;
    }
    .fc-card-inner {
      position: relative;
      width: 100%;
      height: 100%;
      transform-style: preserve-3d;
      transition: transform 0.45s cubic-bezier(.4,0,.2,1);
    }
    .fc-card-wrap.flipped .fc-card-inner {
      transform: rotateY(180deg);
    }
    .fc-face {
      position: absolute;
      inset: 0;
      border-radius: 20px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      backface-visibility: hidden;
      -webkit-backface-visibility: hidden;
      box-shadow: 0 8px 30px rgba(0,0,0,0.10);
      user-select: none;
    }
    .fc-front {
      background: var(--card-bg, #fff);
      border: 2px solid var(--card-border, #e8d5d5);
    }
    .fc-back {
      background: linear-gradient(135deg, rgba(192,57,43,0.07), rgba(192,57,43,0.02));
      border: 2px solid var(--torii, #C0392B);
      transform: rotateY(180deg);
    }
    .fc-kana-big {
      font-size: 5rem;
      color: var(--torii, #C0392B);
      line-height: 1;
    }
    .fc-hint {
      font-size: 0.75rem;
      color: var(--mist);
      margin-top: 10px;
      letter-spacing: 0.05em;
    }
    .fc-romaji-big {
      font-size: 2.4rem;
      font-weight: 800;
      color: var(--torii, #C0392B);
      letter-spacing: 0.06em;
    }
    .fc-kana-small {
      font-size: 1.1rem;
      color: var(--text);
      margin-top: 8px;
      opacity: 0.65;
    }
    .fc-group-tag {
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--mist);
      margin-top: 4px;
    }

    /* Progress dots */
    .fc-dots {
      display: flex;
      gap: 5px;
      flex-wrap: wrap;
      justify-content: center;
      max-width: 340px;
    }
    .fc-dot {
      width: 8px; height: 8px;
      border-radius: 50%;
      background: var(--card-border, #e8d5d5);
      transition: background 0.2s;
      flex-shrink: 0;
    }
    .fc-dot.current  { background: var(--torii, #C0392B); transform: scale(1.3); }
    .fc-dot.correct  { background: var(--bamboo, #4a7c59); }
    .fc-dot.wrong    { background: #e74c3c; }

    /* Answer buttons */
    .fc-answer-row {
      display: flex;
      gap: 12px;
      width: 100%;
      max-width: 340px;
    }
    .fc-answer-btn {
      flex: 1;
      padding: 13px 10px;
      border-radius: 13px;
      font-size: 0.9rem;
      font-weight: 800;
      cursor: pointer;
      border: 2px solid;
      font-family: inherit;
      transition: background 0.2s, transform 0.12s;
    }
    .fc-answer-btn:active { transform: scale(0.95); }
    .fc-btn-wrong {
      background: transparent;
      color: #e74c3c;
      border-color: #e74c3c;
    }
    .fc-btn-wrong:hover { background: rgba(231,76,60,0.08); }
    .fc-btn-correct {
      background: var(--bamboo, #4a7c59);
      color: #fff;
      border-color: var(--bamboo, #4a7c59);
    }
    .fc-btn-correct:hover { background: #3d6b4a; }
    .fc-answer-row.hidden { visibility: hidden; }

    /* Nav row */
    .fc-nav-row {
      display: flex;
      gap: 12px;
      align-items: center;
    }
    .fc-nav-btn {
      padding: 9px 18px;
      border-radius: 10px;
      border: 1.5px solid var(--card-border, #e8d5d5);
      background: var(--card-bg, #fff);
      color: var(--text);
      font-size: 0.85rem;
      font-weight: 700;
      cursor: pointer;
      font-family: inherit;
      transition: border-color 0.2s, color 0.2s;
    }
    .fc-nav-btn:hover { border-color: var(--torii, #C0392B); color: var(--torii, #C0392B); }
    .fc-nav-btn:disabled { opacity: 0.35; cursor: default; }
    .fc-counter {
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--mist);
      min-width: 60px;
      text-align: center;
    }

    /* Finish screen */
    .fc-finish {
      text-align: center;
      padding: 20px 10px;
    }
    .fc-finish-emoji { font-size: 3.5rem; display: block; margin-bottom: 10px; }
    .fc-finish-title { font-size: 1.4rem; font-weight: 800; color: var(--text); margin-bottom: 6px; }
    .fc-finish-sub   { font-size: 0.9rem; color: var(--mist); margin-bottom: 20px; }
    .fc-finish-score {
      display: flex;
      gap: 24px;
      justify-content: center;
      margin-bottom: 24px;
    }
    .fc-finish-score .score-block { text-align: center; }
    .fc-finish-score .score-num {
      font-size: 2rem;
      font-weight: 900;
      display: block;
    }
    .fc-finish-score .score-label {
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--mist);
    }
    .fc-restart-btn {
      padding: 12px 28px;
      border-radius: 12px;
      background: var(--torii, #C0392B);
      color: #fff;
      border: none;
      font-size: 0.9rem;
      font-weight: 800;
      cursor: pointer;
      font-family: inherit;
      transition: background 0.2s;
    }
    .fc-restart-btn:hover { background: #a93226; }

    /* Shuffle toggle */
    .fc-options-row {
      display: flex;
      gap: 14px;
      align-items: center;
      font-size: 0.8rem;
      color: var(--text);
      font-weight: 600;
    }
    .fc-options-row label { display: flex; align-items: center; gap: 6px; cursor: pointer; }

    @media (max-width: 360px) {
      .fc-kana-big { font-size: 3.8rem; }
      .fc-card-wrap { height: 185px; }
      .fc-romaji-big { font-size: 1.9rem; }
    }
  </style>
</head>
<body class="dashboard-page">

  <div class="page-loader" id="pageLoader">
    <span class="loader-kanji">桜</span>
  </div>

  <div class="asanoha-bg"></div>
  <div id="petals"></div>

  <header class="topbar">
    <div class="topbar-brand">桜 Sakura</div>
    <button class="theme-toggle" onclick="toggleTheme()" title="Ganti Tema">☀️</button>
    <a href="beranda.php" class="topbar-back" style="border: 2px solid #a1781e; padding: 8px 12px; border-radius: 8px; text-decoration: none; display: inline-block;">← Beranda</a>
  </header>

  <main class="dashboard-main">

    <section class="welcome-section fade-up">
      <span class="welcome-kanji">仮名</span>
      <h1 class="welcome-title">Hiragana & Katakana</h1>
      <p class="welcome-sub">Pelajari dan hafalkan aksara Jepang dasar. Klik kartu untuk melihat cara baca, centang yang sudah kamu hafal.</p>
      <div class="section-divider"></div>
    </section>

    <div class="profile-card fade-up delay-1">

      <!-- Tab: Hiragana / Katakana / Keduanya -->
      <div class="kana-tabs">
        <button class="kana-tab active" data-tab="hiragana" onclick="switchTab('hiragana')">ひ Hiragana</button>
        <button class="kana-tab" data-tab="katakana" onclick="switchTab('katakana')">カ Katakana</button>
        <button class="kana-tab" data-tab="both" onclick="switchTab('both')">ひカ Keduanya</button>
        <button class="kana-tab" data-tab="flashcard" onclick="switchTab('flashcard')">🃏 Flash Card</button>
        <button class="kana-tab" data-tab="ujian" onclick="switchTab('ujian')">📝 Ujian</button>
      </div>

      <!-- Progress -->
      <div class="kana-progress-wrap">
        <div class="kana-progress-row">
          <span>Sudah dihafal: <strong id="memorizedCount">0</strong> / <span id="totalCount">0</span></span>
          <button class="kana-reset-btn" onclick="confirmReset()">Reset hafalan</button>
        </div>
        <div class="kana-progress-bar">
          <div class="kana-progress-fill" id="progressFill" style="width:0%"></div>
        </div>
      </div>

      <!-- Toolbar: search + filter + quiz -->
      <div class="kana-toolbar">
        <div class="kana-search-wrap">
          <span class="kana-search-icon">🔍</span>
          <input type="text" class="kana-search" id="kanaSearch" placeholder="Cari aksara atau romaji…" oninput="renderGrid()">
        </div>
        <div class="kana-filter-row">
          <button class="kana-filter-btn active" data-filter="all" onclick="setFilter('all')">Semua</button>
          <button class="kana-filter-btn" data-filter="belum" onclick="setFilter('belum')">Belum hafal</button>
          <button class="kana-filter-btn" data-filter="sudah" onclick="setFilter('sudah')">Sudah hafal</button>
          <div class="quiz-toggle-wrap" title="Mode kuis: romaji disembunyikan, klik kartu untuk ungkap">
            <label class="quiz-toggle">
              <input type="checkbox" id="quizMode" onchange="renderGrid()">
              <span class="quiz-slider"></span>
            </label>
            <span>Mode Kuis</span>
          </div>
        </div>
      </div>

      <!-- Grid container -->
      <div id="kanaGridContainer"></div>

      <!-- ═══ FLASH CARD SECTION ═══ -->
      <div id="fcSection" style="display:none;">
        <div class="fc-container">

          <!-- Deck selector -->
          <div class="fc-deck-row" id="fcDeckRow">
            <button class="fc-deck-btn active" data-deck="all" onclick="fcSetDeck('all')">Semua</button>
            <button class="fc-deck-btn" data-deck="dasar" onclick="fcSetDeck('dasar')">Dasar (46)</button>
            <button class="fc-deck-btn" data-deck="dakuten" onclick="fcSetDeck('dakuten')">Dakuten</button>
            <button class="fc-deck-btn" data-deck="yoon" onclick="fcSetDeck('yoon')">Yoon</button>
            <button class="fc-deck-btn" data-deck="belum" onclick="fcSetDeck('belum')">Belum Hafal</button>
          </div>

          <!-- Options: shuffle -->
          <div class="fc-options-row">
            <label>
              <input type="checkbox" id="fcShuffle" onchange="fcRestart()">
              <span>Acak urutan</span>
            </label>
            <label>
              <input type="checkbox" id="fcKatakana">
              <span>Tampilkan Katakana</span>
            </label>
          </div>

          <!-- Score -->
          <div class="fc-score-row" id="fcScoreRow">
            <span class="fc-correct">✓ <span id="fcCorrectCount">0</span> benar</span>
            <span class="fc-wrong">✗ <span id="fcWrongCount">0</span> salah</span>
            <span class="fc-remain">⬡ <span id="fcRemainCount">0</span> tersisa</span>
          </div>

          <!-- Progress dots -->
          <div class="fc-dots" id="fcDots"></div>

          <!-- The flip card -->
          <div class="fc-card-wrap" id="fcCardWrap" onclick="fcFlip()">
            <div class="fc-card-inner" id="fcCardInner">
              <div class="fc-face fc-front">
                <div class="fc-kana-big" id="fcFrontChar">あ</div>
                <div class="fc-hint">Klik untuk lihat jawaban</div>
              </div>
              <div class="fc-face fc-back">
                <div class="fc-romaji-big" id="fcBackRomaji">a</div>
                <div class="fc-kana-small" id="fcBackKana"></div>
                <div class="fc-group-tag" id="fcBackGroup"></div>
              </div>
            </div>
          </div>

          <!-- Answer buttons (shown after flip) -->
          <div class="fc-answer-row hidden" id="fcAnswerRow">
            <button class="fc-answer-btn fc-btn-wrong" onclick="fcAnswer(false)">✗ Belum Hafal</button>
            <button class="fc-answer-btn fc-btn-correct" onclick="fcAnswer(true)">✓ Sudah Hafal</button>
          </div>

          <!-- Nav: prev / counter / next -->
          <div class="fc-nav-row">
            <button class="fc-nav-btn" id="fcPrevBtn" onclick="fcNav(-1)" disabled>← Sebelumnya</button>
            <span class="fc-counter" id="fcCounter">1 / 1</span>
            <button class="fc-nav-btn" id="fcNextBtn" onclick="fcNav(1)">Berikutnya →</button>
          </div>

        </div>

        <!-- Finish screen (hidden until done) -->
        <div class="fc-finish" id="fcFinish" style="display:none;">
          <span class="fc-finish-emoji" id="fcFinishEmoji">🎉</span>
          <div class="fc-finish-title" id="fcFinishTitle">Sesi Selesai!</div>
          <div class="fc-finish-sub" id="fcFinishSub">Hasil sesi kamu:</div>
          <div class="fc-finish-score">
            <div class="score-block">
              <span class="score-num" id="fcFinishCorrect" style="color:var(--bamboo,#4a7c59)">0</span>
              <span class="score-label">Benar</span>
            </div>
            <div class="score-block">
              <span class="score-num" id="fcFinishWrong" style="color:#e74c3c">0</span>
              <span class="score-label">Salah</span>
            </div>
            <div class="score-block">
              <span class="score-num" id="fcFinishPct" style="color:var(--torii,#C0392B)">0%</span>
              <span class="score-label">Skor</span>
            </div>
          </div>
          <button class="fc-restart-btn" onclick="fcRestart()">🔄 Ulangi Lagi</button>
        </div>
      </div><!-- /fcSection -->

      <!-- ═══ UJIAN PEMAHAMAN SECTION ═══ -->
      <div id="ujianSection" style="display:none;">

        <!-- ── Sub-langkah 1: status overview + pilihan jenis ── -->
        <div id="ujianHome">
          <p style="font-size:0.88rem; color:var(--mist); margin:0 0 18px;">
            Tandai sejauh mana pemahamanmu terhadap Hiragana dan Katakana. Jika kamu pilih "Belum Paham",
            kamu bisa membuktikannya lewat ujian — jawab benar <strong>100%</strong> untuk dinyatakan paham.
          </p>

          <div class="ujian-status-grid">
            <!-- Hiragana card -->
            <div class="ujian-status-card">
              <div class="ujian-status-head">
                <span class="ujian-status-kanji">ひ</span>
                <div>
                  <div class="ujian-status-title" style="color:brown">Hiragana</div>
                  <div class="ujian-status-badge" id="badgeHiragana">Memuat…</div>
                </div>
              </div>
              <div class="ujian-status-actions" id="actionsHiragana"></div>
            </div>

            <!-- Katakana card -->
            <div class="ujian-status-card">
              <div class="ujian-status-head">
                <span class="ujian-status-kanji">カ</span>
                <div>
                  <div class="ujian-status-title" style="color: brown">Katakana</div>
                  <div class="ujian-status-badge" id="badgeKatakana">Memuat…</div>
                </div>
              </div>
              <div class="ujian-status-actions" id="actionsKatakana"></div>
            </div>
          </div>
        </div><!-- /ujianHome -->

        <!-- ── Sub-langkah 2: konfirmasi sudah paham (langsung) ── -->
        <!-- (modal dipakai untuk ini, lihat #confirmPahamModal di bawah) -->

        <!-- ── Sub-langkah 3: ujian pilihan ganda berlangsung ── -->
        <div id="ujianExamRunning" style="display:none;">
          <div class="exam-top-row">
            <button style="color: #ff2200" class="kana-reset-btn" onclick="cancelExam()">← Batal &amp; kembali</button>
            <span class="exam-progress-label" id="examProgressLabel">Soal 1 / 10</span>
          </div>

          <div class="exam-progress-bar-wrap">
            <div class="exam-progress-bar-fill" id="examProgressFill" style="width:0%"></div>
          </div>

          <div class="exam-question-card">
            <div class="exam-question-kana" id="examQuestionChar">あ</div>
            <div class="exam-question-hint">Pilih romaji yang tepat untuk aksara di atas</div>
            <div class="exam-options-grid" id="examOptionsGrid"></div>
          </div>
        </div><!-- /ujianExamRunning -->

        <!-- ── Sub-langkah 4: hasil ujian ── -->
        <div id="ujianExamResult" style="display:none;">
          <div class="exam-result-card">
            <span class="exam-result-emoji" id="examResultEmoji">🎉</span>
            <div class="exam-result-title" id="examResultTitle">Selamat!</div>
            <div class="exam-result-sub" id="examResultSub">Kamu lulus ujian pemahaman.</div>
            <div class="exam-result-score" id="examResultScore">100%</div>
            <div class="exam-result-detail" id="examResultDetail">10 / 10 benar</div>
            <div class="exam-result-actions">
              <button class="fc-restart-btn" onclick="retryExam()">🔄 Ulangi Ujian</button>
              <button class="kana-action-btn btn-close-panel" style="flex:none; padding:11px 22px;" onclick="backToUjianHome()">Kembali</button>
            </div>
          </div>
        </div><!-- /ujianExamResult -->

      </div><!-- /ujianSection -->

    </div><!-- /profile-card -->
  </main>

  <!-- Detail bottom sheet -->
  <div class="kana-backdrop" id="kanaBackdrop" onclick="closeDetail()"></div>
  <div class="kana-detail-panel" id="kanaDetailPanel">
    <div class="kana-detail-panel-handle"></div>
    <button class="kana-detail-close" onclick="closeDetail()">×</button>
    <div class="kana-detail-big" id="detailChar"></div>
    <div class="kana-detail-romaji" id="detailRomaji"></div>
    <div class="kana-detail-pair" id="detailPair"></div>
    <div class="kana-detail-actions">
      <button class="kana-action-btn btn-memorize" id="detailMemBtn" onclick="toggleMemorizeFromDetail()">✓ Tandai Hafal</button>
      <button class="kana-action-btn btn-close-panel" onclick="closeDetail()">Tutup</button>
    </div>
  </div>

  <!-- Modal konfirmasi "Sudah Paham" -->
  <div class="kana-backdrop" id="confirmPahamBackdrop" onclick="closeConfirmPahamModal()"></div>
  <div class="confirm-modal" id="confirmPahamModal">
    <div class="confirm-modal-icon" id="confirmPahamIcon">✅</div>
    <div class="confirm-modal-title" id="confirmPahamTitle">Tandai sudah paham?</div>
    <p class="confirm-modal-text" id="confirmPahamText">
      Kamu akan ditandai sudah paham. Catatan ini akan terlihat oleh Admin.
    </p>
    <div class="confirm-modal-actions">
      <button class="kana-action-btn btn-close-panel" onclick="closeConfirmPahamModal()">Batal</button>
      <button class="kana-action-btn btn-memorize" id="confirmPahamBtn" onclick="confirmSetPaham()">Ya, Saya Paham</button>
    </div>
  </div>

  <div class="toast" id="toast"></div>

  <script src="js/theme.js"></script>
  <script src="js/auth.js"></script>
  <script src="js/petals.js"></script>
  <script>
  // ═══════════════════════════════════════════════════════
  //  STATUS PEMAHAMAN USER (dari server)
  // ═══════════════════════════════════════════════════════
  let kanaStatus = {
    hiragana: <?= json_encode($hiraganaStatusInit) ?>,
    katakana: <?= json_encode($katakanaStatusInit) ?>,
  };

  // Kunci tombol "Sudah Paham": true kalau nilai ujian terakhir > 90
  let kanaCanPaham = {
    hiragana: <?= json_encode($hiraganaCanPahamInit) ?>,
    katakana: <?= json_encode($katakanaCanPahamInit) ?>,
  };
  // Nilai ujian terakhir (null kalau belum pernah ujian)
  let kanaExamScore = {
    hiragana: <?= json_encode($hiraganaScoreInit !== null ? (float)$hiraganaScoreInit : null) ?>,
    katakana: <?= json_encode($katakanaScoreInit !== null ? (float)$katakanaScoreInit : null) ?>,
  };

  // ═══════════════════════════════════════════════════════
  //  DATA: Hiragana & Katakana
  // ═══════════════════════════════════════════════════════
  const HIRAGANA = [
    // Gojuuon dasar
    { group:'Vokal', h:'あ', k:'ア', r:'a' },
    { group:'Vokal', h:'い', k:'イ', r:'i' },
    { group:'Vokal', h:'う', k:'ウ', r:'u' },
    { group:'Vokal', h:'え', k:'エ', r:'e' },
    { group:'Vokal', h:'お', k:'オ', r:'o' },

    { group:'K', h:'か', k:'カ', r:'ka' },
    { group:'K', h:'き', k:'キ', r:'ki' },
    { group:'K', h:'く', k:'ク', r:'ku' },
    { group:'K', h:'け', k:'ケ', r:'ke' },
    { group:'K', h:'こ', k:'コ', r:'ko' },

    { group:'S', h:'さ', k:'サ', r:'sa' },
    { group:'S', h:'し', k:'シ', r:'shi' },
    { group:'S', h:'す', k:'ス', r:'su' },
    { group:'S', h:'せ', k:'セ', r:'se' },
    { group:'S', h:'そ', k:'ソ', r:'so' },

    { group:'T', h:'た', k:'タ', r:'ta' },
    { group:'T', h:'ち', k:'チ', r:'chi' },
    { group:'T', h:'つ', k:'ツ', r:'tsu' },
    { group:'T', h:'て', k:'テ', r:'te' },
    { group:'T', h:'と', k:'ト', r:'to' },

    { group:'N', h:'な', k:'ナ', r:'na' },
    { group:'N', h:'に', k:'ニ', r:'ni' },
    { group:'N', h:'ぬ', k:'ヌ', r:'nu' },
    { group:'N', h:'ね', k:'ネ', r:'ne' },
    { group:'N', h:'の', k:'ノ', r:'no' },

    { group:'H', h:'は', k:'ハ', r:'ha' },
    { group:'H', h:'ひ', k:'ヒ', r:'hi' },
    { group:'H', h:'ふ', k:'フ', r:'fu' },
    { group:'H', h:'へ', k:'ヘ', r:'he' },
    { group:'H', h:'ほ', k:'ホ', r:'ho' },

    { group:'M', h:'ま', k:'マ', r:'ma' },
    { group:'M', h:'み', k:'ミ', r:'mi' },
    { group:'M', h:'む', k:'ム', r:'mu' },
    { group:'M', h:'め', k:'メ', r:'me' },
    { group:'M', h:'も', k:'モ', r:'mo' },

    { group:'Y', h:'や', k:'ヤ', r:'ya' },
    { group:'Y', h:'ゆ', k:'ユ', r:'yu' },
    { group:'Y', h:'よ', k:'ヨ', r:'yo' },

    { group:'R', h:'ら', k:'ラ', r:'ra' },
    { group:'R', h:'り', k:'リ', r:'ri' },
    { group:'R', h:'る', k:'ル', r:'ru' },
    { group:'R', h:'れ', k:'レ', r:'re' },
    { group:'R', h:'ろ', k:'ロ', r:'ro' },

    { group:'W', h:'わ', k:'ワ', r:'wa' },
    { group:'W', h:'を', k:'ヲ', r:'wo' },
    { group:'N ん', h:'ん', k:'ン', r:'n' },

    // Dakuten (voiced)
    { group:'G (dakuten)', h:'が', k:'ガ', r:'ga' },
    { group:'G (dakuten)', h:'ぎ', k:'ギ', r:'gi' },
    { group:'G (dakuten)', h:'ぐ', k:'グ', r:'gu' },
    { group:'G (dakuten)', h:'げ', k:'ゲ', r:'ge' },
    { group:'G (dakuten)', h:'ご', k:'ゴ', r:'go' },

    { group:'Z (dakuten)', h:'ざ', k:'ザ', r:'za' },
    { group:'Z (dakuten)', h:'じ', k:'ジ', r:'ji' },
    { group:'Z (dakuten)', h:'ず', k:'ズ', r:'zu' },
    { group:'Z (dakuten)', h:'ぜ', k:'ゼ', r:'ze' },
    { group:'Z (dakuten)', h:'ぞ', k:'ゾ', r:'zo' },

    { group:'D (dakuten)', h:'だ', k:'ダ', r:'da' },
    { group:'D (dakuten)', h:'ぢ', k:'ヂ', r:'ji' },
    { group:'D (dakuten)', h:'づ', k:'ヅ', r:'zu' },
    { group:'D (dakuten)', h:'で', k:'デ', r:'de' },
    { group:'D (dakuten)', h:'ど', k:'ド', r:'do' },

    { group:'B (dakuten)', h:'ば', k:'バ', r:'ba' },
    { group:'B (dakuten)', h:'び', k:'ビ', r:'bi' },
    { group:'B (dakuten)', h:'ぶ', k:'ブ', r:'bu' },
    { group:'B (dakuten)', h:'べ', k:'ベ', r:'be' },
    { group:'B (dakuten)', h:'ぼ', k:'ボ', r:'bo' },

    // Handakuten
    { group:'P (handakuten)', h:'ぱ', k:'パ', r:'pa' },
    { group:'P (handakuten)', h:'ぴ', k:'ピ', r:'pi' },
    { group:'P (handakuten)', h:'ぷ', k:'プ', r:'pu' },
    { group:'P (handakuten)', h:'ぺ', k:'ペ', r:'pe' },
    { group:'P (handakuten)', h:'ぽ', k:'ポ', r:'po' },

    // Yoon (kombinasi)
    { group:'KY (yoon)', h:'きゃ', k:'キャ', r:'kya' },
    { group:'KY (yoon)', h:'きゅ', k:'キュ', r:'kyu' },
    { group:'KY (yoon)', h:'きょ', k:'キョ', r:'kyo' },

    { group:'SH (yoon)', h:'しゃ', k:'シャ', r:'sha' },
    { group:'SH (yoon)', h:'しゅ', k:'シュ', r:'shu' },
    { group:'SH (yoon)', h:'しょ', k:'ショ', r:'sho' },

    { group:'CH (yoon)', h:'ちゃ', k:'チャ', r:'cha' },
    { group:'CH (yoon)', h:'ちゅ', k:'チュ', r:'chu' },
    { group:'CH (yoon)', h:'ちょ', k:'チョ', r:'cho' },

    { group:'NY (yoon)', h:'にゃ', k:'ニャ', r:'nya' },
    { group:'NY (yoon)', h:'にゅ', k:'ニュ', r:'nyu' },
    { group:'NY (yoon)', h:'にょ', k:'ニョ', r:'nyo' },

    { group:'HY (yoon)', h:'ひゃ', k:'ヒャ', r:'hya' },
    { group:'HY (yoon)', h:'ひゅ', k:'ヒュ', r:'hyu' },
    { group:'HY (yoon)', h:'ひょ', k:'ヒョ', r:'hyo' },

    { group:'MY (yoon)', h:'みゃ', k:'ミャ', r:'mya' },
    { group:'MY (yoon)', h:'みゅ', k:'ミュ', r:'myu' },
    { group:'MY (yoon)', h:'みょ', k:'ミョ', r:'myo' },

    { group:'RY (yoon)', h:'りゃ', k:'リャ', r:'rya' },
    { group:'RY (yoon)', h:'りゅ', k:'リュ', r:'ryu' },
    { group:'RY (yoon)', h:'りょ', k:'リョ', r:'ryo' },

    { group:'GY (yoon)', h:'ぎゃ', k:'ギャ', r:'gya' },
    { group:'GY (yoon)', h:'ぎゅ', k:'ギュ', r:'gyu' },
    { group:'GY (yoon)', h:'ぎょ', k:'ギョ', r:'gyo' },

    { group:'J (yoon)', h:'じゃ', k:'ジャ', r:'ja' },
    { group:'J (yoon)', h:'じゅ', k:'ジュ', r:'ju' },
    { group:'J (yoon)', h:'じょ', k:'ジョ', r:'jo' },

    { group:'BY (yoon)', h:'びゃ', k:'ビャ', r:'bya' },
    { group:'BY (yoon)', h:'びゅ', k:'ビュ', r:'byu' },
    { group:'BY (yoon)', h:'びょ', k:'ビョ', r:'byo' },

    { group:'PY (yoon)', h:'ぴゃ', k:'ピャ', r:'pya' },
    { group:'PY (yoon)', h:'ぴゅ', k:'ピュ', r:'pyu' },
    { group:'PY (yoon)', h:'ぴょ', k:'ピョ', r:'pyo' },
  ];

  // ═══════════════════════════════════════════════════════
  //  STATE
  // ═══════════════════════════════════════════════════════
  let currentTab    = 'hiragana';
  let currentFilter = 'all';
  let memorized     = loadMemorized();
  let flipped       = new Set();
  let detailIdx     = null; // index into HIRAGANA[]

  function loadMemorized() {
    try {
      const raw = localStorage.getItem('sakura_kana_memorized');
      return raw ? new Set(JSON.parse(raw)) : new Set();
    } catch { return new Set(); }
  }
  function saveMemorized() {
    localStorage.setItem('sakura_kana_memorized', JSON.stringify([...memorized]));
  }

  // Key for a kana entry: romaji + hiragana so duplicate romaji (ji, zu) stay distinct
  function kanaKey(entry) { return entry.h + '_' + entry.r; }

  // ═══════════════════════════════════════════════════════
  //  TAB / FILTER
  // ═══════════════════════════════════════════════════════
  function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.kana-tab').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    flipped.clear();

    const isFlash = tab === 'flashcard';
    const isUjian = tab === 'ujian';
    document.getElementById('kanaGridContainer').style.display = (isFlash || isUjian) ? 'none' : '';
    document.getElementById('fcSection').style.display = isFlash ? '' : 'none';
    document.getElementById('ujianSection').style.display = isUjian ? '' : 'none';

    // Hide progress bar + toolbar when in flashcard/ujian mode
    document.querySelector('.kana-progress-wrap').style.display = (isFlash || isUjian) ? 'none' : '';
    document.querySelector('.kana-toolbar').style.display = (isFlash || isUjian) ? 'none' : '';

    if (isFlash) {
      fcInit();
    } else if (isUjian) {
      backToUjianHome();
    } else {
      renderGrid();
    }
  }

  function setFilter(f) {
    currentFilter = f;
    document.querySelectorAll('.kana-filter-btn').forEach(b => b.classList.toggle('active', b.dataset.filter === f));
    renderGrid();
  }

  // ═══════════════════════════════════════════════════════
  //  RENDER
  // ═══════════════════════════════════════════════════════
  function getVisibleEntries() {
    const q    = document.getElementById('kanaSearch').value.trim().toLowerCase();
    const quiz = document.getElementById('quizMode').checked;

    return HIRAGANA.filter(e => {
      // Search filter
      if (q) {
        const match = e.h.includes(q) || e.k.includes(q) || e.r.toLowerCase().includes(q) || e.group.toLowerCase().includes(q);
        if (!match) return false;
      }
      // Memorized filter
      const key = kanaKey(e);
      if (currentFilter === 'sudah'  && !memorized.has(key)) return false;
      if (currentFilter === 'belum'  &&  memorized.has(key)) return false;
      return true;
    });
  }

  function renderGrid() {
    const entries = getVisibleEntries();
    const quiz    = document.getElementById('quizMode').checked;
    const container = document.getElementById('kanaGridContainer');

    // Update progress (always count all, not filtered)
    const total = HIRAGANA.length;
    const done  = [...memorized].filter(k => HIRAGANA.some(e => kanaKey(e) === k)).length;
    document.getElementById('memorizedCount').textContent = done;
    document.getElementById('totalCount').textContent     = total;
    document.getElementById('progressFill').style.width   = (total > 0 ? (done/total*100) : 0) + '%';

    if (entries.length === 0) {
      container.innerHTML = '<div class="kana-empty"><span class="icon">🔍</span>Tidak ada aksara yang cocok.</div>';
      return;
    }

    // Group by entry.group
    const groups = {};
    entries.forEach(e => {
      if (!groups[e.group]) groups[e.group] = [];
      groups[e.group].push(e);
    });

    let html = '';
    for (const [groupName, items] of Object.entries(groups)) {
      html += `<div class="kana-group-label">${groupName}</div>`;
      html += `<div class="kana-grid">`;
      items.forEach(e => {
        const key     = kanaKey(e);
        const isMem   = memorized.has(key);
        const isFlip  = flipped.has(key);
        const char    = currentTab === 'katakana' ? e.k : (currentTab === 'both' ? e.h + ' ' + e.k : e.h);
        const tabKey  = currentTab + '_' + key; // for flip state

        html += `<div class="kana-card ${isMem ? 'memorized' : ''} ${isFlip ? 'flipped' : ''} ${quiz ? 'quiz-mode' : ''}"
                      data-key="${escAttr(key)}"
                      onclick="handleCardClick('${escAttr(key)}', event)">
          <span class="kana-check">✓</span>
          <span class="kana-char">${escHtml(char)}</span>
          <span class="kana-romaji">${escHtml(e.r)}</span>
        </div>`;
      });
      html += `</div>`;
    }
    container.innerHTML = html;
  }

  // ═══════════════════════════════════════════════════════
  //  CARD INTERACTION
  // ═══════════════════════════════════════════════════════
  function handleCardClick(key, event) {
    const quiz = document.getElementById('quizMode').checked;
    if (quiz) {
      // In quiz mode: first click reveals, second click memorizes
      if (flipped.has(key)) {
        toggleMemorize(key);
        flipped.delete(key);
        renderGrid();
      } else {
        flipped.add(key);
        renderGrid();
      }
    } else {
      // Normal mode: open detail panel
      openDetail(key);
    }
  }

  // ═══════════════════════════════════════════════════════
  //  DETAIL PANEL
  // ═══════════════════════════════════════════════════════
  function openDetail(key) {
    const e = HIRAGANA.find(x => kanaKey(x) === key);
    if (!e) return;
    detailIdx = key;

    document.getElementById('detailChar').textContent   = currentTab === 'katakana' ? e.k : e.h;
    document.getElementById('detailRomaji').textContent = e.r;

    // Pair card showing both scripts
    document.getElementById('detailPair').innerHTML = `
      <div class="kana-detail-pair-card">
        <div class="kana-detail-pair-label">Hiragana</div>
        <div class="kana-detail-pair-char">${escHtml(e.h)}</div>
      </div>
      <div class="kana-detail-pair-card">
        <div class="kana-detail-pair-label">Katakana</div>
        <div class="kana-detail-pair-char">${escHtml(e.k)}</div>
      </div>
    `;

    updateDetailBtn(key);
    document.getElementById('kanaDetailPanel').classList.add('open');
    document.getElementById('kanaBackdrop').classList.add('open');
    document.body.style.overflow = 'hidden';
  }

  function updateDetailBtn(key) {
    const btn = document.getElementById('detailMemBtn');
    if (memorized.has(key)) {
      btn.textContent = '✓ Sudah Dihafal';
      btn.classList.add('active');
    } else {
      btn.textContent = '✓ Tandai Hafal';
      btn.classList.remove('active');
    }
  }

  function closeDetail() {
    document.getElementById('kanaDetailPanel').classList.remove('open');
    document.getElementById('kanaBackdrop').classList.remove('open');
    document.body.style.overflow = '';
    detailIdx = null;
  }

  function toggleMemorizeFromDetail() {
    if (!detailIdx) return;
    toggleMemorize(detailIdx);
    updateDetailBtn(detailIdx);
    renderGrid();
  }

  // ═══════════════════════════════════════════════════════
  //  MEMORIZE TOGGLE
  // ═══════════════════════════════════════════════════════
  function toggleMemorize(key) {
    if (memorized.has(key)) {
      memorized.delete(key);
    } else {
      memorized.add(key);
    }
    saveMemorized();
  }

  // ═══════════════════════════════════════════════════════
  //  RESET
  // ═══════════════════════════════════════════════════════
  function confirmReset() {
    if (!confirm('Reset semua tanda "sudah hafal"? Progress akan hilang.')) return;
    memorized.clear();
    saveMemorized();
    renderGrid();
    showToast('Progress direset.', 'info');
  }

  // ═══════════════════════════════════════════════════════
  //  TOAST (gunakan fungsi dari app jika ada, fallback manual)
  // ═══════════════════════════════════════════════════════
  function showToast(msg, type) {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.className = 'toast show' + (type === 'error' ? ' toast-error' : type === 'info' ? ' toast-info' : '');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.className = 'toast', 3000);
  }

  // ═══════════════════════════════════════════════════════
  //  UTILS
  // ═══════════════════════════════════════════════════════
  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }
  function escAttr(s) {
    return String(s).replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  // ═══════════════════════════════════════════════════════
  //  FLASH CARD ENGINE
  // ═══════════════════════════════════════════════════════

  let fcDeck       = 'all';   // current deck key
  let fcQueue      = [];      // array of HIRAGANA entries for this session
  let fcIndex      = 0;       // current position in fcQueue
  let fcResults    = [];      // 'correct' | 'wrong' | 'pending' per card
  let fcFlipped_   = false;   // whether current card is face-up
  let fcFinished   = false;

  // Deck definitions (filter function per key)
  const FC_DECKS = {
    all:    () => true,
    dasar:  e => ['Vokal','K','S','T','N','H','M','Y','R','W','N ん'].includes(e.group),
    dakuten:e => e.group.includes('dakuten') || e.group.includes('handakuten') || ['G (dakuten)','Z (dakuten)','D (dakuten)','B (dakuten)','P (handakuten)'].includes(e.group),
    yoon:   e => e.group.includes('yoon'),
    belum:  e => !memorized.has(kanaKey(e)),
  };

  function fcBuildQueue() {
    const filterFn = FC_DECKS[fcDeck] || (() => true);
    let q = HIRAGANA.filter(filterFn);
    if (document.getElementById('fcShuffle') && document.getElementById('fcShuffle').checked) {
      // Fisher-Yates shuffle
      for (let i = q.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [q[i], q[j]] = [q[j], q[i]];
      }
    }
    return q;
  }

  function fcInit() {
    fcQueue    = fcBuildQueue();
    fcIndex    = 0;
    fcResults  = fcQueue.map(() => 'pending');
    fcFlipped_ = false;
    fcFinished = false;
    document.getElementById('fcFinish').style.display    = 'none';
    document.getElementById('fcSection').querySelector('.fc-container').style.display = 'flex';
    fcRender();
  }

  function fcSetDeck(deck) {
    fcDeck = deck;
    document.querySelectorAll('.fc-deck-btn').forEach(b => b.classList.toggle('active', b.dataset.deck === deck));
    fcRestart();
  }

  function fcRestart() {
    fcInit();
  }

  function fcRender() {
    if (!fcQueue.length) {
      document.getElementById('fcSection').querySelector('.fc-container').style.display = 'none';
      document.getElementById('fcFinish').style.display = 'block';
      document.getElementById('fcFinishEmoji').textContent  = '🃏';
      document.getElementById('fcFinishTitle').textContent  = 'Deck Kosong!';
      document.getElementById('fcFinishSub').textContent    = 'Tidak ada kartu di deck ini.';
      document.getElementById('fcFinishCorrect').textContent = '—';
      document.getElementById('fcFinishWrong').textContent   = '—';
      document.getElementById('fcFinishPct').textContent     = '—';
      return;
    }

    const entry = fcQueue[fcIndex];
    const showKatakana = document.getElementById('fcKatakana') && document.getElementById('fcKatakana').checked;
    const frontChar = showKatakana ? entry.k : entry.h;
    const backExtra = showKatakana ? entry.h : entry.k;
    const backLabel = showKatakana ? 'Hiragana: ' : 'Katakana: ';

    // Front
    document.getElementById('fcFrontChar').textContent = frontChar;

    // Back
    document.getElementById('fcBackRomaji').textContent = entry.r;
    document.getElementById('fcBackKana').textContent   = backLabel + backExtra;
    document.getElementById('fcBackGroup').textContent  = entry.group;

    // Flip state
    const wrap = document.getElementById('fcCardWrap');
    if (fcFlipped_) {
      wrap.classList.add('flipped');
    } else {
      wrap.classList.remove('flipped');
    }

    // Answer buttons
    const answerRow = document.getElementById('fcAnswerRow');
    answerRow.classList.toggle('hidden', !fcFlipped_);

    // Nav buttons
    document.getElementById('fcPrevBtn').disabled = (fcIndex === 0);
    document.getElementById('fcNextBtn').disabled = (fcIndex >= fcQueue.length - 1);
    document.getElementById('fcCounter').textContent = (fcIndex + 1) + ' / ' + fcQueue.length;

    // Score
    const correct = fcResults.filter(r => r === 'correct').length;
    const wrong   = fcResults.filter(r => r === 'wrong').length;
    const remain  = fcResults.filter(r => r === 'pending').length;
    document.getElementById('fcCorrectCount').textContent = correct;
    document.getElementById('fcWrongCount').textContent   = wrong;
    document.getElementById('fcRemainCount').textContent  = remain;

    // Dots
    const dotsEl = document.getElementById('fcDots');
    const MAX_DOTS = 40;
    if (fcQueue.length <= MAX_DOTS) {
      dotsEl.innerHTML = fcQueue.map((_, i) => {
        let cls = 'fc-dot';
        if (i === fcIndex) cls += ' current';
        else if (fcResults[i] === 'correct') cls += ' correct';
        else if (fcResults[i] === 'wrong')   cls += ' wrong';
        return `<div class="${cls}"></div>`;
      }).join('');
    } else {
      dotsEl.innerHTML = ''; // too many dots — skip
    }
  }

  function fcFlip() {
    if (fcFinished) return;
    fcFlipped_ = !fcFlipped_;
    fcRender();
  }

  function fcAnswer(isCorrect) {
    if (!fcFlipped_) return;
    fcResults[fcIndex] = isCorrect ? 'correct' : 'wrong';

    // Optionally update memorized state when marked correct
    const entry = fcQueue[fcIndex];
    if (isCorrect) {
      memorized.add(kanaKey(entry));
      saveMemorized();
    }

    // Auto-advance or finish
    if (fcIndex < fcQueue.length - 1) {
      fcIndex++;
      fcFlipped_ = false;
      fcRender();
    } else {
      fcShowFinish();
    }
  }

  function fcNav(dir) {
    const next = fcIndex + dir;
    if (next < 0 || next >= fcQueue.length) return;
    fcIndex    = next;
    fcFlipped_ = false;
    fcRender();
  }

  function fcShowFinish() {
    fcFinished = true;
    const correct = fcResults.filter(r => r === 'correct').length;
    const wrong   = fcResults.filter(r => r === 'wrong').length;
    const total   = fcQueue.length;
    const pct     = total > 0 ? Math.round(correct / total * 100) : 0;

    let emoji = pct >= 90 ? '🎉' : pct >= 70 ? '👏' : pct >= 50 ? '💪' : '📖';
    let title = pct >= 90 ? 'Luar Biasa!' : pct >= 70 ? 'Bagus Sekali!' : pct >= 50 ? 'Terus Berlatih!' : 'Jangan Menyerah!';

    document.getElementById('fcSection').querySelector('.fc-container').style.display = 'none';
    document.getElementById('fcFinish').style.display = 'block';
    document.getElementById('fcFinishEmoji').textContent   = emoji;
    document.getElementById('fcFinishTitle').textContent   = title;
    document.getElementById('fcFinishSub').textContent     = 'Hasil sesi kamu:';
    document.getElementById('fcFinishCorrect').textContent = correct;
    document.getElementById('fcFinishWrong').textContent   = wrong;
    document.getElementById('fcFinishPct').textContent     = pct + '%';
  }

  // ═══════════════════════════════════════════════════════
  //  UJIAN PEMAHAMAN — status, konfirmasi paham, & ujian PG
  // ═══════════════════════════════════════════════════════

  const STATUS_LABEL = {
    belum_dijawab: { text: 'Belum Dijawab', cls: 'badge-belum', icon: '❔' },
    sudah_paham:   { text: 'Sudah Paham',   cls: 'badge-paham', icon: '✅' },
    lulus_ujian:   { text: 'Lulus Ujian (100%)', cls: 'badge-lulus', icon: '🏅' },
  };

  let confirmPahamJenis = null; // 'hiragana' | 'katakana' — jenis yang sedang dikonfirmasi
  let examJenis    = null;      // jenis ujian yang sedang berjalan
  let examQueue    = [];        // soal-soal ujian saat ini
  let examIndex    = 0;
  let examCorrect  = 0;
  let examAnswered = false;

  function renderUjianHome() {
    ['hiragana', 'katakana'].forEach(jenis => {
      const status = kanaStatus[jenis];
      const info   = STATUS_LABEL[status] || STATUS_LABEL.belum_dijawab;
      const badgeEl = document.getElementById('badge' + capitalize(jenis));
      badgeEl.textContent = info.icon + ' ' + info.text;
      badgeEl.className = 'ujian-status-badge ' + info.cls;

      const actionsEl = document.getElementById('actions' + capitalize(jenis));
      if (status === 'belum_dijawab') {
        const canPaham = kanaCanPaham[jenis];
        const score    = kanaExamScore[jenis];

        const pahamBtnHtml = canPaham
          ? `<button class="ujian-action-btn ujian-btn-paham" onclick="openConfirmPahamModal('${jenis}')">✅ Sudah Paham</button>`
          : `<button class="ujian-action-btn ujian-btn-paham" disabled title="Selesaikan Ujian Pemahaman dengan nilai di atas 90 dulu untuk membuka tombol ini">🔒 Sudah Paham</button>`;

        const lockNoteHtml = canPaham ? '' : `
          <div class="ujian-status-note">
            🔒 Tombol ini terkunci. Kerjakan <strong>Ujian Pemahaman</strong> dulu dengan nilai
            di atas 90 untuk membukanya${score !== null ? ` (nilai terakhirmu: ${score}%)` : ''}.
          </div>
        `;

        actionsEl.innerHTML = `
          ${lockNoteHtml}
          ${pahamBtnHtml}
          <button class="ujian-action-btn ujian-btn-ujian" onclick="startExam('${jenis}')">📝 Ujian Pemahaman ${capitalize(jenis)}</button>
        `;
      } else if (status === 'sudah_paham') {
        actionsEl.innerHTML = `
          <div class="ujian-status-note">Kamu sudah menandai paham ${capitalize(jenis)} secara mandiri.</div>
          <button class="ujian-action-btn ujian-btn-ghost" onclick="startExam('${jenis}')">📝 Coba Ujian Pembuktian</button>
        `;
      } else { // lulus_ujian
        actionsEl.innerHTML = `
          <div class="ujian-status-note">Selamat! Kamu sudah lulus ujian pemahaman ${capitalize(jenis)} dengan nilai 100%.</div>
          <button class="ujian-action-btn ujian-btn-ghost" onclick="startExam('${jenis}')">🔄 Ulangi Ujian</button>
        `;
      }
    });
  }

  function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

  function backToUjianHome() {
    document.getElementById('ujianHome').style.display = '';
    document.getElementById('ujianExamRunning').style.display = 'none';
    document.getElementById('ujianExamResult').style.display = 'none';
    renderUjianHome();
  }

  // ── Konfirmasi "Sudah Paham" (modal) ──────────────────────
  function openConfirmPahamModal(jenis) {
    if (!kanaCanPaham[jenis]) {
      showToast('Selesaikan Ujian Pemahaman dengan nilai di atas 90 dulu untuk membuka tombol ini.', 'info');
      return;
    }
    confirmPahamJenis = jenis;
    document.getElementById('confirmPahamTitle').textContent = `Tandai ${capitalize(jenis)} sudah paham?`;
    document.getElementById('confirmPahamText').textContent =
      `Status "Sudah Paham" untuk ${capitalize(jenis)} akan tercatat di profilmu dan terlihat oleh Admin. ` +
      `Jika kurang yakin, kamu bisa membuktikannya lewat ujian pemahaman alih-alih menandai manual.`;
    document.getElementById('confirmPahamModal').classList.add('open');
    document.getElementById('confirmPahamBackdrop').classList.add('open');
  }

  function closeConfirmPahamModal() {
    document.getElementById('confirmPahamModal').classList.remove('open');
    document.getElementById('confirmPahamBackdrop').classList.remove('open');
    confirmPahamJenis = null;
  }

  async function confirmSetPaham() {
    if (!confirmPahamJenis) return;
    const jenis = confirmPahamJenis;
    const btn = document.getElementById('confirmPahamBtn');
    btn.disabled = true;
    btn.textContent = 'Menyimpan…';

    try {
      const res = await fetch('kana.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=kana_set_paham&jenis=' + encodeURIComponent(jenis)
      });
      const data = await res.json();
      if (data.ok) {
        kanaStatus[jenis] = 'sudah_paham';
        showToast(`${capitalize(jenis)} ditandai sudah paham! 🌸`, 'success');
        closeConfirmPahamModal();
        renderUjianHome();
      } else {
        showToast(data.error || 'Gagal menyimpan status.', 'error');
      }
    } catch (e) {
      showToast('Terjadi kesalahan jaringan.', 'error');
    } finally {
      btn.disabled = false;
      btn.textContent = 'Ya, Saya Paham';
    }
  }

  // ── Ujian Pilihan Ganda ────────────────────────────────────
  function buildExamQueue(jenis) {
    // Acak seluruh kana lalu ambil semua (ujian penuh = 100% harus benar)
    const pool = HIRAGANA.slice();
    for (let i = pool.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [pool[i], pool[j]] = [pool[j], pool[i]];
    }
    return pool.map(entry => {
      const correctRomaji = entry.r;
      // Ambil 3 distractor unik dari romaji lain
      const others = HIRAGANA.filter(e => e.r !== correctRomaji);
      const distractors = [];
      const usedR = new Set([correctRomaji]);
      while (distractors.length < 3 && others.length > 0) {
        const pick = others[Math.floor(Math.random() * others.length)];
        if (!usedR.has(pick.r)) {
          usedR.add(pick.r);
          distractors.push(pick.r);
        }
      }
      const options = shuffleArr([correctRomaji, ...distractors]);
      return {
        char: jenis === 'katakana' ? entry.k : entry.h,
        correct: correctRomaji,
        options,
      };
    });
  }

  function shuffleArr(arr) {
    const a = arr.slice();
    for (let i = a.length - 1; i > 0; i--) {
      const j = Math.floor(Math.random() * (i + 1));
      [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
  }

  function startExam(jenis) {
    examJenis    = jenis;
    examQueue    = buildExamQueue(jenis);
    examIndex    = 0;
    examCorrect  = 0;
    examAnswered = false;

    document.getElementById('ujianHome').style.display = 'none';
    document.getElementById('ujianExamResult').style.display = 'none';
    document.getElementById('ujianExamRunning').style.display = '';
    renderExamQuestion();
  }

  function cancelExam() {
    if (examIndex > 0) {
      if (!confirm('Yakin ingin membatalkan ujian? Progress saat ini akan hilang.')) return;
    }
    backToUjianHome();
  }

  function renderExamQuestion() {
    const q = examQueue[examIndex];
    examAnswered = false;

    document.getElementById('examQuestionChar').textContent = q.char;
    document.getElementById('examProgressLabel').textContent = `Soal ${examIndex + 1} / ${examQueue.length}`;
    document.getElementById('examProgressFill').style.width = (examIndex / examQueue.length * 100) + '%';

    const grid = document.getElementById('examOptionsGrid');
    grid.innerHTML = q.options.map(opt => `
      <button class="exam-option-btn" onclick="answerExam('${escAttr(opt)}', this)">${escHtml(opt)}</button>
    `).join('');
  }

  function answerExam(selected, btnEl) {
    if (examAnswered) return;
    examAnswered = true;

    const q = examQueue[examIndex];
    const isCorrect = selected === q.correct;
    if (isCorrect) examCorrect++;

    // Tampilkan feedback warna pada semua opsi
    document.querySelectorAll('#examOptionsGrid .exam-option-btn').forEach(b => {
      b.disabled = true;
      if (b.textContent === q.correct) b.classList.add('opt-correct');
      else if (b === btnEl) b.classList.add('opt-wrong');
    });

    setTimeout(() => {
      if (examIndex < examQueue.length - 1) {
        examIndex++;
        renderExamQuestion();
      } else {
        finishExam();
      }
    }, 550);
  }

  async function finishExam() {
    const total = examQueue.length;
    const correct = examCorrect;
    const pct = total > 0 ? Math.round((correct / total) * 100) : 0;
    const passed = pct >= 100;

    document.getElementById('examProgressFill').style.width = '100%';
    document.getElementById('ujianExamRunning').style.display = 'none';
    document.getElementById('ujianExamResult').style.display = '';

    document.getElementById('examResultEmoji').textContent = passed ? '🎉' : '📖';
    document.getElementById('examResultTitle').textContent = passed ? 'Selamat, Kamu Lulus!' : 'Belum Lulus';
    document.getElementById('examResultSub').textContent = passed
      ? `Kamu menjawab semua soal ${capitalize(examJenis)} dengan benar.`
      : 'Jawaban harus 100% benar untuk dinyatakan paham. Yuk coba lagi!';
    document.getElementById('examResultScore').textContent = pct + '%';
    document.getElementById('examResultScore').style.color = passed ? 'var(--bamboo,#4a7c59)' : 'var(--torii,#C0392B)';
    document.getElementById('examResultDetail').textContent = `${correct} / ${total} jawaban benar`;

    // Kirim hasil ke server
    try {
      const res = await fetch('kana.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=kana_submit_exam&jenis=${encodeURIComponent(examJenis)}&correct=${correct}&total=${total}`
      });
      const data = await res.json();
      if (data.ok) {
        kanaExamScore[examJenis] = data.score;
        kanaCanPaham[examJenis]  = data.score > 90;
      }
      if (data.ok && data.passed) {
        kanaStatus[examJenis] = 'lulus_ujian';
        showToast(`Ujian ${capitalize(examJenis)} lulus 100%! 🏅`, 'success');
      } else if (data.ok && data.score > 90) {
        showToast(`Skor kamu ${data.score}%. Tombol "Sudah Paham" sudah terbuka! 🔓`, 'success');
      } else if (data.ok) {
        showToast(`Skor kamu ${data.score}%. Coba lagi sampai di atas 90% untuk membuka tombol "Sudah Paham"!`, 'info');
      }
    } catch (e) {
      showToast('Hasil tersimpan lokal, tapi gagal sinkron ke server.', 'error');
    }
  }

  function retryExam() {
    startExam(examJenis);
  }

  // ═══════════════════════════════════════════════════════
  //  INIT
  // ═══════════════════════════════════════════════════════
  renderGrid();
  renderUjianHome();
  </script>
</body>
</html>
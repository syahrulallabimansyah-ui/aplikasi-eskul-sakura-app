<?php
require_once 'config.php';
require_once 'exam_helper.php';

header('Content-Type: application/json');
requireLogin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$db = getDB();
$userId = $_SESSION['user_id'];
$isAdmin = ($_SESSION['user_role'] ?? '') === 'admin';

function jres($data) { echo json_encode($data); exit; }

switch ($action) {

    // =========================================================
    // ADMIN: List semua ujian (dengan jumlah soal & peserta)
    // =========================================================
    case 'admin_list_exams':
        if (!$isAdmin) jres(['success' => false, 'message' => 'Akses ditolak.']);
        $rows = $db->query("
            SELECT e.*, 
                   (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id = e.id) AS total_questions,
                   (SELECT COUNT(*) FROM exam_attempts a WHERE a.exam_id = e.id AND a.status='finished') AS total_finished
            FROM exams e ORDER BY e.created_at DESC
        ")->fetchAll();
        jres(['success' => true, 'exams' => $rows]);
        break;

    // =========================================================
    // ADMIN: Buat ujian baru
    // =========================================================
    case 'admin_create_exam':
        if (!$isAdmin) jres(['success' => false, 'message' => 'Akses ditolak.']);
        $title = sanitize($_POST['title'] ?? '');
        $desc  = sanitize($_POST['description'] ?? '');
        $duration = max(1, (int)($_POST['duration_minutes'] ?? 30));
        $token = generateExamToken();

        if (empty($title)) jres(['success' => false, 'message' => 'Judul ujian wajib diisi.']);

        $stmt = $db->prepare("INSERT INTO exams (title, description, token, duration_minutes, status, created_by) VALUES (?, ?, ?, ?, 'draft', ?)");
        $stmt->execute([$title, $desc, $token, $duration, $userId]);
        jres(['success' => true, 'message' => 'Ujian berhasil dibuat.', 'exam_id' => $db->lastInsertId(), 'token' => $token]);
        break;

    // =========================================================
    // ADMIN: Update detail ujian
    // =========================================================
    case 'admin_update_exam':
        if (!$isAdmin) jres(['success' => false, 'message' => 'Akses ditolak.']);
        $examId = (int)($_POST['exam_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $desc  = sanitize($_POST['description'] ?? '');
        $duration = max(1, (int)($_POST['duration_minutes'] ?? 30));

        if (empty($title) || !$examId) jres(['success' => false, 'message' => 'Data tidak valid.']);

        $stmt = $db->prepare("UPDATE exams SET title=?, description=?, duration_minutes=? WHERE id=?");
        $stmt->execute([$title, $desc, $duration, $examId]);
        jres(['success' => true, 'message' => 'Ujian berhasil diperbarui.']);
        break;

    // =========================================================
    // ADMIN: Regenerate token
    // =========================================================
    case 'admin_regenerate_token':
        if (!$isAdmin) jres(['success' => false, 'message' => 'Akses ditolak.']);
        $examId = (int)($_POST['exam_id'] ?? 0);
        $newToken = generateExamToken();
        $stmt = $db->prepare("UPDATE exams SET token=? WHERE id=?");
        $stmt->execute([$newToken, $examId]);
        jres(['success' => true, 'token' => $newToken]);
        break;

    // =========================================================
    // ADMIN: Ubah status (draft / published / closed)
    // =========================================================
    case 'admin_set_status':
        if (!$isAdmin) jres(['success' => false, 'message' => 'Akses ditolak.']);
        $examId = (int)($_POST['exam_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (!in_array($status, ['draft','published','closed'], true)) {
            jres(['success' => false, 'message' => 'Status tidak valid.']);
        }
        if ($status === 'published') {
            $stmtC = $db->prepare("SELECT COUNT(*) FROM exam_questions WHERE exam_id=?");
            $stmtC->execute([$examId]);
            if ((int)$stmtC->fetchColumn() === 0) {
                jres(['success' => false, 'message' => 'Tambahkan minimal 1 pertanyaan sebelum mempublikasikan ujian.']);
            }
        }
        $stmt = $db->prepare("UPDATE exams SET status=? WHERE id=?");
        $stmt->execute([$status, $examId]);
        jres(['success' => true, 'message' => 'Status ujian diperbarui.']);
        break;

    // =========================================================
    // ADMIN: Hapus ujian
    // =========================================================
    case 'admin_delete_exam':
        if (!$isAdmin) jres(['success' => false, 'message' => 'Akses ditolak.']);
        $examId = (int)($_POST['exam_id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM exams WHERE id=?");
        $stmt->execute([$examId]);
        jres(['success' => true, 'message' => 'Ujian dihapus.']);
        break;

    // =========================================================
    // ADMIN: List pertanyaan dalam ujian
    // =========================================================
    case 'admin_list_questions':
        if (!$isAdmin) jres(['success' => false, 'message' => 'Akses ditolak.']);
        $examId = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM exam_questions WHERE exam_id=? ORDER BY question_order ASC, id ASC");
        $stmt->execute([$examId]);
        jres(['success' => true, 'questions' => $stmt->fetchAll()]);
        break;

    // =========================================================
    // ADMIN: Tambah pertanyaan (dengan upload gambar opsional)
    // =========================================================
    case 'admin_add_question':
        if (!$isAdmin) jres(['success' => false, 'message' => 'Akses ditolak.']);
        $examId = (int)($_POST['exam_id'] ?? 0);
        $text   = sanitize($_POST['question_text'] ?? '');
        $optA = sanitize($_POST['option_a'] ?? '');
        $optB = sanitize($_POST['option_b'] ?? '');
        $optC = sanitize($_POST['option_c'] ?? '');
        $optD = sanitize($_POST['option_d'] ?? '');
        $optE = sanitize($_POST['option_e'] ?? '');
        $optF = sanitize($_POST['option_f'] ?? '');
        $correct = $_POST['correct_option'] ?? '';

        if (!$examId || empty($text) || empty($optA) || empty($optB) || empty($optC) || empty($optD)) {
            jres(['success' => false, 'message' => 'Pertanyaan dan pilihan A-D wajib diisi.']);
        }
        if (!in_array($correct, ['a','b','c','d','e','f'], true)) {
            jres(['success' => false, 'message' => 'Jawaban benar tidak valid.']);
        }
        if (($correct === 'e' && empty($optE)) || ($correct === 'f' && empty($optF))) {
            jres(['success' => false, 'message' => 'Pilihan jawaban benar belum diisi.']);
        }

        $imagePath = null;
        if (!empty($_FILES['question_image']['name'])) {
            $file = $_FILES['question_image'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowed, true)) {
                jres(['success' => false, 'message' => 'Format gambar tidak didukung.']);
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                jres(['success' => false, 'message' => 'Ukuran gambar maksimal 5MB.']);
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'q_' . uniqid() . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/uploads/exam_images/' . $filename;
            if (!is_dir(__DIR__ . '/uploads/exam_images')) {
                mkdir(__DIR__ . '/uploads/exam_images', 0755, true);
            }
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $imagePath = 'uploads/exam_images/' . $filename;
            }
        }

        $stmtOrder = $db->prepare("SELECT COALESCE(MAX(question_order), 0) + 1 FROM exam_questions WHERE exam_id=?");
        $stmtOrder->execute([$examId]);
        $order = (int)$stmtOrder->fetchColumn();

        $stmt = $db->prepare("INSERT INTO exam_questions 
            (exam_id, question_text, question_image, option_a, option_b, option_c, option_d, option_e, option_f, correct_option, question_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$examId, $text, $imagePath, $optA, $optB, $optC, $optD, $optE ?: null, $optF ?: null, $correct, $order]);

        jres(['success' => true, 'message' => 'Pertanyaan ditambahkan.', 'question_id' => $db->lastInsertId()]);
        break;

    // =========================================================
    // ADMIN: Edit pertanyaan
    // =========================================================
    case 'admin_update_question':
        if (!$isAdmin) jres(['success' => false, 'message' => 'Akses ditolak.']);
        $qId = (int)($_POST['question_id'] ?? 0);
        $text = sanitize($_POST['question_text'] ?? '');
        $optA = sanitize($_POST['option_a'] ?? '');
        $optB = sanitize($_POST['option_b'] ?? '');
        $optC = sanitize($_POST['option_c'] ?? '');
        $optD = sanitize($_POST['option_d'] ?? '');
        $optE = sanitize($_POST['option_e'] ?? '');
        $optF = sanitize($_POST['option_f'] ?? '');
        $correct = $_POST['correct_option'] ?? '';
        $removeImage = ($_POST['remove_image'] ?? '') === '1';

        if (!$qId || empty($text) || empty($optA) || empty($optB) || empty($optC) || empty($optD)) {
            jres(['success' => false, 'message' => 'Data tidak lengkap.']);
        }
        if (!in_array($correct, ['a','b','c','d','e','f'], true)) {
            jres(['success' => false, 'message' => 'Jawaban benar tidak valid.']);
        }

        $stmtCur = $db->prepare("SELECT question_image FROM exam_questions WHERE id=?");
        $stmtCur->execute([$qId]);
        $current = $stmtCur->fetch();
        $imagePath = $current['question_image'] ?? null;

        if ($removeImage && $imagePath) {
            @unlink(__DIR__ . '/' . $imagePath);
            $imagePath = null;
        }

        if (!empty($_FILES['question_image']['name'])) {
            $file = $_FILES['question_image'];
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($file['type'], $allowed, true)) {
                jres(['success' => false, 'message' => 'Format gambar tidak didukung.']);
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                jres(['success' => false, 'message' => 'Ukuran gambar maksimal 5MB.']);
            }
            if ($imagePath) @unlink(__DIR__ . '/' . $imagePath);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'q_' . uniqid() . '_' . time() . '.' . $ext;
            $dest = __DIR__ . '/uploads/exam_images/' . $filename;
            if (!is_dir(__DIR__ . '/uploads/exam_images')) mkdir(__DIR__ . '/uploads/exam_images', 0755, true);
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $imagePath = 'uploads/exam_images/' . $filename;
            }
        }

        $stmt = $db->prepare("UPDATE exam_questions SET question_text=?, question_image=?, option_a=?, option_b=?, option_c=?, option_d=?, option_e=?, option_f=?, correct_option=? WHERE id=?");
        $stmt->execute([$text, $imagePath, $optA, $optB, $optC, $optD, $optE ?: null, $optF ?: null, $correct, $qId]);
        jres(['success' => true, 'message' => 'Pertanyaan diperbarui.']);
        break;

    // =========================================================
    // ADMIN: Hapus pertanyaan
    // =========================================================
    case 'admin_delete_question':
        if (!$isAdmin) jres(['success' => false, 'message' => 'Akses ditolak.']);
        $qId = (int)($_POST['question_id'] ?? 0);
        $stmtCur = $db->prepare("SELECT question_image FROM exam_questions WHERE id=?");
        $stmtCur->execute([$qId]);
        $current = $stmtCur->fetch();
        if ($current && $current['question_image']) {
            @unlink(__DIR__ . '/' . $current['question_image']);
        }
        $stmt = $db->prepare("DELETE FROM exam_questions WHERE id=?");
        $stmt->execute([$qId]);
        jres(['success' => true, 'message' => 'Pertanyaan dihapus.']);
        break;

    // =========================================================
    // ADMIN: Lihat hasil semua peserta untuk satu ujian
    // =========================================================
    case 'admin_exam_results':
        if (!$isAdmin) jres(['success' => false, 'message' => 'Akses ditolak.']);
        $examId = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
        $stmt = $db->prepare("
            SELECT a.*, u.name, u.email 
            FROM exam_attempts a 
            JOIN users u ON u.id = a.user_id 
            WHERE a.exam_id = ? 
            ORDER BY a.score DESC, a.finished_at ASC
        ");
        $stmt->execute([$examId]);
        jres(['success' => true, 'results' => $stmt->fetchAll()]);
        break;

    // =========================================================
    // USER: List ujian yang dipublikasikan (untuk notifikasi & card)
    // =========================================================
    case 'user_list_exams':
        $stmt = $db->prepare("
            SELECT e.id, e.title, e.description, e.duration_minutes, e.status,
                   (SELECT COUNT(*) FROM exam_questions q WHERE q.exam_id = e.id) AS total_questions,
                   a.status AS attempt_status, a.score, a.finished_at, a.ends_at
            FROM exams e
            LEFT JOIN exam_attempts a ON a.exam_id = e.id AND a.user_id = ?
            WHERE e.status = 'published' OR (e.status='closed' AND a.id IS NOT NULL)
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$userId]);
        jres(['success' => true, 'exams' => $stmt->fetchAll()]);
        break;

    // =========================================================
    // USER: Verifikasi token ujian
    // =========================================================
    case 'user_verify_token':
        $examId = (int)($_POST['exam_id'] ?? 0);
        $token = strtoupper(trim($_POST['token'] ?? ''));

        $stmt = $db->prepare("SELECT * FROM exams WHERE id=? AND status='published'");
        $stmt->execute([$examId]);
        $exam = $stmt->fetch();

        if (!$exam) jres(['success' => false, 'message' => 'Ujian tidak ditemukan atau belum dipublikasikan.']);
        if ($exam['token'] !== $token) jres(['success' => false, 'message' => 'Token ujian salah.']);

        // Cek apakah user sudah pernah mengerjakan / sedang mengerjakan
        $stmtA = $db->prepare("SELECT * FROM exam_attempts WHERE exam_id=? AND user_id=?");
        $stmtA->execute([$examId, $userId]);
        $attempt = $stmtA->fetch();

        if ($attempt && $attempt['status'] === 'finished') {
            jres(['success' => false, 'message' => 'Kamu sudah menyelesaikan ujian ini.', 'already_finished' => true]);
        }

        jres(['success' => true, 'message' => 'Token valid. Bersiaplah!', 'motivation' => getRandomMotivation()]);
        break;

    // =========================================================
    // USER: Mulai ujian (membuat attempt + ends_at, idempotent jika sudah jalan)
    // =========================================================
    case 'user_start_exam':
        $examId = (int)($_POST['exam_id'] ?? 0);
        $token = strtoupper(trim($_POST['token'] ?? ''));

        $stmt = $db->prepare("SELECT * FROM exams WHERE id=? AND status='published'");
        $stmt->execute([$examId]);
        $exam = $stmt->fetch();
        if (!$exam) jres(['success' => false, 'message' => 'Ujian tidak ditemukan.']);
        if ($exam['token'] !== $token) jres(['success' => false, 'message' => 'Token ujian salah.']);

        $stmtA = $db->prepare("SELECT * FROM exam_attempts WHERE exam_id=? AND user_id=?");
        $stmtA->execute([$examId, $userId]);
        $attempt = $stmtA->fetch();

        if ($attempt && $attempt['status'] === 'finished') {
            jres(['success' => false, 'message' => 'Kamu sudah menyelesaikan ujian ini.']);
        }

        if (!$attempt) {
            $endsAt = date('Y-m-d H:i:s', time() + $exam['duration_minutes'] * 60);
            $ins = $db->prepare("INSERT INTO exam_attempts (exam_id, user_id, started_at, ends_at, status) VALUES (?, ?, NOW(), ?, 'in_progress')");
            $ins->execute([$examId, $userId, $endsAt]);
            $attemptId = $db->lastInsertId();
        } else {
            $attemptId = $attempt['id'];
            if ($attempt['status'] === 'not_started') {
                $endsAt = date('Y-m-d H:i:s', time() + $exam['duration_minutes'] * 60);
                $upd = $db->prepare("UPDATE exam_attempts SET started_at=NOW(), ends_at=?, status='in_progress' WHERE id=?");
                $upd->execute([$endsAt, $attemptId]);
            }
        }

        jres(['success' => true, 'attempt_id' => $attemptId, 'redirect' => 'ujian_kerjakan.php?exam_id=' . $examId]);
        break;

    // =========================================================
    // USER: Ambil data ujian untuk dikerjakan (soal tanpa kunci jawaban)
    // =========================================================
    case 'user_get_exam':
        $examId = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);

        $stmt = $db->prepare("SELECT * FROM exams WHERE id=? AND status='published'");
        $stmt->execute([$examId]);
        $exam = $stmt->fetch();
        if (!$exam) jres(['success' => false, 'message' => 'Ujian tidak ditemukan.']);

        $stmtA = $db->prepare("SELECT * FROM exam_attempts WHERE exam_id=? AND user_id=?");
        $stmtA->execute([$examId, $userId]);
        $attempt = $stmtA->fetch();

        if (!$attempt || $attempt['status'] === 'not_started') {
            jres(['success' => false, 'message' => 'Silakan masukkan token untuk memulai ujian.']);
        }
        if ($attempt['status'] === 'finished') {
            jres(['success' => false, 'message' => 'Ujian sudah selesai.', 'finished' => true]);
        }

        // Cek waktu habis
        $now = time();
        $endsAt = strtotime($attempt['ends_at']);
        if ($now >= $endsAt) {
            // auto finish
            finishExamAttempt($db, $attempt['id']);
            jres(['success' => false, 'message' => 'Waktu ujian telah habis.', 'time_up' => true]);
        }

        $stmtQ = $db->prepare("SELECT id, question_text, question_image, option_a, option_b, option_c, option_d, option_e, option_f, question_order FROM exam_questions WHERE exam_id=? ORDER BY question_order ASC, id ASC");
        $stmtQ->execute([$examId]);
        $questions = $stmtQ->fetchAll();

        $stmtAns = $db->prepare("SELECT question_id, selected_option FROM exam_answers WHERE attempt_id=?");
        $stmtAns->execute([$attempt['id']]);
        $answers = [];
        foreach ($stmtAns->fetchAll() as $a) $answers[$a['question_id']] = $a['selected_option'];

        jres([
            'success' => true,
            'exam' => [
                'id' => $exam['id'],
                'title' => $exam['title'],
                'description' => $exam['description'],
                'duration_minutes' => $exam['duration_minutes'],
            ],
            'questions' => $questions,
            'answers' => $answers,
            'ends_at' => $attempt['ends_at'],
            'server_time' => date('Y-m-d H:i:s'),
        ]);
        break;

    // =========================================================
    // USER: Simpan jawaban (autosave real-time)
    // =========================================================
    case 'user_save_answer':
        $examId = (int)($_POST['exam_id'] ?? 0);
        $questionId = (int)($_POST['question_id'] ?? 0);
        $selected = $_POST['selected_option'] ?? null;

        if ($selected !== null && !in_array($selected, ['a','b','c','d','e','f'], true)) {
            jres(['success' => false, 'message' => 'Pilihan tidak valid.']);
        }

        $stmtA = $db->prepare("SELECT * FROM exam_attempts WHERE exam_id=? AND user_id=?");
        $stmtA->execute([$examId, $userId]);
        $attempt = $stmtA->fetch();
        if (!$attempt || $attempt['status'] !== 'in_progress') {
            jres(['success' => false, 'message' => 'Sesi ujian tidak aktif.']);
        }

        // Cek waktu
        if (time() >= strtotime($attempt['ends_at'])) {
            finishExamAttempt($db, $attempt['id']);
            jres(['success' => false, 'message' => 'Waktu ujian telah habis.', 'time_up' => true]);
        }

        $stmtQ = $db->prepare("SELECT correct_option FROM exam_questions WHERE id=? AND exam_id=?");
        $stmtQ->execute([$questionId, $examId]);
        $q = $stmtQ->fetch();
        if (!$q) jres(['success' => false, 'message' => 'Pertanyaan tidak ditemukan.']);

        $isCorrect = $selected !== null ? (int)($selected === $q['correct_option']) : null;

        $stmt = $db->prepare("INSERT INTO exam_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE selected_option=VALUES(selected_option), is_correct=VALUES(is_correct)");
        $stmt->execute([$attempt['id'], $questionId, $selected, $isCorrect]);

        jres(['success' => true]);
        break;

    // =========================================================
    // USER: Cek sisa waktu (polling real-time)
    // =========================================================
    case 'user_check_time':
        $examId = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
        $stmtA = $db->prepare("SELECT * FROM exam_attempts WHERE exam_id=? AND user_id=?");
        $stmtA->execute([$examId, $userId]);
        $attempt = $stmtA->fetch();
        if (!$attempt) jres(['success' => false, 'message' => 'Sesi tidak ditemukan.']);

        if ($attempt['status'] === 'finished') {
            jres(['success' => true, 'finished' => true]);
        }

        $remaining = strtotime($attempt['ends_at']) - time();
        if ($remaining <= 0) {
            finishExamAttempt($db, $attempt['id']);
            jres(['success' => true, 'finished' => true, 'time_up' => true]);
        }

        jres(['success' => true, 'remaining_seconds' => $remaining]);
        break;

    // =========================================================
    // USER: Selesaikan ujian (manual submit)
    // =========================================================
    case 'user_finish_exam':
        $examId = (int)($_POST['exam_id'] ?? 0);
        $stmtA = $db->prepare("SELECT * FROM exam_attempts WHERE exam_id=? AND user_id=?");
        $stmtA->execute([$examId, $userId]);
        $attempt = $stmtA->fetch();
        if (!$attempt) jres(['success' => false, 'message' => 'Sesi tidak ditemukan.']);

        if ($attempt['status'] !== 'finished') {
            finishExamAttempt($db, $attempt['id']);
        }

        $stmtR = $db->prepare("SELECT * FROM exam_attempts WHERE id=?");
        $stmtR->execute([$attempt['id']]);
        $result = $stmtR->fetch();

        jres(['success' => true, 'result' => $result]);
        break;

    // =========================================================
    // USER: Lihat hasil ujian sendiri
    // =========================================================
    case 'user_get_result':
        $examId = (int)($_GET['exam_id'] ?? $_POST['exam_id'] ?? 0);
        $stmt = $db->prepare("SELECT a.*, e.title FROM exam_attempts a JOIN exams e ON e.id = a.exam_id WHERE a.exam_id=? AND a.user_id=?");
        $stmt->execute([$examId, $userId]);
        $result = $stmt->fetch();
        if (!$result) jres(['success' => false, 'message' => 'Hasil tidak ditemukan.']);
        jres(['success' => true, 'result' => $result]);
        break;

    default:
        jres(['success' => false, 'message' => 'Aksi tidak dikenali.']);
        break;
}

// =================================================================
// Helper: Finalisasi attempt (hitung skor)
// =================================================================
function finishExamAttempt(PDO $db, int $attemptId): void {
    $stmtA = $db->prepare("SELECT * FROM exam_attempts WHERE id=?");
    $stmtA->execute([$attemptId]);
    $attempt = $stmtA->fetch();
    if (!$attempt || $attempt['status'] === 'finished') return;

    $stmtQ = $db->prepare("SELECT COUNT(*) FROM exam_questions WHERE exam_id=?");
    $stmtQ->execute([$attempt['exam_id']]);
    $total = (int)$stmtQ->fetchColumn();

    $stmtC = $db->prepare("SELECT COUNT(*) FROM exam_answers WHERE attempt_id=? AND is_correct=1");
    $stmtC->execute([$attemptId]);
    $correct = (int)$stmtC->fetchColumn();

    $score = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

    $upd = $db->prepare("UPDATE exam_attempts SET status='finished', finished_at=NOW(), score=?, total_correct=?, total_questions=? WHERE id=?");
    $upd->execute([$score, $correct, $total, $attemptId]);
}

<?php
require_once 'config.php';

header('Content-Type: application/json');
startSecureSession();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {

    // ─── LOGIN ────────────────────────────────────────────────────
    case 'login':
        $nis      = sanitize($_POST['nis'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($nis) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'NIS dan password wajib diisi.']);
            exit;
        }

        $db   = getDB();
        $stmt = $db->prepare("SELECT * FROM users WHERE nis = ? LIMIT 1");
        $stmt->execute([$nis]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            echo json_encode(['success' => false, 'message' => 'NIS atau password salah.']);
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['name'];

        echo json_encode([
            'success'  => true,
            'message'  => 'Login berhasil! Selamat datang, ' . $user['name'],
            'role'     => $user['role'],
            'redirect' => 'beranda.php'
        ]);
        break;

    // ─── REGISTER ─────────────────────────────────────────────────
    case 'register':
        $name     = sanitize($_POST['name'] ?? '');
        $nis      = sanitize($_POST['nis'] ?? '');
        $email    = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (empty($name) || empty($nis) || empty($email) || empty($password)) {
            echo json_encode(['success' => false, 'message' => 'Semua kolom wajib diisi.']);
            exit;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Format email tidak valid.']);
            exit;
        }

        if (strlen($password) < 6) {
            echo json_encode(['success' => false, 'message' => 'Password minimal 6 karakter.']);
            exit;
        }

        if ($password !== $confirm) {
            echo json_encode(['success' => false, 'message' => 'Konfirmasi password tidak cocok.']);
            exit;
        }

        $db   = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE nis = ? OR email = ?");
        $stmt->execute([$nis, $email]);

        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'NIS atau email sudah terdaftar.']);
            exit;
        }

        $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
        $insert = $db->prepare("INSERT INTO users (name, nis, email, password, role) VALUES (?, ?, ?, ?, 'user')");
        $insert->execute([$name, $nis, $email, $hashed]);

        echo json_encode([
            'success' => true,
            'message' => 'Akun berhasil dibuat! Silakan masuk.'
        ]);
        break;

    // ─── LOGOUT ───────────────────────────────────────────────────
    case 'logout':
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie('PHPSESSID', '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        session_destroy();
        echo json_encode(['success' => true, 'redirect' => 'index.php']);
        break;

    // ─── CHECK SESSION ────────────────────────────────────────────
    case 'check':
        if (isLoggedIn()) {
            $user = getCurrentUser();
            echo json_encode(['logged_in' => true, 'user' => $user]);
        } else {
            echo json_encode(['logged_in' => false]);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Aksi tidak dikenali.']);
        break;
}
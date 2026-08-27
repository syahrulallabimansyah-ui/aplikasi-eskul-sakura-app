<?php
require_once 'config.php';
requireAdmin();

$user = getCurrentUser();
$initial = strtoupper(mb_substr($user['name'], 0, 1));

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = sanitize($_POST['name'] ?? '');
    $nis      = sanitize($_POST['nis'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'user';

    if ($role !== 'admin' && $role !== 'user') {
        $role = 'user';
    }

    if ($name === '' || $nis === '' || $email === '' || $password === '') {
        $message = 'Semua field wajib diisi.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Format email tidak valid.';
        $messageType = 'error';
    } elseif (strlen($password) < 6) {
        $message = 'Kata sandi minimal 6 karakter.';
        $messageType = 'error';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM users WHERE nis = ? OR email = ? LIMIT 1");
        $stmt->execute([$nis, $email]);

        if ($stmt->fetch()) {
            $message = 'NIS atau email sudah digunakan oleh anggota lain.';
            $messageType = 'error';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $db->prepare("INSERT INTO users (name, nis, email, password, role) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $nis, $email, $hash, $role]);

            $message = 'Anggota baru "' . $name . '" berhasil ditambahkan dengan NIS ' . $nis . '.';
            $messageType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>桜 Sakura — Tambah Anggota</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <div class="asanoha-bg"></div>

  <button class="theme-toggle" onclick="toggleTheme()" title="Mode Terang" style="position:fixed; top:20px; right:20px; z-index:10;">☀️</button>

  <div class="auth-page">
    <div class="auth-card fade-up" style="max-width:520px;">
      <div class="torii-accent"></div>

      <div class="auth-brand">
        <span class="kanji">桜</span>
        <span class="brand-name">Tambah Anggota Baru</span>
        <div class="brand-divider"></div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?= $messageType ?>" style="display:block;"><?= htmlspecialchars($message) ?></div>
      <?php endif; ?>

      <form method="POST" action="tambah_anggota.php">
        <div class="form-group">
          <label class="form-label" for="name">Nama Lengkap</label>
          <input class="form-input" type="text" id="name" name="name"
                 placeholder="Nama anggota" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="nis">NIS / Nomor Induk</label>
          <input class="form-input" type="text" id="nis" name="nis"
                 placeholder="Contoh: 2025001" required value="<?= htmlspecialchars($_POST['nis'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="email">Surel</label>
          <input class="form-input" type="email" id="email" name="email"
                 placeholder="nama@email.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label" for="password">Kata Sandi</label>
          <input class="form-input" type="password" id="password" name="password"
                 placeholder="Minimal 6 karakter" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="role">Peran</label>
          <select class="form-input" id="role" name="role">
            <option value="user" <?= (($_POST['role'] ?? 'user') === 'user') ? 'selected' : '' ?>>Member (User)</option>
            <option value="admin" <?= (($_POST['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrator</option>
          </select>
        </div>

        <button class="btn-primary" type="submit">Tambah Anggota 追加</button>
      </form>

      <div style="margin-top:16px; text-align:center;">
        <a href="beranda.php" style="color:var(--torii); text-decoration:none; font-size:0.9rem;">← Kembali ke Beranda</a>
      </div>
    </div>
  </div>

  <script src="js/theme.js"></script>
</body>
</html>

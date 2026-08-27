<?php
require_once 'config.php';
requireLogin();

$user    = getCurrentUser();
$isAdmin = $user && $user['role'] === 'admin';
$db      = getDB();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/* ── Helper ── */
function jsonOut($data) { echo json_encode($data); exit; }
function err($msg, $code = 400) {
    http_response_code($code);
    jsonOut(['success' => false, 'message' => $msg]);
}
function ok($data = []) {
    jsonOut(array_merge(['success' => true], $data));
}

/* ── Upload file ── */
function handleUpload($field, $allowedTypes, $subdir = 'hafalan') {
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) return null;
    $file    = $_FILES[$field];
    $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $mime    = mime_content_type($file['tmp_name']);
    $allowed = false;
    foreach ($allowedTypes as $type) {
        if (str_starts_with($mime, $type)) { $allowed = true; break; }
    }
    if (!$allowed) return false;
    $dir = "uploads/$subdir/";
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $filename = uniqid('hf_', true) . '.' . $ext;
    $dest     = $dir . $filename;
    move_uploaded_file($file['tmp_name'], $dest);
    return $dest;
}

/* ═══════════════════════════════════════
   KATEGORI
═══════════════════════════════════════ */

/* GET: daftar kategori + jumlah item */
if ($action === 'get_kategori') {
    $rows = $db->query("
        SELECT k.*, COUNT(i.id) AS jumlah_item
        FROM hafalan_kategori k
        LEFT JOIN hafalan_item i ON i.kategori_id = k.id
        GROUP BY k.id
        ORDER BY k.urutan, k.id
    ")->fetchAll();
    ok(['data' => $rows]);
}

/* POST: tambah kategori (admin only) */
if ($action === 'tambah_kategori') {
    if (!$isAdmin) err('Akses ditolak', 403);
    $nama  = trim($_POST['nama'] ?? '');
    $desc  = trim($_POST['deskripsi'] ?? '');
    $icon  = trim($_POST['icon'] ?? '📚');
    if ($nama === '') err('Nama kategori wajib diisi');
    $urutan = (int)$db->query("SELECT COALESCE(MAX(urutan),0)+1 FROM hafalan_kategori")->fetchColumn();
    $stmt = $db->prepare("INSERT INTO hafalan_kategori (nama, deskripsi, icon, urutan) VALUES (?,?,?,?)");
    $stmt->execute([$nama, $desc, $icon, $urutan]);
    ok(['id' => $db->lastInsertId(), 'message' => 'Kategori ditambahkan']);
}

/* POST: edit kategori (admin only) */
if ($action === 'edit_kategori') {
    if (!$isAdmin) err('Akses ditolak', 403);
    $id    = (int)($_POST['id'] ?? 0);
    $nama  = trim($_POST['nama'] ?? '');
    $desc  = trim($_POST['deskripsi'] ?? '');
    $icon  = trim($_POST['icon'] ?? '📚');
    if (!$id || $nama === '') err('Data tidak lengkap');
    $stmt = $db->prepare("UPDATE hafalan_kategori SET nama=?, deskripsi=?, icon=? WHERE id=?");
    $stmt->execute([$nama, $desc, $icon, $id]);
    ok(['message' => 'Kategori diperbarui']);
}

/* POST: hapus kategori (admin only) */
if ($action === 'hapus_kategori') {
    if (!$isAdmin) err('Akses ditolak', 403);
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) err('ID tidak valid');
    // hapus file fisik item di dalam kategori ini
    $items = $db->prepare("SELECT file_path FROM hafalan_item WHERE kategori_id=?");
    $items->execute([$id]);
    foreach ($items->fetchAll() as $it) {
        if ($it['file_path'] && file_exists($it['file_path'])) unlink($it['file_path']);
    }
    $db->prepare("DELETE FROM hafalan_kategori WHERE id=?")->execute([$id]);
    ok(['message' => 'Kategori dihapus']);
}

/* ═══════════════════════════════════════
   ITEM
═══════════════════════════════════════ */

/* GET: daftar item per kategori */
if ($action === 'get_item') {
    $katId = (int)($_GET['kategori_id'] ?? 0);
    if (!$katId) err('kategori_id wajib');
    $stmt = $db->prepare("SELECT * FROM hafalan_item WHERE kategori_id=? ORDER BY urutan, id");
    $stmt->execute([$katId]);
    ok(['data' => $stmt->fetchAll()]);
}

/* POST: tambah item (admin only) */
if ($action === 'tambah_item') {
    if (!$isAdmin) err('Akses ditolak', 403);
    $katId = (int)($_POST['kategori_id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $desc  = trim($_POST['deskripsi'] ?? '');
    $tipe  = $_POST['tipe'] ?? '';
    $link  = trim($_POST['link_url'] ?? '');

    if (!$katId || $judul === '' || !in_array($tipe, ['gambar','audio','video','link'])) {
        err('Data tidak lengkap');
    }

    $filePath = null;
    if ($tipe === 'gambar') {
        $res = handleUpload('file', ['image/']);
        if ($res === false) err('Format gambar tidak didukung');
        $filePath = $res;
    } elseif ($tipe === 'audio') {
        $res = handleUpload('file', ['audio/']);
        if ($res === false) err('Format audio tidak didukung');
        $filePath = $res;
    } elseif ($tipe === 'video') {
        $res = handleUpload('file', ['video/']);
        if ($res === false) err('Format video tidak didukung');
        $filePath = $res;
    }
    // tipe 'link' → gunakan link_url, tidak perlu upload

    if (in_array($tipe, ['gambar','audio','video']) && !$filePath && !$link) {
        err('File atau link wajib diisi');
    }

    // Kalau tipe upload tapi user isi link_url (tidak upload file), simpan sebagai link
    if (!$filePath && $link) {
        $filePath = null; // tetap null, simpan di link_url
    }

    $urutan = (int)$db->prepare("SELECT COALESCE(MAX(urutan),0)+1 FROM hafalan_item WHERE kategori_id=?")->execute([$katId]) ? 0 : 0;
    $stmt2  = $db->prepare("SELECT COALESCE(MAX(urutan),0)+1 FROM hafalan_item WHERE kategori_id=?");
    $stmt2->execute([$katId]);
    $urutan = (int)$stmt2->fetchColumn();

    $ins = $db->prepare("INSERT INTO hafalan_item (kategori_id, judul, deskripsi, tipe, file_path, link_url, urutan) VALUES (?,?,?,?,?,?,?)");
    $ins->execute([$katId, $judul, $desc, $tipe, $filePath, $link ?: null, $urutan]);
    ok(['id' => $db->lastInsertId(), 'message' => 'Item ditambahkan']);
}

/* POST: edit item (admin only) */
if ($action === 'edit_item') {
    if (!$isAdmin) err('Akses ditolak', 403);
    $id    = (int)($_POST['id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $desc  = trim($_POST['deskripsi'] ?? '');
    $link  = trim($_POST['link_url'] ?? '');
    if (!$id || $judul === '') err('Data tidak lengkap');

    // Cek apakah ada file baru di-upload
    $existing = $db->prepare("SELECT * FROM hafalan_item WHERE id=?");
    $existing->execute([$id]);
    $item = $existing->fetch();
    if (!$item) err('Item tidak ditemukan', 404);

    $tipe     = $item['tipe'];
    $filePath = $item['file_path'];

    if ($tipe === 'gambar' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $res = handleUpload('file', ['image/']);
        if ($res === false) err('Format gambar tidak didukung');
        if ($res && $filePath && file_exists($filePath)) unlink($filePath);
        $filePath = $res;
    } elseif ($tipe === 'audio' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $res = handleUpload('file', ['audio/']);
        if ($res === false) err('Format audio tidak didukung');
        if ($res && $filePath && file_exists($filePath)) unlink($filePath);
        $filePath = $res;
    } elseif ($tipe === 'video' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $res = handleUpload('file', ['video/']);
        if ($res === false) err('Format video tidak didukung');
        if ($res && $filePath && file_exists($filePath)) unlink($filePath);
        $filePath = $res;
    }

    $upd = $db->prepare("UPDATE hafalan_item SET judul=?, deskripsi=?, file_path=?, link_url=? WHERE id=?");
    $upd->execute([$judul, $desc, $filePath, $link ?: null, $id]);
    ok(['message' => 'Item diperbarui']);
}

/* POST: hapus item (admin only) */
if ($action === 'hapus_item') {
    if (!$isAdmin) err('Akses ditolak', 403);
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) err('ID tidak valid');
    $row = $db->prepare("SELECT file_path FROM hafalan_item WHERE id=?");
    $row->execute([$id]);
    $item = $row->fetch();
    if ($item && $item['file_path'] && file_exists($item['file_path'])) {
        unlink($item['file_path']);
    }
    $db->prepare("DELETE FROM hafalan_item WHERE id=?")->execute([$id]);
    ok(['message' => 'Item dihapus']);
}

err('Aksi tidak dikenali', 404);
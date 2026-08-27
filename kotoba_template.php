<?php
require_once 'config.php';

requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    header('Location: beranda.php');
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_kotoba.csv"');
header('Cache-Control: max-age=0');

// BOM agar Excel membuka UTF-8 (karakter Jepang) dengan benar
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header
fputcsv($out, ['Kotoba', 'Cara Baca', 'Arti', 'Opsi A', 'Opsi B', 'Opsi C', 'Opsi D', 'Jawaban Benar (a/b/c/d)']);

// Contoh data
fputcsv($out, ['食べる', 'たべる', 'Makan', 'Makan', 'Minum', 'Tidur', 'Berjalan', 'a']);
fputcsv($out, ['学校', 'がっこう', 'Sekolah', 'Rumah', 'Sekolah', 'Kantor', 'Pasar', 'b']);
fputcsv($out, ['新しい', 'あたらしい', 'Baru', 'Lama', 'Besar', 'Baru', 'Kecil', 'c']);

fclose($out);
exit;
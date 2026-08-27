<?php
require_once __DIR__ . '/config.php';

// Pesan motivasi random sebelum ujian dimulai
function getRandomMotivation(): string {
    $messages = [
        "Percaya pada dirimu sendiri — kamu sudah mempersiapkan ini dengan baik! 🌸",
        "Setiap usaha yang kamu lakukan tidak akan sia-sia. Semangat! ⛩",
        "Tenang dan fokus, kamu pasti bisa melewatinya dengan baik. 🌸",
        "Kegagalan hanyalah langkah menuju kesuksesan. Berikan yang terbaik! 🌸",
        "Hari ini adalah kesempatanmu untuk menunjukkan kemampuan terbaikmu. 桜",
        "Bernapaslah dengan tenang, baca soal pelan-pelan, dan percayalah pada prosesmu. 🌸",
        "Kamu telah berlatih untuk momen ini. Saatnya bersinar! ⛩",
        "Sukses adalah hasil dari kerja keras dan ketenangan pikiran. Kamu pasti bisa! 🌸",
        "Jangan terburu-buru, kerjakan dengan teliti dan penuh keyakinan. 桜",
        "Setiap soal adalah kesempatan untuk menunjukkan apa yang telah kamu pelajari. Semangat! 🌸"
    ];
    return $messages[array_rand($messages)];
}

function generateExamToken(): string {
    return strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}
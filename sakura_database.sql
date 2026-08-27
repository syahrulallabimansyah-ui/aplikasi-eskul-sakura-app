-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Aug 27, 2026 at 11:38 AM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `sakura_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `id` int UNSIGNED NOT NULL,
  `message` text NOT NULL COMMENT 'Isi pesan pengumuman',
  `created_by` int UNSIGNED NOT NULL COMMENT 'ID admin yang mengirim',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=aktif, 0=dihapus'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Tabel pengumuman dari admin ke semua user';

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`id`, `message`, `created_by`, `created_at`, `is_active`) VALUES
(1, 'hallo', 1, '2026-06-15 13:05:08', 0),
(2, 'beb', 1, '2026-06-15 13:07:52', 0),
(3, 'token adalah 123456', 1, '2026-06-15 13:26:00', 0),
(4, 'hei kamu fokus', 1, '2026-06-15 13:26:37', 0),
(5, 'lalu 2', 1, '2026-06-15 13:26:55', 0),
(6, 'syahrul', 1, '2026-06-15 13:27:21', 0),
(7, 'bibi', 1, '2026-06-15 13:27:45', 0),
(8, 'bibi', 1, '2026-06-15 13:28:02', 0),
(9, 'hihi', 1, '2026-06-15 13:28:10', 0),
(10, 'hallo', 1, '2026-06-15 23:44:44', 0),
(11, 'kumpul yok', 1, '2026-06-16 01:20:08', 0),
(12, 'Hallo', 1, '2026-06-17 04:37:47', 0),
(13, 'hallo', 1, '2026-06-19 07:49:22', 0);

-- --------------------------------------------------------

--
-- Table structure for table `announcement_reads`
--

CREATE TABLE `announcement_reads` (
  `id` int UNSIGNED NOT NULL,
  `announcement_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `read_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `announcement_reads`
--

INSERT INTO `announcement_reads` (`id`, `announcement_id`, `user_id`, `read_at`) VALUES
(1, 1, 7, '2026-06-15 13:07:12'),
(2, 2, 7, '2026-06-15 13:07:56'),
(3, 3, 7, '2026-06-15 13:26:09'),
(4, 4, 7, '2026-06-15 13:26:39'),
(7, 5, 7, '2026-06-15 13:27:10'),
(8, 6, 7, '2026-06-15 13:27:24'),
(9, 7, 7, '2026-06-15 13:27:54'),
(10, 8, 7, '2026-06-15 13:28:08'),
(11, 9, 7, '2026-06-15 13:28:23'),
(12, 10, 7, '2026-06-15 23:44:48'),
(13, 11, 7, '2026-06-16 01:20:20'),
(14, 12, 7, '2026-06-17 04:37:56'),
(15, 12, 6, '2026-06-19 07:29:26'),
(16, 13, 6, '2026-06-19 07:49:32');

-- --------------------------------------------------------

--
-- Table structure for table `exams`
--

CREATE TABLE `exams` (
  `id` int UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text,
  `token` varchar(50) NOT NULL,
  `token_expires_at` datetime DEFAULT NULL COMMENT 'Batas waktu token bisa dipakai untuk memulai ujian (NULL = tidak ada batas)',
  `duration_minutes` int UNSIGNED NOT NULL DEFAULT '30',
  `status` enum('draft','published','closed') NOT NULL DEFAULT 'draft',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `exams`
--

INSERT INTO `exams` (`id`, `title`, `description`, `token`, `token_expires_at`, `duration_minutes`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(10, 'L', '', '275049', NULL, 30, 'published', 1, '2026-06-21 23:21:29', '2026-07-19 02:41:14');

-- --------------------------------------------------------

--
-- Table structure for table `exam_answers`
--

CREATE TABLE `exam_answers` (
  `id` int UNSIGNED NOT NULL,
  `attempt_id` int UNSIGNED NOT NULL,
  `question_id` int UNSIGNED NOT NULL,
  `selected_option` enum('a','b','c','d','e','f') DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `exam_answers`
--

INSERT INTO `exam_answers` (`id`, `attempt_id`, `question_id`, `selected_option`, `is_correct`) VALUES
(28, 8, 130, 'a', 1),
(29, 9, 130, 'a', 1);

-- --------------------------------------------------------

--
-- Table structure for table `exam_attempts`
--

CREATE TABLE `exam_attempts` (
  `id` int UNSIGNED NOT NULL,
  `exam_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `total_correct` int UNSIGNED DEFAULT NULL,
  `total_questions` int UNSIGNED DEFAULT NULL,
  `status` enum('not_started','in_progress','finished') NOT NULL DEFAULT 'not_started'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `exam_attempts`
--

INSERT INTO `exam_attempts` (`id`, `exam_id`, `user_id`, `started_at`, `ends_at`, `finished_at`, `score`, `total_correct`, `total_questions`, `status`) VALUES
(8, 10, 7, '2026-07-19 02:41:43', '2026-07-18 20:11:43', '2026-07-19 02:42:05', 100.00, 1, 1, 'finished'),
(9, 10, 8, '2026-07-20 05:51:16', '2026-07-19 23:21:16', '2026-07-20 05:51:39', 100.00, 1, 1, 'finished');

-- --------------------------------------------------------

--
-- Table structure for table `exam_questions`
--

CREATE TABLE `exam_questions` (
  `id` int UNSIGNED NOT NULL,
  `exam_id` int UNSIGNED NOT NULL,
  `question_text` text NOT NULL,
  `question_image` varchar(255) DEFAULT NULL,
  `option_a` text NOT NULL,
  `option_b` text NOT NULL,
  `option_c` text NOT NULL,
  `option_d` text NOT NULL,
  `option_e` text,
  `option_f` text,
  `correct_option` enum('a','b','c','d','e','f') NOT NULL,
  `question_order` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `exam_questions`
--

INSERT INTO `exam_questions` (`id`, `exam_id`, `question_text`, `question_image`, `option_a`, `option_b`, `option_c`, `option_d`, `option_e`, `option_f`, `correct_option`, `question_order`) VALUES
(130, 10, 'blalala', NULL, '1', '2', '3', '4', NULL, NULL, 'a', 1);

-- --------------------------------------------------------

--
-- Table structure for table `grammar_lesson`
--

CREATE TABLE `grammar_lesson` (
  `id` int UNSIGNED NOT NULL,
  `level_id` int UNSIGNED NOT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text,
  `urutan` int UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Pelajaran tata bahasa per level';

--
-- Dumping data for table `grammar_lesson`
--

INSERT INTO `grammar_lesson` (`id`, `level_id`, `judul`, `deskripsi`, `urutan`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(2, 1, 'pelajara 1', 'partikel dasar dan penggunaan lampau', 1, 1, 1, '2026-06-19 02:41:04', '2026-06-19 02:41:35');

-- --------------------------------------------------------

--
-- Table structure for table `grammar_level`
--

CREATE TABLE `grammar_level` (
  `id` int UNSIGNED NOT NULL,
  `kode` enum('N5','N4','N3','N2','N1') NOT NULL,
  `nama` varchar(50) NOT NULL DEFAULT '',
  `deskripsi` text,
  `urutan` int UNSIGNED NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Level JLPT N5 sampai N1';

--
-- Dumping data for table `grammar_level`
--

INSERT INTO `grammar_level` (`id`, `kode`, `nama`, `deskripsi`, `urutan`, `created_at`) VALUES
(1, 'N5', 'JLPT N5', 'Level dasar — cocok untuk pemula', 5, '2026-06-17 08:37:00'),
(2, 'N4', 'JLPT N4', 'Level menengah bawah', 4, '2026-06-17 08:37:00'),
(3, 'N3', 'JLPT N3', 'Level menengah', 3, '2026-06-17 08:37:00'),
(4, 'N2', 'JLPT N2', 'Level menengah atas', 2, '2026-06-17 08:37:00'),
(5, 'N1', 'JLPT N1', 'Level mahir', 1, '2026-06-17 08:37:00');

-- --------------------------------------------------------

--
-- Table structure for table `grammar_materi`
--

CREATE TABLE `grammar_materi` (
  `id` int UNSIGNED NOT NULL,
  `lesson_id` int UNSIGNED NOT NULL,
  `judul` varchar(200) NOT NULL,
  `penjelasan_singkat` text,
  `konten` longtext COMMENT 'Konten lengkap materi (HTML/markdown)',
  `urutan` int UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Materi tata bahasa per pelajaran';

--
-- Dumping data for table `grammar_materi`
--

INSERT INTO `grammar_materi` (`id`, `lesson_id`, `judul`, `penjelasan_singkat`, `konten`, `urutan`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(3, 2, 'ha', 'igsugdwofouewgfgewfoe\r\n\r\nefohewfoeg', 'oiefohfewhfewfoiggw\r\n\r\nfoewfiewiw\r\nfoiewfefouw\r\nclknenvkjdsbv\r\ncndvlkdsbvls', 1, 1, 1, '2026-06-19 03:01:47', '2026-06-19 03:01:47');

-- --------------------------------------------------------

--
-- Table structure for table `hafalan_item`
--

CREATE TABLE `hafalan_item` (
  `id` int UNSIGNED NOT NULL,
  `kategori_id` int UNSIGNED NOT NULL,
  `judul` varchar(255) NOT NULL,
  `deskripsi` text,
  `tipe` enum('gambar','audio','video','link') NOT NULL DEFAULT 'gambar',
  `file_path` varchar(500) DEFAULT NULL,
  `link_url` varchar(1000) DEFAULT NULL,
  `urutan` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hafalan_item`
--

INSERT INTO `hafalan_item` (`id`, `kategori_id`, `judul`, `deskripsi`, `tipe`, `file_path`, `link_url`, `urutan`, `created_at`) VALUES
(3, 4, 'kikimasu', '', 'audio', 'uploads/hafalan/hf_6a34f4f34bd745.35477534.mp3', NULL, 1, '2026-06-19 07:51:15'),
(4, 3, 'MYMY', 'love forever', 'gambar', 'uploads/hafalan/hf_6a505d24b5d733.75581588.jpg', NULL, 1, '2026-07-10 02:47:00');

-- --------------------------------------------------------

--
-- Table structure for table `hafalan_kategori`
--

CREATE TABLE `hafalan_kategori` (
  `id` int UNSIGNED NOT NULL,
  `nama` varchar(100) NOT NULL,
  `deskripsi` text,
  `icon` varchar(10) DEFAULT '?',
  `urutan` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `hafalan_kategori`
--

INSERT INTO `hafalan_kategori` (`id`, `nama`, `deskripsi`, `icon`, `urutan`, `created_at`) VALUES
(2, 'Kanji', 'Karakter kanji beserta artinya', '漢', 2, '2026-06-13 13:25:53'),
(3, 'Grammar', 'Pola kalimat dan tata bahasa', '📖', 3, '2026-06-13 13:25:53'),
(4, 'Kotoba', 'kata', '📚', 4, '2026-06-13 21:42:00');

-- --------------------------------------------------------

--
-- Table structure for table `kotoba_quiz`
--

CREATE TABLE `kotoba_quiz` (
  `id` int UNSIGNED NOT NULL,
  `judul` varchar(150) NOT NULL,
  `deskripsi` text,
  `token` varchar(50) NOT NULL,
  `duration_minutes` int UNSIGNED NOT NULL DEFAULT '15',
  `status` enum('draft','published','closed') NOT NULL DEFAULT 'draft',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kotoba_quiz`
--

INSERT INTO `kotoba_quiz` (`id`, `judul`, `deskripsi`, `token`, `duration_minutes`, `status`, `created_by`, `created_at`, `updated_at`) VALUES
(8, 'bab 3 Quiz', '', '9ACBDC', 15, 'published', 1, '2026-06-16 00:16:00', '2026-06-16 00:21:52');

-- --------------------------------------------------------

--
-- Table structure for table `kotoba_quiz_answers`
--

CREATE TABLE `kotoba_quiz_answers` (
  `id` int UNSIGNED NOT NULL,
  `attempt_id` int UNSIGNED NOT NULL,
  `soal_id` int UNSIGNED NOT NULL,
  `selected_option` enum('a','b','c','d') DEFAULT NULL,
  `is_correct` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `kotoba_quiz_attempts`
--

CREATE TABLE `kotoba_quiz_attempts` (
  `id` int UNSIGNED NOT NULL,
  `quiz_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ends_at` timestamp NULL DEFAULT NULL,
  `finished_at` timestamp NULL DEFAULT NULL,
  `score` decimal(5,2) DEFAULT NULL,
  `total_correct` int UNSIGNED DEFAULT NULL,
  `total_questions` int UNSIGNED DEFAULT NULL,
  `status` enum('not_started','in_progress','finished') NOT NULL DEFAULT 'not_started'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kotoba_quiz_attempts`
--

INSERT INTO `kotoba_quiz_attempts` (`id`, `quiz_id`, `user_id`, `started_at`, `ends_at`, `finished_at`, `score`, `total_correct`, `total_questions`, `status`) VALUES
(5, 8, 7, '2026-07-18 03:38:46', '2026-07-18 03:53:46', '2026-07-18 10:39:02', 0.00, 0, 32, 'finished'),
(6, 8, 6, '2026-06-19 00:31:25', '2026-06-19 00:46:25', NULL, NULL, NULL, 32, 'in_progress'),
(7, 8, 8, '2026-07-19 23:00:20', '2026-07-19 23:15:20', '2026-07-20 06:00:27', 0.00, 0, 32, 'finished');

-- --------------------------------------------------------

--
-- Table structure for table `kotoba_quiz_soal`
--

CREATE TABLE `kotoba_quiz_soal` (
  `id` int UNSIGNED NOT NULL,
  `quiz_id` int UNSIGNED NOT NULL,
  `kotoba` varchar(255) NOT NULL COMMENT 'Kata bahasa Jepang (hiragana/katakana/kanji)',
  `cara_baca` varchar(255) DEFAULT NULL COMMENT 'Cara baca / furigana (opsional)',
  `arti` varchar(255) NOT NULL COMMENT 'Arti / terjemahan bahasa Indonesia',
  `option_a` text NOT NULL,
  `option_b` text NOT NULL,
  `option_c` text NOT NULL,
  `option_d` text NOT NULL,
  `correct_option` enum('a','b','c','d') NOT NULL,
  `question_order` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kotoba_quiz_soal`
--

INSERT INTO `kotoba_quiz_soal` (`id`, `quiz_id`, `kotoba`, `cara_baca`, `arti`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`, `question_order`) VALUES
(41, 8, 'あそこ', 'あそこ', 'Di sana', 'Di sana', 'Di sini', 'Rumah', 'Telepon', 'a', 1),
(42, 8, 'いくら', 'いくら', 'Berapa harga', 'Negara', 'Berapa harga', 'Sepatu', 'Kamar', 'b', 2),
(43, 8, 'うけつけ', 'うけつけ', 'Resepsionis', 'Resepsionis', 'Kantin', 'Lobby', 'Wine', 'a', 3),
(44, 8, 'うち', 'うち', 'Rumah', 'Perusahaan', 'Rumah', 'Ruang kelas', 'Elevator', 'b', 4),
(45, 8, 'うりば', 'うりば', 'Tempat berjualan', 'Kamar', 'Negara', 'Tempat berjualan', 'Telepon', 'c', 5),
(46, 8, 'えすかれーたー', 'えすかれーたー', 'Eskalator', 'Elevator', 'Eskalator', 'Lobby', 'Sepatu', 'b', 6),
(47, 8, 'えれべーたー', 'えれべーたー', 'Elevator', 'Elevator', 'Eskalator', 'Kantin', 'Rumah', 'a', 7),
(48, 8, 'えん', 'えん', 'Yen', 'Seratus', 'Yen', 'Sepuluh ribu', 'Negara', 'b', 8),
(49, 8, 'おくに', 'おくに', 'Negara', 'Negara', 'Kamar', 'Dasi', 'Telepon', 'a', 9),
(50, 8, 'おくります', 'おくります', 'Mengirim', 'Menerima', 'Mengirim', 'Menjual', 'Membeli', 'b', 10),
(51, 8, 'おてあらい', 'おてあらい', 'Kamar kecil', 'Lobby', 'Kamar kecil', 'Ruang kantor', 'Rumah', 'b', 11),
(52, 8, 'かい', 'かい', 'Lantai', 'Lantai', 'Negara', 'Sepatu', 'Kantin', 'a', 12),
(53, 8, 'かいぎしつ', 'かいぎしつ', 'Ruang meeting', 'Perusahaan', 'Ruang meeting', 'Kamar', 'Lobby', 'b', 13),
(54, 8, 'かいしゃ', 'かいしゃ', 'Perusahaan', 'Rumah', 'Kantin', 'Perusahaan', 'Telepon', 'c', 14),
(55, 8, 'きつえん', 'きつえん', 'Merokok', 'Belajar', 'Makan', 'Minum', 'Merokok', 'd', 15),
(56, 8, 'きょうしつ', 'きょうしつ', 'Ruang kelas', 'Lobby', 'Ruang kelas', 'Toilet', 'Negara', 'b', 16),
(57, 8, 'くつ', 'くつ', 'Sepatu', 'Sepatu', 'Tas', 'Buku', 'Meja', 'a', 17),
(58, 8, 'ここ', 'ここ', 'Di sini', 'Di sini', 'Di sana', 'Rumah', 'Lobby', 'a', 18),
(59, 8, 'こちら', 'こちら', 'Di sini (sopan)', 'Di mana', 'Di sini (sopan)', 'Negara', 'Telepon', 'b', 19),
(60, 8, 'じむしょ', 'じむしょ', 'Ruang kantor', 'Ruang kantor', 'Kantin', 'Rumah', 'Negara', 'a', 20),
(61, 8, 'しょくどう', 'しょくどう', 'Kantin', 'Perusahaan', 'Lobby', 'Kantin', 'Telepon', 'c', 21),
(62, 8, 'ちか', 'ちか', 'Bawah tanah', 'Atas', 'Bawah tanah', 'Depan', 'Samping', 'b', 22),
(63, 8, 'でんわ', 'でんわ', 'Telepon', 'Telepon', 'Dasi', 'Sepatu', 'Kamar', 'a', 23),
(64, 8, 'どこ', 'どこ', 'Di mana', 'Negara', 'Kamar', 'Di mana', 'Lobby', 'c', 24),
(65, 8, 'どちら', 'どちら', 'Di mana (sopan)', 'Di sini', 'Di mana (sopan)', 'Rumah', 'Kantin', 'b', 25),
(66, 8, 'なんがい', 'なんがい', 'Lantai berapa', 'Lantai berapa', 'Seratus', 'Sepuluh ribu', 'Negara', 'a', 26),
(67, 8, 'ねくたい', 'ねくたい', 'Dasi', 'Sepatu', 'Dasi', 'Kamar', 'Telepon', 'b', 27),
(68, 8, 'ひゃく', 'ひゃく', 'Seratus', 'Sepuluh', 'Seratus', 'Seribu', 'Juta', 'b', 28),
(69, 8, 'へや', 'へや', 'Kamar', 'Kamar', 'Lobby', 'Negara', 'Kantin', 'a', 29),
(70, 8, 'まん', 'まん', 'Sepuluh ribu', 'Seratus', 'Seribu', 'Sepuluh ribu', 'Juta', 'c', 30),
(71, 8, 'ろびー', 'ろびー', 'Lobby', 'Toilet', 'Lobby', 'Elevator', 'Rumah', 'b', 31),
(72, 8, 'わいん', 'わいん', 'Wine', 'Kopi', 'Teh', 'Jus', 'Wine', 'd', 32);

-- --------------------------------------------------------

--
-- Table structure for table `reading_lesson`
--

CREATE TABLE `reading_lesson` (
  `id` int UNSIGNED NOT NULL,
  `level_id` int UNSIGNED NOT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` text,
  `urutan` int UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Bab / kumpulan cerita bacaan per level JLPT';

--
-- Dumping data for table `reading_lesson`
--

INSERT INTO `reading_lesson` (`id`, `level_id`, `judul`, `deskripsi`, `urutan`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'kehidupan sehari-hari', 'she got the pocket', 1, 1, 1, '2026-06-19 07:27:10', '2026-06-19 07:27:10');

-- --------------------------------------------------------

--
-- Table structure for table `reading_story`
--

CREATE TABLE `reading_story` (
  `id` int UNSIGNED NOT NULL,
  `lesson_id` int UNSIGNED NOT NULL,
  `judul` varchar(200) NOT NULL,
  `kategori` varchar(50) NOT NULL DEFAULT 'Cerita' COMMENT 'Jenis bacaan: Cerita, Artikel, Berita, Dialog, dll — bebas diisi admin',
  `penjelasan_singkat` text,
  `konten` longtext COMMENT 'Isi lengkap cerita/artikel/berita (HTML/teks)',
  `urutan` int UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='Cerita / artikel / berita bacaan per bab';

--
-- Dumping data for table `reading_story`
--

INSERT INTO `reading_story` (`id`, `lesson_id`, `judul`, `kategori`, `penjelasan_singkat`, `konten`, `urutan`, `is_active`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 'liburan', 'Cerita', 'siapa dalam nya', 'lorem ipsum', 1, 1, 1, '2026-06-19 07:28:19', '2026-06-19 07:28:19');

-- --------------------------------------------------------

--
-- Table structure for table `tugas`
--

CREATE TABLE `tugas` (
  `id` int UNSIGNED NOT NULL,
  `judul` varchar(200) NOT NULL,
  `deskripsi` longtext COMMENT 'Konten teks / HTML tugas',
  `video_url` varchar(1000) DEFAULT NULL COMMENT 'URL video embed (YouTube, dsb)',
  `tipe_upload` enum('foto','video','foto_video') NOT NULL DEFAULT 'foto' COMMENT 'Jenis file yang wajib diunggah user',
  `status` enum('draft','published','closed') NOT NULL DEFAULT 'draft',
  `deadline` datetime DEFAULT NULL COMMENT 'Batas waktu pengumpulan tugas (opsional)',
  `created_by` int UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tugas`
--

INSERT INTO `tugas` (`id`, `judul`, `deskripsi`, `video_url`, `tipe_upload`, `status`, `deadline`, `created_by`, `created_at`, `updated_at`) VALUES
(11, 'tulis satu buku huruf hiragana', 'batas waktu sampai hari senin jika sampi hari senin tidak di kumpulkan maka tidak akan mendapat nilai', NULL, 'foto', 'published', NULL, 1, '2026-06-19 07:54:36', '2026-06-19 07:54:36');

-- --------------------------------------------------------

--
-- Table structure for table `tugas_submissions`
--

CREATE TABLE `tugas_submissions` (
  `id` int UNSIGNED NOT NULL,
  `tugas_id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `catatan` text COMMENT 'Catatan opsional dari user',
  `file_foto` varchar(500) DEFAULT NULL COMMENT 'Path file foto yang diunggah',
  `file_video` varchar(500) DEFAULT NULL COMMENT 'Path file video yang diunggah',
  `nilai` decimal(5,2) DEFAULT NULL COMMENT 'Nilai tugas (0-100), diisi admin',
  `feedback` text COMMENT 'Catatan/feedback dari admin',
  `graded_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu penilaian',
  `submitted_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `tugas_submissions`
--

INSERT INTO `tugas_submissions` (`id`, `tugas_id`, `user_id`, `catatan`, `file_foto`, `file_video`, `nilai`, `feedback`, `graded_at`, `submitted_at`, `updated_at`) VALUES
(4, 11, 6, 'jawaban ada hati mu mwehehe', 'uploads/tugas/foto_6_6a34f612d90db.jfif', NULL, 75.00, NULL, '2026-06-19 07:57:44', '2026-06-19 07:56:02', '2026-06-19 07:57:44'),
(5, 11, 7, NULL, 'uploads/tugas/foto_7_6a3b2a54c414a.jpg', NULL, 1.00, NULL, '2026-06-24 00:54:08', '2026-06-24 00:52:36', '2026-06-24 00:54:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `nis` varchar(30) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `avatar` varchar(255) DEFAULT NULL,
  `bio` text,
  `hiragana_status` enum('belum_dijawab','sudah_paham','lulus_ujian') NOT NULL DEFAULT 'belum_dijawab' COMMENT 'Status pemahaman Hiragana user',
  `hiragana_updated_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu terakhir status hiragana berubah',
  `katakana_status` enum('belum_dijawab','sudah_paham','lulus_ujian') NOT NULL DEFAULT 'belum_dijawab' COMMENT 'Status pemahaman Katakana user',
  `katakana_updated_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu terakhir status katakana berubah',
  `hiragana_exam_score` decimal(5,2) DEFAULT NULL COMMENT 'Skor ujian pemahaman hiragana terakhir (%)',
  `katakana_exam_score` decimal(5,2) DEFAULT NULL COMMENT 'Skor ujian pemahaman katakana terakhir (%)',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `kana_onboarding_asked_at` timestamp NULL DEFAULT NULL COMMENT 'Waktu modal onboarding hiragana/katakana pertama kali dijawab (NULL = belum pernah ditanya / user baru)'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `nis`, `email`, `password`, `role`, `avatar`, `bio`, `hiragana_status`, `hiragana_updated_at`, `katakana_status`, `katakana_updated_at`, `hiragana_exam_score`, `katakana_exam_score`, `created_at`, `updated_at`, `kana_onboarding_asked_at`) VALUES
(1, 'Administrator', 'ADM001', 'admin@sakura.com', '$2y$12$19GrNk4zHwcBIKsjD3ALCujR6WgLv7vT9apbKtPYeSooYrDDeRB4m', 'admin', NULL, 'Pengelola utama sistem Sakura App.', 'belum_dijawab', NULL, 'belum_dijawab', NULL, NULL, NULL, '2026-06-13 04:02:54', '2026-06-20 04:35:13', '2026-06-20 04:35:13'),
(6, 'syahrul', '2026001', 'bima@gmail.com', '$2y$12$XQAOT0Wz4BlfCAhqVh/ZjunxLeJ948TJxnwFSxxL1Huk13PQyn4kC', 'user', NULL, NULL, 'belum_dijawab', NULL, 'belum_dijawab', NULL, NULL, NULL, '2026-06-15 10:45:37', '2026-06-20 04:35:13', '2026-06-20 04:35:13'),
(7, 'bimansyah', '2026002', 'bimansyah@gmail.com', '$2y$12$5WLD0Z3y5UczZdx3qd6DOOU24MVc/G.5tDnPeGhJQXS1iR/4WQY6O', 'user', NULL, NULL, 'sudah_paham', '2026-07-18 10:36:32', 'belum_dijawab', NULL, NULL, NULL, '2026-06-15 13:06:49', '2026-07-18 10:36:32', '2026-06-20 04:35:13'),
(8, 'walnuts', '2026003', 'walnuts@gmail.com', '$2y$12$zwub/x5YGnqFnMjIOaB2ceQ0VJrWvHRzCFgjMkOlMsuYp2lhIOPJm', 'user', NULL, NULL, 'belum_dijawab', NULL, 'belum_dijawab', NULL, NULL, NULL, '2026-06-20 03:46:47', '2026-06-20 04:35:13', '2026-06-20 04:35:13'),
(9, 'gigi', '2026004', 'gigi@gmail.com', '$2y$12$5vmdRPreK2gj56HxTBUGaeIx19nzi4I0xV9i8fzcNBbmE3vU006LS', 'user', NULL, NULL, 'sudah_paham', '2026-06-20 04:07:41', 'sudah_paham', '2026-06-20 04:07:42', NULL, NULL, '2026-06-20 03:55:25', '2026-06-20 04:35:13', '2026-06-20 04:35:13'),
(10, 'gugu', '2026005', 'gugu@gmail.com', '$2y$12$A09Yz1TjGXnd9gXSHyFRgesq7dR8vPI7voqu.K/Dz0ygt8Td06m2y', 'user', NULL, NULL, 'belum_dijawab', NULL, 'belum_dijawab', NULL, NULL, NULL, '2026-06-20 04:07:01', '2026-06-20 04:35:13', '2026-06-20 04:35:13'),
(11, 'bibi', '2026006', 'bibi@gmail.com', '$2y$12$91pQoRZQMonODODmQkNTcO0iJQh7qfj.Z0kp6RZsBWzyazar.xAW2', 'user', NULL, NULL, 'belum_dijawab', NULL, 'belum_dijawab', NULL, NULL, NULL, '2026-06-20 04:37:00', '2026-06-20 04:37:40', '2026-06-20 04:37:40'),
(12, 'dudu', '2026007', 'dudu@gmail.com', '$2y$12$1XL7qmOVt3w9G6zMe8Ypause89pRBsZ6VuLh0NwJ50ZrkJxCN6z6.', 'user', NULL, NULL, 'belum_dijawab', NULL, 'belum_dijawab', NULL, NULL, NULL, '2026-06-20 04:55:06', '2026-06-20 04:59:16', '2026-06-20 04:59:16');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int UNSIGNED NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `last_activity` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_ann_user` (`announcement_id`,`user_id`);

--
-- Indexes for table `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `exam_answers`
--
ALTER TABLE `exam_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_attempt_question` (`attempt_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_exam_user` (`exam_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `exam_id` (`exam_id`);

--
-- Indexes for table `grammar_lesson`
--
ALTER TABLE `grammar_lesson`
  ADD PRIMARY KEY (`id`),
  ADD KEY `level_id` (`level_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `grammar_level`
--
ALTER TABLE `grammar_level`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`);

--
-- Indexes for table `grammar_materi`
--
ALTER TABLE `grammar_materi`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_id` (`lesson_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `hafalan_item`
--
ALTER TABLE `hafalan_item`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kategori_id` (`kategori_id`);

--
-- Indexes for table `hafalan_kategori`
--
ALTER TABLE `hafalan_kategori`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kotoba_quiz`
--
ALTER TABLE `kotoba_quiz`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `kotoba_quiz_answers`
--
ALTER TABLE `kotoba_quiz_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_kotoba_attempt_soal` (`attempt_id`,`soal_id`),
  ADD KEY `soal_id` (`soal_id`);

--
-- Indexes for table `kotoba_quiz_attempts`
--
ALTER TABLE `kotoba_quiz_attempts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_kotoba_quiz_user` (`quiz_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `kotoba_quiz_soal`
--
ALTER TABLE `kotoba_quiz_soal`
  ADD PRIMARY KEY (`id`),
  ADD KEY `quiz_id` (`quiz_id`);

--
-- Indexes for table `reading_lesson`
--
ALTER TABLE `reading_lesson`
  ADD PRIMARY KEY (`id`),
  ADD KEY `level_id` (`level_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `reading_story`
--
ALTER TABLE `reading_story`
  ADD PRIMARY KEY (`id`),
  ADD KEY `lesson_id` (`lesson_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `tugas`
--
ALTER TABLE `tugas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `tugas_submissions`
--
ALTER TABLE `tugas_submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_tugas_user` (`tugas_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nis` (`nis`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `announcement_reads`
--
ALTER TABLE `announcement_reads`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `exam_answers`
--
ALTER TABLE `exam_answers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `exam_questions`
--
ALTER TABLE `exam_questions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=131;

--
-- AUTO_INCREMENT for table `grammar_lesson`
--
ALTER TABLE `grammar_lesson`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `grammar_level`
--
ALTER TABLE `grammar_level`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `grammar_materi`
--
ALTER TABLE `grammar_materi`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `hafalan_item`
--
ALTER TABLE `hafalan_item`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hafalan_kategori`
--
ALTER TABLE `hafalan_kategori`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `kotoba_quiz`
--
ALTER TABLE `kotoba_quiz`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `kotoba_quiz_answers`
--
ALTER TABLE `kotoba_quiz_answers`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=77;

--
-- AUTO_INCREMENT for table `kotoba_quiz_attempts`
--
ALTER TABLE `kotoba_quiz_attempts`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `kotoba_quiz_soal`
--
ALTER TABLE `kotoba_quiz_soal`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT for table `reading_lesson`
--
ALTER TABLE `reading_lesson`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `reading_story`
--
ALTER TABLE `reading_story`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tugas`
--
ALTER TABLE `tugas`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `tugas_submissions`
--
ALTER TABLE `tugas_submissions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `exams`
--
ALTER TABLE `exams`
  ADD CONSTRAINT `exams_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_answers`
--
ALTER TABLE `exam_answers`
  ADD CONSTRAINT `exam_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `exam_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `exam_questions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_attempts`
--
ALTER TABLE `exam_attempts`
  ADD CONSTRAINT `exam_attempts_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `exam_attempts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD CONSTRAINT `exam_questions_ibfk_1` FOREIGN KEY (`exam_id`) REFERENCES `exams` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `grammar_lesson`
--
ALTER TABLE `grammar_lesson`
  ADD CONSTRAINT `grammar_lesson_ibfk_level` FOREIGN KEY (`level_id`) REFERENCES `grammar_level` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grammar_lesson_ibfk_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `grammar_materi`
--
ALTER TABLE `grammar_materi`
  ADD CONSTRAINT `grammar_materi_ibfk_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `grammar_lesson` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `grammar_materi_ibfk_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hafalan_item`
--
ALTER TABLE `hafalan_item`
  ADD CONSTRAINT `hafalan_item_ibfk_1` FOREIGN KEY (`kategori_id`) REFERENCES `hafalan_kategori` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `kotoba_quiz`
--
ALTER TABLE `kotoba_quiz`
  ADD CONSTRAINT `kotoba_quiz_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `kotoba_quiz_answers`
--
ALTER TABLE `kotoba_quiz_answers`
  ADD CONSTRAINT `kotoba_quiz_answers_ibfk_1` FOREIGN KEY (`attempt_id`) REFERENCES `kotoba_quiz_attempts` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `kotoba_quiz_answers_ibfk_2` FOREIGN KEY (`soal_id`) REFERENCES `kotoba_quiz_soal` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `kotoba_quiz_attempts`
--
ALTER TABLE `kotoba_quiz_attempts`
  ADD CONSTRAINT `kotoba_quiz_attempts_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `kotoba_quiz` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `kotoba_quiz_attempts_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `kotoba_quiz_soal`
--
ALTER TABLE `kotoba_quiz_soal`
  ADD CONSTRAINT `kotoba_quiz_soal_ibfk_1` FOREIGN KEY (`quiz_id`) REFERENCES `kotoba_quiz` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reading_lesson`
--
ALTER TABLE `reading_lesson`
  ADD CONSTRAINT `reading_lesson_ibfk_level` FOREIGN KEY (`level_id`) REFERENCES `grammar_level` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reading_lesson_ibfk_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `reading_story`
--
ALTER TABLE `reading_story`
  ADD CONSTRAINT `reading_story_ibfk_lesson` FOREIGN KEY (`lesson_id`) REFERENCES `reading_lesson` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reading_story_ibfk_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tugas`
--
ALTER TABLE `tugas`
  ADD CONSTRAINT `tugas_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tugas_submissions`
--
ALTER TABLE `tugas_submissions`
  ADD CONSTRAINT `tugas_submissions_ibfk_1` FOREIGN KEY (`tugas_id`) REFERENCES `tugas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tugas_submissions_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

# 桜 Sakura App — Setup Guide

## Struktur File
```
sakura-app/
├── index.php         ← Halaman Sign In & Register
├── beranda.php       ← Halaman Dashboard (setelah login)
├── auth.php          ← API handler (login, register, logout)
├── config.php        ← Konfigurasi database & helper
├── database.sql      ← Script database MySQL
├── css/
│   └── style.css     ← Styling utama (Japanese aesthetic)
└── js/
    ├── auth.js       ← Logic form auth
    └── petals.js     ← Animasi kelopak sakura
```

## Cara Setup di Laragon

### 1. Taruh folder di htdocs
Salin folder `sakura-app/` ke:
```
C:\laragon\www\sakura-app\
```

### 2. Buat database
1. Buka **phpMyAdmin** di Laragon: http://localhost/phpmyadmin
2. Klik tab **SQL**
3. Salin isi file `database.sql` dan **Execute**

### 3. Akses website
Buka browser: **http://localhost/sakura-app**

---

## Akun Default

| Role  | Email            | Password  |
|-------|------------------|-----------|
| Admin | admin@sakura.com | password  |
| User  | user@sakura.com  | password  |

---

## Fitur
- ✅ Sign In & Register dengan validasi
- ✅ Session PHP yang aman
- ✅ Role: Admin & User
- ✅ Beranda dengan profil sesuai role
- ✅ Admin: statistik total pengguna
- ✅ Desain Jepang (motif asanoha, kanji, torii accent)
- ✅ Animasi kelopak sakura mengambang
- ✅ Responsive (mobile-friendly)

## Konfigurasi Database
Edit `config.php` jika pengaturan MySQL kamu berbeda:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sakura_app');
define('DB_USER', 'root');   // username MySQL kamu
define('DB_PASS', '');        // password MySQL kamu
```

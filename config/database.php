<?php
// config/database.php - koneksi PDO (otomatis baca env Railway, fallback ke XAMPP lokal)
$dsn = 'mysql:host=' . (getenv('MYSQLHOST') ?: '127.0.0.1') . ';port=' . (getenv('MYSQLPORT') ?: '3306')
     . ';dbname=' . (getenv('MYSQLDATABASE') ?: 'ruang_hukum') . ';charset=utf8mb4';
$pdo = new PDO($dsn, getenv('MYSQLUSER') ?: 'root', getenv('MYSQLPASSWORD') ?: '', [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

<?php
// includes/init.php - dipanggil paling awal di setiap halaman: session, DB, helper, user saat ini
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
session_set_cookie_params(['lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
session_start();
$MOD = require __DIR__ . '/../config/moderation.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/moderation.php';
require_once __DIR__ . '/post_card.php';
$currentUser = getCurrentUser($pdo);
if ($currentUser['status'] === 'banned' && (int) $currentUser['is_guest'] === 0) {
    session_destroy();
    die('Akun ini diblokir permanen.');
}

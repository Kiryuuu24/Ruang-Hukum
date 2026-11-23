<?php
// database/create_admin.php - bikin admin/lawyer lewat CLI. Pakai: php database/create_admin.php username email password [admin|lawyer]
if (PHP_SAPI !== 'cli') exit('Hanya lewat CLI');
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
$user = $argv[1] ?? null; $mail = $argv[2] ?? null; $pass = $argv[3] ?? null; $role = $argv[4] ?? 'admin';
if (!$user || !$mail || !$pass || !in_array($role, ['admin', 'lawyer'], true)) exit("Pakai: php database/create_admin.php username email password [admin|lawyer]\n");
$pdo->prepare('INSERT INTO users (username, email, password_hash, is_guest, role, avatar_color) VALUES (:u, :e, :p, 0, :r, :c)')
    ->execute([':u' => $user, ':e' => $mail, ':p' => password_hash($pass, PASSWORD_DEFAULT), ':r' => $role, ':c' => $role === 'admin' ? '#b03a2e' : '#0f766e']);
echo "Akun $role '$user' dibuat.\n";

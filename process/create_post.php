<?php
// process/create_post.php - memproses postingan baru lewat pipeline moderasi
require_once '../includes/init.php';
requirePostWithCsrf();
$content = trim($_POST['content'] ?? '');
$catId = (int) ($_POST['category_id'] ?? 0);
setOldInput(['content' => $content, 'category_id' => $catId]);

$cek = $pdo->prepare('SELECT id FROM categories WHERE id = :id');
$cek->execute([':id' => $catId]);
if (!$cek->fetchColumn()) { setFlash('error', 'Pilih kategori yang valid.'); header('Location: ../index.php'); exit; }

$hasil = moderateContent($pdo, $currentUser, $content, 'post', $_POST);
if ($hasil['action'] === 'reject') { setFlash('error', $hasil['message']); header('Location: ../index.php'); exit; }

$pdo->prepare('INSERT INTO posts (user_id, category_id, content, status, flag_reason, content_hash) VALUES (:u, :c, :t, :s, :f, :h)')
    ->execute([':u' => $currentUser['id'], ':c' => $catId, ':t' => $hasil['content'], ':s' => $hasil['status'], ':f' => $hasil['flag'], ':h' => $hasil['hash']]);
logActivity($pdo, (int) $currentUser['id'], 'post_create');
unset($_SESSION['old']);
setFlash('success', $hasil['status'] === 'pending' ? 'Terkirim. Postinganmu menunggu tinjauan moderator sebelum tampil.' : 'Postingan terkirim.');
header('Location: ../index.php');

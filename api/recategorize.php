<?php
// api/recategorize.php - lawyer/admin mengubah kategori. SENGAJA tidak mengisi updated_at (supaya tidak ada label "diedit" palsu)
define('IS_API', true);
require_once '../includes/init.php';
requirePostWithCsrf(true);
requireRole($currentUser, ['lawyer', 'admin'], true);
$pid = (int) ($_POST['post_id'] ?? 0); $cid = (int) ($_POST['category_id'] ?? 0);
$k = $pdo->prepare('SELECT id FROM categories WHERE id = :id'); $k->execute([':id' => $cid]);
$p = $pdo->prepare("SELECT user_id FROM posts WHERE id = :id AND status = 'published'"); $p->execute([':id' => $pid]);
$post = $p->fetch();
if (!$k->fetchColumn() || !$post) jsonResponse(['success' => false, 'code' => 'INVALID', 'message' => 'Data tidak valid.'], 422);
$pdo->prepare('UPDATE posts SET category_id = :c WHERE id = :id')->execute([':c' => $cid, ':id' => $pid]);
createNotification($pdo, (int) $post['user_id'], 'recategorized', ['actor_id' => $currentUser['id'], 'post_id' => $pid]);
jsonResponse(['success' => true, 'message' => 'Kategori diubah.']);

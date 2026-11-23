<?php
// api/like.php - toggle like (JSON). User yang muted masih boleh like, suspended/banned tidak
define('IS_API', true);
require_once '../includes/init.php';
requirePostWithCsrf(true);
$pid = (int) ($_POST['post_id'] ?? 0);
$r = getRestriction($pdo, $currentUser);
if ($r && $r['code'] !== 'MUTED') jsonResponse(['success' => false, 'code' => $r['code'], 'message' => $r['message']], 403);

$st = $pdo->prepare("SELECT id, user_id FROM posts WHERE id = :id AND status = 'published'");
$st->execute([':id' => $pid]);
$post = $st->fetch();
if (!$post) jsonResponse(['success' => false, 'code' => 'NOT_FOUND', 'message' => 'Postingan tidak ditemukan.'], 404);

$role = getUserRole($currentUser);
if ($role !== 'admin') {   // maksimal 20 (tamu) / 40 like per menit
    $c = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id = :u AND action = 'like' AND created_at >= NOW() - INTERVAL 1 MINUTE");
    $c->execute([':u' => $currentUser['id']]);
    if ((int) $c->fetchColumn() >= ($role === 'guest' ? 20 : 40)) {
        jsonResponse(['success' => false, 'code' => 'RATE_LIMIT', 'message' => 'Pelan-pelan dulu, tunggu sebentar.', 'retry_after' => 60], 429);
    }
}
$del = $pdo->prepare('DELETE FROM likes WHERE user_id = :u AND post_id = :p');
$del->execute([':u' => $currentUser['id'], ':p' => $pid]);
$liked = false;
if ($del->rowCount() === 0) {
    $pdo->prepare('INSERT INTO likes (user_id, post_id) VALUES (:u, :p)')->execute([':u' => $currentUser['id'], ':p' => $pid]);
    logActivity($pdo, (int) $currentUser['id'], 'like');
    createNotification($pdo, (int) $post['user_id'], 'like', ['actor_id' => $currentUser['id'], 'post_id' => $pid]);
    $liked = true;
}
$n = $pdo->prepare('SELECT COUNT(*) FROM likes WHERE post_id = :p');
$n->execute([':p' => $pid]);
jsonResponse(['success' => true, 'liked' => $liked, 'count' => (int) $n->fetchColumn()]);

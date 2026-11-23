<?php
// api/reply.php - jawaban baru (JSON), lewat pipeline moderasi
define('IS_API', true);
require_once '../includes/init.php';
requirePostWithCsrf(true);
$pid = (int) ($_POST['post_id'] ?? 0);
$content = trim($_POST['content'] ?? '');

$st = $pdo->prepare("SELECT id, user_id FROM posts WHERE id = :id AND status = 'published'");
$st->execute([':id' => $pid]);
$post = $st->fetch();
if (!$post) jsonResponse(['success' => false, 'code' => 'NOT_FOUND', 'message' => 'Postingan tidak ditemukan.'], 404);

$h = moderateContent($pdo, $currentUser, $content, 'reply', $_POST);
if ($h['action'] === 'reject') {
    jsonResponse(['success' => false, 'code' => $h['code'], 'message' => $h['message'], 'retry_after' => $h['retry_after'] ?? null], $h['http']);
}
$pdo->prepare('INSERT INTO replies (post_id, user_id, content, status, flag_reason, content_hash) VALUES (:p, :u, :c, :s, :f, :h)')
    ->execute([':p' => $pid, ':u' => $currentUser['id'], ':c' => $h['content'], ':s' => $h['status'], ':f' => $h['flag'], ':h' => $h['hash']]);
$rid = (int) $pdo->lastInsertId();
logActivity($pdo, (int) $currentUser['id'], 'reply_create');
if ($h['status'] === 'published') {   // pending belum memicu notifikasi
    createNotification($pdo, (int) $post['user_id'], isLawyer($currentUser) ? 'lawyer_reply' : 'reply',
        ['actor_id' => $currentUser['id'], 'post_id' => $pid, 'reply_id' => $rid]);
}
$row = $pdo->prepare('SELECT r.*, u.username, u.role, u.is_guest, u.avatar_color FROM replies r JOIN users u ON u.id = r.user_id WHERE r.id = :id');
$row->execute([':id' => $rid]);
jsonResponse(['success' => true, 'html' => renderReply($row->fetch(), $currentUser),
    'message' => $h['status'] === 'pending' ? 'Terkirim. Jawabanmu menunggu tinjauan moderator.' : 'Jawaban terkirim.']);

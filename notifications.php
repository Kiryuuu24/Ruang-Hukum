<?php
// notifications.php - daftar notifikasi; semua ditandai dibaca setelah halaman dirender
require_once 'includes/init.php';
$list = fetchNotifications($pdo, (int) $currentUser['id']);
$pageTitle = 'Notifikasi';
include 'includes/header.php';
if (!isMember($currentUser)): ?><div class="alert info">Notifikasi ini tersimpan di browser ini. <a href="register.php">Daftar</a> supaya tidak hilang kalau cookie terhapus.</div><?php endif;
foreach ($list as $n):
    $href = $n['post_id'] ? 'detail-post.php?id=' . (int) $n['post_id'] . ($n['reply_id'] ? '#reply-' . (int) $n['reply_id'] : '') : '#'; ?>
    <a class="notif <?= $n['is_read'] ? '' : 'unread' ?>" href="<?= e($href) ?>">
        <span><?= renderNotificationText($n) ?></span><span class="dim"><?= e(timeAgo($n['updated_at'])) ?></span>
    </a>
<?php endforeach;
if (!$list) echo '<p class="empty">Belum ada notifikasi.</p>';
include 'includes/footer.php';
$pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = :u AND is_read = 0')->execute([':u' => $currentUser['id']]);

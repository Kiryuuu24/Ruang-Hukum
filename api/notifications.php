<?php
// api/notifications.php - jumlah notifikasi belum dibaca (dipolling tiap 30 detik)
define('IS_API', true);
require_once '../includes/init.php';
jsonResponse(['success' => true, 'unread' => countUnread($pdo, (int) $currentUser['id'])]);

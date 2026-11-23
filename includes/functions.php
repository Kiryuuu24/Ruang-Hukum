<?php
// includes/functions.php - fungsi bantu: escape, CSRF, flash, tamu, query feed, notifikasi, ikon
function e($s): string { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }

function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function setFlash(string $type, string $msg): void { $_SESSION['flash'] = [$type, $msg]; }
function getFlash(): ?array { $f = $_SESSION['flash'] ?? null; unset($_SESSION['flash']); return $f; }
function setOldInput(array $a): void { $_SESSION['old'] = $a; }

function getCsrfToken(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function isCsrfValid(): bool {
    $t = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && hash_equals(getCsrfToken(), $t);
}
function requirePostWithCsrf(bool $api = false): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isCsrfValid()) return;
    if ($api) jsonResponse(['success' => false, 'code' => 'CSRF', 'message' => 'Sesi tidak valid, muat ulang halaman.'], 403);
    setFlash('error', 'Sesi tidak valid, coba lagi.');
    header('Location: ../index.php');
    exit;
}

// ---------- pengguna & tamu ----------
function fetchUser(PDO $pdo, int $id): ?array {
    $st = $pdo->prepare('SELECT id, username, email, is_guest, role, status, restricted_until, penalty_points,
                                penalty_updated_at, avatar_color, created_at FROM users WHERE id = :id');
    $st->execute([':id' => $id]);
    return $st->fetch() ?: null;
}
function createGuestUser(PDO $pdo): array {
    $warna = ['#0f766e', '#b45309', '#1d4ed8', '#9f1239', '#4d7c0f', '#475569'];
    for ($i = 0; $i < 10; $i++) {
        try {
            $st = $pdo->prepare('INSERT INTO users (username, is_guest, avatar_color, last_ip_hash) VALUES (:u, 1, :c, :ip)');
            $st->execute([':u' => 'Pengguna-' . random_int(1000, 9999), ':c' => $warna[array_rand($warna)], ':ip' => getClientIpHash()]);
            $_SESSION['user_id'] = (int) $pdo->lastInsertId();
            return fetchUser($pdo, $_SESSION['user_id']);
        } catch (PDOException $ex) {
            if ($ex->getCode() !== '23000') throw $ex;   // 23000 = username bentrok, coba lagi
        }
    }
    throw new RuntimeException('Gagal membuat identitas tamu');
}
function getCurrentUser(PDO $pdo): array {
    if (!empty($_SESSION['user_id'])) {
        $u = fetchUser($pdo, (int) $_SESSION['user_id']);
        if ($u) return $u;
    }
    return createGuestUser($pdo);
}

// ---------- kategori ----------
function getCategories(PDO $pdo): array {
    return $pdo->query('SELECT id, name, slug, is_system FROM categories ORDER BY is_system, id')->fetchAll();
}

// Class warna kategori. Cocokkan lewat kata kunci di slug, jadi "hukum-ketenagakerjaan", "ketenagakerjaan", dst sama-sama kena.
// Kategori yang tidak dikenal dapat warna tetap (berdasarkan slug) dari palet, bukan abu-abu. Abu-abu khusus "Belum Diketahui".
function catClass(string $slug): string {
    if ($slug === 'belum-diketahui') return 'cc-x';
    $kunci = ['pidana' => 1, 'perdata' => 2, 'keluarga' => 3, 'kerja' => 4, 'konsumen' => 5,
              'tanah' => 6, 'agraria' => 6, 'properti' => 6];
    foreach ($kunci as $k => $n) if (strpos($slug, $k) !== false) return 'cc' . $n;
    return 'cc' . (1 + abs(crc32($slug)) % 8);
}
// ---------- feed ----------
// Pending/hidden hanya terlihat oleh penulisnya sendiri
function fetchPosts(PDO $pdo, array $v, array $o = []): array {
    $w = ["(p.status = 'published' OR (p.user_id = :vid AND p.status IN ('pending','hidden')))"];
    $par = [':vid' => $v['id'], ':vid2' => $v['id']];
    if (!empty($o['cat'])) { $w[] = 'c.slug = :cat'; $par[':cat'] = $o['cat']; }
    if (!empty($o['id']))  { $w[] = 'p.id = :pid';  $par[':pid'] = $o['id']; }
    $order = ($o['sort'] ?? 'new') === 'top' ? 'likes DESC, p.created_at DESC' : 'p.created_at DESC';
    $sql = "SELECT p.*, u.username, u.role, u.is_guest, u.avatar_color, c.name AS cat_name, c.slug AS cat_slug,
              (SELECT COUNT(*) FROM likes l WHERE l.post_id = p.id) AS likes,
              (SELECT COUNT(*) FROM replies r WHERE r.post_id = p.id AND r.status = 'published') AS reply_count,
              EXISTS(SELECT 1 FROM likes l WHERE l.post_id = p.id AND l.user_id = :vid2) AS liked
            FROM posts p JOIN users u ON u.id = p.user_id JOIN categories c ON c.id = p.category_id
            WHERE " . implode(' AND ', $w) . " ORDER BY $order LIMIT " . (int) ($o['limit'] ?? 30);
    $st = $pdo->prepare($sql);
    $st->execute($par);
    return $st->fetchAll();
}
// Jawaban advokat tampil paling atas, sisanya urut waktu
function fetchReplies(PDO $pdo, array $v, int $postId): array {
    $st = $pdo->prepare("SELECT r.*, u.username, u.role, u.is_guest, u.avatar_color FROM replies r
        JOIN users u ON u.id = r.user_id
        WHERE r.post_id = :p AND (r.status = 'published' OR (r.user_id = :v AND r.status IN ('pending','hidden')))
        ORDER BY (u.role = 'lawyer' AND u.is_guest = 0) DESC, r.created_at ASC, r.id ASC");
    $st->execute([':p' => $postId, ':v' => $v['id']]);
    return $st->fetchAll();
}

// ---------- notifikasi ----------
function createNotification(PDO $pdo, int $to, string $type, array $o = []): void {
    $actor = $o['actor_id'] ?? null;
    if ($actor !== null && (int) $actor === $to) return;   // tidak notif untuk aksi sendiri
    $post = $o['post_id'] ?? null; $reply = $o['reply_id'] ?? null;
    if (in_array($type, ['reply', 'like'], true)) {         // gabungkan selagi belum dibaca
        $st = $pdo->prepare('SELECT id FROM notifications WHERE user_id = :u AND type = :t AND post_id = :p AND is_read = 0 LIMIT 1');
        $st->execute([':u' => $to, ':t' => $type, ':p' => $post]);
        if ($id = $st->fetchColumn()) {
            $pdo->prepare('UPDATE notifications SET group_count = group_count + 1, actor_id = :a, reply_id = :r, updated_at = NOW() WHERE id = :id')
                ->execute([':a' => $actor, ':r' => $reply, ':id' => $id]);
            return;
        }
    }
    $pdo->prepare('INSERT INTO notifications (user_id, type, actor_id, post_id, reply_id, message) VALUES (:u, :t, :a, :p, :r, :m)')
        ->execute([':u' => $to, ':t' => $type, ':a' => $actor, ':p' => $post, ':r' => $reply, ':m' => $o['message'] ?? null]);
}
function countUnread(PDO $pdo, int $uid): int {
    $st = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :u AND is_read = 0');
    $st->execute([':u' => $uid]);
    return (int) $st->fetchColumn();
}
function fetchNotifications(PDO $pdo, int $uid, int $limit = 20): array {
    $st = $pdo->prepare('SELECT n.*, a.username AS actor_name FROM notifications n
        LEFT JOIN users a ON a.id = n.actor_id WHERE n.user_id = :u ORDER BY n.updated_at DESC LIMIT ' . (int) $limit);
    $st->execute([':u' => $uid]);
    return $st->fetchAll();
}
function renderNotificationText(array $n): string {
    $a = '<strong>' . e($n['actor_name'] ?? 'Seseorang') . '</strong>';
    $more = $n['group_count'] > 1 ? ' dan ' . ($n['group_count'] - 1) . ' lainnya' : '';
    switch ($n['type']) {
        case 'reply':         return $a . $more . ' menjawab postinganmu';
        case 'lawyer_reply':  return 'Advokat terverifikasi ' . $a . ' menjawab postinganmu';
        case 'like':          return $a . $more . ' menyukai postinganmu';
        case 'recategorized': return 'Postinganmu dipindah kategori oleh ' . $a;
        default:              return e($n['message'] ?? 'Ada pembaruan untukmu');
    }
}

// ---------- tampilan ----------
function icon(string $name, int $s = 20): string {
    $p = [
        'home'  => '<path d="M3 11l9-8 9 8v10a1 1 0 0 1-1 1h-5v-7h-6v7H4a1 1 0 0 1-1-1z"/>',
        'bell'  => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21.2l7.8-7.8 1-1a5.5 5.5 0 0 0 0-7.8z"/>',
        'chat'  => '<path d="M21 11.5a8.4 8.4 0 0 1-12.4 7.4L3 20.5l1.6-5.4A8.4 8.4 0 1 1 21 11.5z"/>',
        'user'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'moon'  => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
    ];
    return '<svg width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($p[$name] ?? '') . '</svg>';
}
function renderRoleBadge(array $u): string {
    if ((int) $u['is_guest'] === 1) return '<span class="badge">Tamu</span>';
    if ($u['role'] === 'lawyer') return '<span class="badge badge-lawyer" title="Advokat Terverifikasi">' . icon('check', 12) . ' Advokat</span>';
    if ($u['role'] === 'admin')  return '<span class="badge badge-admin">Admin</span>';
    return '';
}
function avatar(array $u, int $size = 40): string {
    $huruf = mb_strtoupper(mb_substr(preg_replace('/^Pengguna-/', '', $u['username']), 0, 1));
    return '<span class="avatar" style="background:' . e($u['avatar_color']) . ';width:' . $size . 'px;height:' . $size . 'px">' . e($huruf) . '</span>';
}
function timeAgo(string $dt): string {
    $d = time() - strtotime($dt);
    if ($d < 60) return 'baru saja';
    if ($d < 3600) return floor($d / 60) . ' mnt';
    if ($d < 86400) return floor($d / 3600) . ' j';
    if ($d < 604800) return floor($d / 86400) . ' h';
    return date('j M Y', strtotime($dt));
}

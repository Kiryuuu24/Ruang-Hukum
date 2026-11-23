<?php
// login.php - masuk (hanya anggota, is_guest = 0). Gagal 5x per 15 menit per IP = ditolak sementara
require_once 'includes/init.php';
if (isMember($currentUser)) { header('Location: index.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isCsrfValid()) {
    $id = trim($_POST['identity'] ?? '');
    $c = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE ip_hash = :ip AND action = 'login_fail' AND created_at >= NOW() - INTERVAL 15 MINUTE");
    $c->execute([':ip' => getClientIpHash()]);
    if ((int) $c->fetchColumn() >= 5) $err = 'Terlalu banyak percobaan gagal. Coba lagi 15 menit lagi.';
    else {
        $s = $pdo->prepare('SELECT id, password_hash, status FROM users WHERE (username = :i OR email = :i2) AND is_guest = 0');
        $s->execute([':i' => $id, ':i2' => $id]);
        $u = $s->fetch();
        if ($u && password_verify($_POST['password'] ?? '', $u['password_hash']) && $u['status'] !== 'banned') {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $u['id'];
            header('Location: index.php'); exit;
        }
        logActivity($pdo, null, 'login_fail');
        $err = 'Username/email atau password salah.';
    }
}
$pageTitle = 'Masuk';
include 'includes/header.php';
?>
<form class="auth" method="post">
    <?php if ($err): ?><div class="alert error"><?= e($err) ?></div><?php endif; ?>
    <input name="identity" placeholder="Username atau email" required>
    <input name="password" type="password" placeholder="Password" required>
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <button class="btn" type="submit">Masuk</button>
    <p class="dim">Belum punya akun? <a href="register.php">Daftar</a></p>
</form>
<?php include 'includes/footer.php'; ?>

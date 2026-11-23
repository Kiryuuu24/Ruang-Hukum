<?php
// register.php - daftar. UPDATE baris tamu yang sama (bukan INSERT) supaya postingan lama ikut pindah
require_once 'includes/init.php';
if (isMember($currentUser)) { header('Location: index.php'); exit; }
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isCsrfValid()) {
    $user = trim($_POST['username'] ?? ''); $mail = trim($_POST['email'] ?? ''); $pass = $_POST['password'] ?? '';
    $c = $pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE ip_hash = :ip AND action = 'register' AND created_at >= NOW() - INTERVAL 1 HOUR");
    $c->execute([':ip' => getClientIpHash()]);
    if (!empty($_POST['website'])) $err = 'Terjadi kesalahan, coba lagi.';
    elseif ((int) $c->fetchColumn() >= 3) $err = 'Terlalu banyak pendaftaran dari jaringan ini. Coba lagi nanti.';
    elseif (!preg_match('/^[A-Za-z0-9_]{3,20}$/', $user) || stripos($user, 'pengguna') === 0) $err = 'Username 3-20 karakter (huruf, angka, garis bawah) dan tidak boleh diawali "Pengguna".';
    elseif (!filter_var($mail, FILTER_VALIDATE_EMAIL)) $err = 'Email tidak valid.';
    elseif (strlen($pass) < 8) $err = 'Password minimal 8 karakter.';
    else {
        $d = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :u OR email = :e'); $d->execute([':u' => $user, ':e' => $mail]);
        if ((int) $d->fetchColumn() > 0) $err = 'Username atau email sudah dipakai.';
        else {
            $pdo->prepare('UPDATE users SET username = :u, email = :e, password_hash = :p, is_guest = 0 WHERE id = :id AND is_guest = 1')
                ->execute([':u' => $user, ':e' => $mail, ':p' => password_hash($pass, PASSWORD_DEFAULT), ':id' => $currentUser['id']]);
            logActivity($pdo, (int) $currentUser['id'], 'register');
            session_regenerate_id(true);
            setFlash('success', 'Akun berhasil dibuat. Postingan lamamu ikut pindah.');
            header('Location: index.php'); exit;
        }
    }
}
$pageTitle = 'Daftar';
include 'includes/header.php';
?>
<form class="auth" method="post">
    <?php if ($err): ?><div class="alert error"><?= e($err) ?></div><?php endif; ?>
    <p class="dim">Daftar tanpa kehilangan postingan yang sudah kamu buat sebagai <?= e($currentUser['username']) ?>.</p>
    <input name="username" placeholder="Username" required><input name="email" type="email" placeholder="Email" required>
    <input name="password" type="password" placeholder="Password (min. 8 karakter)" required>
    <div class="hp"><input name="website" tabindex="-1" autocomplete="off"></div>
    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
    <button class="btn" type="submit">Daftar</button>
</form>
<?php include 'includes/footer.php'; ?>

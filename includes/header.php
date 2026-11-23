<?php
// includes/header.php - kerangka halaman: sidebar kiri (desktop), topbar (mobile), flash message
$unread = countUnread($pdo, (int) $currentUser['id']);
$flash = getFlash();
?><!DOCTYPE html>
<html lang="id"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= e($pageTitle ?? APP_NAME) ?> · RuangHukum</title>
<script>try{var t=localStorage.getItem('theme');if(t)document.documentElement.dataset.theme=t}catch(e){}</script>
<link rel="stylesheet" href="assets/css/style.css">
</head><body> <a class="skip" href="#main">Lewati ke konten</a>
<div class="app">
<aside class="side">
    <a class="brand" href="index.php">Ruang<b>Hukum</b></a>
    <nav aria-label="Navigasi utama">
    <a href="index.php" title="Beranda"><?= icon('home', 24) ?><span>Beranda</span></a>
    <a href="notifications.php" title="Notifikasi"><?= icon('bell', 24) ?><span>Notifikasi</span><i class="dot" data-bell <?= $unread ? '' : 'hidden' ?>><?= $unread ?></i></a>
    <?php if (isMember($currentUser)): ?>
        <a href="logout.php" title="Keluar"><?= icon('user', 24) ?><span>Keluar</span></a>
    <?php else: ?>
        <a href="register.php" title="Daftar"><?= icon('user', 24) ?><span>Daftar</span></a>
        <a href="login.php" title="Masuk"><?= icon('user', 24) ?><span>Masuk</span></a>
    <?php endif; ?>
    <button type="button" data-theme-toggle title="Ganti tema"><?= icon('moon', 24) ?><span>Tema</span></button>
</nav>
    <div class="me"><?= avatar($currentUser, 36) ?><div><div class="name"><?= e($currentUser['username']) ?></div><?= renderRoleBadge($currentUser) ?></div></div>
</aside>
<main class="col" id="main">
<header class="top"><strong><?= e($pageTitle ?? 'Beranda') ?></strong></header>
<?php if ($flash): ?><div class="alert <?= e($flash[0]) ?>"><?= e($flash[1]) ?></div><?php endif; ?>

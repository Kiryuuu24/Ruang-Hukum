<?php // includes/footer.php - tutup kolom tengah, kolom kanan, navigasi bawah mobile, toast, script
$cats = getCategories($pdo); ?>
</main>
<aside class="right">
    <section class="box"><h3>Kategori</h3>
        <?php foreach ($cats as $c): ?><a class="cd <?= e(catClass($c['slug'])) ?>" href="index.php?kategori=<?= e($c['slug']) ?>"><?= e($c['name']) ?></a><?php endforeach; ?>
    </section>
    <section class="box"><h3>Sebelum bertanya</h3>
        <p>Jangan tulis nama lengkap, alamat, atau nomor KTP. Jawaban di sini bersifat informasi umum, bukan nasihat hukum resmi.</p>
    </section>
</aside>
</div>
<nav class="bottom" aria-label="Navigasi bawah">
    <a href="index.php" aria-label="Beranda"><?= icon('home', 24) ?></a>
    <a href="notifications.php" aria-label="Notifikasi"><?= icon('bell', 24) ?><i class="dot" data-bell <?= $unread ? '' : 'hidden' ?>><?= $unread ?></i></a>
    <a href="<?= isMember($currentUser) ? 'logout.php' : 'login.php' ?>" aria-label="<?= isMember($currentUser) ? 'Keluar' : 'Masuk' ?>"><?= icon('user', 24) ?></a>
    <button type="button" data-theme-toggle aria-label="Ganti tema"><?= icon('moon', 24) ?></button>
</nav>
<div id="toast" role="status"></div>
<script src="assets/js/app.js"></script>
</body></html>

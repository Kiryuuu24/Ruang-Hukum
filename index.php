<?php
// index.php - beranda: form posting + feed + filter kategori
require_once 'includes/init.php';
$cats = getCategories($pdo);
$slugs = array_column($cats, 'slug');
$cat = in_array($_GET['kategori'] ?? '', $slugs, true) ? $_GET['kategori'] : '';
$sort = ($_GET['urut'] ?? '') === 'populer' ? 'top' : 'new';
$posts = fetchPosts($pdo, $currentUser, ['cat' => $cat, 'sort' => $sort]);
$old = $_SESSION['old'] ?? []; unset($_SESSION['old']);
$belum = 0;
if (canRecategorize($currentUser)) $belum = (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE category_id = 7 AND status = 'published'")->fetchColumn();
$pageTitle = 'Beranda';
include 'includes/header.php';
?>
<form class="composer" action="process/create_post.php" method="post">
    <?= avatar($currentUser) ?>
    <div class="fields">
        <textarea name="content" rows="2" maxlength="1000" placeholder="Ceritakan masalah hukummu..." required><?= e($old['content'] ?? '') ?></textarea>
        <div class="row">
            <select name="category_id" required>
                <option value="">Pilih kategori</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= (int) $c['id'] ?>" <?= (int) ($old['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="row-end"><span class="count" data-count>0/1000</span><button class="btn" type="submit">Kirim</button></div>
        </div>
        <small class="dim">Belum tahu ini bidang hukum apa? Pilih <em>Belum Diketahui</em>, nanti advokat bantu mengkategorikan.</small>
        <?= renderFormGuards() ?>
    </div>
</form>

<nav class="tabs" aria-label="Filter kategori">
    <a href="index.php" class="<?= $cat === '' ? 'on' : '' ?>">Semua</a>
    <?php foreach ($cats as $c): ?>
    <a href="index.php?kategori=<?= e($c['slug']) ?>" class="cd <?= e(catClass($c['slug'])) ?> <?= $cat === $c['slug'] ? 'on' : '' ?>"><?= e($c['name']) ?><?= ($c['is_system'] && $belum) ? ' · ' . $belum : '' ?></a>
    <?php endforeach; ?>
</nav>
<div class="sort"><a href="?<?= http_build_query(['kategori' => $cat]) ?>" class="<?= $sort === 'new' ? 'on' : '' ?>">Terbaru</a>
    <a href="?<?= http_build_query(['kategori' => $cat, 'urut' => 'populer']) ?>" class="<?= $sort === 'top' ? 'on' : '' ?>">Populer</a></div>
<?php foreach ($posts as $p) echo renderPostCard($p, $currentUser); ?>
<?php if (!$posts): ?><p class="empty">Belum ada postingan di sini. Jadi yang pertama bertanya.</p><?php endif; ?>
<?php include 'includes/footer.php'; ?>

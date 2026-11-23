<?php
// detail-post.php - satu postingan beserta jawabannya
require_once 'includes/init.php';
$id = (int) ($_GET['id'] ?? 0);
$rows = fetchPosts($pdo, $currentUser, ['id' => $id, 'limit' => 1]);
$pageTitle = 'Postingan';
include 'includes/header.php';
if (!$rows) { echo '<p class="empty">Postingan tidak ditemukan atau sudah dihapus.</p>'; include 'includes/footer.php'; exit; }
$p = $rows[0];
echo renderPostCard($p, $currentUser, true);
if (canRecategorize($currentUser)):
    $cats = getCategories($pdo); ?>
    <form class="recat" id="recat" data-post="<?= (int) $p['id'] ?>">
        <span class="dim">Ubah kategori</span>
        <select name="category_id"><?php foreach ($cats as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $p['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?></select>
        <button class="btn small" type="submit">Simpan</button>
    </form>
<?php endif;
if ($p['status'] === 'published'): ?>
    <form class="composer reply-box" id="reply-form">
        <?= avatar($currentUser, 36) ?>
        <div class="fields">
            <textarea name="content" rows="2" maxlength="1000" placeholder="Tulis jawabanmu..." required></textarea>
            <div class="row"><span></span><div class="row-end"><span class="count" data-count>0/1000</span><button class="btn" type="submit">Jawab</button></div></div>
            <input type="hidden" name="post_id" value="<?= (int) $p['id'] ?>">
            <?= renderFormGuards() ?>
        </div>
    </form>
<?php endif; ?>
<div id="replies">
<?php $replies = fetchReplies($pdo, $currentUser, $id);
foreach ($replies as $r) echo renderReply($r, $currentUser);
if (!$replies) echo '<p class="empty">Belum ada jawaban.</p>'; ?>
</div>
<?php include 'includes/footer.php'; ?>

<?php
// includes/post_card.php - HTML kartu postingan dan jawaban (dipakai PHP dan dikirim ke JS lewat JSON)
function renderPostCard(array $p, array $v, bool $detail = false): string {
    $href = 'detail-post.php?id=' . (int) $p['id'];
    $h  = '<article class="post" id="post-' . (int) $p['id'] . '"' . ($detail ? '' : ' data-href="' . $href . '"') . '>';
    $h .= avatar($p) . '<div class="body"><div class="meta"><span class="name">' . e($p['username']) . '</span>' . renderRoleBadge($p);
    $h .= '<span class="dim">· <a href="' . $href . '">' . e(timeAgo($p['created_at'])) . '</a>' . ($p['updated_at'] ? ' · diedit' : '') . '</span></div>';
    if ($p['status'] === 'pending') $h .= '<div class="notice">Menunggu tinjauan moderator</div>';
    $h .= '<div class="text">' . nl2br(e($p['content'])) . '</div>';
    $h .= '<a class="pill ' . catClass($p['cat_slug']) . '" href="index.php?kategori=' . e($p['cat_slug']) . '">' . e($p['cat_name']) . '</a>';
    $h .= '<div class="actions"><a class="act" href="' . $href . '" aria-label="Jawaban">' . icon('chat', 18) . '<span>' . (int) $p['reply_count'] . '</span></a>';
    $h .= '<button class="act like' . ($p['liked'] ? ' on' : '') . '" data-like="' . (int) $p['id'] . '" type="button" aria-label="Suka" aria-pressed="' . ($p['liked'] ? 'true' : 'false') . '">' . icon('heart', 18) . '<span class="n">' . (int) $p['likes'] . '</span></button>';
    return $h . '</div></div></article>';
}
function renderReply(array $r, array $v): string {
    $lawyer = $r['role'] === 'lawyer' && (int) $r['is_guest'] === 0;
    $h  = '<article class="post reply' . ($lawyer ? ' by-lawyer' : '') . '" id="reply-' . (int) $r['id'] . '">' . avatar($r, 36);
    $h .= '<div class="body"><div class="meta"><span class="name">' . e($r['username']) . '</span>' . renderRoleBadge($r);
    $h .= '<span class="dim">· ' . e(timeAgo($r['created_at'])) . '</span></div>';
    if ($r['status'] === 'pending') $h .= '<div class="notice">Menunggu tinjauan moderator</div>';
    return $h . '<div class="text">' . nl2br(e($r['content'])) . '</div></div></article>';
}
function renderFormGuards(): string {   // honeypot + tanda waktu form + CSRF
    return '<div class="hp" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>'
         . '<input type="hidden" name="form_ts" value="' . e(makeFormTs()) . '">'
         . '<input type="hidden" name="csrf_token" value="' . e(getCsrfToken()) . '">';
}

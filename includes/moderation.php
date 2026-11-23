<?php
// includes/moderation.php - rate limit, penalti, normalisasi teks, filter kata kasar, skor spam, pipeline moderateContent()

// ---------- log & rate limit (jendela geser dari activity_log) ----------
function logActivity(PDO $pdo, ?int $uid, string $action, ?string $detail = null): void {
    $pdo->prepare('INSERT INTO activity_log (user_id, ip_hash, action, detail) VALUES (:u, :ip, :a, :d)')
        ->execute([':u' => $uid, ':ip' => getClientIpHash(), ':a' => $action, ':d' => $detail]);
}
// Mengembalikan sisa detik yang harus ditunggu (0 = aman)
function checkRateLimit(PDO $pdo, int $uid, string $action, int $gap, int $perHour): int {
    $st = $pdo->prepare('SELECT COUNT(*) c, TIMESTAMPDIFF(SECOND, MAX(created_at), NOW()) sejak_terakhir,
                                TIMESTAMPDIFF(SECOND, MIN(created_at), NOW()) sejak_pertama
                         FROM activity_log WHERE user_id = :u AND action = :a AND created_at >= NOW() - INTERVAL 1 HOUR');
    $st->execute([':u' => $uid, ':a' => $action]);
    $r = $st->fetch();
    if ((int) $r['c'] === 0) return 0;
    if ($gap - (int) $r['sejak_terakhir'] > 0) return $gap - (int) $r['sejak_terakhir'];
    if ((int) $r['c'] >= $perHour) return max(1, 3600 - (int) $r['sejak_pertama']);
    return 0;
}

// ---------- penalti: poin meluruh 1 per 24 jam (dihitung saat dicek) ----------
function getEffectivePenalty(PDO $pdo, int $uid): int {
    $st = $pdo->prepare('SELECT GREATEST(0, penalty_points - FLOOR(IFNULL(TIMESTAMPDIFF(HOUR, penalty_updated_at, NOW()), 0) / 24)) FROM users WHERE id = :id');
    $st->execute([':id' => $uid]);
    return (int) $st->fetchColumn();
}
function addPenalty(PDO $pdo, int $uid, int $pts, string $alasan): void {
    global $MOD;
    $new = min(255, getEffectivePenalty($pdo, $uid) + $pts);
    $status = null; $iv = '';
    if ($new >= $MOD['suspend_at'])   { $status = 'suspended'; $iv = '7 DAY'; }
    elseif ($new >= $MOD['mute_at'])  { $status = 'muted';     $iv = '24 HOUR'; }
    $sql = 'UPDATE users SET penalty_points = :p, penalty_updated_at = NOW()'
         . ($status ? ", status = '$status', restricted_until = NOW() + INTERVAL $iv" : '')
         . " WHERE id = :id AND status <> 'banned'";
    $pdo->prepare($sql)->execute([':p' => $new, ':id' => $uid]);
    if ($status) createNotification($pdo, $uid, 'penalty', ['message' => 'Akunmu dibatasi sementara: ' . $alasan]);
}
// Mengembalikan null kalau bebas, atau ['code','message'] kalau sedang dibatasi
function getRestriction(PDO $pdo, array $u): ?array {
    $st = $pdo->prepare('SELECT status, restricted_until, restricted_until > NOW() AS aktif FROM users WHERE id = :id');
    $st->execute([':id' => $u['id']]);
    $r = $st->fetch();
    if ($r['status'] === 'banned') return ['code' => 'BANNED', 'message' => 'Akun ini diblokir permanen.'];
    if (in_array($r['status'], ['muted', 'suspended'], true)) {
        if ($r['aktif']) {
            $waktu = date('j M H:i', strtotime($r['restricted_until']));
            return $r['status'] === 'muted'
                ? ['code' => 'MUTED', 'message' => "Akunmu dibisukan sampai $waktu karena melanggar aturan komunitas."]
                : ['code' => 'SUSPENDED', 'message' => "Akunmu ditangguhkan sampai $waktu."];
        }
        $pdo->prepare("UPDATE users SET status = 'active', restricted_until = NULL WHERE id = :id")->execute([':id' => $u['id']]);
    }
    return null;
}

// ---------- form_ts (tanda tangan waktu form) ----------
function makeFormTs(): string { $t = time(); return $t . '.' . hash_hmac('sha256', (string) $t, APP_SALT); }
function isFormTsValid(string $v): bool {
    global $MOD;
    $p = explode('.', $v, 2);
    if (count($p) !== 2 || !ctype_digit($p[0])) return false;
    if (!hash_equals(hash_hmac('sha256', $p[0], APP_SALT), $p[1])) return false;
    $umur = time() - (int) $p[0];
    return $umur >= $MOD['min_form_seconds'] && $umur <= $MOD['max_form_seconds'];
}

// ---------- normalisasi & kata terlarang ----------
function normalizeText(string $t): string {
    $t = mb_strtolower($t, 'UTF-8');
    $t = strtr($t, ['4' => 'a', '3' => 'e', '1' => 'i', '0' => 'o', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '!' => 'i']);
    $t = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9\s]/', ' ', $t)));
    // "a n j i n g" -> "anjing"
    return preg_replace_callback('/\b(?:[a-z] ){3,}[a-z]\b/', fn($m) => str_replace(' ', '', $m[0]), $t);
}
function squeezeRepeat(string $s): string { return preg_replace('/(.)\1+/u', '$1', $s); }
function getBannedWords(PDO $pdo): array {
    static $cache = null;   // dimuat sekali per request
    if ($cache === null) $cache = $pdo->query('SELECT word, kind FROM banned_words WHERE is_active = 1')->fetchAll();
    return $cache;
}
function checkProfanity(PDO $pdo, string $norm): array {
    $ada = [];
    foreach (explode(' ', $norm) as $tok) if ($tok !== '') { $ada[$tok] = 1; $ada[squeezeRepeat($tok)] = 1; }
    $hasil = ['severe' => false, 'mild' => []];
    foreach (getBannedWords($pdo) as $w) {
        if (!in_array($w['kind'], ['profanity_severe', 'profanity_mild', 'hate'], true)) continue;
        if (isset($ada[$w['word']]) || isset($ada[squeezeRepeat($w['word'])])) {
            if ($w['kind'] === 'profanity_mild') $hasil['mild'][] = $w['word']; else $hasil['severe'] = true;
        }
    }
    return $hasil;
}
function maskMildWords(string $text, array $words): string {
    foreach ($words as $w) {
        $text = preg_replace_callback('/(?<![\p{L}\p{N}])' . preg_quote($w, '/') . '(?![\p{L}\p{N}])/iu', function ($m) {
            $n = mb_strlen($m[0]);
            return $n <= 2 ? str_repeat('*', $n) : mb_substr($m[0], 0, 1) . str_repeat('*', $n - 2) . mb_substr($m[0], -1);
        }, $text);
    }
    return $text;
}

// ---------- skor spam ----------
function calculateSpamScore(PDO $pdo, string $text, string $norm, string $role): int {
    $s = 0;
    $bebas = in_array($role, ['lawyer', 'admin'], true);
    $link = preg_match_all('~(https?://|www\.|\b[a-z0-9-]+\.(com|net|id|xyz|top|info|site|online)\b)~i', $text);
    if ($link > 0 && !$bebas) { $s += $role === 'guest' ? 2 : 1; if ($link >= 2) $s += 3; }
    $padded = ' ' . $norm . ' '; $kuat = 0; $lemah = 0;
    foreach (getBannedWords($pdo) as $w) {
        if (!in_array($w['kind'], ['spam_strong', 'spam_weak'], true)) continue;
        if (strpos($padded, ' ' . normalizeText($w['word']) . ' ') !== false) { $w['kind'] === 'spam_strong' ? $kuat++ : $lemah++; }
    }
    if ($kuat) $s += 6;
    $s += min(4, $lemah * 2);
    if (preg_match('/(\+62|\b08)\d{8,12}\b/', $text)) $s += 2;
    if (preg_match('/(.)\1{7,}/su', $text)) $s += 3;
    if (preg_match('/\S{41,}/u', preg_replace('~https?://\S+~i', '', $text))) $s += 3;
    $huruf = preg_match_all('/\p{L}/u', $text); $besar = preg_match_all('/\p{Lu}/u', $text);
    if (mb_strlen($text) > 30 && $huruf > 0 && $besar / $huruf > 0.7) $s += 2;
    return $s;
}

// ---------- pipeline utama ----------
// Mengembalikan ['action'=>'allow'|'pending'|'reject', 'content', 'status', 'flag', 'hash', 'code', 'message', 'http', 'retry_after']
function moderateContent(PDO $pdo, array $u, string $text, string $type, array $in = []): array {
    global $MOD;
    $role = getUserRole($u);
    $tolak = fn($kode, $pesan, $http = 422, $extra = []) => array_merge(['action' => 'reject', 'code' => $kode, 'message' => $pesan, 'http' => $http], $extra);
    $min = $type === 'post' ? 10 : 3;
    $lenOk = mb_strlen($text) >= $min && mb_strlen($text) <= 1000;
    $lenMsg = "Isi harus $min sampai 1000 karakter.";
    $ok = ['action' => 'allow', 'content' => $text, 'status' => 'published', 'flag' => null, 'hash' => hash('sha256', str_replace(' ', '', normalizeText($text)))];

    if ($role === 'admin') return $lenOk ? $ok : $tolak('LENGTH', $lenMsg);   // admin melewati langkah 1-8

    // 1. status akun
    if ($r = getRestriction($pdo, $u)) return $tolak($r['code'], $r['message'], 403);
    // 2. bot (honeypot + waktu isi form)
    if (!empty($in['website']) || !isFormTsValid((string) ($in['form_ts'] ?? ''))) {
        logActivity($pdo, $u['id'], 'blocked_bot');
        addPenalty($pdo, $u['id'], $MOD['points']['bot'], 'terdeteksi bot');
        return $tolak('BOT', 'Terjadi kesalahan, muat ulang halaman lalu coba lagi.');
    }
    // 3. rate limit (tamu < 10 menit: batas lebih ketat)
    $lim = $MOD['limits'][$role]; $efektif = getEffectivePenalty($pdo, $u['id']);
    $gap = $lim[$type . '_gap']; $jam = $lim[$type . '_hour'];
    if ($role === 'guest' && (time() - strtotime($u['created_at'])) < 600) { $gap *= 2; $jam = (int) floor($jam / 2); }
    if ($efektif >= 4) $gap = $MOD['limited_gap'];
    $tunggu = checkRateLimit($pdo, $u['id'], $type . '_create', $gap, $jam);
    if ($tunggu > 0) {
        return $efektif >= 4
            ? $tolak('COOLDOWN', 'Kamu sedang dibatasi sementara. Coba lagi dalam ' . ceil($tunggu / 60) . ' menit.', 429, ['retry_after' => $tunggu])
            : $tolak('RATE_LIMIT', "Pelan-pelan dulu, tunggu $tunggu detik sebelum kirim lagi.", 429, ['retry_after' => $tunggu]);
    }
    // 4. panjang
    if (!$lenOk) return $tolak('LENGTH', $lenMsg);
    // 5-6. normalisasi + kata kasar
    $norm = normalizeText($text);
    $kasar = checkProfanity($pdo, $norm);
    if ($kasar['severe']) {
        logActivity($pdo, $u['id'], 'blocked_profanity');
        addPenalty($pdo, $u['id'], $MOD['points']['profanity'], 'kata tidak pantas');
        return $tolak('PROFANITY', 'Postinganmu mengandung kata yang tidak diperbolehkan. Coba tulis ulang dengan bahasa yang sopan.');
    }
    if ($kasar['mild']) { $ok['content'] = maskMildWords($text, $kasar['mild']); logActivity($pdo, $u['id'], 'masked_mild'); }
    // 7. skor spam
    $skor = calculateSpamScore($pdo, $text, $norm, $role);
    if ($skor >= $MOD['score_reject']) {
        logActivity($pdo, $u['id'], 'blocked_spam', "skor $skor");
        addPenalty($pdo, $u['id'], $MOD['points']['spam'], 'terdeteksi spam');
        return $tolak('SPAM', 'Postinganmu terdeteksi sebagai spam. Kalau menurutmu ini keliru, ubah isinya lalu kirim lagi.');
    }
    // 8. duplikat (24 jam)
    $tabel = $type === 'post' ? 'posts' : 'replies';
    $st = $pdo->prepare("SELECT 1 FROM $tabel WHERE user_id = :u AND content_hash = :h AND created_at >= NOW() - INTERVAL 24 HOUR LIMIT 1");
    $st->execute([':u' => $u['id'], ':h' => $ok['hash']]);
    if ($st->fetchColumn()) {
        logActivity($pdo, $u['id'], 'blocked_duplicate');
        addPenalty($pdo, $u['id'], $MOD['points']['duplicate'], 'konten duplikat');
        return $tolak('DUPLICATE', 'Isi ini sama dengan yang baru saja kamu kirim.');
    }
    // 9. keputusan akhir
    if ($role === 'guest' && ($skor >= $MOD['score_pending'] || $efektif >= 4)) {
        $ok['action'] = 'pending'; $ok['status'] = 'pending'; $ok['flag'] = 'skor ' . $skor;
        addPenalty($pdo, $u['id'], $MOD['points']['pending'], 'konten mencurigakan');
    } elseif ($role === 'user' && $skor >= $MOD['score_pending']) {
        $ok['flag'] = 'skor ' . $skor;   // tayang, tapi ditandai untuk admin
    }
    return $ok;
}

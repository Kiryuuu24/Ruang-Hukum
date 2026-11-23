<?php
// includes/auth.php - helper role dan hash IP pengunjung
function getUserRole(array $user): string {
    return (int) $user['is_guest'] === 1 ? 'guest' : $user['role'];
}
function isAdmin(array $user): bool  { return getUserRole($user) === 'admin'; }
function isLawyer(array $user): bool { return getUserRole($user) === 'lawyer'; }
function isMember(array $user): bool { return (int) $user['is_guest'] === 0; }
function canRecategorize(array $user): bool { return in_array(getUserRole($user), ['lawyer', 'admin'], true); }

function requireRole(array $user, array $roles, bool $isApi = false): void {
    if (in_array(getUserRole($user), $roles, true)) return;
    if ($isApi) jsonResponse(['success' => false, 'code' => 'FORBIDDEN', 'message' => 'Kamu tidak punya akses.'], 403);
    setFlash('error', 'Kamu tidak punya akses ke halaman itu.');
    header('Location: index.php');
    exit;
}

// IP tidak pernah disimpan mentah. Di Railway IP asli ada di X-Forwarded-For (entri pertama)
function getClientIpHash(): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) $ip = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
    return hash('sha256', $ip . APP_SALT);
}

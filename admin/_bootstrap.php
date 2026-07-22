<?php
declare(strict_types=1);

$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
$adminPath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin'))), '/') ?: '/';
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('bsg_admin');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $adminPath,
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrfToken(): string
{
    if (empty($_SESSION['admin_csrf'])) {
        $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['admin_csrf'];
}

function requireAdmin(): void
{
    if (($_SESSION['admin_authenticated'] ?? false) !== true) {
        header('Location: login.php');
        exit;
    }

    $lastSeen = (int) ($_SESSION['admin_last_seen'] ?? 0);
    if ($lastSeen > 0 && time() - $lastSeen > 1800) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: login.php?expired=1');
        exit;
    }
    $_SESSION['admin_last_seen'] = time();
}

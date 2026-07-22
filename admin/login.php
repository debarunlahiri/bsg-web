<?php
declare(strict_types=1);
require __DIR__ . '/_bootstrap.php';
require_once dirname(__DIR__) . '/config.php';

if (($_SESSION['admin_authenticated'] ?? false) === true) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attempts = array_values(array_filter(
        (array) ($_SESSION['login_attempts'] ?? []),
        static fn($timestamp): bool => is_int($timestamp) && $timestamp > time() - 900
    ));

    if (count($attempts) >= 5) {
        http_response_code(429);
        $error = 'Too many attempts. Please wait 15 minutes and try again.';
    } elseif (!hash_equals(csrfToken(), (string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        $error = 'Your session expired. Refresh the page and try again.';
    } else {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $admin = null;
        try {
            $statement = database()->prepare(
                'SELECT id, username, password_hash FROM admin_users WHERE username = :username AND is_active = 1 LIMIT 1'
            );
            $statement->execute(['username' => $username]);
            $admin = $statement->fetch() ?: null;
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }

        // Always verify a hash so unknown usernames do not return noticeably faster.
        $hashToCheck = $admin['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        $valid = is_array($admin) && password_verify($password, $hashToCheck);

        if ($valid) {
            session_regenerate_id(true);
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_user_id'] = (int) $admin['id'];
            $_SESSION['admin_username'] = (string) $admin['username'];
            $_SESSION['admin_last_seen'] = time();
            $_SESSION['login_attempts'] = [];
            $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
            database()->prepare('UPDATE admin_users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id')
                ->execute(['id' => $admin['id']]);
            header('Location: index.php');
            exit;
        }

        $attempts[] = time();
        $_SESSION['login_attempts'] = $attempts;
        usleep(random_int(250000, 500000));
        $error = 'Invalid username or password.';
    }
}
?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Admin sign in</title><link rel="stylesheet" href="admin.css?v=6"></head>
<body><main class="login-shell"><section class="login-card">
    <img src="../assets/logo.jpeg" alt="Bhatnagar Sabha Ghaziabad">
    <p class="eyebrow">Private area</p><h1>Admin sign in</h1>
    <p class="muted">Only authorized administrators can view registrations.</p>
    <?php if (isset($_GET['expired'])): ?><div class="notice">Your session expired. Please sign in again.</div><?php endif; ?>
    <?php if ($error !== ''): ?><div class="error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
        <label>Username<input name="username" maxlength="80" required autocomplete="username"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button type="submit">Sign in securely</button>
    </form>
    <a class="back-link" href="../"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg><span>Back to registration form</span></a>
</section></main></body></html>

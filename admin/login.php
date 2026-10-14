<?php
require __DIR__ . '/../config.php';
if (is_admin()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE email=?");
    $stmt->execute([trim($_POST['email'] ?? '')]);
    $admin = $stmt->fetch();
    // Always run a hash check so response time doesn't reveal whether the email exists.
    $hash = $admin['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
    $ok = password_verify((string)($_POST['password'] ?? ''), $hash) && $admin;
    if ($ok) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$admin['id'];
        $_SESSION['admin_name'] = $admin['name'];
        if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
            $pdo->prepare("UPDATE admins SET password_hash=? WHERE id=?")
                ->execute([password_hash((string)$_POST['password'], PASSWORD_DEFAULT), $admin['id']]);
        }
        if (!empty($admin['must_change_password'])) {
            $_SESSION['must_change'] = true;
            flash('error', 'For security, please set a new password before continuing.');
            redirect('password.php');
        }
        redirect('index.php');
    }
    usleep(500000);
    $error = 'Invalid email or password.';
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Admin Login</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&display=swap" rel="stylesheet"><link rel="stylesheet" href="../assets/css/admin.css"></head><body class="login-body"><form class="login-card" method="post"><div class="login-mark">B</div><h1>Sign in</h1><p>Manage your portfolio content, projects and profile.</p><?php if($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><label>Email<input type="email" name="email" required autofocus autocomplete="username"></label><label>Password<input type="password" name="password" required autocomplete="current-password"></label><button>Sign In</button><small>Forgot your password? Reset the hash in the database (see README).</small></form></body></html>
<?php
require __DIR__ . '/../config.php'; require_admin();
$st = $pdo->prepare("SELECT * FROM admins WHERE id=?");
$st->execute([$_SESSION['admin_id']]);
$admin = $st->fetch();
if (!$admin) { redirect('logout.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    $email   = trim((string)($_POST['email'] ?? ''));
    $name    = trim((string)($_POST['name'] ?? ''));
    $error = '';
    if (!password_verify($current, $admin['password_hash'])) $error = 'Current password is incorrect.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
    elseif ($name === '') $error = 'Name is required.';
    elseif ($new !== '' && strlen($new) < 10) $error = 'New password must be at least 10 characters.';
    elseif ($new !== $confirm) $error = 'New password and confirmation do not match.';
    elseif ($new === '' && !empty($admin['must_change_password'])) $error = 'Please choose a new password.';
    elseif ($new === 'ChangeMe@2026') $error = 'Choose a different password from the default.';
    if (!$error) {
        $dup = $pdo->prepare("SELECT id FROM admins WHERE email=? AND id<>?");
        $dup->execute([$email, $admin['id']]);
        if ($dup->fetch()) $error = 'That email is already used by another admin.';
    }
    if ($error) {
        flash('error', $error);
        redirect('password.php');
    }
    if ($new !== '') {
        $pdo->prepare("UPDATE admins SET name=?, email=?, password_hash=?, must_change_password=0 WHERE id=?")
            ->execute([$name, $email, password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
        session_regenerate_id(true);
        unset($_SESSION['must_change']);
    } else {
        $pdo->prepare("UPDATE admins SET name=?, email=? WHERE id=?")->execute([$name, $email, $admin['id']]);
    }
    $_SESSION['admin_name'] = $name;
    flash('success', 'Account updated.');
    redirect('index.php');
}
include __DIR__ . '/partials.php'; admin_header('Account');
?>
<form class="admin-panel form-grid" method="post" autocomplete="off"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<label>Name<input name="name" value="<?= e($admin['name']) ?>" required></label>
<label>Login Email<input type="email" name="email" value="<?= e($admin['email']) ?>" required></label>
<label class="full">Current Password<input type="password" name="current_password" required autocomplete="current-password"></label>
<label>New Password<input type="password" name="new_password" minlength="10" autocomplete="new-password"><small>At least 10 characters. Leave blank to keep the current password.</small></label>
<label>Confirm New Password<input type="password" name="confirm_password" minlength="10" autocomplete="new-password"></label>
<div class="full"><button>Save Account</button></div></form>
<?php admin_footer(); ?>
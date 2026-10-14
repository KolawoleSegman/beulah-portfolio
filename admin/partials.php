<?php
function admin_header(string $title): void {
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $type = (string)($_GET['type'] ?? '');
    $items = [
        ['index.php', 'Dashboard', 'index.php', ''],
        ['profile.php', 'Profile', 'profile.php', ''],
        ['projects.php', 'Portfolio', 'projects.php', ''],
        ['content.php?type=services', 'Services', 'content.php', 'services'],
        ['content.php?type=experience', 'Experience', 'content.php', 'experience'],
        ['content.php?type=education', 'Education', 'content.php', 'education'],
        ['content.php?type=certifications', 'Certifications', 'content.php', 'certifications'],
        ['content.php?type=leadership', 'Leadership', 'content.php', 'leadership'],
        ['content.php?type=skills', 'Skills &amp; Tools', 'content.php', 'skills'],
        ['password.php', 'Account', 'password.php', ''],
    ]; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> | Portfolio CMS</title>
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/admin.css"></head><body>
<div class="admin-layout"><aside><div class="admin-brand"><span class="mark">B</span>Portfolio CMS</div><nav aria-label="Admin">
<?php foreach ($items as [$href, $label, $file, $t]):
    $active = ($script === $file) && ($t === '' || $t === $type) ? ' class="active" aria-current="page"' : ''; ?>
<a href="<?= $href ?>"<?= $active ?>><?= $label ?></a>
<?php endforeach; ?>
<a href="logout.php" class="logout">Log out</a></nav></aside><section class="admin-main"><header class="admin-top"><strong><?= e($title) ?></strong><a href="../index.php" target="_blank" rel="noopener">View site</a></header><main>
<?php if ($f = get_flash()): ?><div class="alert <?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div><?php endif; ?>
<?php }
function admin_footer(): void { ?></main></section></div></body></html><?php }

/** Small POST form button used for delete actions (CSRF-protected). */
function delete_button(string $action, array $fields, string $confirm, string $label = 'Delete'): string {
    $html = '<form method="post" action="' . e($action) . '" class="inline-form" onsubmit="return confirm(\'' . e(addslashes($confirm)) . '\')">';
    $html .= '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
    foreach ($fields as $k => $v) {
        $html .= '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    return $html . '<button type="submit" class="link-danger">' . e($label) . '</button></form>';
}
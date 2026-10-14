<?php
require __DIR__ . '/../config.php'; require_admin();
$counts = [
'projects' => (int)$pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn(),
'published' => (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE status='published'")->fetchColumn(),
'videos' => (int)$pdo->query("SELECT COUNT(*) FROM project_media WHERE media_type IN ('video','embed')")->fetchColumn(),
'services' => (int)$pdo->query("SELECT COUNT(*) FROM services WHERE status='published'")->fetchColumn()
];
include __DIR__ . '/partials.php';
admin_header('Dashboard');
?>
<div class="stats-admin"><?php foreach($counts as $k=>$v): ?><div><b><?= $v ?></b><span><?= e(ucfirst($k)) ?></span></div><?php endforeach; ?></div>
<div class="admin-panel"><h2>Welcome, <?= e($_SESSION['admin_name'] ?? 'Admin') ?></h2><p>Use the menu to update profile information and continuously add new portfolio work without changing the code.</p><div class="quick-actions"><a href="project-form.php">＋ Add New Project</a><a href="profile.php">Edit Profile</a><a href="../index.php" target="_blank" rel="noopener">View Website ↗</a></div></div>
<?php admin_footer(); ?>

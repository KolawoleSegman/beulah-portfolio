<?php
require __DIR__ . '/../config.php'; require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    verify_csrf();
    $id=(int)$_POST['delete'];
    $stmt=$pdo->prepare("SELECT cover_image FROM projects WHERE id=?"); $stmt->execute([$id]); $p=$stmt->fetch();
    if($p) {
        $stmt=$pdo->prepare("SELECT file_path FROM project_media WHERE project_id=?"); $stmt->execute([$id]); $files=$stmt->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("DELETE FROM projects WHERE id=?")->execute([$id]);
        // Remove files only after the database rows are gone.
        delete_upload($p['cover_image']);
        foreach($files as $f) delete_upload($f);
        flash('success','Project deleted.');
    } else {
        flash('error','Project not found.');
    }
    redirect('projects.php');
}
$hasLinks=ensure_service_links($pdo);
$projects=$pdo->query("SELECT p.*, (SELECT COUNT(*) FROM project_media m WHERE m.project_id=p.id) media_count".($hasLinks?", (SELECT title FROM services s WHERE s.id=p.service_id) service_title":", NULL service_title")." FROM projects p ORDER BY p.featured DESC,p.created_at DESC")->fetchAll();
include __DIR__ . '/partials.php'; admin_header('Portfolio');
?>
<div class="toolbar"><a class="button" href="project-form.php">＋ Add Project</a></div>
<div class="table-wrap"><table><thead><tr><th>Project</th><th>Service</th><th>Media</th><th>Status</th><th>Featured</th><th>Actions</th></tr></thead><tbody>
<?php foreach($projects as $p): ?><tr><td><strong><?= e($p['title']) ?></strong><br><small><?= e($p['client']) ?></small></td><td><?= $p['service_title'] ? e($p['service_title']) : '<small>Not assigned</small>' ?></td><td><?= (int)$p['media_count'] ?></td><td><span class="badge <?= e($p['status']) ?>"><?= e($p['status']) ?></span></td><td><?= $p['featured']?'Yes':'No' ?></td><td class="actions"><a href="project-form.php?id=<?= (int)$p['id'] ?>">Edit</a> · <a href="../project.php?slug=<?= urlencode($p['slug']) ?>" target="_blank" rel="noopener">View</a> · <?= delete_button('projects.php', ['delete'=>$p['id']], 'Delete this project and all its media?') ?></td></tr><?php endforeach; ?>
<?php if(!$projects): ?><tr><td colspan="6">No projects yet. Click “Add Project” to create the first one.</td></tr><?php endif; ?>
</tbody></table></div>
<?php admin_footer(); ?>
<?php
require __DIR__ . '/../config.php'; require_admin();
$profile = get_profile($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $name = trim((string)($_POST['name'] ?? ''));
        $title = trim((string)($_POST['title'] ?? ''));
        if ($name === '' || $title === '') throw new RuntimeException('Name and professional title are required.');
        $email = trim((string)($_POST['email'] ?? ''));
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a valid email address.');
        $linkedinRaw = trim((string)($_POST['linkedin'] ?? ''));
        $linkedin = safe_url($linkedinRaw);
        if ($linkedinRaw !== '' && $linkedin === '') throw new RuntimeException('LinkedIn URL must start with http:// or https://');

        $oldPhoto = $profile['profile_photo'] ?? '';
        $photo = $oldPhoto;
        if (!empty($_FILES['profile_photo']['name'])) {
            $new = upload_file($_FILES['profile_photo'], 'uploads/profile', ['jpg','jpeg','png','webp'], 5*1024*1024);
            if ($new) $photo = $new;
        }
        $stmt = $pdo->prepare("UPDATE profile SET name=?,title=?,location=?,phone=?,email=?,linkedin=?,summary=?,profile_photo=? WHERE id=1");
        $stmt->execute([$name,$title,trim((string)($_POST['location'] ?? '')),trim((string)($_POST['phone'] ?? '')),$email,$linkedin,trim((string)($_POST['summary'] ?? '')),$photo]);
        if ($stmt->rowCount() === 0 && !$pdo->query("SELECT 1 FROM profile WHERE id=1")->fetch()) {
            $pdo->prepare("INSERT INTO profile (id,name,title,location,phone,email,linkedin,summary,profile_photo) VALUES (1,?,?,?,?,?,?,?,?)")
                ->execute([$name,$title,trim((string)($_POST['location'] ?? '')),trim((string)($_POST['phone'] ?? '')),$email,$linkedin,trim((string)($_POST['summary'] ?? '')),$photo]);
        }
        if ($photo !== $oldPhoto) delete_upload($oldPhoto);
        flash('success','Profile updated successfully.'); redirect('profile.php');
    } catch(RuntimeException $ex){ flash('error',$ex->getMessage()); redirect('profile.php'); }
}
include __DIR__ . '/partials.php'; admin_header('Profile');
?>
<form class="admin-panel form-grid" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<label>Name<input name="name" value="<?= e($profile['name']) ?>" required></label><label>Professional Title<input name="title" value="<?= e($profile['title']) ?>" required></label>
<label>Location<input name="location" value="<?= e($profile['location']) ?>"></label><label>Phone<input name="phone" value="<?= e($profile['phone']) ?>"></label>
<label>Email<input type="email" name="email" value="<?= e($profile['email']) ?>"></label><label>LinkedIn URL<input type="url" name="linkedin" value="<?= e($profile['linkedin']) ?>" placeholder="https://linkedin.com/in/..."></label>
<label class="full">Professional Summary<textarea name="summary" rows="8"><?= e($profile['summary']) ?></textarea></label>
<label>Profile Photo<input type="file" name="profile_photo" accept=".jpg,.jpeg,.png,.webp"><small>JPG, PNG or WebP, up to 5MB.</small></label>
<?php if(!empty($profile['profile_photo'])): ?><img class="profile-preview" src="../<?= e($profile['profile_photo']) ?>" alt="Profile"><?php endif; ?>
<div class="full"><button>Save Profile</button></div></form>
<?php admin_footer(); ?>
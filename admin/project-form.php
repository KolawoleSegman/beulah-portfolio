<?php
require __DIR__ . '/../config.php'; require_admin();
$id=(int)($_GET['id'] ?? 0);
$project=['title'=>'','category'=>'','client'=>'','platform'=>'','description'=>'','tools'=>'','project_url'=>'','cover_image'=>'','featured'=>0,'status'=>'published'];
if($id){
    $st=$pdo->prepare("SELECT * FROM projects WHERE id=?");$st->execute([$id]);$found=$st->fetch();
    if(!$found){ flash('error','Project not found.'); redirect('projects.php'); }
    $project=$found;
}
ensure_embed_support($pdo);
$hasServiceLinks=ensure_service_links($pdo);
$allServices=$pdo->query("SELECT id,title FROM services ORDER BY sort_order,id")->fetchAll();
if(!isset($project['service_id'])) $project['service_id']=null;
$formUrl='project-form.php'.($id?'?id='.$id:'');

// Delete a single media item
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['delete_media']) && $id){
    verify_csrf();
    $st=$pdo->prepare("SELECT file_path FROM project_media WHERE id=? AND project_id=?");$st->execute([(int)$_POST['delete_media'],$id]);$m=$st->fetch();
    if($m){ $pdo->prepare("DELETE FROM project_media WHERE id=?")->execute([(int)$_POST['delete_media']]); delete_upload($m['file_path']); flash('success','Media removed.'); }
    redirect($formUrl);
}

if($_SERVER['REQUEST_METHOD']==='POST'){
 verify_csrf();
 $newFiles=[]; // files saved during this request, removed again if anything fails
 try{
  $title=trim((string)($_POST['title'] ?? ''));
  if($title==='') throw new RuntimeException('Project title is required.');
  $status=in_array($_POST['status'] ?? '',['published','draft'],true)?$_POST['status']:'draft';
  $urlRaw=trim((string)($_POST['project_url'] ?? '')); $url=safe_url($urlRaw);
  if($urlRaw!=='' && $url==='') throw new RuntimeException('Project URL must start with http:// or https://');
  $featured=!empty($_POST['featured'])?1:0;
  $serviceId=clean_service_id($pdo,$_POST['service_id'] ?? 0);
  $slug=unique_slug($pdo,$title,$id?:null);

  $oldCover=$project['cover_image']; $cover=$oldCover;
  if(!empty($_FILES['cover_image']['name'])){
      $c=upload_file($_FILES['cover_image'],'uploads/images',['jpg','jpeg','png','webp'],5*1024*1024);
      if($c){ $cover=$c; $newFiles[]=$c; }
  }

  // Validate and store additional media before touching the database.
  $mediaRows=[];
  if(!empty($_FILES['media']['name']) && is_array($_FILES['media']['name'])){
   foreach($_FILES['media']['name'] as $i=>$name){
    if($name==='' || ($_FILES['media']['error'][$i] ?? 0)===UPLOAD_ERR_NO_FILE) continue;
    $file=['name'=>$name,'type'=>$_FILES['media']['type'][$i],'tmp_name'=>$_FILES['media']['tmp_name'][$i],'error'=>$_FILES['media']['error'][$i],'size'=>$_FILES['media']['size'][$i]];
    $isVideo=in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['mp4','webm','mov','m4v'],true);
    $path=upload_file($file,$isVideo?'uploads/videos':'uploads/images',$isVideo?['mp4','webm','mov','m4v']:['jpg','jpeg','png','webp'],$isVideo?500*1024*1024:5*1024*1024);
    if($path){ $newFiles[]=$path; $mediaRows[]=[$isVideo?'video':'image',$path]; }
   }
  }

  // Video links (YouTube / Vimeo), one per line
  $linkRows=[];
  foreach(preg_split('/\R/',(string)($_POST['video_links'] ?? '')) ?: [] as $line){
   $line=trim($line); if($line==='') continue;
   $v=parse_video_link($line);
   if(!$v) throw new RuntimeException('Not a valid YouTube or Vimeo link: '.mb_substr($line,0,80));
   $linkRows[video_watch_url($v)]=true; // keyed to drop duplicates
  }
  if($id && $linkRows){
   $have=$pdo->prepare("SELECT file_path FROM project_media WHERE project_id=? AND media_type='embed'");$have->execute([$id]);
   foreach($have->fetchAll(PDO::FETCH_COLUMN) as $u) unset($linkRows[$u]);
  }

  $pdo->beginTransaction();
  $vals=[$title,$slug,trim((string)($_POST['category'] ?? '')),trim((string)($_POST['client'] ?? '')),trim((string)($_POST['platform'] ?? '')),trim((string)($_POST['description'] ?? '')),trim((string)($_POST['tools'] ?? '')),$url,$cover,$featured,$status];
  if($id){
   $pdo->prepare("UPDATE projects SET title=?,slug=?,category=?,client=?,platform=?,description=?,tools=?,project_url=?,cover_image=?,featured=?,status=? WHERE id=?")->execute(array_merge($vals,[$id]));
   if($hasServiceLinks) $pdo->prepare("UPDATE projects SET service_id=? WHERE id=?")->execute([$serviceId,$id]);
   $projectId=$id;
  } else {
   $pdo->prepare("INSERT INTO projects(title,slug,category,client,platform,description,tools,project_url,cover_image,featured,status) VALUES(?,?,?,?,?,?,?,?,?,?,?)")->execute($vals);
   $projectId=(int)$pdo->lastInsertId();
   if($hasServiceLinks) $pdo->prepare("UPDATE projects SET service_id=? WHERE id=?")->execute([$serviceId,$projectId]);
  }
  $ins=$pdo->prepare("INSERT INTO project_media(project_id,media_type,file_path) VALUES(?,?,?)");
  foreach($mediaRows as $r) $ins->execute([$projectId,$r[0],$r[1]]);
  foreach(array_keys($linkRows) as $u) $ins->execute([$projectId,'embed',$u]);
  // Update captions of existing media
  if($id && !empty($_POST['caption']) && is_array($_POST['caption'])){
   $cap=$pdo->prepare("UPDATE project_media SET caption=? WHERE id=? AND project_id=?");
   foreach($_POST['caption'] as $mid=>$text) $cap->execute([mb_substr(trim((string)$text),0,255),(int)$mid,$projectId]);
  }
  $pdo->commit();
  if($cover!==$oldCover) delete_upload($oldCover); // only after the new cover is safely saved
  flash('success','Project saved successfully.'); redirect('projects.php');
 }catch(Throwable $ex){
  if($pdo->inTransaction()) $pdo->rollBack();
  foreach($newFiles as $f) delete_upload($f);
  flash('error',$ex->getMessage()); redirect($formUrl);
 }
}
$media=[]; if($id){$st=$pdo->prepare("SELECT * FROM project_media WHERE project_id=? ORDER BY sort_order,id");$st->execute([$id]);$media=$st->fetchAll();}
include __DIR__ . '/partials.php'; admin_header($id?'Edit Project':'Add Project');
?>
<form class="admin-panel form-grid" method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
<?php if($hasServiceLinks): ?><label class="full">Service this work belongs to<select name="service_id"><option value="">— Not assigned (shows under “More work”) —</option><?php foreach($allServices as $sv): ?><option value="<?= (int)$sv['id'] ?>" <?= (int)($project['service_id'] ?? 0)===(int)$sv['id']?'selected':'' ?>><?= e($sv['title']) ?></option><?php endforeach; ?></select><small>The project appears on that service’s page, under Work.</small></label><?php endif; ?>
<label>Project Title<input name="title" value="<?= e($project['title']) ?>" required></label><label>Category<input name="category" value="<?= e($project['category']) ?>" placeholder="Social Media, Video, Branding..."></label>
<label>Client / Brand<input name="client" value="<?= e($project['client']) ?>"></label><label>Platform<input name="platform" value="<?= e($project['platform']) ?>" placeholder="Instagram, LinkedIn, TikTok..."></label>
<label>Tools Used<input name="tools" value="<?= e($project['tools']) ?>" placeholder="Canva, CapCut, Premiere Pro..."></label><label>Project URL<input type="url" name="project_url" value="<?= e($project['project_url']) ?>" placeholder="https://..."></label>
<label class="full">Description<textarea name="description" rows="8" placeholder="Explain the project, your role, the objective and what you delivered."><?= e($project['description']) ?></textarea></label>
<label>Cover Image<input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp"><?php if($project['cover_image']): ?><small>Current cover is kept unless you choose a new file.</small><?php endif; ?></label>
<label>Additional Images / Videos<input type="file" name="media[]" multiple accept=".jpg,.jpeg,.png,.webp,.mp4,.webm,.mov,.m4v"><small>You can select multiple files. Images: 5MB each. Videos: 500MB each (also limited by your server's PHP settings).</small></label>
<label class="full">Video Links (YouTube or Vimeo)<textarea name="video_links" rows="3" placeholder="https://www.youtube.com/watch?v=...&#10;https://vimeo.com/..."></textarea><small>One link per line. They play directly on the project page. Videos can be public or unlisted.</small></label>
<label>Status<select name="status"><option value="published" <?= $project['status']==='published'?'selected':'' ?>>Published</option><option value="draft" <?= $project['status']==='draft'?'selected':'' ?>>Draft</option></select></label>
<label class="check"><input type="checkbox" name="featured" value="1" <?= $project['featured']?'checked':'' ?>> Featured project</label>
<?php if($media): ?><div class="full"><strong>Captions for existing media</strong><?php foreach($media as $m): ?><label class="caption-row"><span><?= e($m['media_type']==='embed' ? $m['file_path'] : basename($m['file_path'])) ?></span><input name="caption[<?= (int)$m['id'] ?>]" value="<?= e($m['caption']) ?>" placeholder="Optional caption"></label><?php endforeach; ?></div><?php endif; ?>
<div class="full"><button>Save Project</button> <a class="button muted" href="projects.php">Cancel</a></div></form>
<?php if($media): ?><div class="admin-panel"><h2>Existing Media</h2><div class="media-admin"><?php foreach($media as $m): ?><div><?php if($m['media_type']==='embed'): ?><a href="<?= e($m['file_path']) ?>" target="_blank" rel="noopener">▶ <?= e($m['file_path']) ?></a><br><?php elseif($m['media_type']==='video'): ?><video controls preload="metadata" src="../<?= e($m['file_path']) ?>"></video><?php else: ?><img src="../<?= e($m['file_path']) ?>" alt="Media"><?php endif; ?><small><?= e($m['media_type']) ?></small> <?= delete_button($formUrl, ['delete_media'=>$m['id']], 'Remove this file?', 'Remove') ?></div><?php endforeach; ?></div></div><?php endif; ?>
<?php admin_footer(); ?>
<?php
require __DIR__ . '/../config.php'; require_admin();
$type = $_GET['type'] ?? ($_POST['type'] ?? 'services');
$allowed = ['services','experience','education','certifications','leadership','skills'];
if (!in_array($type, $allowed, true)) $type = 'services';

// heading, singular label, table, editable fields, main label column, "details" column
$meta = [
 'services'       => ['Services','Service','services',['title','description','icon','sort_order'],'title','description'],
 'experience'     => ['Experience','Experience','experience',['company','role','period','description','sort_order'],'company','role'],
 'education'      => ['Education','Education','education',['institution','qualification','period','description','sort_order'],'institution','qualification'],
 'certifications' => ['Certifications','Certification','certifications',['title','issuer','date_text','sort_order'],'title','issuer'],
 'leadership'     => ['Leadership','Leadership','leadership',['organization','role','period','description','sort_order'],'organization','role'],
 'skills'         => ['Skills & Tools','Skill','skills',['group_name','skill_name','sort_order'],'group_name','skill_name'],
];
[$heading,$singular,$table,$fields,$labelField,$detailField] = $meta[$type];

// Experience entries can be tied to one service (needs the service_id column).
$withService = ($type === 'experience') && ensure_service_links($pdo);
$allServices = $withService ? $pdo->query("SELECT id,title FROM services ORDER BY sort_order,id")->fetchAll() : [];
// Fields that may be left blank (matches the database defaults)
$optional = ['description','period','icon','issuer','date_text','sort_order'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['delete'])) {
        $pdo->prepare("DELETE FROM {$table} WHERE id=?")->execute([(int)$_POST['delete']]);
        flash('success','Item deleted.');
        redirect('content.php?type='.$type);
    }
    $id = (int)($_POST['id'] ?? 0);
    $vals = [];
    foreach ($fields as $f) {
        $v = trim((string)($_POST[$f] ?? ''));
        if ($f === 'sort_order') $v = (string)(int)$v;
        elseif ($v === '' && !in_array($f, $optional, true)) {
            flash('error', ucwords(str_replace('_',' ',$f)).' is required.');
            redirect('content.php?type='.$type.($id ? '&edit='.$id : ''));
        }
        $vals[] = $v;
    }
    if ($withService) { $fields[] = 'service_id'; $vals[] = clean_service_id($pdo, $_POST['service_id'] ?? 0); }
    if ($id) {
        $set = implode(',', array_map(fn($f) => "$f=?", $fields));
        $vals[] = $id;
        $pdo->prepare("UPDATE {$table} SET {$set} WHERE id=?")->execute($vals);
    } else {
        $cols = implode(',', $fields);
        $qs = implode(',', array_fill(0, count($fields), '?'));
        $pdo->prepare("INSERT INTO {$table}({$cols}) VALUES({$qs})")->execute($vals);
    }
    flash('success','Saved.');
    redirect('content.php?type='.$type);
}

$edit = null;
if (isset($_GET['edit'])) {
    $st = $pdo->prepare("SELECT * FROM {$table} WHERE id=?");
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch() ?: null;
}
$svcNames = []; foreach ($allServices as $sv) $svcNames[$sv['id']] = $sv['title'];
$rows = $pdo->query("SELECT * FROM {$table} ORDER BY sort_order,id")->fetchAll();
include __DIR__ . '/partials.php'; admin_header($heading);
?>
<div class="admin-panel"><h2><?= $edit?'Edit':'Add' ?> <?= e($singular) ?></h2><form class="form-grid" method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="type" value="<?= e($type) ?>"><input type="hidden" name="id" value="<?= (int)($edit['id']??0) ?>">
<?php foreach($fields as $f): $label=ucwords(str_replace('_',' ',$f)); $req=in_array($f,$optional,true)?'':' required';
 if($f==='description'): ?><label class="full"><?= e($label) ?><textarea name="<?= e($f) ?>" rows="5"><?= e($edit[$f]??'') ?></textarea></label>
<?php elseif($f==='sort_order'): ?><label><?= e($label) ?><input type="number" name="<?= e($f) ?>" value="<?= e($edit[$f]??0) ?>"></label>
<?php else: ?><label><?= e($label) ?><input name="<?= e($f) ?>" value="<?= e($edit[$f]??'') ?>"<?= $req ?>></label>
<?php endif; endforeach; ?>
<?php if($withService): ?><label class="full">Service this experience belongs to<select name="service_id"><option value="">— Not assigned (shows in About, under “More experience”) —</option><?php foreach($allServices as $sv): ?><option value="<?= (int)$sv['id'] ?>" <?= (int)($edit['service_id']??0)===(int)$sv['id']?'selected':'' ?>><?= e($sv['title']) ?></option><?php endforeach; ?></select><small>It appears on that service’s page, under Experience.</small></label><?php endif; ?>
<div class="full"><button>Save</button><?php if($edit): ?> <a class="button muted" href="content.php?type=<?= e($type) ?>">Cancel</a><?php endif; ?></div></form></div>
<div class="table-wrap"><table><thead><tr><th>Item</th><th>Details</th><th>Order</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><strong><?= e($r[$labelField]) ?></strong></td><td><?= e(mb_strimwidth((string)($r[$detailField] ?? ''),0,120,'…')) ?><?php if($withService): ?><br><small><?= !empty($r['service_id']) ? e($svcNames[$r['service_id']] ?? '') : 'Not assigned' ?></small><?php endif; ?></td><td><?= (int)$r['sort_order'] ?></td><td class="actions"><a href="?type=<?= e($type) ?>&amp;edit=<?= (int)$r['id'] ?>">Edit</a> · <?= delete_button('content.php?type='.$type, ['delete'=>$r['id'],'type'=>$type], 'Delete this item?') ?></td></tr><?php endforeach; ?>
<?php if(!$rows): ?><tr><td colspan="4">Nothing here yet. Use the form above to add the first item.</td></tr><?php endif; ?></tbody></table></div>
<?php admin_footer(); ?>

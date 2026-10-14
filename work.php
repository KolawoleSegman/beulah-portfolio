<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/site_parts.php';

ensure_service_links($pdo);

$profile  = get_profile($pdo);
$services = get_services($pdo);
$filter   = (int)($_GET['service'] ?? 0);   // 0 = everything, grouped by service

site_head('Work | ' . tidy_case($profile['name']), 'Social media, content, branding and digital work, organised by service.', 'page-work');
site_header($profile, 'work');

// Build the groups: one per service, plus "Other work" for anything not yet assigned.
$groups = [];
foreach ($services as $i => $svc) {
    if ($filter && (int)$svc['id'] !== $filter) continue;
    $items = get_projects($pdo, (int)$svc['id']);
    if ($items || $filter) $groups[] = ['service' => $svc, 'index' => $i, 'items' => $items];
}
if (!$filter) {
    $loose = get_projects($pdo, 0);
    if ($loose) $groups[] = ['service' => null, 'index' => count($services), 'items' => $loose];
}
$anyWork = false;
foreach ($groups as $g) if ($g['items']) $anyWork = true;
?>
<main id="main">
  <section class="container page-head" id="top">
    <p class="eyebrow">Portfolio</p>
    <h1>My work, <em>by service.</em></h1>
    <p class="lead">Everything I've delivered, grouped by what I did. Pick a service to focus on it.</p>

    <?php if ($services): ?>
    <nav class="svc-chips filters" aria-label="Filter by service">
      <a class="svc-chip<?= !$filter ? ' is-active' : '' ?>" href="work.php">All</a>
      <?php foreach ($services as $i => $svc): ?>
      <a class="svc-chip tone-<?= e(service_tone($i)) ?><?= $filter === (int)$svc['id'] ? ' is-active' : '' ?>" href="work.php?service=<?= (int)$svc['id'] ?>"><?= e($svc['title']) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>
  </section>

  <div class="container work-groups">
    <?php foreach ($groups as $g): $svc = $g['service']; ?>
    <section class="work-group">
      <div class="group-head">
        <h2 class="sec-title sm"><?= $svc ? e($svc['title']) : 'More work' ?> <span class="count"><?= count($g['items']) ?></span></h2>
        <?php if ($svc): ?><a class="text-link" href="service.php?id=<?= (int)$svc['id'] ?>">Experience &amp; details →</a><?php endif; ?>
      </div>
      <?php if ($g['items']): ?>
      <div class="work-grid">
        <?php foreach ($g['items'] as $i => $p) render_work_card($p, $g['index'] + $i); ?>
      </div>
      <?php else: ?>
      <div class="empty">No projects in this service yet.</div>
      <?php endif; ?>
    </section>
    <?php endforeach; ?>

    <?php if (!$anyWork && !$filter): ?>
    <div class="empty">New projects are on the way. Check back soon.</div>
    <?php endif; ?>
  </div>

  <?php site_contact($profile); ?>
</main>
<?php site_footer($profile); ?>

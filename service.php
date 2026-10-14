<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/site_parts.php';

ensure_service_links($pdo);

$id = (int)($_GET['id'] ?? 0);
$services = get_services($pdo);

$service = null;
$toneIndex = 0;
foreach ($services as $i => $s) {
    if ((int)$s['id'] === $id) { $service = $s; $toneIndex = $i; break; }
}
if (!$service) {
    http_response_code(404);
    $profile = get_profile($pdo);
    site_head('Service not found | ' . tidy_case($profile['name']), '', 'page-service');
    site_header($profile, 'service');
    echo '<main id="main" class="container page-head"><h1>Service not found</h1><p class="lead">That service is no longer available.</p><p><a class="btn btn-ink" href="index.php#services" data-open="services">See all services</a></p></main>';
    site_footer($profile);
    exit;
}

$profile    = get_profile($pdo);
$name       = tidy_case((string)$profile['name']);
$tone       = service_tone($toneIndex);
$experience = get_experience($pdo, $id);
$projects   = get_projects($pdo, $id);
$others     = array_values(array_filter($services, fn($s) => (int)$s['id'] !== $id));
$icon       = trim((string)$service['icon']);

site_head(
    $service['title'] . ' | ' . $name,
    first_sentences((string)$service['description'], 155) ?: ('Experience and work in ' . $service['title']),
    'page-service'
);
site_header($profile, 'service');
?>
<main id="main">

  <section class="svc-hero tone-<?= e($tone) ?>" id="top">
    <div class="container">
      <a class="back-link" href="index.php#services" data-open="services"><?= icon('back', 18) ?> All services</a>
      <div class="svc-hero-row">
        <span class="svc-hero-icon" aria-hidden="true"><?= e($icon !== '' ? $icon : sprintf('%02d', $toneIndex + 1)) ?></span>
        <div>
          <p class="eyebrow">Service</p>
          <h1><?= e($service['title']) ?></h1>
        </div>
      </div>
      <?php if (trim((string)$service['description']) !== ''): ?>
      <p class="svc-hero-desc"><?= nl2br(e($service['description'])) ?></p>
      <?php endif; ?>
      <ul class="svc-hero-stats">
        <li><b><?= count($experience) ?></b> <?= $experience && count($experience) === 1 ? 'role' : 'roles' ?></li>
        <li><b><?= count($projects) ?></b> <?= count($projects) === 1 ? 'project' : 'projects' ?></li>
      </ul>
    </div>
  </section>

  <section class="container tabs-wrap" data-tabs>
    <div class="tab-list" role="tablist" aria-label="<?= e($service['title']) ?>">
      <button class="tab" type="button" role="tab" id="tab-experience" aria-controls="panel-experience" aria-selected="true">
        Experience <span class="tab-count"><?= count($experience) ?></span>
      </button>
      <button class="tab" type="button" role="tab" id="tab-work" aria-controls="panel-work" aria-selected="false" tabindex="-1">
        Work <span class="tab-count"><?= count($projects) ?></span>
      </button>
    </div>

    <!-- EXPERIENCE -->
    <div class="tab-panel" id="panel-experience" role="tabpanel" aria-labelledby="tab-experience">
      <div class="panel-title">
        <h2 class="sec-title">Experience in <?= e($service['title']) ?></h2>
      </div>
      <?php if ($experience): ?>
      <div class="exp-list">
        <?php foreach ($experience as $x) render_experience_entry($x); ?>
      </div>
      <?php else: ?>
      <div class="empty">Experience for this service is coming soon.</div>
      <?php endif; ?>
    </div>

    <!-- WORK -->
    <div class="tab-panel" id="panel-work" role="tabpanel" aria-labelledby="tab-work" hidden>
      <div class="panel-title">
        <h2 class="sec-title">Work in <?= e($service['title']) ?></h2>
      </div>
      <?php if ($projects): ?>
      <div class="work-grid">
        <?php foreach ($projects as $i => $p) render_work_card($p, $toneIndex + $i); ?>
      </div>
      <?php else: ?>
      <div class="empty">Projects for this service are on the way. <a href="index.php#contact">Get in touch</a> to see work that isn't online yet.</div>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($others): ?>
  <section class="container more-services">
    <h2 class="sec-title sm">Explore other services</h2>
    <div class="svc-chips">
      <?php foreach ($others as $o):
          $oi = array_search($o, $services, true); ?>
      <a class="svc-chip tone-<?= e(service_tone((int)$oi)) ?>" href="service.php?id=<?= (int)$o['id'] ?>"><?= e($o['title']) ?> <?= icon('arrow', 16) ?></a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>

  <?php site_contact($profile, 'Interested in ' . $service['title'] . '?', "Let's talk about what you're building and how I can help."); ?>
</main>
<?php site_footer($profile); ?>

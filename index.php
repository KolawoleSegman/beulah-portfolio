<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/site_parts.php';

ensure_service_links($pdo);

/* ---------------------------------------------------------
   DATA
--------------------------------------------------------- */
$profile        = get_profile($pdo);
$services       = get_services($pdo);
$projectCounts  = service_counts($pdo, 'projects');
$expCounts      = service_counts($pdo, 'experience');
$totalProjects  = (int)$pdo->query("SELECT COUNT(*) FROM projects WHERE status='published'")->fetchColumn();
$totalExp       = (int)$pdo->query("SELECT COUNT(*) FROM experience")->fetchColumn();
$otherExp       = get_experience($pdo, 0);               // experience not yet assigned to a service
$education      = $pdo->query("SELECT * FROM education ORDER BY sort_order, id")->fetchAll();
$certifications = $pdo->query("SELECT * FROM certifications ORDER BY sort_order, id")->fetchAll();
$leadership     = $pdo->query("SELECT * FROM leadership ORDER BY sort_order, id")->fetchAll();
$skills         = $pdo->query("SELECT * FROM skills ORDER BY sort_order, id")->fetchAll();

/* ---------------------------------------------------------
   PROFILE HELPERS
--------------------------------------------------------- */
$name     = tidy_case((string)($profile['name'] ?? ''));
$roles    = array_values(array_filter(array_map('trim', explode('|', tidy_case((string)($profile['title'] ?? ''))))));
$initials = name_initials((string)($profile['name'] ?? ''));
$photo    = trim((string)($profile['profile_photo'] ?? ''));
$summary  = trim(preg_replace('/[ \t]+/', ' ', (string)($profile['summary'] ?? '')) ?? '');
$heroLead = first_sentences($summary);
$aboutText = $summary;
if ($heroLead !== '' && substr($heroLead, -1) !== '…' && strncmp($summary, $heroLead, strlen($heroLead)) === 0 && mb_strlen($summary) > mb_strlen($heroLead) + 20) {
    $aboutText = ltrim(substr($summary, strlen($heroLead)));
}

$skillGroups = [];
foreach ($skills as $s) {
    $g = trim((string)($s['group_name'] ?? '')) ?: 'Skills';
    $skillGroups[$g] = array_merge($skillGroups[$g] ?? [], split_list((string)($s['skill_name'] ?? '')));
}

$tickerItems = $services ? array_column($services, 'title') : $roles;

site_head(
    $name !== '' ? $name . ' | ' . implode(', ', array_slice($roles, 0, 2)) : 'Portfolio',
    first_sentences($summary, 155),
    'page-home'
);
site_header($profile, 'home');
?>
<main id="main">

  <!-- ===================================================== HERO -->
  <section class="hero" id="top">
    <span class="shape shape-sun" aria-hidden="true"></span>
    <span class="shape shape-blush" aria-hidden="true"></span>
    <span class="shape shape-mint" aria-hidden="true"></span>

    <div class="container hero-grid">
      <div class="hero-copy">
        <?php if ($roles): ?>
        <ul class="role-chips" aria-label="Roles">
          <?php foreach ($roles as $role): ?><li><?= e($role) ?></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <h1>Helping brands <em>show up,</em> connect &amp; grow.</h1>

        <?php if ($heroLead !== ''): ?><p class="hero-lead"><?= e($heroLead) ?></p><?php endif; ?>

        <div class="hero-actions">
          <a class="btn btn-ink" href="#services" data-open="services">See my services <?= icon('arrow', 18) ?></a>
          <a class="btn btn-line" href="#about" data-open="about">About me</a>
        </div>

        <?php if (!empty($profile['location'])): ?>
        <p class="hero-where"><?= icon('pin', 17) ?> <?= e($profile['location']) ?></p>
        <?php endif; ?>
      </div>

      <?php if ($services): ?>
      <!-- quick links to the first services (desktop) -->
      <div class="hero-stack" aria-label="Services">
        <?php foreach (array_slice($services, 0, 3) as $i => $svc): ?>
        <a class="stack-card tone-<?= e(service_tone($i)) ?>" href="service.php?id=<?= (int)$svc['id'] ?>">
          <span class="stack-icon" aria-hidden="true"><?= e(trim((string)$svc['icon']) !== '' ? $svc['icon'] : sprintf('%02d', $i + 1)) ?></span>
          <span class="stack-title"><?= e($svc['title']) ?></span>
          <span class="stack-go" aria-hidden="true"><?= icon('arrow', 20) ?></span>
        </a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($tickerItems): ?>
    <div class="ticker" aria-hidden="true">
      <div class="ticker-track">
        <?php for ($r = 0; $r < 2; $r++): foreach ($tickerItems as $t): ?><span><?= e($t) ?></span><i>✦</i><?php endforeach; endfor; ?>
        <?php for ($r = 0; $r < 2; $r++): foreach ($tickerItems as $t): ?><span><?= e($t) ?></span><i>✦</i><?php endforeach; endfor; ?>
      </div>
    </div>
    <?php endif; ?>
  </section>

  <!-- ===================================================== FOLDS -->
  <div class="container folds">

    <!-- ---------------- ABOUT ---------------- -->
    <section class="fold fold-about" id="about" data-fold>
      <button class="fold-btn" type="button" aria-expanded="false" aria-controls="about-panel">
        <span class="fold-num">01</span>
        <span class="fold-label"><b>About me</b><small>Who I am &amp; what I care about</small></span>
        <span class="fold-plus" aria-hidden="true"><?= icon('plus', 22) ?></span>
      </button>

      <div class="fold-panel" id="about-panel" role="region" aria-label="About me">
        <div class="about-grid">

          <div class="about-photo-col">
            <?php if ($photo !== ''): ?>
            <button class="photo-frame" type="button" data-lightbox="<?= e($photo) ?>" data-alt="Portrait of <?= e($name) ?>" aria-label="View photo of <?= e($name) ?> full size">
              <img src="<?= e($photo) ?>" alt="Portrait of <?= e($name) ?>" loading="lazy">
              <span class="photo-hint"><?= icon('expand', 16) ?> Tap to view</span>
            </button>
            <?php else: ?>
            <div class="photo-frame photo-empty" aria-hidden="true"><span><?= e($initials) ?></span></div>
            <?php endif; ?>
          </div>

          <div class="about-body">
            <p class="eyebrow desk-only">About me</p>
            <h2 class="about-title">Creative thinking. <em>Organised execution.</em></h2>
            <?php if ($aboutText !== ''): ?><p class="about-text"><?= nl2br(e($aboutText)) ?></p><?php endif; ?>

            <ul class="proof">
              <li><b><?= count($services) ?: '–' ?></b><span>Services</span></li>
              <li><b><?= $totalProjects ?: '–' ?></b><span>Projects</span></li>
              <li><b><?= $totalExp ?: '–' ?></b><span>Roles</span></li>
            </ul>

            <ul class="facts">
              <?php if (!empty($profile['location'])): ?><li><?= icon('pin', 17) ?> <?= e($profile['location']) ?></li><?php endif; ?>
              <?php if (!empty($profile['email'])): ?><li><?= icon('mail', 17) ?> <a href="mailto:<?= e($profile['email']) ?>"><?= e($profile['email']) ?></a></li><?php endif; ?>
            </ul>
          </div>
        </div>

        <?php if ($education || $certifications || $leadership || $skillGroups || $otherExp): ?>
        <div class="more">
          <?php if ($education): ?>
          <details class="more-item" data-desk-open>
            <summary>Education</summary>
            <div class="more-body">
              <?php foreach ($education as $it): ?>
              <div class="cred"><strong><?= e($it['qualification'] ?? '') ?></strong><span><?= e($it['institution'] ?? '') ?></span><?php if (!empty($it['period'])): ?><span class="muted"><?= e($it['period']) ?></span><?php endif; ?></div>
              <?php endforeach; ?>
            </div>
          </details>
          <?php endif; ?>

          <?php if ($certifications): ?>
          <details class="more-item" data-desk-open>
            <summary>Certifications</summary>
            <div class="more-body">
              <?php foreach ($certifications as $it):
                  $line = implode(' · ', array_filter([trim((string)($it['issuer'] ?? '')), trim((string)($it['date_text'] ?? ''))])); ?>
              <div class="cred"><strong><?= e($it['title'] ?? '') ?></strong><?php if ($line !== ''): ?><span><?= e($line) ?></span><?php endif; ?></div>
              <?php endforeach; ?>
            </div>
          </details>
          <?php endif; ?>

          <?php if ($leadership): ?>
          <details class="more-item" data-desk-open>
            <summary>Leadership</summary>
            <div class="more-body">
              <?php foreach ($leadership as $it): ?>
              <div class="cred"><strong><?= e($it['role'] ?? '') ?></strong><span><?= e($it['organization'] ?? '') ?></span><?php if (!empty($it['period'])): ?><span class="muted"><?= e($it['period']) ?></span><?php endif; ?></div>
              <?php endforeach; ?>
            </div>
          </details>
          <?php endif; ?>

          <?php if ($skillGroups): ?>
          <details class="more-item" data-desk-open>
            <summary>Skills &amp; tools</summary>
            <div class="more-body">
              <?php foreach ($skillGroups as $group => $items): ?>
              <div class="skill-group"><h4><?= e($group) ?></h4>
                <ul class="tags"><?php foreach ($items as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
              </div>
              <?php endforeach; ?>
            </div>
          </details>
          <?php endif; ?>

          <?php if ($otherExp): ?>
          <details class="more-item" data-desk-open>
            <summary>More experience</summary>
            <div class="more-body">
              <?php foreach ($otherExp as $x): ?>
              <div class="cred"><strong><?= e($x['role'] ?? '') ?></strong><span><?= e($x['company'] ?? '') ?></span><?php if (!empty($x['period'])): ?><span class="muted"><?= e($x['period']) ?></span><?php endif; ?></div>
              <?php endforeach; ?>
            </div>
          </details>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </section>

    <!-- ---------------- SERVICES ---------------- -->
    <section class="fold fold-services" id="services" data-fold>
      <button class="fold-btn" type="button" aria-expanded="false" aria-controls="services-panel">
        <span class="fold-num">02</span>
        <span class="fold-label"><b>Services</b><small>Tap a service to see my work &amp; experience</small></span>
        <span class="fold-plus" aria-hidden="true"><?= icon('plus', 22) ?></span>
      </button>

      <div class="fold-panel" id="services-panel" role="region" aria-label="Services">
        <div class="panel-head desk-only">
          <div>
            <p class="eyebrow">What I do</p>
            <h2 class="sec-title">Services, each with its own story.</h2>
          </div>
          <p>Open a service to see the experience behind it and the work I've delivered.</p>
        </div>

        <?php if ($services): ?>
        <div class="svc-grid">
          <?php foreach ($services as $i => $svc):
              $sid = (int)$svc['id'];
              $pc = $projectCounts[$sid] ?? 0;
              $ec = $expCounts[$sid] ?? 0; ?>
          <a class="svc-card tone-<?= e(service_tone($i)) ?> reveal" href="service.php?id=<?= $sid ?>">
            <span class="svc-top">
              <span class="svc-icon" aria-hidden="true"><?= e(trim((string)$svc['icon']) !== '' ? $svc['icon'] : sprintf('%02d', $i + 1)) ?></span>
              <span class="svc-counts">
                <?php if ($pc): ?><span><?= e(plural($pc, 'project')) ?></span><?php endif; ?>
                <?php if ($ec): ?><span><?= e(plural($ec, 'role')) ?></span><?php endif; ?>
              </span>
            </span>
            <h3><?= e($svc['title']) ?></h3>
            <?php if (trim((string)$svc['description']) !== ''): ?><p><?= e(first_sentences((string)$svc['description'], 140)) ?></p><?php endif; ?>
            <span class="svc-btn">View work &amp; experience <?= icon('arrow', 18) ?></span>
          </a>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty">Services are being added. Check back soon.</div>
        <?php endif; ?>

        <p class="all-work"><a class="text-link" href="work.php">Browse all work →</a></p>
      </div>
    </section>
  </div>

  <?php site_contact($profile); ?>
</main>
<?php site_footer($profile); ?>

<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/site_parts.php';

ensure_service_links($pdo);

$slug = trim($_GET['slug'] ?? '');
// Admins can preview drafts; the public only sees published projects.
$stmt = $pdo->prepare("SELECT * FROM projects WHERE slug=?" . (is_admin() ? '' : " AND status='published'"));
$stmt->execute([$slug]);
$project = $stmt->fetch();
if (!$project) { http_response_code(404); exit('Project not found.'); }

$stmt = $pdo->prepare("SELECT * FROM project_media WHERE project_id=? ORDER BY sort_order,id");
$stmt->execute([$project['id']]);
$media = $stmt->fetchAll();

$profile    = get_profile($pdo);
$projectUrl = safe_url((string)$project['project_url']);
$meta       = array_filter(['Client' => $project['client'], 'Platform' => $project['platform'], 'Tools' => $project['tools']]);

// Which service does this project belong to? (for the back link)
$service = null; $toneIndex = 0;
if (!empty($project['service_id'])) {
    foreach (get_services($pdo) as $i => $s) {
        if ((int)$s['id'] === (int)$project['service_id']) { $service = $s; $toneIndex = $i; break; }
    }
}
$backUrl   = $service ? 'service.php?id=' . (int)$service['id'] . '#work' : 'work.php';
$backLabel = $service ? $service['title'] : 'All work';

site_head($project['title'] . ' | ' . tidy_case($profile['name']), first_sentences((string)$project['description'], 155), 'page-project');
site_header($profile, 'work');
?>
<main id="main">
  <article class="container project-page" id="top">
    <header class="project-head">
      <a class="back-link" href="<?= e($backUrl) ?>"><?= icon('back', 18) ?> <?= e($backLabel) ?></a>
      <?php if ($project['status'] !== 'published'): ?><div class="draft-banner">Draft preview. Only you can see this while logged in.</div><?php endif; ?>
      <div class="chip-row">
        <?php if ($service): ?><a class="chip tone-<?= e(service_tone($toneIndex)) ?>" href="service.php?id=<?= (int)$service['id'] ?>"><?= e($service['title']) ?></a><?php endif; ?>
        <?php if ($project['category'] && (!$service || mb_strtolower(trim($project['category'])) !== mb_strtolower(trim($service['title'])))): ?><span class="chip"><?= e($project['category']) ?></span><?php endif; ?>
      </div>
      <h1><?= e($project['title']) ?></h1>
      <?php if ($meta): ?>
      <dl class="project-meta"><?php foreach ($meta as $label => $value): ?><div><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd></div><?php endforeach; ?></dl>
      <?php endif; ?>
    </header>

    <?php if ($project['cover_image']): ?><img class="project-cover" src="<?= e($project['cover_image']) ?>" alt="<?= e($project['title']) ?>"><?php endif; ?>

    <?php if (trim((string)$project['description']) !== ''): ?><div class="project-copy"><?= linkify($project['description']) ?></div><?php endif; ?>

    <?php if ($media): ?>
    <div class="media-gallery">
      <?php foreach ($media as $m):
          $vid = $m['media_type'] === 'embed' ? parse_video_link($m['file_path']) : null;
          if ($m['media_type'] === 'embed' && !$vid) continue; ?>
      <figure<?= $vid ? ' class="embed"' : '' ?>>
        <?php if ($vid): ?><div class="embed-frame"><iframe src="<?= e(video_embed_src($vid)) ?>" title="<?= e($m['caption'] ?: $project['title']) ?>" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
        <?php elseif ($m['media_type'] === 'video'): ?><video controls playsinline preload="metadata"><source src="<?= e($m['file_path']) ?>" type="<?= e(video_mime($m['file_path'])) ?>"></video>
        <?php else: ?><img src="<?= e($m['file_path']) ?>" alt="<?= e($m['caption'] ?: $project['title']) ?>" loading="lazy"><?php endif; ?>
        <?php if ($m['caption']): ?><figcaption><?= e($m['caption']) ?></figcaption><?php endif; ?>
      </figure>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($projectUrl): ?><p class="project-cta"><a class="btn btn-ink" href="<?= e($projectUrl) ?>" target="_blank" rel="noopener">Visit project <?= icon('arrow', 18) ?></a></p><?php endif; ?>
  </article>

  <?php site_contact($profile); ?>
</main>
<?php site_footer($profile); ?>

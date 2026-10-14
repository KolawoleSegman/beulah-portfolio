<?php
declare(strict_types=1);

/**
 * Shared public-site components.
 * All styling lives in assets/css/style.css, all behaviour in assets/js/site.js.
 */

const SERVICE_TONES = ['blush', 'blue', 'mint', 'sun', 'lilac'];

/** Colour name for the Nth service (cycles). */
function service_tone(int $index): string {
    return SERVICE_TONES[$index % count(SERVICE_TONES)];
}

/** Add ?v=filetime so visitors always get the newest CSS/JS after an upload. */
function asset_url(string $path): string {
    $file = __DIR__ . '/' . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

/* =========================================================
   DATA HELPERS
========================================================= */

/** Published projects. $serviceId: int = that service, 0 = not assigned, null = all. */
function get_projects(PDO $pdo, ?int $serviceId = null, ?int $limit = null): array {
    $sql = "SELECT * FROM projects WHERE status='published'";
    $params = [];
    if ($serviceId !== null && ensure_service_links($pdo)) {
        if ($serviceId > 0) { $sql .= " AND service_id = ?"; $params[] = $serviceId; }
        else { $sql .= " AND service_id IS NULL"; }
    } elseif ($serviceId !== null) {
        return $serviceId === 0 ? get_projects($pdo, null, $limit) : [];
    }
    $sql .= " ORDER BY featured DESC, created_at DESC";
    if ($limit) $sql .= " LIMIT " . (int)$limit;
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** Experience entries. Same $serviceId rules as get_projects(). */
function get_experience(PDO $pdo, ?int $serviceId = null): array {
    $sql = "SELECT * FROM experience";
    $params = [];
    if ($serviceId !== null && ensure_service_links($pdo)) {
        if ($serviceId > 0) { $sql .= " WHERE service_id = ?"; $params[] = $serviceId; }
        else { $sql .= " WHERE service_id IS NULL"; }
    } elseif ($serviceId !== null) {
        return $serviceId === 0 ? get_experience($pdo) : [];
    }
    $sql .= " ORDER BY sort_order, id";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

/** [service_id => count] for projects or experience. */
function service_counts(PDO $pdo, string $table): array {
    if (!ensure_service_links($pdo)) return [];
    $where = $table === 'projects' ? "WHERE status='published' AND service_id IS NOT NULL" : "WHERE service_id IS NOT NULL";
    $table = $table === 'projects' ? 'projects' : 'experience';
    $rows = $pdo->query("SELECT service_id, COUNT(*) c FROM {$table} {$where} GROUP BY service_id")->fetchAll();
    $out = [];
    foreach ($rows as $r) $out[(int)$r['service_id']] = (int)$r['c'];
    return $out;
}

function plural(int $n, string $one, string $many = ''): string {
    return $n . ' ' . ($n === 1 ? $one : ($many !== '' ? $many : $one . 's'));
}

/* =========================================================
   ICONS (inline SVG, no external requests)
========================================================= */

function icon(string $name, int $size = 20): string {
    $paths = [
        'arrow'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'back'   => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'close'  => '<path d="M6 6l12 12M18 6 6 18"/>',
        'pin'    => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'mail'   => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m4 8 8 6 8-6"/>',
        'phone'  => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
        'link'   => '<path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
        'expand' => '<path d="M4 9V4h5M20 9V4h-5M4 15v5h5M20 15v5h-5"/>',
        'play'   => '<path d="M8 5v14l11-7z"/>',
    ];
    $p = $paths[$name] ?? '';
    return '<svg class="ico" viewBox="0 0 24 24" width="' . $size . '" height="' . $size . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
}

/* =========================================================
   HEAD
========================================================= */

function site_head(string $title, string $description = '', string $bodyClass = ''): void {
    $title = trim($title) !== '' ? trim($title) : 'Portfolio';
    $description = trim($description);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#f3f5ff">
<title><?= e($title) ?></title>
<?php if ($description !== ''): ?><meta name="description" content="<?= e($description) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<?php endif; ?>
<script>document.documentElement.className += ' js';</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400..800&family=Source+Serif+4:ital,opsz,wght@1,8..60,500..600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<?php
}

/* =========================================================
   HEADER
   $page: 'home' | 'work' | 'service'
========================================================= */

function site_header(array $profile, string $page = 'home'): void {
    $name = tidy_case((string)($profile['name'] ?? ''));
    $initials = name_initials((string)($profile['name'] ?? ''));
    $isHome = $page === 'home';
    $p = $isHome ? '' : 'index.php';
    ?>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header" id="site-header">
  <div class="container nav">
    <a class="brand" href="index.php" aria-label="<?= e($name) ?> – home">
      <span class="brand-mark" aria-hidden="true"><?= e($initials) ?></span>
      <span class="brand-name"><?= e($name) ?></span>
    </a>

    <nav id="primary-nav" aria-label="Primary">
      <a href="<?= $p ?>#about" data-open="about">About</a>
      <a href="<?= $p ?>#services" data-open="services"<?= $page === 'service' ? ' aria-current="true"' : '' ?>>Services</a>
      <a href="work.php"<?= $page === 'work' ? ' aria-current="page"' : '' ?>>Work</a>
      <a href="<?= $p ?>#contact">Contact</a>
    </nav>

    <a class="nav-cta" href="<?= $p ?>#contact">Hire me</a>

    <button class="menu-toggle" type="button" aria-label="Open menu" aria-expanded="false" aria-controls="primary-nav">
      <span class="bars" aria-hidden="true"></span>
    </button>
  </div>
</header>
<?php
}

/* =========================================================
   CONTACT BLOCK (shared by home + service pages)
========================================================= */

function site_contact(array $profile, string $heading = 'Have a project or opportunity?', string $text = "Let's talk about how strategic content, digital support and organised execution can move it forward."): void {
    $phone = preg_replace('/[^0-9+]/', '', (string)($profile['phone'] ?? '')) ?? '';
    $linkedin = safe_url((string)($profile['linkedin'] ?? ''));
    $email = trim((string)($profile['email'] ?? ''));
    ?>
<section id="contact" class="container contact-wrap">
  <div class="contact-panel reveal">
    <div class="contact-copy">
      <p class="eyebrow">Start a conversation</p>
      <h2><?= e($heading) ?></h2>
      <p><?= e($text) ?></p>
    </div>
    <div class="contact-links">
      <?php if ($email !== ''): ?><a class="btn btn-sun" href="mailto:<?= e($email) ?>"><?= icon('mail', 18) ?> Email me</a><?php endif; ?>
      <?php if ($phone !== ''): ?><a class="btn btn-glass" href="tel:<?= e($phone) ?>"><?= icon('phone', 18) ?> Call me</a><?php endif; ?>
      <?php if ($linkedin): ?><a class="btn btn-glass" href="<?= e($linkedin) ?>" target="_blank" rel="noopener noreferrer"><?= icon('link', 18) ?> LinkedIn</a><?php endif; ?>
    </div>
  </div>
</section>
<?php
}

/* =========================================================
   FOOTER
========================================================= */

function site_footer(array $profile): void {
    $name = tidy_case((string)($profile['name'] ?? ''));
    ?>
<footer class="site-footer">
  <div class="container">
    <span>© <?= date('Y') ?> <?= e($name) ?></span>
    <a href="#top">Back to top ↑</a>
  </div>
</footer>

<dialog id="lightbox" class="lightbox" aria-label="Photo">
  <button class="lightbox-close" type="button" aria-label="Close photo"><?= icon('close', 22) ?></button>
  <img alt="" src="">
</dialog>

<script src="<?= e(asset_url('assets/js/site.js')) ?>"></script>
</body>
</html>
<?php
}

/* =========================================================
   WORK CARD
========================================================= */

function render_work_card(array $p, int $i = 0, string $serviceLabel = ''): void {
    $title = trim((string)($p['title'] ?? 'Untitled project'));
    $slug = trim((string)($p['slug'] ?? ''));
    $category = trim((string)($p['category'] ?? ''));
    $client = trim((string)($p['client'] ?? ''));
    $platform = trim((string)($p['platform'] ?? ''));
    $cover = trim((string)($p['cover_image'] ?? ''));
    $letter = mb_strtoupper(mb_substr(trim($title), 0, 1));
    $url = $slug !== '' ? 'project.php?slug=' . urlencode($slug) : 'work.php';
    $sub = implode(' · ', array_filter([$client, $platform]));
    ?>
<a class="work-card reveal" href="<?= e($url) ?>" aria-label="View project: <?= e($title) ?>">
  <div class="work-thumb tone-<?= e(service_tone($i)) ?>">
    <?php if ($cover !== ''): ?>
      <img src="<?= e($cover) ?>" alt="" loading="lazy" decoding="async">
    <?php else: ?>
      <span class="thumb-letter" aria-hidden="true"><?= e($letter) ?></span>
    <?php endif; ?>
    <?php if (!empty($p['featured'])): ?><span class="badge-star">★ Featured</span><?php endif; ?>
    <span class="work-view" aria-hidden="true"><?= icon('arrow', 18) ?></span>
  </div>
  <div class="work-meta">
    <?php if ($category !== '' || $serviceLabel !== ''): ?><p class="chip"><?= e($serviceLabel !== '' ? $serviceLabel : $category) ?></p><?php endif; ?>
    <h3><?= e($title) ?></h3>
    <?php if ($sub !== ''): ?><p class="work-sub"><?= e($sub) ?></p><?php endif; ?>
  </div>
</a>
<?php
}

/* =========================================================
   EXPERIENCE ENTRY
========================================================= */

function render_experience_entry(array $x): void {
    $period = trim((string)($x['period'] ?? ''));
    $current = stripos($period, 'present') !== false;
    $desc = trim((string)($x['description'] ?? ''));
    ?>
<article class="exp-card reveal">
  <div class="exp-period"><?php if ($current): ?><span class="live-dot" title="Current role"></span><?php endif; ?><?= e($period) ?></div>
  <div class="exp-body">
    <h3><?= e($x['role'] ?? '') ?></h3>
    <p class="exp-org"><?= e($x['company'] ?? '') ?></p>
    <?php if ($desc !== ''): ?><p class="exp-desc"><?= nl2br(e($desc)) ?></p><?php endif; ?>
  </div>
</article>
<?php
}

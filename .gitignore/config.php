<?php
declare(strict_types=1);

// Requires PHP 7.4+ (tested on 8.x).

date_default_timezone_set('Africa/Lagos');

// ---- Database settings -------------------------------------------------
// Edit here, or create config.local.php (not overwritten on updates) that
// sets $dbHost, $dbName, $dbUser, $dbPass.
$dbHost = 'localhost';
$dbName = 'beulah_portfolio';
$dbUser = 'root';
$dbPass = '';
if (is_file(__DIR__ . '/config.local.php')) {
    require __DIR__ . '/config.local.php';
}

// ---- Session hardening -------------------------------------------------
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $ex) {
    http_response_code(500);
    exit('Database connection failed. Check config.php.');
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: {$url}");
    exit;
}

function is_admin(): bool {
    return !empty($_SESSION['admin_id']);
}

function require_admin(): void {
    if (!is_admin()) redirect('login.php');
    // Force the temporary seeded password to be replaced before anything else.
    $script = basename($_SERVER['SCRIPT_NAME'] ?? '');
    if (!empty($_SESSION['must_change']) && !in_array($script, ['password.php', 'logout.php'], true)) {
        redirect('password.php');
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(419);
        exit('Invalid security token. Please go back, refresh the page and try again.');
    }
}

/** Require a valid POST + CSRF token (used for delete actions). */
function require_post_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        exit('Method not allowed.');
    }
    verify_csrf();
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

/** Only allow http(s) URLs (blocks javascript: and similar). Returns '' if invalid. */
function safe_url(?string $url): string {
    $url = trim((string)$url);
    if ($url === '') return '';
    if (!preg_match('~^https?://~i', $url)) return '';
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
}

function slugify(string $text): string {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    return $text !== '' ? $text : 'project';
}

function unique_slug(PDO $pdo, string $title, ?int $ignoreId = null): string {
    $base = slugify($title);
    $slug = $base;
    $n = 2;
    while (true) {
        $sql = "SELECT id FROM projects WHERE slug = ?";
        $params = [$slug];
        if ($ignoreId) {
            $sql .= " AND id != ?";
            $params[] = $ignoreId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) return $slug;
        $slug = $base . '-' . $n++;
    }
}

/**
 * Validate and store an uploaded file. Returns the relative path, or null if no file was sent.
 * Checks extension AND real content type (finfo / getimagesize), not just the file name.
 */
function upload_file(array $file, string $folder, array $allowedExt, int $maxBytes): ?string {
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) return null;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('File is larger than the server upload limit (upload_max_filesize / post_max_size).');
    }
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('Upload failed (code ' . (int)$err . ').');
    if (($file['size'] ?? 0) > $maxBytes) throw new RuntimeException('File is too large.');
    if (!is_uploaded_file($file['tmp_name'])) throw new RuntimeException('Invalid upload.');

    $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) throw new RuntimeException('File type not allowed.');

    // Verify actual content matches the extension family.
    $imageExt = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($ext, $imageExt, true)) {
        $info = @getimagesize($file['tmp_name']);
        $okMimes = ['image/jpeg', 'image/png', 'image/webp'];
        if ($info === false || !in_array($info['mime'], $okMimes, true)) {
            throw new RuntimeException('File is not a valid image.');
        }
    } else {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string)$finfo->file($file['tmp_name']);
        $okVideo = ['video/mp4', 'video/webm', 'video/quicktime', 'video/x-m4v', 'application/octet-stream'];
        if (!in_array($mime, $okVideo, true)) throw new RuntimeException('File is not a valid video.');
    }

    $safe = bin2hex(random_bytes(10)) . '.' . $ext;
    $dir = __DIR__ . '/' . trim($folder, '/');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Upload folder is missing and could not be created.');
    }

    $destination = $dir . '/' . $safe;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Could not save uploaded file. Check folder permissions.');
    }
    return trim($folder, '/') . '/' . $safe;
}

/** Delete a file, but only inside the uploads/ folder. */
function delete_upload(?string $path): void {
    if (!$path) return;
    $base = realpath(__DIR__ . '/uploads');
    $full = realpath(__DIR__ . '/' . ltrim($path, '/'));
    if ($base === false || $full === false) return;
    if (strncmp($full, $base . DIRECTORY_SEPARATOR, strlen($base) + 1) !== 0) return;
    if (is_file($full)) @unlink($full);
}

function video_mime(string $path): string {
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return ['mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime'][$ext] ?? 'video/mp4';
}

function get_profile(PDO $pdo): array {
    $p = $pdo->query("SELECT * FROM profile WHERE id=1")->fetch();
    return $p ?: [
        'name' => 'Your Name', 'title' => '', 'location' => '', 'phone' => '',
        'email' => '', 'linkedin' => '', 'summary' => '', 'profile_photo' => '',
    ];
}

/**
 * Recognise a YouTube or Vimeo link. Returns ['youtube', id] or ['vimeo', id, hash], or null.
 */
function parse_video_link(string $url): ?array {
    $url = trim($url);
    if (!preg_match('~^https?://~i', $url)) return null;
    $p = parse_url($url);
    if (!$p || empty($p['host'])) return null;
    $host = strtolower(preg_replace('/^www\./i', '', $p['host']) ?? '');
    $path = $p['path'] ?? '';
    parse_str($p['query'] ?? '', $q);

    if (in_array($host, ['youtube.com', 'm.youtube.com', 'music.youtube.com', 'youtube-nocookie.com'], true)) {
        $id = (string)($q['v'] ?? '');
        if ($id === '' && preg_match('~^/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})~', $path, $m)) $id = $m[1];
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $id)) return ['youtube', $id];
    } elseif ($host === 'youtu.be') {
        if (preg_match('~^/([A-Za-z0-9_-]{11})~', $path, $m)) return ['youtube', $m[1]];
    } elseif ($host === 'vimeo.com' || $host === 'player.vimeo.com') {
        if (preg_match('~/(?:video/)?(\d+)(?:/([a-f0-9]+))?~i', $path, $m)) {
            return ['vimeo', $m[1], $m[2] ?? (string)($q['h'] ?? '')];
        }
    }
    return null;
}

/** Normalised watch URL we store in the database. */
function video_watch_url(array $v): string {
    if ($v[0] === 'youtube') return 'https://www.youtube.com/watch?v=' . $v[1];
    return 'https://vimeo.com/' . $v[1] . (!empty($v[2]) ? '/' . $v[2] : '');
}

/** URL used inside the <iframe>. */
function video_embed_src(array $v): string {
    if ($v[0] === 'youtube') return 'https://www.youtube-nocookie.com/embed/' . $v[1];
    return 'https://player.vimeo.com/video/' . $v[1] . (!empty($v[2]) ? '?h=' . $v[2] : '');
}

/** Escape text, turn http(s) links into clickable links, keep line breaks. */
function linkify(?string $text): string {
    $safe = e($text);
    $safe = preg_replace_callback(
        '~https?://(?:(?!&quot;|&#039;|&lt;|&gt;)[^\s<])+~i',
        function ($m) {
            $url = $m[0];
            $trail = '';
            while ($url !== '' && strpos('.,;:!?)', substr($url, -1)) !== false) {
                $trail = substr($url, -1) . $trail;
                $url = substr($url, 0, -1);
            }
            return '<a href="' . $url . '" target="_blank" rel="noopener noreferrer">' . $url . '</a>' . $trail;
        },
        $safe
    ) ?? $safe;
    return nl2br($safe);
}

/** Older databases only allow image/video media. Add 'embed' automatically if needed. */
function ensure_embed_support(PDO $pdo): void {
    try {
        $type = $pdo->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_media' AND COLUMN_NAME = 'media_type'")->fetchColumn();
        if ($type && stripos((string)$type, "'embed'") === false) {
            $pdo->exec("ALTER TABLE project_media MODIFY media_type ENUM('image','video','embed') NOT NULL");
        }
    } catch (Throwable $ex) {
        // If this fails (no ALTER permission), run migration_embed_links.sql manually.
    }
}

/** "BALOGUN BEULAH" -> "Balogun Beulah" (only when the whole text is upper-case). */
function tidy_case(?string $s): string {
    $s = trim((string)$s);
    if ($s !== '' && $s === mb_strtoupper($s)) return mb_convert_case(mb_strtolower($s), MB_CASE_TITLE);
    return $s;
}

/** First + last initial ("Balogun Beulah Toluwalase" -> "BT"). */
function name_initials(?string $name): string {
    $words = preg_split('/\s+/', trim((string)$name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$words) return '•';
    $first = mb_strtoupper(mb_substr($words[0], 0, 1));
    $last = count($words) > 1 ? mb_strtoupper(mb_substr($words[count($words) - 1], 0, 1)) : '';
    return $first . $last;
}

/** "A, B, C" -> ['A','B','C'] */
function split_list(?string $s): array {
    return array_values(array_filter(array_map('trim', explode(',', (string)$s)), fn($x) => $x !== ''));
}

/** First sentence(s) of a text, up to roughly $max characters. */
function first_sentences(?string $text, int $max = 230): string {
    $text = trim(preg_replace('/\s+/', ' ', (string)$text) ?? '');
    if ($text === '') return '';
    $parts = preg_split('/(?<=[.!?])\s+/', $text) ?: [$text];
    $out = '';
    foreach ($parts as $p) {
        if ($out !== '' && mb_strlen($out . ' ' . $p) > $max) break;
        $out = $out === '' ? $p : $out . ' ' . $p;
    }
    return mb_strlen($out) > $max + 40 ? rtrim(mb_strimwidth($out, 0, $max, '', 'UTF-8')) . '…' : $out;
}

/* =========================================================
   SERVICE LINKS
   Each project and each experience entry can belong to one
   service. Adds the `service_id` column automatically the
   first time any page loads (or run migration_service_links.sql
   in phpMyAdmin if your host blocks ALTER TABLE).
========================================================= */

/** Make sure projects + experience have a service_id column. Returns true when both do. */
function ensure_service_links(PDO $pdo): bool {
    static $ok = null;
    if ($ok !== null) return $ok;
    $ok = true;
    foreach (['projects', 'experience'] as $table) {
        try {
            $has = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'service_id'")->fetch();
            if (!$has) {
                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN service_id INT NULL DEFAULT NULL");
                if ($table === 'projects') {
                    // One-time convenience: a project whose category matches a service title joins that service.
                    $pdo->exec("UPDATE projects p JOIN services s ON LOWER(TRIM(s.title)) = LOWER(TRIM(p.category)) SET p.service_id = s.id WHERE p.service_id IS NULL");
                }
            }
        } catch (Throwable $ex) {
            $ok = false; // no ALTER permission: run migration_service_links.sql manually
        }
    }
    return $ok;
}

/** Published services, in display order. */
function get_services(PDO $pdo): array {
    return $pdo->query("SELECT * FROM services WHERE status = 'published' ORDER BY sort_order, id")->fetchAll();
}

/** Turn a posted service id into int|null (blank or unknown -> null). */
function clean_service_id(PDO $pdo, $value): ?int {
    $id = (int)$value;
    if ($id <= 0) return null;
    $st = $pdo->prepare("SELECT id FROM services WHERE id = ?");
    $st->execute([$id]);
    return $st->fetch() ? $id : null;
}

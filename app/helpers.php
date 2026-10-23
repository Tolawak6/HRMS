<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

/* ---------------------------------------------------------------------------
 * Output, URLs and navigation
 * ------------------------------------------------------------------------ */

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $path = ltrim($path, '/');
    return APP_BASE_PATH . ($path === '' ? '/' : '/' . $path);
}

function asset(string $path): string
{
    $relative = ltrim($path, '/');
    $full = BASE_PATH . '/' . $relative;
    $version = is_file($full) ? (string) filemtime($full) : '1';
    return url($relative) . '?v=' . rawurlencode($version);
}

function redirect(string $path = 'dashboard.php'): void
{
    if (!headers_sent()) {
        header('Location: ' . url($path), true, 302);
    }
    exit;
}

function current_route(): string
{
    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if (APP_BASE_PATH !== '' && str_starts_with($script, APP_BASE_PATH)) {
        $script = substr($script, strlen(APP_BASE_PATH));
    }
    return ltrim($script, '/');
}

function query_url(string $path, array $overrides = []): string
{
    $query = $_GET;
    unset($query['page']);
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }
    $suffix = $query ? '?' . http_build_query($query) : '';
    return url($path) . $suffix;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/* ---------------------------------------------------------------------------
 * Request input
 * ------------------------------------------------------------------------ */

function post_str(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function post_raw(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_string($value) ? $value : $default;
}

function post_int(string $key, int $default = 0): int
{
    $value = $_POST[$key] ?? null;
    return is_scalar($value) ? (int) $value : $default;
}

function get_str(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function get_int(string $key, int $default = 0): int
{
    $value = $_GET[$key] ?? null;
    return is_scalar($value) ? (int) $value : $default;
}

function old(string $key, mixed $default = ''): string
{
    $value = $_POST[$key] ?? $default;
    return is_scalar($value) ? (string) $value : '';
}

function selected(mixed $value, mixed $expected): string
{
    return (string) $value === (string) $expected ? ' selected' : '';
}

function checked(bool $condition): string
{
    return $condition ? ' checked' : '';
}

/* ---------------------------------------------------------------------------
 * Flash messages
 * ------------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    if ($type === 'error') {
        $type = 'danger';
    }
    $allowed = ['success', 'danger', 'warning', 'info'];
    if (!in_array($type, $allowed, true)) {
        $type = 'info';
    }
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $items = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($items) ? $items : [];
}

/* ---------------------------------------------------------------------------
 * CSRF
 * ------------------------------------------------------------------------ */

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function rotate_csrf(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/* ---------------------------------------------------------------------------
 * Errors
 * ------------------------------------------------------------------------ */

function abort(int $status, string $title = 'Something went wrong', string $message = ''): void
{
    http_response_code($status);
    $home = isset($_SESSION['uid']) ? url('dashboard.php') : url('login.php');
    $homeLabel = isset($_SESSION['uid']) ? 'Back to dashboard' : 'Go to sign in';
    $logo = url('images/logo.png');
    $titleSafe = e($title);
    $messageSafe = e($message !== '' ? $message : 'The request could not be completed.');
    $homeSafe = e($home);
    $homeLabelSafe = e($homeLabel);
    echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$titleSafe} · ESS HRMS</title>
<style>
:root{color-scheme:light}
*{box-sizing:border-box}
body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f3f6fa;color:#1f2a37;
font-family:"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif;padding:24px}
.card{width:100%;max-width:480px;background:#fff;border:1px solid #dfe6ee;border-radius:12px;
padding:40px;text-align:center;box-shadow:0 10px 30px rgba(16,42,67,.08)}
img{width:64px;height:auto;margin-bottom:20px}
h1{font-size:22px;margin:0 0 10px;color:#102a43}
p{color:#52606d;line-height:1.6;margin:0 0 24px}
a{display:inline-block;background:#1d4f91;color:#fff;text-decoration:none;padding:11px 20px;
border-radius:8px;font-weight:600}
code{background:#eef2f7;padding:2px 6px;border-radius:4px;color:#334e68}
</style>
</head>
<body>
<div class="card">
<img src="{$logo}" alt="Ethiopian Statistical Service">
<h1>{$titleSafe}</h1>
<p>{$messageSafe}</p>
<p><code>HTTP {$status}</code></p>
<a href="{$homeSafe}">{$homeLabelSafe}</a>
</div>
</body>
</html>
HTML;
    exit;
}

/* ---------------------------------------------------------------------------
 * Formatting
 * ------------------------------------------------------------------------ */

function format_date(?string $date, string $fallback = '—'): string
{
    if ($date === null || $date === '' || $date === '0000-00-00') {
        return $fallback;
    }
    try {
        return (new DateTimeImmutable($date))->format('d M Y');
    } catch (Exception) {
        return $fallback;
    }
}

function format_datetime(?string $date, string $fallback = '—'): string
{
    if ($date === null || $date === '' || $date === '0000-00-00 00:00:00') {
        return $fallback;
    }
    try {
        return (new DateTimeImmutable($date))->format('d M Y, H:i');
    } catch (Exception) {
        return $fallback;
    }
}

function time_ago(?string $date): string
{
    if ($date === null || $date === '') {
        return '—';
    }
    try {
        $then = new DateTimeImmutable($date);
    } catch (Exception) {
        return '—';
    }
    $diff = time() - $then->getTimestamp();
    if ($diff < 60) {
        return 'Just now';
    }
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return $mins . ($mins === 1 ? ' minute ago' : ' minutes ago');
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . ($hours === 1 ? ' hour ago' : ' hours ago');
    }
    if ($diff < 2592000) {
        $days = (int) floor($diff / 86400);
        return $days . ($days === 1 ? ' day ago' : ' days ago');
    }
    return $then->format('d M Y');
}

function initials(?string $first, ?string $last = null): string
{
    $a = mb_substr(trim((string) $first), 0, 1);
    $b = mb_substr(trim((string) $last), 0, 1);
    $result = mb_strtoupper($a . $b, 'UTF-8');
    return $result !== '' ? $result : '?';
}

function person_name(array $user): string
{
    $name = trim(implode(' ', array_filter([
        (string) ($user['first_name'] ?? ''),
        (string) ($user['middle_name'] ?? ''),
        (string) ($user['last_name'] ?? ''),
    ], static fn (string $part): bool => $part !== '')));
    return $name !== '' ? $name : (string) ($user['username'] ?? 'Employee');
}

function role_label(?string $role): string
{
    return match ($role) {
        'admin'     => 'Administrator',
        'hr_staff'  => 'HR Staff',
        'ess_staff' => 'Employee',
        default     => 'User',
    };
}

function status_badge_class(string $status): string
{
    return match (strtolower($status)) {
        'active', 'approved', 'paid' => 'badge-soft-success',
        'pending', 'on leave', 'warning' => 'badge-soft-warning',
        'rejected', 'suspended', 'inactive' => 'badge-soft-danger',
        'retired', 'resigned', 'cancelled', 'archived' => 'badge-soft-neutral',
        default => 'badge-soft-info',
    };
}

function format_days(float|int|string $days): string
{
    $value = (float) $days;
    return rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
}

function human_file_size(?int $bytes): string
{
    if ($bytes === null || $bytes <= 0) {
        return '—';
    }
    $units = ['B', 'KB', 'MB', 'GB'];
    $power = (int) min(count($units) - 1, floor(log($bytes, 1024)));
    return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1) . ' ' . $units[$power];
}

/* ---------------------------------------------------------------------------
 * SQL helpers
 * ------------------------------------------------------------------------ */

/**
 * Escape user input for a LIKE pattern. Pair with: LIKE ? ESCAPE '!'
 */
function like_term(string $term): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
}

function csv_safe(string $value): string
{
    // Prevent CSV formula injection in spreadsheet applications.
    if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
        return "'" . $value;
    }
    return $value;
}

/* ---------------------------------------------------------------------------
 * Pagination
 * ------------------------------------------------------------------------ */

function per_page_value(int $fallback = 10): int
{
    $value = get_int('per_page', $fallback);
    return in_array($value, [10, 25, 50], true) ? $value : $fallback;
}

function paginate(int $total, int $perPage, int $page): array
{
    $total = max(0, $total);
    $perPage = max(1, $perPage);
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    return [
        'total'    => $total,
        'per_page' => $perPage,
        'page'     => $page,
        'pages'    => $pages,
        'offset'   => ($page - 1) * $perPage,
        'from'     => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
        'to'       => min($page * $perPage, $total),
    ];
}

/* ---------------------------------------------------------------------------
 * Dates
 * ------------------------------------------------------------------------ */

function working_days_between(string $start, string $end): int
{
    try {
        $from = new DateTimeImmutable($start);
        $to = new DateTimeImmutable($end);
    } catch (Exception) {
        return 0;
    }
    if ($to < $from) {
        return 0;
    }
    $days = 0;
    $guard = 0;
    for ($day = $from; $day <= $to && $guard < 400; $day = $day->modify('+1 day'), $guard++) {
        if ((int) $day->format('N') <= 5) {
            $days++;
        }
    }
    return $days;
}

function today(): string
{
    return date('Y-m-d');
}

function current_year(): int
{
    return (int) date('Y');
}

/* ---------------------------------------------------------------------------
 * Icons
 * ------------------------------------------------------------------------ */

function icon(string $name, string $class = ''): string
{
    if (!preg_match('/^[a-z0-9-]+$/', $name)) {
        return '';
    }
    $href = asset('assets/vendor/bootstrap-icons/bootstrap-icons.svg') . '#' . $name;
    return '<svg class="bi ' . e($class) . '" width="1em" height="1em" fill="currentColor" '
        . 'aria-hidden="true" focusable="false"><use href="' . e($href) . '"></use></svg>';
}

function alert_icon(string $type): string
{
    return match ($type) {
        'success' => 'check-circle',
        'danger'  => 'exclamation-triangle',
        'warning' => 'exclamation-triangle',
        default   => 'info-circle',
    };
}

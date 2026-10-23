<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$actor = require_auth();
$id = get_int('id');
$owner = user_find($id);
if ($owner === null) {
    abort(404, 'Photo not found', 'No employee record matches this request.');
}
if (!can_access_employee($owner)) {
    abort(403, 'Access denied', 'You do not have permission to view this photo.');
}

$candidates = [];
$photo = basename((string) ($owner['photo_path'] ?? ''));
if ($photo !== '' && preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|webp)$/i', $photo)) {
    $candidates[] = rtrim((string) config('storage.photos'), '/') . '/' . $photo;
}
$legacy = basename(str_replace('\\', '/', (string) ($owner['image'] ?? '')));
if ($legacy !== '' && preg_match('/^[A-Za-z0-9._-]+\.(jpe?g|png|webp)$/i', $legacy)) {
    $candidates[] = BASE_PATH . '/uploads/images/' . $legacy;
}

$path = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate) && is_readable($candidate)) {
        $path = $candidate;
        break;
    }
}
if ($path === null) {
    header('Content-Type: image/svg+xml');
    header('Cache-Control: private, max-age=300');
    readfile(BASE_PATH . '/assets/img/avatar.svg');
    exit;
}

$mime = 'image/jpeg';
if (class_exists('finfo')) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected = (string) $finfo->file($path);
    if (in_array($detected, ['image/jpeg', 'image/png', 'image/webp'], true)) {
        $mime = $detected;
    }
}
$mtime = (int) filemtime($path);
$etag = '"' . md5($path . '|' . $mtime) . '"';

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
    http_response_code(304);
    exit;
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Cache-Control: private, max-age=300');
header('ETag: ' . $etag);
header('Content-Disposition: inline');
readfile($path);

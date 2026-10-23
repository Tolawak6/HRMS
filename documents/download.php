<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$user = require_auth();
$document = document_find(get_int('id'));
if ($document === null) {
    abort(404, 'Document not found', 'The requested document does not exist.');
}
if (!document_can_access($document)) {
    app_log('warning', 'Document access denied', ['user_id' => (int) $user['id'], 'document_id' => (int) $document['id']]);
    abort(403, 'Access denied', 'You do not have permission to download this document.');
}

$path = document_resolve_path($document);
if ($path === null) {
    abort(404, 'File unavailable', 'The stored file could not be located. Contact the system administrator.');
}

$mime = document_mime_type($document, $path);
$filename = (string) ($document['file_name'] ?? 'document');
$fallback = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'document';
$fallback = trim($fallback, '-.') ?: 'document';

audit_log('document.download', 'document', (int) $document['id'], 'Document downloaded');

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header('Content-Disposition: attachment; filename="' . $fallback . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
readfile($path);

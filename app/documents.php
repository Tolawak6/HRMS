<?php
declare(strict_types=1);

defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

function document_allowed_extensions(): array
{
    return array_keys((array) config('storage.allowed_documents', []));
}

function document_upload_errors(array $file): array
{
    $errors = [];
    if (!isset($file['error'])) {
        return ['file' => 'Choose a document to upload.'];
    }
    $error = (int) $file['error'];
    if ($error !== UPLOAD_ERR_OK) {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'The file exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'The file exceeds the allowed size.',
            UPLOAD_ERR_PARTIAL    => 'The upload did not complete. Please try again.',
            UPLOAD_ERR_NO_FILE    => 'Choose a document to upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server upload directory is unavailable.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION  => 'The upload was blocked by the server.',
        ];
        return ['file' => $messages[$error] ?? 'The document could not be uploaded.'];
    }
    $size = (int) ($file['size'] ?? 0);
    $maxBytes = (int) config('storage.max_file_mb', 5) * 1024 * 1024;
    if ($size <= 0) {
        $errors['file'] = 'The selected file is empty.';
    } elseif ($size > $maxBytes) {
        $errors['file'] = 'Documents must be smaller than ' . (int) config('storage.max_file_mb', 5) . ' MB.';
    }

    $original = (string) ($file['name'] ?? '');
    $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $allowed = (array) config('storage.allowed_documents', []);
    if (!isset($allowed[$ext])) {
        $errors['file'] = 'Allowed formats: ' . strtoupper(implode(', ', document_allowed_extensions())) . '.';
        return $errors;
    }

    $mime = '';
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp !== '' && is_file($tmp)) {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = (string) finfo_file($finfo, $tmp);
                finfo_close($finfo);
            }
        } elseif (function_exists('mime_content_type')) {
            $mime = (string) mime_content_type($tmp);
        }
    }
    if ($mime !== '' && !in_array($mime, (array) $allowed[$ext], true)) {
        $errors['file'] = 'The file contents do not match the selected file type.';
    }
    return $errors;
}

function safe_original_filename(string $name): string
{
    $name = basename(str_replace('\\', '/', $name));
    $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;
    $name = trim($name);
    if ($name === '' || $name === '.' || $name === '..') {
        $name = 'document';
    }
    if (mb_strlen($name) > 200) {
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $base = mb_substr($name, 0, 180);
        $name = $base . ($ext !== '' ? '.' . $ext : '');
    }
    return $name;
}

/**
 * @return array{ok:bool,id?:int,error?:string}
 */
function document_upload(int $employeeId, array $file, string $description, array $actor): array
{
    $employee = employee_find($employeeId);
    if ($employee === null) {
        return ['ok' => false, 'error' => 'Employee record not found.'];
    }
    if (!empty($employee['archived_at'])) {
        return ['ok' => false, 'error' => 'Documents cannot be added to an archived employee.'];
    }
    $description = trim($description);
    if (mb_strlen($description) > 255) {
        return ['ok' => false, 'error' => 'Description must be 255 characters or fewer.'];
    }

    $errors = document_upload_errors($file);
    if ($errors !== []) {
        return ['ok' => false, 'error' => $errors['file']];
    }

    $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
    $mime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = (string) finfo_file($finfo, (string) $file['tmp_name']);
            if (PHP_VERSION_ID < 80500) {
                finfo_close($finfo);
            }
        }
    }

    $relativeDir = 'uploads/documents/' . date('Y') . '/' . date('m');
    $directory = rtrim((string) config('storage.path'), '/') . '/' . $relativeDir;
    if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
        return ['ok' => false, 'error' => 'Document storage is not writable. Contact the system administrator.'];
    }

    $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
    $target = $directory . '/' . $storedName;
    if (!move_uploaded_file((string) $file['tmp_name'], $target)) {
        return ['ok' => false, 'error' => 'The document could not be saved.'];
    }

    $originalName = safe_original_filename((string) $file['name']);
    $logicalPath = 'storage/' . $relativeDir . '/' . $storedName;

    try {
        $id = db()->transaction(function (Database $db) use (
            $employeeId,
            $originalName,
            $logicalPath,
            $relativeDir,
            $storedName,
            $mime,
            $file,
            $description,
            $actor
        ): int {
            $newId = $db->insert(
                'INSERT INTO file_attachments
                    (ess_staff_id, file_name, file_path, uploaded_at, uploaded_by, stored_name,
                     mime_type, file_size, description)
                 VALUES (?, ?, ?, NOW(), ?, ?, ?, ?, ?)',
                [
                    $employeeId,
                    $originalName,
                    $logicalPath,
                    (int) $actor['id'],
                    $relativeDir . '/' . $storedName,
                    $mime !== '' ? $mime : null,
                    (int) $file['size'],
                    $description !== '' ? $description : null,
                ]
            );
            return $newId;
        });
    } catch (Throwable $exception) {
        @unlink($target);
        app_log('error', 'Document upload failed', ['reason' => $exception->getMessage()]);
        return ['ok' => false, 'error' => 'The document could not be recorded. Please try again.'];
    }

    audit_log('document.upload', 'document', $id, 'Document added to employee file');
    notify(
        $employeeId,
        'document',
        'New document added',
        'A new document was added to your employee file.',
        'documents/index.php'
    );
    return ['ok' => true, 'id' => $id];
}

function document_find(int $id): ?array
{
    if ($id <= 0) {
        return null;
    }
    return db()->one(
        'SELECT fa.*, u.first_name, u.middle_name, u.last_name, u.employee_number,
                u.photo_path, u.image, u.username
         FROM file_attachments fa
         JOIN users u ON u.id = fa.ess_staff_id
         WHERE fa.id = ? LIMIT 1',
        [$id]
    );
}

function document_can_access(array $document): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }
    if (can_manage_employees()) {
        return true;
    }
    return (int) $document['ess_staff_id'] === (int) $user['id'];
}

/**
 * Resolve an attachment to a readable path without allowing directory escape.
 */
function document_resolve_path(array $document): ?string
{
    $storageRoot = realpath((string) config('storage.path'));
    $legacyRoot = realpath(BASE_PATH . '/uploads');
    $stored = ltrim((string) ($document['stored_name'] ?? ''), '/');
    $candidates = [];

    if ($stored !== '' && $storageRoot !== false && !str_contains($stored, '..')) {
        $candidates[] = $storageRoot . '/' . $stored;
    }
    $filePath = ltrim((string) ($document['file_path'] ?? ''), '/');
    if ($filePath !== '' && !str_contains($filePath, '..')) {
        $filePath = preg_replace('#^storage/#', '', $filePath) ?? $filePath;
        if ($storageRoot !== false) {
            $candidates[] = $storageRoot . '/' . $filePath;
        }
        if ($legacyRoot !== false) {
            $legacyPath = preg_replace('#^uploads/#', '', $filePath) ?? $filePath;
            $candidates[] = $legacyRoot . '/' . $legacyPath;
        }
    }

    foreach ($candidates as $candidate) {
        $real = realpath($candidate);
        if ($real === false || !is_file($real) || !is_readable($real)) {
            continue;
        }
        $inStorage = $storageRoot !== false && str_starts_with($real, $storageRoot . DIRECTORY_SEPARATOR);
        $inLegacy = $legacyRoot !== false && str_starts_with($real, $legacyRoot . DIRECTORY_SEPARATOR);
        if ($inStorage || $inLegacy) {
            return $real;
        }
    }
    return null;
}

function document_mime_type(array $document, ?string $path = null): string
{
    $mime = (string) ($document['mime_type'] ?? '');
    $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (in_array($mime, $allowed, true)) {
        return $mime;
    }
    if ($path !== null && function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detected = (string) finfo_file($finfo, $path);
            if (PHP_VERSION_ID < 80500) {
                finfo_close($finfo);
            }
            if ($detected !== '') {
                return $detected;
            }
        }
    }
    return 'application/octet-stream';
}

function document_delete(int $id, array $actor): array
{
    $document = document_find($id);
    if ($document === null) {
        return ['ok' => false, 'error' => 'Document not found.'];
    }
    $path = document_resolve_path($document);
    $storageRoot = realpath((string) config('storage.path'));

    db()->execute('DELETE FROM file_attachments WHERE id = ?', [$id]);
    if ($path !== null && $storageRoot !== false && str_starts_with($path, $storageRoot . DIRECTORY_SEPARATOR)) {
        @unlink($path);
    }
    audit_log('document.delete', 'document', $id, 'Document deleted from employee file');
    return ['ok' => true, 'employee_id' => (int) $document['ess_staff_id']];
}

function documents_for_employee(int $employeeId, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    return db()->all(
        'SELECT fa.*, uploader.first_name AS uploader_first, uploader.last_name AS uploader_last
         FROM file_attachments fa
         LEFT JOIN users uploader ON uploader.id = fa.uploaded_by
         WHERE fa.ess_staff_id = ?
         ORDER BY fa.uploaded_at DESC, fa.id DESC LIMIT ' . $limit,
        [$employeeId]
    );
}

function document_filters(): array
{
    return [
        'q'        => get_str('q'),
        'employee' => max(0, get_int('employee')),
        'page'     => max(1, get_int('page', 1)),
        'per_page' => per_page_value(),
    ];
}

function documents_search(array $filters): array
{
    $where = [];
    $params = [];
    if ($filters['employee'] > 0) {
        $where[] = 'fa.ess_staff_id = ?';
        $params[] = $filters['employee'];
    }
    if ($filters['q'] !== '') {
        $term = '%' . like_term(mb_substr($filters['q'], 0, 80)) . '%';
        $where[] = "(fa.file_name LIKE ? ESCAPE '!'
            OR fa.description LIKE ? ESCAPE '!'
            OR u.first_name LIKE ? ESCAPE '!'
            OR u.last_name LIKE ? ESCAPE '!'
            OR u.employee_number LIKE ? ESCAPE '!')";
        array_push($params, $term, $term, $term, $term, $term);
    }
    $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);
    $total = (int) db()->value(
        "SELECT COUNT(*) FROM file_attachments fa JOIN users u ON u.id = fa.ess_staff_id $whereSql",
        $params,
        0
    );
    $pagination = paginate($total, $filters['per_page'], $filters['page']);
    $rows = db()->all(
        "SELECT fa.*, u.first_name, u.middle_name, u.last_name, u.employee_number,
                uploader.first_name AS uploader_first, uploader.last_name AS uploader_last
         FROM file_attachments fa
         JOIN users u ON u.id = fa.ess_staff_id
         LEFT JOIN users uploader ON uploader.id = fa.uploaded_by
         $whereSql
         ORDER BY fa.uploaded_at DESC, fa.id DESC
         LIMIT " . (int) $pagination['per_page'] . ' OFFSET ' . (int) $pagination['offset'],
        $params
    );
    return ['rows' => $rows, 'pagination' => $pagination, 'filters' => $filters];
}

function document_icon(string $filename): string
{
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return match ($ext) {
        'pdf' => 'file-earmark-pdf',
        'jpg', 'jpeg', 'png', 'webp' => 'file-earmark-image',
        'docx' => 'file-earmark-word',
        'xlsx' => 'file-earmark-excel',
        default => 'file-earmark-text',
    };
}

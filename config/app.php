<?php
declare(strict_types=1);

// Configuration is loaded by app/bootstrap.php only.
defined('HRMS_BOOTSTRAPPED') || exit('Application not bootstrapped.');

/*
 * Application configuration defaults.
 * Environment-specific values belong in .env (see .env.example).
 * No credentials are stored in this file.
 */

$appRoot = dirname(__DIR__);

$storagePath = trim((string) env('STORAGE_PATH', ''));
if ($storagePath === '') {
    $storagePath = $appRoot . '/storage';
}

return [
    'name'    => (string) env('APP_NAME', 'ESS HRMS'),
    'env'     => (string) env('APP_ENV', 'production'),
    'debug'   => filter_var(env('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN),
    'url'     => rtrim((string) env('APP_URL', ''), '/'),
    'timezone' => (string) env('APP_TIMEZONE', 'Africa/Addis_Ababa'),

    'db' => [
        'host'     => (string) env('DB_HOST', '127.0.0.1'),
        'port'     => (int) env('DB_PORT', 3306),
        'name'     => (string) env('DB_DATABASE', 'hrms'),
        'user'     => (string) env('DB_USERNAME', 'root'),
        'password' => (string) env('DB_PASSWORD', ''),
        'socket'   => trim((string) env('DB_SOCKET', '')),
        'charset'  => 'utf8mb4',
    ],

    'session' => [
        'name'            => 'ess_hrms_session',
        'idle_minutes'    => max(5, (int) env('SESSION_IDLE_MINUTES', 30)),
        'max_hours'       => max(1, (int) env('SESSION_MAX_HOURS', 8)),
        'regenerate_every' => 900,
        // Lax is correct for a normal top-level deployment. Embedded previews
        // served in a cross-site iframe need "None" (which requires HTTPS);
        // see SESSION_SAMESITE in .env.example.
        'same_site'       => strtolower(trim((string) env('SESSION_SAMESITE', 'Lax'))),
    ],

    'security' => [
        'login_max_attempts'  => max(3, (int) env('LOGIN_MAX_ATTEMPTS', 5)),
        'login_window_minutes' => max(5, (int) env('LOGIN_WINDOW_MINUTES', 15)),
        'password_min_length' => max(8, (int) env('PASSWORD_MIN_LENGTH', 10)),
        'trust_proxy'         => filter_var(env('TRUST_PROXY', 'false'), FILTER_VALIDATE_BOOLEAN),
    ],

    'storage' => [
        'path'        => $storagePath,
        'documents'   => $storagePath . '/uploads/documents',
        'photos'      => $storagePath . '/photos',
        'logs'        => $storagePath . '/logs',
        'max_file_mb' => 5,
        'allowed_documents' => [
            'pdf'  => ['application/pdf'],
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'webp' => ['image/webp'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        ],
        'allowed_photos' => ['jpg', 'jpeg', 'png', 'webp'],
    ],
];

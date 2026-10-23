<?php
declare(strict_types=1);

/*
 * Router for PHP's built-in development server:
 *   php -S 0.0.0.0:8000 router.php
 *
 * Apache deployments use .htaccess instead. This router keeps the same
 * protections during local development and preview hosting: source files,
 * configuration, logs and stored uploads are never served directly.
 */

$path = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
$path = '/' . ltrim($path, '/');

if (
    preg_match('#(^|/)\.#', $path)
    || preg_match('#^/(app|config|database|bin|includes|storage|uploads)(/|$)#', $path)
    || str_contains($path, '..')
) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo "Not found.\n";
    return true;
}

return false;

<?php
// Router for PHP's built-in server (php -S localhost:8000 router.php).
// Blocks files that must never be public, such as .env, then serves everything else normally.

$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

if (preg_match('#(^|/)\.|^/(database|tests)(/|$)|^/api/_|^/router\.php$#', $path)) {
    http_response_code(404);
    echo 'Not found';
    return true;
}

return false;

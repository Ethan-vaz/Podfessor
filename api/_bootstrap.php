<?php
// Shared setup for every API endpoint: config, database connection, sessions and JSON helpers.

declare(strict_types=1);

header('Content-Type: application/json');

set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    respond(500, ['error' => 'Something went wrong on the server. Please try again.']);
});

/** Read a setting from the environment, falling back to a .env file in the project root. */
function config(string $key, ?string $default = null): ?string
{
    static $fileValues = null;

    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if ($fileValues === null) {
        $fileValues = [];
        $path = dirname(__DIR__) . '/.env';
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
                    continue;
                }
                [$name, $val] = explode('=', $line, 2);
                $fileValues[trim($name)] = trim(trim($val), "\"'");
            }
        }
    }

    return ($fileValues[$key] ?? '') !== '' ? $fileValues[$key] : $default;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            config('DB_HOST', '127.0.0.1'),
            config('DB_PORT', '3306'),
            config('DB_NAME', 'podfessor')
        );
        $pdo = new PDO($dsn, config('DB_USER', 'root'), config('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}

function respond(int $status, ?array $body = null): never
{
    http_response_code($status);
    if ($body !== null) {
        echo json_encode($body);
    }
    exit;
}

/** Allow only the given HTTP methods and return the one used. */
function require_method(string ...$methods): string
{
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method, $methods, true)) {
        header('Allow: ' . implode(', ', $methods));
        respond(405, ['error' => 'Method not allowed.']);
    }
    return $method;
}

/**
 * Decode a JSON request body. Requiring the JSON content type also blocks
 * cross-site form posts, since browsers can't send it cross-site without CORS approval.
 */
function read_json(): array
{
    if (!str_starts_with($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        respond(415, ['error' => 'Send the request body as JSON.']);
    }
    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        respond(400, ['error' => 'The request body is not valid JSON.']);
    }
    return $data;
}

/** Return a trimmed string field, or '' when it's missing or not a string. */
function string_field(array $data, string $key): string
{
    return is_string($data[$key] ?? null) ? trim($data[$key]) : '';
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => ($_SERVER['HTTPS'] ?? '') === 'on',
    ]);
    session_name('podfessor_session');
    session_start();
}

function current_user_id(): ?int
{
    start_session();
    return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function require_login(): int
{
    $userId = current_user_id();
    if ($userId === null) {
        respond(401, ['error' => 'Please log in first.']);
    }
    return $userId;
}

function log_in(int $userId): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
}

function public_user(array $row): array
{
    return ['id' => (int) $row['id'], 'name' => $row['name'], 'email' => $row['email']];
}

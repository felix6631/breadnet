<?php
declare(strict_types=1);

// PHP is modern; page rendering deliberately stays simple.
$minimum = match (PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION) {
    '8.4' => '8.4.27', '8.5' => '8.5.12', default => null,
};
if (preg_match('/alpha|beta|RC|dev/i', PHP_VERSION) ||
    ($minimum === null && version_compare(PHP_VERSION, '8.6.0', '<')) ||
    ($minimum !== null && version_compare(PHP_VERSION, $minimum, '<'))) {
    throw new RuntimeException('Use a supported, security-patched PHP release (8.4.27 / 8.5.12 or newer).');
}
date_default_timezone_set('Asia/Seoul');

function db(): PDO
{
    static $db;
    if ($db instanceof PDO) return $db;
    $path = getenv('BREADNET_DB') ?: dirname(__DIR__) . '/var/breadnet.sqlite';
    if (!is_file($path)) throw new RuntimeException('Database not initialized. Run php bin/setup.php.');
    $db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    return $db;
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
function redirect(string $path): never
{
    header('Location: ' . $path, true, 303); exit;
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}
function input(string $name): string
{
    return isset($_POST[$name]) && is_string($_POST[$name]) ? trim($_POST[$name]) : '';
}
function valid_text(string $value, int $max): bool
{
    return $value !== '' && mb_check_encoding($value, 'UTF-8') && mb_strlen($value, 'UTF-8') <= $max;
}
function admin(): bool
{
    if (!isset($_SESSION['admin_id'], $_SESSION['login_at'], $_SESSION['session_version']) || time() - $_SESSION['login_at'] >= 7200) return false;
    $q = db()->prepare('SELECT session_version FROM admins WHERE id=?');
    $q->execute([$_SESSION['admin_id']]); $version = $q->fetchColumn();
    return is_string($version) && hash_equals($version, $_SESSION['session_version']);
}
function flash(string $message): void { $_SESSION['flash'] = $message; }
function take_flash(): string
{
    $message = $_SESSION['flash'] ?? ''; unset($_SESSION['flash']); return $message;
}
function page_number(string $key): int
{
    $value = $_GET[$key] ?? '1';
    return is_string($value) ? max(1, min(1000000, (int)$value)) : 1;
}

// Count attempts atomically across sessions; never trust forwarding headers.
function allow_attempt(string $scope, int $limit, int $window): bool
{
    $db = db();
    $secret = $db->query("SELECT value FROM settings WHERE name = 'rate_secret'")->fetchColumn();
    $key = hash_hmac('sha256', $scope . '|' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), $secret);
    $now = time();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $db->prepare('DELETE FROM attempts WHERE expires <= ?')->execute([$now]);
        $q = $db->prepare('SELECT count FROM attempts WHERE key = ?'); $q->execute([$key]);
        if ((int)$q->fetchColumn() >= $limit) { $db->exec('COMMIT'); return false; }
        $db->prepare('INSERT INTO attempts (key,count,expires) VALUES (?,1,?) ON CONFLICT(key) DO UPDATE SET count=count+1')
            ->execute([$key, $now + $window]);
        $db->exec('COMMIT'); return true;
    } catch (Throwable $error) { $db->exec('ROLLBACK'); throw $error; }
}

if (PHP_SAPI !== 'cli') {
    ini_set('display_errors', '0');
    set_exception_handler(function (Throwable $error): void {
        error_log((string)$error); http_response_code(500);
        echo '서버 오류가 발생했습니다. 잠시 후 다시 방문해 주세요.';
    });
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $local = PHP_SAPI === 'cli-server' && in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    if (!$https && !$local) { http_response_code(400); exit('HTTPS is required.'); }
    header('Content-Type: text/html; charset=UTF-8');
    header("Content-Security-Policy: default-src 'none'; script-src 'self'; connect-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store');
    if ($https) header('Strict-Transport-Security: max-age=31536000');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('breadnet_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https,
        'httponly' => true, 'samesite' => 'Lax']);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals($_SESSION['csrf'], input('csrf'))) {
        http_response_code(403); exit('요청 확인에 실패했습니다. 페이지를 새로 열어 주세요.');
    }
}

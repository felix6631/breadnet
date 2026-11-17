<?php
require dirname(__DIR__) . '/app/bootstrap.php';
require dirname(__DIR__) . '/app/terminal.php';
header('Content-Type: application/json; charset=UTF-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST'); http_response_code(405); echo json_encode(['error' => 'POST required']); exit;
}
$command = input('command'); $key = input('key');
if (!mb_check_encoding($command, 'UTF-8') || mb_strlen($command) > 4096 ||
    !in_array($key, ['', 'ArrowUp', 'ArrowDown', 'j', 'k', 'Enter', 'q', 'Escape', 'ControlC'], true)) {
    http_response_code(422); echo json_encode(['error' => 'Invalid command or key.']); exit;
}
$bucket = $_SESSION['terminal_rate'] ?? ['start' => time(), 'count' => 0];
if (time() - $bucket['start'] >= 60) $bucket = ['start' => time(), 'count' => 0];
$_SESSION['terminal_rate'] = ['start' => $bucket['start'], 'count' => $bucket['count'] + 1];
if ($bucket['count'] >= 240) {
    http_response_code(429); header('Retry-After: 60'); echo json_encode(['error' => 'Too many inputs. Please wait one minute.']); exit;
}
echo json_encode(terminal_handle($command, $key), JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

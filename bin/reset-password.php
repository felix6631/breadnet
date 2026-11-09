<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/app/bootstrap.php';
$password = bin2hex(random_bytes(16));
$q = db()->prepare('UPDATE admins SET password_hash = ?,session_version = ? WHERE username = ?');
$q->execute([password_hash($password, PASSWORD_DEFAULT), bin2hex(random_bytes(32)), 'breadkeeper']);
if ($q->rowCount() !== 1) { fwrite(STDERR, "Administrator not found.\n"); exit(1); }
echo "New password: $password\nExisting administrator sessions have been revoked.\n";

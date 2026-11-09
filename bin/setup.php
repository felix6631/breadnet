<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require dirname(__DIR__) . '/app/bootstrap.php';
$path = getenv('BREADNET_DB') ?: dirname(__DIR__) . '/var/breadnet.sqlite';
if (file_exists($path)) { fwrite(STDERR, "Database already exists; nothing changed.\n"); exit(1); }
if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
$db = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->beginTransaction();
$db->exec('CREATE TABLE admins (id INTEGER PRIMARY KEY, username TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, session_version TEXT NOT NULL);
CREATE TABLE posts (id INTEGER PRIMARY KEY, title TEXT NOT NULL, body TEXT NOT NULL, created_at TEXT NOT NULL);
CREATE TABLE guestbook (id INTEGER PRIMARY KEY, name TEXT NOT NULL, body TEXT NOT NULL, approved INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL);
CREATE INDEX guestbook_public ON guestbook(approved, id);
CREATE TABLE attempts (key TEXT PRIMARY KEY, count INTEGER NOT NULL, expires INTEGER NOT NULL);
CREATE TABLE settings (name TEXT PRIMARY KEY, value TEXT NOT NULL);');
$password = bin2hex(random_bytes(16));
$db->prepare('INSERT INTO admins(username,password_hash,session_version) VALUES (?,?,?)')->execute(['breadkeeper', password_hash($password, PASSWORD_DEFAULT), bin2hex(random_bytes(32))]);
$db->prepare('INSERT INTO settings(name,value) VALUES (?,?)')->execute(['rate_secret', bin2hex(random_bytes(32))]);
$db->prepare('INSERT INTO posts(title,body,created_at) VALUES (?,?,?)')->execute([
    '다시, 나만의 홈페이지를 만들기로 했다.',
    "내 취향이 묻어나는 작은 집 하나.\n메뉴 이름을 고르고, 배경색을 칠하는 일부터 시작해 본다.\n\n누가 찾아올지는 모르지만, 일단 현관등은 켜 두기로.", date('Y-m-d H:i:s')]);
$db->commit();
chmod($path, 0600);
echo "Database initialized.\nUsername: breadkeeper\nPassword: $password\nStore this password in your password manager. It is shown only once.\n";

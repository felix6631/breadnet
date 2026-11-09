<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$error = ''; $title = '주인장 관리실';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input('action');
    if ($action === 'login') {
        if (!allow_attempt('login', 5, 900)) {
            http_response_code(429); header('Retry-After: 900'); $error = '로그인 시도가 많습니다. 15분 후 다시 시도해 주세요.';
        } else {
            $q = db()->prepare('SELECT * FROM admins WHERE username = ?'); $q->execute([input('username')]); $user = $q->fetch();
            $hash = $user ? $user['password_hash'] : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            $raw = $_POST['password'] ?? '';
            $valid = is_string($raw) && strlen($raw) <= 1024 && password_verify($raw, $hash);
            if ($user && $valid) {
                session_regenerate_id(true); $_SESSION = ['admin_id' => $user['id'], 'login_at' => time(), 'session_version' => $user['session_version'], 'csrf' => bin2hex(random_bytes(32))];
                redirect('/admin.php');
            }
            $error = '아이디 또는 비밀번호를 확인해 주세요.';
        }
    } elseif (!admin()) {
        http_response_code(403); $error = '다시 로그인해 주세요.';
    } elseif ($action === 'logout') {
        $_SESSION = []; session_regenerate_id(true); $_SESSION['csrf'] = bin2hex(random_bytes(32)); redirect('/admin.php');
    } elseif ($action === 'save') {
        if (!valid_text(input('title'), 200) || !valid_text(input('body'), 20000)) {
            $error = '제목은 1~200자, 내용은 1~20,000자로 입력해 주세요.';
        } else {
            $id = (int)input('id');
            if ($id > 0) db()->prepare('UPDATE posts SET title=?,body=? WHERE id=?')->execute([input('title'), input('body'), $id]);
            else db()->prepare('INSERT INTO posts(title,body,created_at) VALUES (?,?,?)')->execute([input('title'), input('body'), date('Y-m-d H:i:s')]);
            flash('일기를 저장했어요.'); redirect('/admin.php');
        }
    } elseif (in_array($action, ['delete_post', 'approve_guest', 'delete_guest'], true)) {
        $sql = match($action) { 'delete_post' => 'DELETE FROM posts WHERE id=?',
            'approve_guest' => 'UPDATE guestbook SET approved=1 WHERE id=?', 'delete_guest' => 'DELETE FROM guestbook WHERE id=?' };
        db()->prepare($sql)->execute([(int)input('id')]); flash('처리했어요.'); redirect('/admin.php');
    } else { http_response_code(400); $error = '알 수 없는 요청입니다.'; }
}
require dirname(__DIR__) . '/templates/header.php';
?>
<div class="admin-content"><h1>주인장 관리실</h1>
<?php if ($error): ?><p class="message" role="alert"><?= e($error) ?></p><?php endif ?>
<?php if (!admin()): ?>
<form method="post" action="/admin.php"><?= csrf_field() ?><input type="hidden" name="action" value="login">
<p><label for="username">아이디</label><input id="username" name="username" maxlength="80" autocomplete="username" required></p>
<p><label for="password">비밀번호</label><input id="password" type="password" name="password" maxlength="1024" autocomplete="current-password" required></p>
<button type="submit">로그인</button></form>
<?php else:
$edit = null;
if (isset($_GET['edit']) && is_string($_GET['edit'])) { $q = db()->prepare('SELECT * FROM posts WHERE id=?'); $q->execute([(int)$_GET['edit']]); $edit = $q->fetch() ?: null; }
$formTitle = $error && input('action') === 'save' ? input('title') : ($edit['title'] ?? '');
$formBody = $error && input('action') === 'save' ? input('body') : ($edit['body'] ?? '');
$formId = $error && input('action') === 'save' ? (int)input('id') : ($edit['id'] ?? 0);
?>
<form method="post" action="/admin.php"><?= csrf_field() ?><input type="hidden" name="action" value="logout"><button type="submit">로그아웃</button></form>
<h2><?= $formId ? '일기 수정' : '새 일기' ?></h2>
<form method="post" action="/admin.php"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($formId) ?>">
<p><label for="title">제목</label><input id="title" name="title" maxlength="200" value="<?= e($formTitle) ?>" required></p>
<p><label for="body">내용 (HTML 없이 글로 작성)</label><textarea id="body" name="body" rows="10" maxlength="20000" required><?= e($formBody) ?></textarea></p>
<button type="submit">일기 저장</button> <a href="/admin.php">새 글로 돌아가기</a></form>
<h2>일기 관리</h2><p>최근 50개 표시 · 삭제는 되돌릴 수 없어요.</p>
<?php foreach (db()->query('SELECT id,title FROM posts ORDER BY id DESC LIMIT 50') as $post): ?>
<div class="admin-row"><a href="/admin.php?edit=<?= e($post['id']) ?>"><?= e($post['title']) ?> — 수정</a>
<form method="post" action="/admin.php"><?= csrf_field() ?><input type="hidden" name="action" value="delete_post"><input type="hidden" name="id" value="<?= e($post['id']) ?>"><label><input type="checkbox" required> 삭제 확인</label> <button type="submit">삭제</button></form></div>
<?php endforeach ?>
<h2>방명록 확인</h2><p>승인 대기 우선, 최근 50개 표시</p>
<?php foreach (db()->query('SELECT * FROM guestbook ORDER BY approved ASC,id DESC LIMIT 50') as $guest): ?>
<div class="guest"><b><?= e($guest['name']) ?></b> · <?= $guest['approved'] ? '공개 중' : '승인 대기' ?><p><?= nl2br(e($guest['body'])) ?></p>
<form method="post" action="/admin.php"><?= csrf_field() ?><input type="hidden" name="id" value="<?= e($guest['id']) ?>">
<?php if (!$guest['approved']): ?><button name="action" value="approve_guest" type="submit">공개 승인</button><?php endif ?>
<button name="action" value="delete_guest" type="submit">삭제</button></form></div>
<?php endforeach ?>
<?php endif ?></div>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>

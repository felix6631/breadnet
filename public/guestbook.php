<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
$name = input('name'); $body = input('body');
if (!valid_text($name, 40) || !valid_text($body, 1000)) {
    flash('이름은 1~40자, 내용은 1~1,000자로 입력해 주세요.'); redirect('/#guestbook');
}
if (!allow_attempt('guestbook', 3, 600)) {
    http_response_code(429); header('Retry-After: 600');
    $title = '잠시 쉬어가기'; require dirname(__DIR__) . '/templates/header.php';
    echo '<p>방명록은 10분에 세 번까지 남길 수 있어요. 잠시 후 다시 방문해 주세요.</p><p><a href="/#guestbook">방명록으로 돌아가기</a></p>';
    require dirname(__DIR__) . '/templates/footer.php'; exit;
}
db()->prepare('INSERT INTO guestbook(name,body,created_at) VALUES (?,?,?)')->execute([$name, $body, date('Y-m-d H:i:s')]);
flash('발자국을 남겼어요. 주인장이 확인하면 공개됩니다.'); redirect('/#guestbook');

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ko"><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? '나의 작은 인터넷') ?> — Breadnet</title>
<link rel="stylesheet" type="text/css" href="/style.css">
<script src="/terminal.js" defer></script>
</head><body><div class="page" id="top">
<div class="utility">개인 홈페이지 · HANDMADE WITH LOVE <span>책갈피에 꽂아두고 가세요 ☆</span></div>
<div class="header"><div class="bread" aria-hidden="true">▰</div><div class="logo">Bread<span>net</span><small>갓 구운 나의 작은 인터넷</small></div><p>조금 느려도 괜찮아.<br>여기는 내 마음대로 굽는 공간.</p></div>
<div class="nav"><a href="/">처음으로</a><a href="/#diary">빵 부스러기</a><a href="/#about">주인장 소개</a><a href="/#links">즐겨찾기</a><a href="/#guestbook">방명록</a><a href="/terminal.php" data-terminal-open>게스트 터미널</a><a href="/admin.php">관리실</a></div>
<?php if ($message = take_flash()): ?><p class="message" role="status"><?= e($message) ?></p><?php endif ?>

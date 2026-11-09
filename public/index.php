<?php
require dirname(__DIR__) . '/app/bootstrap.php';
if (!in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/', '/index.php'], true)) {
    http_response_code(404); $title = '길을 잃으셨나요?';
    require dirname(__DIR__) . '/templates/header.php';
    echo '<p>찾으시는 페이지가 없습니다. <a href="/">처음으로 돌아가기</a></p>';
    require dirname(__DIR__) . '/templates/footer.php'; exit;
}
$page = page_number('page'); $guestPage = page_number('guest_page');
$q = db()->prepare('SELECT * FROM posts ORDER BY id DESC LIMIT 6 OFFSET ?');
$q->bindValue(1, ($page - 1) * 5, PDO::PARAM_INT); $q->execute(); $posts = $q->fetchAll();
$morePosts = count($posts) > 5; $posts = array_slice($posts, 0, 5);
$q = db()->prepare('SELECT * FROM guestbook WHERE approved=1 ORDER BY id DESC LIMIT 11 OFFSET ?');
$q->bindValue(1, ($guestPage - 1) * 10, PDO::PARAM_INT); $q->execute(); $guests = $q->fetchAll();
$moreGuests = count($guests) > 10; $guests = array_slice($guests, 0, 10);
require dirname(__DIR__) . '/templates/header.php';
?>
<table class="layout" role="presentation" cellpadding="0" cellspacing="0"><tr>
<td class="left">
<div class="box" id="about"><h2>주인장의 작은 방</h2><div class="portrait"><span aria-hidden="true">▰</span><br>freshly baked!</div><div class="inside"><b>빵지기</b><small class="online">● 접속 중인 기분</small><p>빵과 음악, 그리고<br>오래된 인터넷을 좋아합니다.</p><div class="mood">오늘의 기분 : 따끈따끈 ☀</div></div></div>
<div class="box menu"><h2>서랍을 열어보세요</h2><a href="#diary">▸ 일상의 기록 </a><a href="#memo">▸ 작은 메모 </a><a href="#links">▸ 인터넷 산책 </a><a href="#guestbook">▸ 다녀간 흔적 </a></div>
<div class="counter">YOU ARE VISITOR<strong>HELLO, WORLD</strong><small>들러주셔서 고맙습니다</small></div>
<a class="banner" href="#top">BREADNET<small>100% handmade homepage</small></a>
</td>
<td class="main">
<div class="welcome"><small>WELCOME TO MY HOMEPAGE</small><h1>잘 오셨어요.<br>마침 빵이 구워졌어요.</h1><p>흘러가는 일상에서 조금씩 떼어 온 이야기들.<br>작고 소박하지만, 오래 머물고 싶은 곳을 만들고 있어요.</p><div>이곳의 모든 페이지는 천천히 익어갑니다. <span>✳</span></div></div>
<div class="section" id="diary"><h2>빵 부스러기 <small>DIARY</small></h2><span>소소한 날들의 기록</span></div>
<?php foreach ($posts as $post): ?>
<div class="post"><div class="date"><?= e($post['created_at']) ?></div><h3><?= e($post['title']) ?></h3><p><?= nl2br(e($post['body'])) ?></p><div class="post-footer">빵지기의 일상</div></div>
<?php endforeach ?>
<?php if (!$posts): ?><p>아직 기록이 없어요.</p><?php endif ?>
<div class="pagination"><?php if ($page > 1): ?><a href="/?page=<?= $page - 1 ?>#diary">← 이전</a><?php endif ?>
<span><?= $page ?> 쪽</span><?php if ($morePosts): ?><a href="/?page=<?= $page + 1 ?>#diary">다음 →</a><?php endif ?></div>
<div class="memo" id="memo"><b>✎ 책상 위 메모</b><p>빨리 채우려고 하지 말 것.<br>좋아하는 것부터 하나씩 올려두기.</p><small>— 나에게 보내는 작은 약속</small></div>
<div class="section" id="guestbook"><h2>다녀간 흔적 <small>GUESTBOOK</small></h2><span>작은 인사 한마디</span></div>
<?php foreach ($guests as $guest): ?>
<div class="guest"><b><?= e($guest['name']) ?></b><span><?= e($guest['created_at']) ?></span><p><?= nl2br(e($guest['body'])) ?></p></div>
<?php endforeach ?>
<?php if (!$guests): ?><p>첫 발자국을 기다리고 있어요.</p><?php endif ?>
<div class="pagination"><?php if ($guestPage > 1): ?><a href="/?guest_page=<?= $guestPage - 1 ?>#guestbook">← 이전</a><?php endif ?><span><?= $guestPage ?> 쪽</span><?php if ($moreGuests): ?><a href="/?guest_page=<?= $guestPage + 1 ?>#guestbook">다음 →</a><?php endif ?></div>
<form class="guest-form" method="post" action="/guestbook.php">
<?= csrf_field() ?>
<p><label for="guest-name">이름</label><input id="guest-name" name="name" maxlength="40" autocomplete="nickname" required></p>
<p><label for="guest-body">남길 말</label><textarea id="guest-body" name="body" maxlength="1000" rows="4" required></textarea></p>
<p>주인장 확인 후 공개됩니다. 개인정보는 남기지 말아 주세요.</p>
<button type="submit">발자국 남기기</button></form>
</td>
<td class="right">
<div class="box"><h2>지금 이곳은</h2><div class="status"><div class="sun">☀</div><b>느긋한 오후</b><p>창문 열고 환기하기 좋은 날</p><hr><small>NOW PLAYING</small><p>♪ 빵 굽는 소리</p><small>오늘은 조용히 쉬어갑니다.</small></div></div>
<div class="box" id="links"><h2>인터넷 산책길</h2><div class="links"><a href="https://archive.org/">Internet Archive ↗</a><small>오래된 인터넷의 보물창고</small><a href="https://www.wikipedia.org/">Wikipedia ↗</a><small>궁금한 게 생기는 날에는</small><a href="https://neocities.org/">Neocities ↗</a><small>이웃들의 작은 홈페이지</small></div></div>
<div class="construction"><b>공사 중인 구석</b><p>사진첩 · 좋아하는 음반<br>그리고 아직 이름 없는 것들</p><small>UNDER CONSTRUCTION</small></div>
<div class="ring">작은 홈페이지 연합<b>〈 PERSONAL WEB 〉</b><small>나만의 공간이 있다는 건 좋은 일!</small></div>
</td>
</tr></table>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>

<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$title = '게스트 터미널'; $terminalStandalone = true;
require dirname(__DIR__) . '/templates/header.php';
?>
<noscript><p>대화형 터미널에는 JavaScript가 필요합니다. 홈페이지의 다른 기능은 JavaScript 없이 사용할 수 있습니다.</p></noscript>
<?php require dirname(__DIR__) . '/templates/footer.php'; ?>

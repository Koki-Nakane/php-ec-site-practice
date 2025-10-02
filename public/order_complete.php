<?php

declare(strict_types=1);

session_start();

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ご注文完了</title>
  <meta http-equiv="Content-Security-Policy" content="default-src 'self'">
</head>
<body>
  <h1>ご注文ありがとうございました！</h1>
  <p>ご注文が正常に完了しました。</p>
  <p><a href="/">トップへ戻る</a></p>
</body>
</html>

<?php

declare(strict_types=1);

// === cURLによるAPIリクエスト ===
$ch = curl_init();

// フロントコントローラ配下の新しいエンドポイント（.php なしのルート）を使用
curl_setopt($ch, CURLOPT_URL, 'http://app/api/products');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);

$responseJson = curl_exec($ch);

curl_close($ch);

$productsArray = json_decode($responseJson, true);

echo '<pre>';
print_r($productsArray);
echo '</pre>';

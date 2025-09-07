<?php

declare(strict_types=1);

// === cURLによるAPIリクエスト ===
// 1. 初期化
$ch = curl_init();

// 2. オプション設定
curl_setopt($ch, CURLOPT_URL, 'http://app/api/products.php'); // 宛先
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 結果を文字列で受け取る
curl_setopt($ch, CURLOPT_HEADER, false); // ヘッダーは不要

// 3. 実行！
$responseJson = curl_exec($ch);

// 4. 後片付け
curl_close($ch);

// === レスポンスの処理 ===
// 5. 受け取ったJSON文字列を、PHPの連想配列に変換（デコード）
$productsArray = json_decode($responseJson, true);

// 6. 結果を人間が見やすいように表示
echo '<pre>';
print_r($productsArray);
echo '</pre>';

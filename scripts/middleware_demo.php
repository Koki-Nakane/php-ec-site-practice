<?php

declare(strict_types=1);

// scripts/middleware_demo.php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Http\Request;
use App\Http\Response;
use App\Infrastructure\Middleware\AuthenticationMiddleware;
use App\Infrastructure\Middleware\LoggingMiddleware;
use App\Infrastructure\Middleware\Pipeline;

// 最終的なリクエストハンドラ（アプリケーション本体の代わり）
$destination = function (Request $request): Response {
    echo "-> Reached the final destination! (Application Logic)\n";
    $user = $request->headers['x-authenticated-user'] ?? 'Guest';
    return new Response(200, 'Hello, ' . $user . '!');
};

echo "-------------------------------------------\n";
echo "--- Scenario 1: Authentication Successful ---\n";
echo "-------------------------------------------\n";

// 1. パイプラインを準備
$pipeline = new Pipeline();

// 2. ミドルウェアをパイプラインに追加（実行順）
// LoggingMiddleware -> AuthenticationMiddleware -> Destination
$pipeline->pipe(new LoggingMiddleware());
$pipeline->pipe(new AuthenticationMiddleware());

// 3. 認証情報を含むリクエストを作成
$request = new Request(
    method: 'GET',
    path: '/test',
    headers: ['x-authenticated-user' => 'koki']
);

// 4. パイプラインを実行
$response = $pipeline->process($request, $destination);

// 5. 結果を出力
echo "Response Status: {$response->status}\n";
echo "Response Body: {$response->body}\n";
echo "\n";

echo "-------------------------------------------------------\n";
echo "--- Scenario 2: Authentication Failed (Short-circuit) ---\n";
echo "-------------------------------------------------------\n";

// 1. パイプラインを準備（新しいインスタンスを使用）
$pipeline2 = new Pipeline();
$pipeline2->pipe(new LoggingMiddleware());
$pipeline2->pipe(new AuthenticationMiddleware());

// 2. 認証情報を含まないリクエストを作成
$request2 = new Request('GET', '/test');

// 3. パイプラインを実行
$response2 = $pipeline2->process($request2, $destination);

// 4. 結果を出力
echo "Response Status: {$response2->status}\n";
echo "Response Body: {$response2->body}\n";
echo "\n";

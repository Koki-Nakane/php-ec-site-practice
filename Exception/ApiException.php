<?php

declare(strict_types=1);

namespace App\Exception;

use Exception;

final class ApiException extends Exception
{
    private int $statusCode;

    /**
     * @param string $message エラーメッセージ (親クラスに渡す)
     * @param int $statusCode HTTPステータスコード (自分だけのプロパティ)
     * @param int $code 通常のエラーコード (親クラスに渡す)
     */

    public function __construct(string $message = '', int $statusCode = 500, int $code = 0)
    {
        // 2. ★★★親クラスのコンストラクタを呼び出す！★★★
        //    $message と $code の処理は、親に丸投げする！
        parent::__construct($message, $code);

        // 3. 自分だけの追加の仕事をする
        //    受け取ったHTTPステータスコードを、自分だけのプロパティに保存する
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

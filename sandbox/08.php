<?php

/* 8. Logger トレイトの作成:
ファイルにログメッセージを書き出す log(string $message) メソッドを持つ Logger トレイトを作成してください。ログには、現在の日時も記録するように DateTime クラスを使用してください。
*/

declare(strict_types=1);

use DateTime;

trait Logger
{
    public function log(string $message): void
    {
        $date = new DateTime();
        $logEntry = $date->format('Y-m-d H:i:s') . ': ' . $message . "\n";
        file_put_contents('app.log', $logEntry, FILE_APPEND);
    }
}

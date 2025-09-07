<?php

declare(strict_types=1);
/* 5. Database 接続クラス (Singleton):
PDO を使用してデータベースに接続する Database クラスを、シングルトンパターンで実装してください。アプリケーション全体で単一のデータベース接続を共有できるようにします。
参考：https://laranote.jp/learning-singleton-in-php/
*/

final class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        $dsn = 'mysql:host=localhost;dbname=php_advanced_db';
        $username = 'user';
        $password = 'password';

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $this->connection = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            throw new PDOException($e->getMessage(), (int)$e->getCode());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }
}

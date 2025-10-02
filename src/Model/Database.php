<?php

declare(strict_types=1);

namespace App\Model;

use PDO;
use PDOException;

final class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        // Updated to match docker-compose MYSQL_DATABASE value
        $dsn = 'mysql:host=db;dbname=php-ec-site-practice_db';
        $username = 'user';
        $password = 'user_password';

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

    public function __clone(): void
    {
        throw new \Exception('Cannot clone a singleton.');
    }

    public function __wakeup(): void
    {
        throw new \Exception('Cannot unserialize a singleton.');
    }
}

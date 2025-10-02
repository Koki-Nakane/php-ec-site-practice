<?php

declare(strict_types=1);

class Autoloader
{
    public static function load($className): void
    {
        // ベース名前空間文字列
        $namespacePrefix = 'App\\';

        if (!str_starts_with($className, $namespacePrefix)) {
            return;
        }

        $prefixLen = strlen($namespacePrefix);
        $relativeClass = substr($className, $prefixLen);

        $relativeFile = str_replace('\\', '/', $relativeClass);

        // ベースディレクトリ
        $baseDir = __DIR__ . '/src/';
        $filePath = $baseDir . $relativeFile . '.php';

        if (!file_exists($filePath)) {
            return;
        }

        require_once $filePath;
    }
}

spl_autoload_register(['Autoloader', 'load']);

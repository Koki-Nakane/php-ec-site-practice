<?php

/* 30. ファイルアップロード機能:
ブログ記事に画像を添付できるように、ファイルアップロードを処理する ImageUploader クラスを作成してください。ファイル名が重複しないようリネームし、指定されたディレクトリに保存する機能を持ちます。
*/

declare(strict_types=1);

namespace App\Service;

use DomainException;
use InvalidArgumentException;
use RuntimeException;

final class ImageUploader
{
    public function __construct(
        private string $basePublicDir = __DIR__ . '/../../public/uploads/images',
        private int $maxBytes = 2_000_000,
        private array $allowedMime = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
        ]
    ) {
    }

    // $file = $_FILES['image']
    public function upload(array $file): string
    {
        $this->validateStructure($file);
        $this->validateError($file['error']);
        $this->validateSize((int) $file['size']);

        $mime = $this->detectMime($file['tmp_name']);
        $this->assertAllowedMime($mime);

        $originalExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION) ?: '');
        $ext = $this->resolveExtension($mime, $originalExt);

        $this->ensureDir($this->basePublicDir);

        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Not an uploaded file.');
        }

        $filename = $this->generateFilename($ext);
        $target = rtrim($this->basePublicDir, '/') . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $target)) {
            throw new RuntimeException('Move failed.');
        }

        return 'uploads/images/' . $filename;
    }

    private function validateStructure(array $f): void
    {
        foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $k) {
            if (!array_key_exists($k, $f)) {
                throw new InvalidArgumentException('Malformed file array.');
            }
        }
    }

    private function validateError(int $err): void
    {
        if ($err === UPLOAD_ERR_OK) {
            return;
        }

        throw new RuntimeException('Upload error code: ' . $err);
    }

    private function validateSize(int $size): void
    {
        if ($size <= 0) {
            throw new DomainException('Empty file.');
        }

        if ($size > $this->maxBytes) {
            throw new DomainException('File too large.');
        }
    }

    private function detectMime(string $tmp): string
    {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        if (!$fi) {
            throw new RuntimeException('finfo_open failed.');
        }

        $mime = finfo_file($fi, $tmp) ?: '';
        finfo_close($fi);

        return $mime;
    }

    private function assertAllowedMime(string $mime): void
    {
        if (!isset($this->allowedMime[$mime])) {
            throw new DomainException('MIME not allowed: ' . $mime);
        }
    }

    private function resolveExtension(string $mime, string $originalExt): string
    {
        $expected = $this->allowedMime[$mime];
        if ($originalExt !== $expected) {
            return $expected;
        }

        return $originalExt;
    }

    private function ensureDir(string $dir): void
    {
        if (is_dir($dir)) {
            return;
        }

        if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create directory.');
        }
    }

    private function generateFilename(string $ext): string
    {
        return uniqid('', true) . '.' . $ext;
    }
}

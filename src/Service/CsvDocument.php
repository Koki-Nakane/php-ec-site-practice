<?php

declare(strict_types=1);

namespace App\Service;

final class CsvDocument
{
    public function __construct(
        private string $filename,
        private string $content,
        private string $contentType = 'text/csv; charset=UTF-8',
    ) {
    }

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }
}

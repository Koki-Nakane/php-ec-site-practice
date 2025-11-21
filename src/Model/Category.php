<?php

declare(strict_types=1);

namespace App\Model;

use InvalidArgumentException;

final class Category
{
    private ?int $id;
    private string $name;
    private string $slug;

    public function __construct(
        string $name,
        string $slug,
        ?int $id = null
    ) {
        $this->name = $this->normalizeName($name);
        $this->slug = $this->normalizeSlug($slug);
        $this->id = $id;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getName(): string
    {
        return $this->name;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('カテゴリ名は必須です。');
        }

        if (mb_strlen($name, 'UTF-8') > 255) {
            throw new InvalidArgumentException('カテゴリ名は255文字以内にしてください。');
        }

        return $name;
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            throw new InvalidArgumentException('カテゴリの slug は必須です。');
        }

        return $slug;
    }
}

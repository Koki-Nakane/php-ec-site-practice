<?php

/* 26. Post クラスと Category クラスの作成:
ブログ記事を表す Post クラス (id, title, content, createdAt) と、カテゴリを表す Category クラス (id, name) を作成してください。
*/

declare(strict_types=1);

final class Category
{
    private ?int $id;
    private string $name;

    public function __construct(
        string $name,
        ?int $id = null
    ) {
        $this->name = $name;
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
}

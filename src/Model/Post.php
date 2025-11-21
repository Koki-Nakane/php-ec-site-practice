<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;
use Parsedown;

// Post.php
final class Post
{
    private const ALLOWED_STATUSES = ['draft', 'published'];

    private ?int $id;
    private string $title;
    private ?int $authorId;
    private string $slug;
    private string $content;
    private DateTimeImmutable $createdAt;
    private ?DateTimeImmutable $updatedAt;
    private string $status;
    private int $commentCount;
    /** @var Category[] */
    private array $categories = [];

    public function __construct(
        string $title,
        string $content,
        string $slug,
        ?int $authorId = null,
        ?int $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        array $categories = [],
        string $status = 'published',
        int $commentCount = 0
    ) {
        $this->title = $title;
        $this->content = $content;
        $this->slug = $this->normalizeSlug($slug);
        $this->authorId = $authorId;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt;
        $this->setCategories($categories);
        $this->setStatus($status);
        $this->setCommentCount($commentCount);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getAuthorId(): ?int
    {
        return $this->authorId;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $normalized = strtolower(trim($status));

        if (!in_array($normalized, self::ALLOWED_STATUSES, true)) {
            throw new InvalidArgumentException('ステータスは draft または published のみ許可されます。');
        }

        $this->status = $normalized;
    }

    public function getCommentCount(): int
    {
        return $this->commentCount;
    }

    public function setCommentCount(int $commentCount): void
    {
        if ($commentCount < 0) {
            throw new InvalidArgumentException('コメント数は 0 以上で指定してください。');
        }

        $this->commentCount = $commentCount;
    }

    public function setId(int $id): void
    {
        if ($this->id !== null) {
            throw new DomainException('ID は一度設定すると変更できません。');
        }

        if ($id <= 0) {
            throw new InvalidArgumentException('ID は正の整数で指定してください。');
        }

        $this->id = $id;
    }

    /**
     * @return Category[]
     */
    public function getCategories(): array
    {
        return $this->categories;
    }

    public function setCategories(array $categories): void
    {
        foreach ($categories as $category) {
            if (!$category instanceof Category) {
                throw new InvalidArgumentException('Categories must be instances of Category.');
            }
        }

        $this->categories = array_values($categories);
    }

    public function getHtmlContent(): string
    {
        $parsedown = new Parsedown();
        $parsedown->setSafeMode(true);
        return $parsedown->text($this->content);
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = trim($slug);

        if ($slug === '') {
            throw new InvalidArgumentException('Slug は空にできません。');
        }

        return $slug;
    }
}

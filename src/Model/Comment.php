<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

final class Comment
{
    private ?int $id = null;
    private int $postId;
    private ?int $userId;
    private string $content;
    private DateTimeImmutable $createdAt;

    public function __construct(
        int $postId,
        ?int $userId,
        string $content,
        ?int $id = null,
        ?DateTimeImmutable $createdAt = null
    ) {
        $this->postId = $postId;
        $this->userId = $userId;
        $this->content = $content;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPostId(): int
    {
        return $this->postId;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}

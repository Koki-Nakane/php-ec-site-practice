<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;
use InvalidArgumentException;

final class Review
{
    private ?int $id;
    private int $productId;
    private int $userId;
    private string $title;
    private int $rating;
    private string $comment;
    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;
    private ?DateTimeImmutable $deletedAt;

    public function __construct(
        int $productId,
        int $userId,
        string $title,
        int $rating,
        string $comment,
        ?int $id = null,
        ?DateTimeImmutable $createdAt = null,
        ?DateTimeImmutable $updatedAt = null,
        ?DateTimeImmutable $deletedAt = null,
    ) {
        $this->productId = $productId;
        $this->userId = $userId;
        $this->setTitle($title);
        $this->setRating($rating);
        $this->setComment($comment);
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? new DateTimeImmutable();
        $this->deletedAt = $deletedAt;
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['product_id'],
            (int) $row['user_id'],
            (string) $row['title'],
            (int) $row['rating'],
            (string) $row['comment'],
            id: isset($row['id']) ? (int) $row['id'] : null,
            createdAt: isset($row['created_at']) ? new DateTimeImmutable((string) $row['created_at']) : null,
            updatedAt: isset($row['updated_at']) ? new DateTimeImmutable((string) $row['updated_at']) : null,
            deletedAt: !empty($row['deleted_at']) ? new DateTimeImmutable((string) $row['deleted_at']) : null,
        );
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getProductId(): int
    {
        return $this->productId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $trimmed = trim($title);
        if ($trimmed === '') {
            throw new InvalidArgumentException('タイトルを入力してください。');
        }
        $this->title = $trimmed;
    }

    public function getRating(): int
    {
        return $this->rating;
    }

    public function setRating(int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('評価は1〜5の整数で指定してください。');
        }

        $this->rating = $rating;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function setComment(string $comment): void
    {
        $trimmed = trim($comment);
        if ($trimmed === '') {
            throw new InvalidArgumentException('レビュー本文を入力してください。');
        }

        $this->comment = $trimmed;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(DateTimeImmutable $when): void
    {
        $this->updatedAt = $when;
    }

    public function getDeletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function markDeleted(DateTimeImmutable $when): void
    {
        $this->deletedAt = $when;
        $this->touch($when);
    }
}

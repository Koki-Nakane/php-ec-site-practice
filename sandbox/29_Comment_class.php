<?php

/* 29. コメント機能 (Comment クラス):
記事へのコメントを表す Comment クラス (id, postId, authorName, content, createdAt) と、それをDB操作する CommentMapper クラスを作成してください。
*/

declare(strict_types=1);

namespace App\Model;

final class Comment
{
    private ?int $id = null;
    private int $postId;
    private string $authorName;
    private string $content;
    private \DateTime $createdAt;

    public function __construct(
        int $postId,
        string $authorName,
        string $content,
        ?int $id = null,
        ?\DateTime $createdAt = null
    ) {
        $this->postId = $postId;
        $this->authorName = $authorName;
        $this->content = $content;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPostId(): int
    {
        return $this->postId;
    }

    public function getAuthorName(): string
    {
        return $this->authorName;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }
}

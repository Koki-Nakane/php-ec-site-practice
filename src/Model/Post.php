<?php

declare(strict_types=1);

namespace App\Model;

use DateTime;
use Parsedown;

// Post.php
final class Post
{
    // プロパティプロモーションを使わない、伝統的な書き方の方が柔軟だ！
    private ?int $id;
    private string $title;
    private string $content;
    private DateTime $createdAt;

    public function __construct(
        string $title,
        string $content,
        ?int $id = null,
        ?DateTime $createdAt = null // createdAtもオプションにする！
    ) {
        $this->title = $title;
        $this->content = $content;
        $this->id = $id;
        $this->createdAt = $createdAt ?? new DateTime();
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
    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
    public function getHtmlContent(): string
    {
        $parsedown = new Parsedown();
        $parsedown->setSafeMode(true);
        return $parsedown->text($this->content);
    }
}

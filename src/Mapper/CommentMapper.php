<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Comment;
use DateTime;

final class CommentMapper
{
    public function __construct(
        private \PDO $pdo
    ) {
    }

    public function findByPostId(int $postId): array
    {
        $sql = 'SELECT * FROM comments WHERE post_id = ? ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$postId]);
        // Fetch associative arrays for clearer mapping
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $comments = [];

        foreach ($rows as $row) {
            $comments[] = new Comment(
                (int)$row['post_id'],
                $row['author_name'],
                $row['content'],
                (int)$row['id'],
                new DateTime($row['created_at'])
            );
        }
        return $comments;
    }

    public function save(Comment $comment): void
    {
        if ($comment->getId() === null) {
            $this->insert($comment);
        } else {
            $this->update($comment);
        }
    }

    private function insert(Comment $comment): void
    {
        $sql = 'INSERT INTO comments (post_id, author_name, content) VALUES (:post_id, :author_name, :content)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            // post_id references the related post, not the comment id
            ':post_id' => $comment->getPostId(),
            ':author_name' => $comment->getAuthorName(),
            ':content' => $comment->getContent(),
        ]);

        $id = $this->pdo->lastInsertId();
        $comment->setId((int)$id);
    }

    private function update(Comment $comment): void
    {
        // Only author_name and content are updatable; created_at remains immutable
        $sql = 'UPDATE comments SET author_name = :author_name, content = :content WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':author_name' => $comment->getAuthorName(),
            ':content' => $comment->getContent(),
            ':id' => $comment->getId(),
        ]);
    }
}

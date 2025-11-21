<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Comment;
use DateTimeImmutable;
use PDO;

final class CommentMapper
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    /**
     * @return array{comments: Comment[], total: int}
     */
    public function findByPostId(int $postId, int $limit, int $offset, string $orderColumn, string $orderDirection): array
    {
        $countStmt = $this->pdo->prepare('SELECT COUNT(*) FROM comments WHERE post_id = :post_id');
        $countStmt->execute([':post_id' => $postId]);
        $total = (int) $countStmt->fetchColumn();

        $sql = sprintf(
            'SELECT * FROM comments WHERE post_id = :post_id ORDER BY %s %s LIMIT :limit OFFSET :offset',
            $orderColumn,
            $orderDirection
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':post_id', $postId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $comments = [];

        foreach ($rows as $row) {
            $comments[] = new Comment(
                (int) $row['post_id'],
                isset($row['user_id']) ? (int) $row['user_id'] : null,
                (string) $row['content'],
                (int) $row['id'],
                new DateTimeImmutable((string) $row['created_at'])
            );
        }
        return ['comments' => $comments, 'total' => $total];
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
        $sql = 'INSERT INTO comments (post_id, user_id, content) VALUES (:post_id, :user_id, :content)';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            // post_id references the related post, not the comment id
            ':post_id' => $comment->getPostId(),
            ':user_id' => $comment->getUserId(),
            ':content' => $comment->getContent(),
        ]);

        $id = $this->pdo->lastInsertId();
        $comment->setId((int)$id);
    }

    private function update(Comment $comment): void
    {
        // Allow content edits while keeping created_at immutable
        $sql = 'UPDATE comments SET content = :content WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':content' => $comment->getContent(),
            ':id' => $comment->getId(),
        ]);
    }
}

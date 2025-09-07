<?php

/* 29. コメント機能 (Comment クラス):
記事へのコメントを表す Comment クラス (id, postId, authorName, content, createdAt) と、それをDB操作する CommentMapper クラスを作成してください。
*/

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Comment;
use DateTime;
use PDO;

final class CommentMapper
{
    public function __construct(
        private PDO $pdo
    ) {
    }

    public function findByPostId(int $postId): array
    {
        $sql = 'SELECT * FROM comments WHERE post_id = ? ORDER BY created_at DESC';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$postId]);
        $rows = $stmt->fetchAll();

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
        if ($comment->getId() === null || $comment->getId() === 0) {
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
            ':post_id' => $comment->getID(),
            ':author_name' => $comment->getAuthorName(),
            ':content' => $comment->getContent(),
        ]);

        $id = $this->pdo->lastInsertId();
        $comment->setId((int)$id);
    }

    private function update(Comment $comment): void
    {
        $sql = 'UPDATE comments SET name = :name, price = :price, description = :description, stock = :stock WHERE id = :id';

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':name' => $comment->getName(),
            ':price' => $comment->getPrice(),
            ':description' => $comment->getDescription(),
            ':stock' => $comment->getStock(),
            ':id' => $comment->getId(),
        ]);
    }
}

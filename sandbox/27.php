<?php

/* 27. 多対多リレーションの実装:
1つの記事が複数のカテゴリに属せるよう、中間テーブル (post_categories) を設計してください。その上で、特定のカテゴリに属する記事一覧を取得する findPostsByCategory(int $categoryId) のようなメソッドを PostMapper に実装してください。
*/

declare(strict_types=1);

namespace App\Mapper;

use DateTime;
use PDO;

final class PostMapper
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findPostsByCategory(int $categoryId): array
    {
        $sql = 'SELECT
                    p.*
                FROM
                    posts AS p
                INNER JOIN
                    post_categories AS pc ON p.id = pc.post_id
                WHERE
                    pc.category_id = ?';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$categoryId]);
        $rows = $stmt->fetchAll();

        $posts = [];

        foreach ($rows as $row) {
            $post = new Post(
                (string)$row['title'],
                (string)$row['content'],
                (int)$row['id'],
                new DateTime($row['created_at']),
            );
            $posts[] = $post;
        }

        return $posts;
    }
}

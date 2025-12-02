<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Review;
use DateTimeImmutable;
use PDO;

final class ReviewMapper
{
    private const DATE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<int,array{review:Review,author_name:string}>
     */
    public function listActiveByProduct(int $productId, int $limit, int $offset): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT pr.*, u.name AS author_name
             FROM product_reviews pr
             JOIN users u ON pr.user_id = u.id
             WHERE pr.product_id = :product_id AND pr.deleted_at IS NULL
             ORDER BY pr.created_at DESC
             LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':product_id', $productId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'review' => Review::fromRow($row),
                'author_name' => (string) $row['author_name'],
            ];
        }

        return $results;
    }

    public function countActiveByProduct(int $productId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM product_reviews WHERE product_id = :product_id AND deleted_at IS NULL');
        $stmt->execute([':product_id' => $productId]);

        return (int) $stmt->fetchColumn();
    }

    public function averageRating(int $productId): ?float
    {
        $stmt = $this->pdo->prepare('SELECT AVG(rating) FROM product_reviews WHERE product_id = :product_id AND deleted_at IS NULL');
        $stmt->execute([':product_id' => $productId]);
        $value = $stmt->fetchColumn();
        if ($value === false || $value === null) {
            return null;
        }

        return (float) $value;
    }

    public function findActiveByUserAndProduct(int $userId, int $productId): ?Review
    {
        $stmt = $this->pdo->prepare('SELECT * FROM product_reviews WHERE user_id = :user_id AND product_id = :product_id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([
            ':user_id' => $userId,
            ':product_id' => $productId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Review::fromRow($row) : null;
    }

    public function findById(int $id): ?Review
    {
        $stmt = $this->pdo->prepare('SELECT * FROM product_reviews WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? Review::fromRow($row) : null;
    }

    public function insert(Review $review): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO product_reviews (product_id, user_id, title, rating, comment, created_at, updated_at)
             VALUES (:product_id, :user_id, :title, :rating, :comment, :created_at, :updated_at)'
        );
        $stmt->execute([
            ':product_id' => $review->getProductId(),
            ':user_id' => $review->getUserId(),
            ':title' => $review->getTitle(),
            ':rating' => $review->getRating(),
            ':comment' => $review->getComment(),
            ':created_at' => $review->getCreatedAt()->format(self::DATE_FORMAT),
            ':updated_at' => $review->getUpdatedAt()->format(self::DATE_FORMAT),
        ]);

        $review->setId((int) $this->pdo->lastInsertId());
    }

    public function softDelete(int $id, DateTimeImmutable $when): bool
    {
        $stmt = $this->pdo->prepare('UPDATE product_reviews SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            ':deleted_at' => $when->format(self::DATE_FORMAT),
            ':updated_at' => $when->format(self::DATE_FORMAT),
            ':id' => $id,
        ]);

        return $stmt->rowCount() > 0;
    }
}

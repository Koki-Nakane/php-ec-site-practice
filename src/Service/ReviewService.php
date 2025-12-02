<?php

declare(strict_types=1);

namespace App\Service;

use App\Mapper\ReviewMapper;
use App\Model\Enum\OrderStatus;
use App\Model\Review;
use DateTimeImmutable;
use PDO;

final class ReviewService
{
    private const TITLE_MIN = 2;
    private const TITLE_MAX = 120;
    private const COMMENT_MAX = 500;

    public function __construct(
        private ReviewMapper $reviews,
        private PDO $pdo,
    ) {
    }

    /**
     * @return array{items:array<int,array{review:Review,authorName:string}>,total:int,average:?float,page:int,perPage:int,totalPages:int}
     */
    public function listProductReviews(int $productId, int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset = ($page - 1) * $perPage;

        $items = array_map(
            fn (array $row): array => ['review' => $row['review'], 'authorName' => $row['author_name']],
            $this->reviews->listActiveByProduct($productId, $perPage, $offset)
        );
        $total = $this->reviews->countActiveByProduct($productId);
        $average = $this->reviews->averageRating($productId);
        $totalPages = (int) max(1, ceil($total / $perPage));

        return [
            'items' => $items,
            'total' => $total,
            'average' => $average,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * @return array{status:string,review:?Review}
     */
    public function checkEligibility(int $productId, int $userId): array
    {
        $existing = $this->reviews->findActiveByUserAndProduct($userId, $productId);
        if ($existing !== null) {
            return ['status' => 'already_reviewed', 'review' => $existing];
        }

        if (!$this->userHasPurchasedProduct($productId, $userId)) {
            return ['status' => 'purchase_required', 'review' => null];
        }

        return ['status' => 'eligible', 'review' => null];
    }

    /**
     * @return array{ok:bool,errors:array<string,string>,review?:Review}
     */
    public function createReview(int $productId, int $userId, string $title, string $comment, int $rating): array
    {
        $errors = $this->validateInputs($title, $comment, $rating);

        if (!$this->userHasPurchasedProduct($productId, $userId)) {
            $errors['purchase'] = '購入済みのお客様のみレビューを投稿できます。';
        }

        if ($this->reviews->findActiveByUserAndProduct($userId, $productId) !== null) {
            $errors['duplicate'] = 'この商品へのレビューは既に投稿済みです。削除後に再投稿してください。';
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $now = new DateTimeImmutable();
        $review = new Review(
            $productId,
            $userId,
            $title,
            $rating,
            $comment,
            createdAt: $now,
            updatedAt: $now,
        );
        $this->reviews->insert($review);

        return ['ok' => true, 'errors' => [], 'review' => $review];
    }

    /**
     * @return array{ok:bool,error?:string}
     */
    public function deleteReview(int $reviewId, int $actorId, bool $actorIsAdmin): array
    {
        $review = $this->reviews->findById($reviewId);
        if ($review === null || $review->isDeleted()) {
            return ['ok' => false, 'error' => 'not_found'];
        }

        if (!$actorIsAdmin && $review->getUserId() !== $actorId) {
            return ['ok' => false, 'error' => 'forbidden'];
        }

        $deleted = $this->reviews->softDelete($reviewId, new DateTimeImmutable());
        if (!$deleted) {
            return ['ok' => false, 'error' => 'already_deleted'];
        }

        return ['ok' => true];
    }

    public function findUserReview(int $productId, int $userId): ?Review
    {
        return $this->reviews->findActiveByUserAndProduct($userId, $productId);
    }

    /**
     * @return array<string,string>
     */
    public function validateInputs(string $title, string $comment, int $rating): array
    {
        $errors = [];

        $titleTrimmed = trim($title);
        if ($titleTrimmed === '') {
            $errors['title'] = 'タイトルを入力してください。';
        } elseif (mb_strlen($titleTrimmed, 'UTF-8') < self::TITLE_MIN || mb_strlen($titleTrimmed, 'UTF-8') > self::TITLE_MAX) {
            $errors['title'] = sprintf('タイトルは%d〜%d文字で入力してください。', self::TITLE_MIN, self::TITLE_MAX);
        }

        $commentTrimmed = trim($comment);
        if ($commentTrimmed === '') {
            $errors['comment'] = 'レビュー本文を入力してください。';
        } elseif (mb_strlen($commentTrimmed, 'UTF-8') > self::COMMENT_MAX) {
            $errors['comment'] = sprintf('レビュー本文は最大%d文字です。', self::COMMENT_MAX);
        }

        if ($rating < 1 || $rating > 5) {
            $errors['rating'] = '評価は1〜5の整数で入力してください。';
        }

        return $errors;
    }

    public function userHasPurchasedProduct(int $productId, int $userId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM order_items oi
             INNER JOIN orders o ON o.id = oi.order_id
             WHERE oi.product_id = :product_id
               AND o.user_id = :user_id
               AND (o.deleted_at IS NULL)
               AND o.status <> :canceled
             LIMIT 1'
        );
        $stmt->execute([
            ':product_id' => $productId,
            ':user_id' => $userId,
            ':canceled' => OrderStatus::CANCELED,
        ]);

        return $stmt->fetchColumn() !== false;
    }

    /**
     * @return array<string,mixed>
     */
    public function formatReviewForApi(Review $review, string $authorName): array
    {
        return [
            'id' => $review->getId(),
            'productId' => $review->getProductId(),
            'userId' => $review->getUserId(),
            'title' => $review->getTitle(),
            'rating' => $review->getRating(),
            'comment' => $review->getComment(),
            'author' => ['name' => $authorName],
            'createdAt' => $review->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $review->getUpdatedAt()->format(DATE_ATOM),
        ];
    }

    public function getReview(int $reviewId): ?Review
    {
        return $this->reviews->findById($reviewId);
    }
}

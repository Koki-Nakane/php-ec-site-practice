<?php

declare(strict_types=1);

namespace App\Mapper;

use App\Model\Category;
use App\Model\Post;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use RuntimeException;

final class PostMapper
{
    private const DATE_TIME_FORMAT = 'Y-m-d H:i:s';

    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array{posts: Post[], total: int}
     */
    public function findAll(PostFilter $filter): array
    {
        $params = [];
        $whereClause = $this->buildWhereClause($filter, $params);
        $orderColumn = $this->mapSortToColumn($filter->getSort());
        $orderDirection = strtoupper($filter->getOrder());

        $idsSql = sprintf(
            '%s%s ORDER BY %s %s LIMIT :limit OFFSET :offset',
            $this->baseIdSelectSql(),
            $whereClause,
            $orderColumn,
            $orderDirection
        );

        $stmt = $this->pdo->prepare($idsSql);
        $this->bindParams($stmt, $params);
        $stmt->bindValue(':limit', $filter->getPerPage(), PDO::PARAM_INT);
        $stmt->bindValue(':offset', $filter->getOffset(), PDO::PARAM_INT);
        $stmt->execute();
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if ($ids === []) {
            $total = $this->countByFilter($whereClause, $params);

            return ['posts' => [], 'total' => $total];
        }

        $posts = $this->fetchPostsInOrder($ids);
        $total = $this->countByFilter($whereClause, $params);

        return ['posts' => $posts, 'total' => $total];
    }

    public function findById(int $id): ?Post
    {
        $sql = $this->baseSelectSql() . ' WHERE p.id = :id';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $posts = $this->mapRowsToPosts($rows);

        return $posts[0] ?? null;
    }

    /**
     * @return Post[]
     */
    public function findPostsByCategory(int $categoryId): array
    {
        return $this->findPostsByCategories([$categoryId]);
    }

    /**
     * @param int[] $categoryIds
     * @return Post[]
     */
    public function findPostsByCategories(array $categoryIds): array
    {
        $categoryIds = array_values(array_filter(array_map('intval', $categoryIds), static fn (int $id) => $id > 0));

        if ($categoryIds === []) {
            return [];
        }

        $in = $this->prepareInClause($categoryIds, 'cat');
        $sql = sprintf(
            'SELECT DISTINCT p.id FROM posts p INNER JOIN post_categories pc ON pc.post_id = p.id WHERE pc.category_id IN (%s) ORDER BY p.created_at DESC',
            $in['placeholders']
        );

        $stmt = $this->pdo->prepare($sql);
        $this->bindParams($stmt, $in['params']);
        $stmt->execute();
        $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        return $ids === [] ? [] : $this->fetchPostsInOrder($ids);
    }

    public function insert(Post $post): int
    {
        try {
            $this->pdo->beginTransaction();

            $sql = 'INSERT INTO posts (title, slug, content, status, comment_count, created_at, updated_at)
                    VALUES (:title, :slug, :content, :status, :comment_count, :created_at, :updated_at)';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':title' => $post->getTitle(),
                ':slug' => $post->getSlug(),
                ':content' => $post->getContent(),
                ':status' => $post->getStatus(),
                ':comment_count' => $post->getCommentCount(),
                ':created_at' => $this->formatDate($post->getCreatedAt()),
                ':updated_at' => $this->formatNullableDate($post->getUpdatedAt()),
            ]);

            $id = (int) $this->pdo->lastInsertId();
            if ($id <= 0) {
                throw new RuntimeException('Failed to retrieve inserted post ID.');
            }

            $post->setId($id);
            $this->syncCategories($id, $post->getCategories());

            $this->pdo->commit();

            return $id;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function update(Post $post): void
    {
        $id = $post->getId();
        if ($id === null) {
            throw new InvalidArgumentException('更新には Post ID が必要です。');
        }

        $updatedAt = $post->getUpdatedAt() ?? new DateTimeImmutable();
        $post->setUpdatedAt($updatedAt);

        try {
            $this->pdo->beginTransaction();

            $sql = 'UPDATE posts
                    SET title = :title,
                        slug = :slug,
                        content = :content,
                        status = :status,
                        comment_count = :comment_count,
                        updated_at = :updated_at
                    WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':title' => $post->getTitle(),
                ':slug' => $post->getSlug(),
                ':content' => $post->getContent(),
                ':status' => $post->getStatus(),
                ':comment_count' => $post->getCommentCount(),
                ':updated_at' => $this->formatDate($updatedAt),
                ':id' => $id,
            ]);

            if ($stmt->rowCount() === 0) {
                $this->assertPostExists($id);
            }

            $this->syncCategories($id, $post->getCategories());
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM posts WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    private function baseSelectSql(): string
    {
        return 'SELECT p.id, p.title, p.slug, p.content, p.status, p.comment_count, p.created_at, p.updated_at, ' .
            'c.id AS category_id, c.name AS category_name, c.slug AS category_slug ' .
            'FROM posts p ' .
            'LEFT JOIN post_categories pc ON pc.post_id = p.id ' .
            'LEFT JOIN categories c ON c.id = pc.category_id';
    }

    private function baseIdSelectSql(): string
    {
        return 'SELECT DISTINCT p.id FROM posts p ' .
            'LEFT JOIN post_categories pc ON pc.post_id = p.id ' .
            'LEFT JOIN categories c ON c.id = pc.category_id';
    }

    private function baseCountSql(): string
    {
        return 'SELECT COUNT(DISTINCT p.id) FROM posts p ' .
            'LEFT JOIN post_categories pc ON pc.post_id = p.id ' .
            'LEFT JOIN categories c ON c.id = pc.category_id';
    }

    /**
     * @param array<string, scalar> $params
     */
    private function bindParams(PDOStatement $stmt, array $params): void
    {
        foreach ($params as $placeholder => $value) {
            $type = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($placeholder, $value, $type);
        }
    }

    /**
     * @param array<int, int|string> $values
     * @return array{placeholders: string, params: array<string, int|string>}
     */
    private function prepareInClause(array $values, string $prefix): array
    {
        $placeholders = [];
        $params = [];

        foreach (array_values($values) as $index => $value) {
            $name = sprintf(':%s_%d', $prefix, $index);
            $placeholders[] = $name;
            $params[$name] = $value;
        }

        return ['placeholders' => implode(',', $placeholders), 'params' => $params];
    }

    private function buildWhereClause(PostFilter $filter, array &$params): string
    {
        $conditions = [];

        if ($filter->getCategoryIds() !== []) {
            $in = $this->prepareInClause($filter->getCategoryIds(), 'catId');
            $conditions[] = sprintf('pc.category_id IN (%s)', $in['placeholders']);
            $params += $in['params'];
        }

        if ($filter->getCategorySlugs() !== []) {
            $in = $this->prepareInClause($filter->getCategorySlugs(), 'catSlug');
            $conditions[] = sprintf('c.slug IN (%s)', $in['placeholders']);
            $params += $in['params'];
        }

        if ($filter->getStatus() !== null) {
            $conditions[] = 'p.status = :status';
            $params[':status'] = $filter->getStatus();
        }

        if ($filter->getQuery() !== null) {
            $conditions[] = '(p.title LIKE :keyword OR p.content LIKE :keyword)';
            $params[':keyword'] = '%' . $filter->getQuery() . '%';
        }

        return $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
    }

    /**
     * @param string $whereClause
     * @param array<string, scalar> $params
     */
    private function countByFilter(string $whereClause, array $params): int
    {
        $sql = $this->baseCountSql() . $whereClause;
        $stmt = $this->pdo->prepare($sql);
        $this->bindParams($stmt, $params);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param int[] $ids
     * @return Post[]
     */
    private function fetchPostsInOrder(array $ids): array
    {
        $postsById = $this->fetchPostsByIds($ids);
        $ordered = [];

        foreach ($ids as $id) {
            if (isset($postsById[$id])) {
                $ordered[] = $postsById[$id];
            }
        }

        return $ordered;
    }

    /**
     * @param int[] $ids
     * @return array<int, Post>
     */
    private function fetchPostsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $in = $this->prepareInClause($ids, 'postId');
        $sql = $this->baseSelectSql() . sprintf(' WHERE p.id IN (%s)', $in['placeholders']);

        $stmt = $this->pdo->prepare($sql);
        $this->bindParams($stmt, $in['params']);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $posts = $this->mapRowsToPosts($rows);
        $indexed = [];

        foreach ($posts as $post) {
            $postId = $post->getId();
            if ($postId !== null) {
                $indexed[$postId] = $post;
            }
        }

        return $indexed;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return Post[]
     */
    private function mapRowsToPosts(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $postRows = [];
        $categoryMap = [];

        foreach ($rows as $row) {
            $postId = isset($row['id']) ? (int) $row['id'] : null;
            if ($postId === null) {
                continue;
            }

            if (!isset($postRows[$postId])) {
                $postRows[$postId] = $row;
                $categoryMap[$postId] = [];
            }

            $category = $this->hydrateCategory($row);
            if ($category !== null) {
                $key = $category->getId() ?? $category->getSlug();
                $categoryMap[$postId][$key] = $category;
            }
        }

        $posts = [];
        foreach ($postRows as $postId => $row) {
            $categories = array_values($categoryMap[$postId]);
            $posts[] = $this->hydratePost($row, $categories);
        }

        return $posts;
    }

    /**
     * @param array<string, mixed> $row
     * @param Category[] $categories
     */
    private function hydratePost(array $row, array $categories): Post
    {
        $slug = (string) ($row['slug'] ?? '');
        if ($slug === '') {
            throw new RuntimeException('posts.slug is required to hydrate Post.');
        }

        $createdAt = $this->createDateTime($row['created_at'] ?? null, 'created_at');
        $updatedAt = $this->createNullableDateTime($row['updated_at'] ?? null);
        $id = isset($row['id']) ? (int) $row['id'] : null;
        $status = (string) ($row['status'] ?? 'published');
        $commentCount = isset($row['comment_count']) ? (int) $row['comment_count'] : 0;

        return new Post(
            (string) ($row['title'] ?? ''),
            (string) ($row['content'] ?? ''),
            $slug,
            $id,
            $createdAt,
            $updatedAt,
            $categories,
            $status,
            $commentCount
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateCategory(array $row): ?Category
    {
        if (!array_key_exists('category_id', $row) || $row['category_id'] === null) {
            return null;
        }

        $id = (int) $row['category_id'];
        $name = (string) ($row['category_name'] ?? '');
        $slug = (string) ($row['category_slug'] ?? '');

        if ($slug === '') {
            $slug = $name !== '' ? $this->slugify($name) : 'category-' . $id;
        }

        return new Category($name !== '' ? $name : sprintf('Category %d', $id), $slug, $id);
    }

    private function createDateTime(?string $value, string $column): DateTimeImmutable
    {
        if ($value === null || $value === '') {
            throw new RuntimeException(sprintf('%s column is required to hydrate Post.', $column));
        }

        return new DateTimeImmutable($value);
    }

    private function createNullableDateTime(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new DateTimeImmutable($value);
    }

    private function formatDate(DateTimeImmutable $value): string
    {
        return $value->format(self::DATE_TIME_FORMAT);
    }

    private function formatNullableDate(?DateTimeImmutable $value): ?string
    {
        return $value?->format(self::DATE_TIME_FORMAT);
    }

    /**
     * @param Category[] $categories
     */
    private function syncCategories(int $postId, array $categories): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM post_categories WHERE post_id = :post_id');
        $stmt->execute([':post_id' => $postId]);

        if ($categories === []) {
            return;
        }

        $insert = $this->pdo->prepare('INSERT INTO post_categories (post_id, category_id) VALUES (:post_id, :category_id)');
        foreach ($categories as $category) {
            $categoryId = $category->getId();
            if ($categoryId === null) {
                throw new InvalidArgumentException('カテゴリを関連付けるには ID が必要です。');
            }

            $insert->execute([
                ':post_id' => $postId,
                ':category_id' => $categoryId,
            ]);
        }
    }

    private function assertPostExists(int $id): void
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM posts WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->fetchColumn() === false) {
            throw new RuntimeException(sprintf('Post not found: %d', $id));
        }
    }

    private function mapSortToColumn(string $sort): string
    {
        return match ($sort) {
            'commentCount' => 'p.comment_count',
            'updatedAt' => 'p.updated_at',
            default => 'p.created_at',
        };
    }

    private function slugify(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/', '-', $normalized);

        if ($normalized === null) {
            $normalized = '';
        }

        $normalized = trim($normalized, '-');

        return $normalized !== '' ? $normalized : 'category';
    }
}

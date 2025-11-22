<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\CommentMapper;
use App\Mapper\PostFilter;
use App\Mapper\PostMapper;
use App\Mapper\UserMapper;
use App\Model\Category;
use App\Model\Comment;
use App\Model\Post;
use DateTimeImmutable;
use JsonException;
use PDO;

final class PostController
{
    private array $authorCache = [];

    public function __construct(
        private PostMapper $posts,
        private CommentMapper $comments,
        private AuthController $auth,
        private UserMapper $users,
        private PDO $pdo,
    ) {
    }

    public function index(Request $request): Response
    {
        $filter = $this->buildPostFilter($request);
        $result = $this->posts->findAll($filter);

        $data = array_map(fn (Post $post): array => $this->formatPost($post), $result['posts']);

        $meta = [
            'page' => $filter->getPage(),
            'perPage' => $filter->getPerPage(),
            'total' => $result['total'],
            'totalPages' => (int) ceil(max(1, $result['total']) / $filter->getPerPage()),
        ];

        return Response::json(['data' => $data, 'meta' => $meta]);
    }

    public function show(Request $request): Response
    {
        $postId = $this->extractResourceId($request, 'post');
        if ($postId === null) {
            return Response::json(['error' => 'invalid_post_id'], 400);
        }

        $post = $this->posts->findById($postId);
        if ($post === null) {
            return Response::json(['error' => 'post_not_found'], 404);
        }

        return Response::json($this->formatPost($post));
    }

    public function store(Request $request): Response
    {
        $user = $this->auth->requireUser();
        if ($user === null) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $payload = $this->decodeJsonBody($request, $errorResponse);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $guard = $this->guardPayloadKeys($payload, ['title', 'body', 'categories']);
        if ($guard !== null) {
            return $guard;
        }

        $validation = $this->validatePostPayload($payload, false);
        if ($validation['errors'] !== []) {
            return Response::json(['errors' => $validation['errors']], 422);
        }

        $categoriesResult = $this->resolveCategories($validation['categories']);
        if ($categoriesResult['errors'] !== []) {
            return Response::json(['errors' => $categoriesResult['errors']], 422);
        }

        $slug = $this->generateUniqueSlug($validation['title']);

        $post = new Post(
            $validation['title'],
            $validation['body'],
            $slug,
            $user->getId(),
            categories: $categoriesResult['categories'],
            status: $validation['status'] ?? 'published',
            commentCount: 0,
        );

        $this->posts->insert($post);

        return Response::json($this->formatPost($post), 201)
            ->withHeader('Location', sprintf('/posts/%d', $post->getId()));
    }

    public function update(Request $request): Response
    {
        $postId = $this->extractResourceId($request, 'post');
        if ($postId === null) {
            return Response::json(['error' => 'invalid_post_id'], 400);
        }

        $existing = $this->posts->findById($postId);
        if ($existing === null) {
            return Response::json(['error' => 'post_not_found'], 404);
        }

        $user = $this->auth->requireUser();
        if ($user === null) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $payload = $this->decodeJsonBody($request, $errorResponse);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        if ($payload === []) {
            return Response::json(['error' => 'empty_payload'], 400);
        }

        $guard = $this->guardPayloadKeys($payload, ['title', 'body', 'categories', 'status']);
        if ($guard !== null) {
            return $guard;
        }

        $validation = $this->validatePostPayload($payload, true);
        if ($validation['errors'] !== []) {
            return Response::json(['errors' => $validation['errors']], 422);
        }

        $categories = $existing->getCategories();
        if ($validation['categories'] !== null) {
            $categoriesResult = $this->resolveCategories($validation['categories']);
            if ($categoriesResult['errors'] !== []) {
                return Response::json(['errors' => $categoriesResult['errors']], 422);
            }
            $categories = $categoriesResult['categories'];
        }

        $title = $validation['title'] ?? $existing->getTitle();
        $body = $validation['body'] ?? $existing->getContent();
        $status = $validation['status'] ?? $existing->getStatus();

        $updated = new Post(
            $title,
            $body,
            $existing->getSlug(),
            $existing->getAuthorId(),
            $existing->getId(),
            $existing->getCreatedAt(),
            new DateTimeImmutable(),
            $categories,
            $status,
            $existing->getCommentCount()
        );

        $this->posts->update($updated);

        return Response::json($this->formatPost($updated));
    }

    public function destroy(Request $request): Response
    {
        $postId = $this->extractResourceId($request, 'post');
        if ($postId === null) {
            return Response::json(['error' => 'invalid_post_id'], 400);
        }

        $post = $this->posts->findById($postId);
        if ($post === null) {
            return Response::json(['error' => 'post_not_found'], 404);
        }

        $this->posts->delete($postId);

        return new Response(status: 204);
    }

    public function comments(Request $request): Response
    {
        $postId = $this->extractResourceId($request, 'post');
        if ($postId === null) {
            return Response::json(['error' => 'invalid_post_id'], 400);
        }

        if ($this->posts->findById($postId) === null) {
            return Response::json(['error' => 'post_not_found'], 404);
        }

        $page = $this->intFromQuery($request, 'page', 1, 1);
        $perPage = $this->intFromQuery($request, 'perPage', 20, 1, 50);

        $sortColumn = $this->normalizeCommentSort($request->query['sort'] ?? 'createdAt');
        $order = $this->normalizeOrder($request->query['order'] ?? 'desc');

        $result = $this->comments->findByPostId(
            $postId,
            $perPage,
            ($page - 1) * $perPage,
            $sortColumn,
            strtoupper($order)
        );

        $data = array_map(fn (Comment $comment): array => $this->formatComment($comment), $result['comments']);
        $total = $result['total'];

        return Response::json([
            'data' => $data,
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => (int) ceil(max(1, $total) / $perPage),
            ],
        ]);
    }

    public function storeComment(Request $request): Response
    {
        $postId = $this->extractResourceId($request, 'post');
        if ($postId === null) {
            return Response::json(['error' => 'invalid_post_id'], 400);
        }

        $post = $this->posts->findById($postId);
        if ($post === null) {
            return Response::json(['error' => 'post_not_found'], 404);
        }

        $user = $this->auth->requireUser();
        if ($user === null) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $payload = $this->decodeJsonBody($request, $errorResponse);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $guard = $this->guardPayloadKeys($payload, ['body']);
        if ($guard !== null) {
            return $guard;
        }

        $body = isset($payload['body']) ? trim((string) $payload['body']) : '';
        if ($body === '' || mb_strlen($body, 'UTF-8') > 2000) {
            return Response::json(['errors' => ['body' => 'コメントは1〜2000文字で入力してください。']], 422);
        }

        $comment = new Comment(
            $postId,
            $user->getId(),
            $body
        );
        $this->comments->save($comment);
        $this->incrementCommentCount($postId, 1);

        return Response::json($this->formatComment($comment), 201)
            ->withHeader('Location', sprintf('/comments/%d', $comment->getId()));
    }

    public function deleteComment(Request $request): Response
    {
        $commentId = $this->extractResourceId($request, 'comment');
        if ($commentId === null) {
            return Response::json(['error' => 'invalid_comment_id'], 400);
        }

        $stmt = $this->pdo->prepare('SELECT id, post_id FROM comments WHERE id = :id');
        $stmt->execute([':id' => $commentId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return Response::json(['error' => 'comment_not_found'], 404);
        }

        $this->pdo->prepare('DELETE FROM comments WHERE id = :id')->execute([':id' => $commentId]);
        $this->incrementCommentCount((int) $row['post_id'], -1);

        return new Response(status: 204);
    }

    private function buildPostFilter(Request $request): PostFilter
    {
        $page = $this->intFromQuery($request, 'page', 1, 1);
        $perPage = $this->intFromQuery($request, 'perPage', 20, 1, 50);
        $q = $this->stringOrNull($request->query['q'] ?? null);
        $categorySlugs = $this->normalizeStringArray($request->query['category'] ?? []);

        $sort = $this->normalizeSort($request->query['sort'] ?? 'createdAt');
        $order = $this->normalizeOrder($request->query['order'] ?? 'desc');

        $status = null;
        $requestedStatus = $this->stringOrNull($request->query['status'] ?? null);
        if (!$this->auth->isAdmin()) {
            $status = 'published';
        } elseif ($requestedStatus !== null) {
            $status = $requestedStatus;
        }

        return new PostFilter(
            $page,
            $perPage,
            $q,
            categorySlugs: $categorySlugs,
            status: $status,
            sort: $sort,
            order: $order,
        );
    }

    private function normalizeSort(?string $sort): string
    {
        return match ($sort) {
            'commentCount' => 'commentCount',
            'updatedAt' => 'updatedAt',
            default => 'createdAt',
        };
    }

    private function normalizeOrder(?string $order): string
    {
        $normalized = strtolower((string) $order);
        return $normalized === 'asc' ? 'asc' : 'desc';
    }

    private function normalizeCommentSort(?string $sort): string
    {
        $normalized = strtolower(trim((string) $sort));
        if ($normalized === 'created_at' || $normalized === 'createdat') {
            return 'created_at';
        }

        return 'created_at';
    }

    private function intFromQuery(Request $request, string $key, int $default, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
    {
        $value = $request->query[$key] ?? $default;
        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        if ($intValue === false) {
            return $default;
        }

        return max($min, min($max, $intValue));
    }

    private function stringOrNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeStringArray(mixed $value): array
    {
        $values = [];
        if (is_string($value)) {
            $values[] = $value;
        } elseif (is_array($value)) {
            $values = $value;
        }

        $result = [];
        foreach ($values as $item) {
            $slug = trim((string) $item);
            if ($slug !== '') {
                $result[] = $slug;
            }
        }

        return array_values(array_unique($result));
    }

    private function guardPayloadKeys(array $payload, array $allowedKeys): ?Response
    {
        if ($payload === []) {
            return null;
        }

        $allowed = array_fill_keys($allowedKeys, true);
        $unexpected = [];
        foreach (array_keys($payload) as $key) {
            if (!array_key_exists($key, $allowed)) {
                $unexpected[] = $key;
            }
        }

        if ($unexpected !== []) {
            return Response::json([
                'error' => 'invalid_fields',
                'fields' => $unexpected,
            ], 400);
        }

        return null;
    }

    private function decodeJsonBody(Request $request, ?Response &$error): array
    {
        $error = null;
        if ($request->body !== []) {
            return $request->body;
        }

        $raw = $request->rawBody;
        if ($raw === null) {
            return [];
        }

        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        try {
            $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $error = Response::json(['error' => 'invalid_json'], 400);
            return [];
        }

        if (!is_array($data)) {
            $error = Response::json(['error' => 'invalid_json'], 400);
            return [];
        }

        return $data;
    }

    private function validatePostPayload(array $payload, bool $isUpdate): array
    {
        $errors = [];

        $title = null;
        if (array_key_exists('title', $payload) || !$isUpdate) {
            $title = isset($payload['title']) ? trim((string) $payload['title']) : '';
            if ($title === '' || mb_strlen($title, 'UTF-8') > 255) {
                $errors['title'] = 'タイトルは1〜255文字で入力してください。';
            }
        }

        $body = null;
        if (array_key_exists('body', $payload) || !$isUpdate) {
            $body = isset($payload['body']) ? trim((string) $payload['body']) : '';
            if ($body === '' || mb_strlen($body, 'UTF-8') > 10000) {
                $errors['body'] = '本文は1〜10000文字で入力してください。';
            }
        }

        $status = null;
        if (array_key_exists('status', $payload)) {
            $candidate = strtolower(trim((string) $payload['status']));
            if (!in_array($candidate, ['draft', 'published'], true)) {
                $errors['status'] = 'status は draft か published のみ許可されます。';
            } elseif (!$this->auth->isAdmin()) {
                $errors['status'] = 'status を変更できるのは管理者のみです。';
            } else {
                $status = $candidate;
            }
        }

        $categories = null;
        if (array_key_exists('categories', $payload)) {
            if (!is_array($payload['categories'])) {
                $errors['categories'] = 'categories は文字列配列で指定してください。';
            } else {
                $categories = $this->normalizeStringArray($payload['categories']);
                if (count($categories) > 10) {
                    $errors['categories'] = 'カテゴリは最大10件までです。';
                }
            }
        } elseif (!$isUpdate) {
            $categories = [];
        }

        return [
            'errors' => $errors,
            'title' => $title,
            'body' => $body,
            'status' => $status,
            'categories' => $categories,
        ];
    }

    private function resolveCategories(?array $slugs): array
    {
        if ($slugs === null || $slugs === []) {
            return ['categories' => [], 'errors' => []];
        }

        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $stmt = $this->pdo->prepare(sprintf('SELECT id, name, slug FROM categories WHERE slug IN (%s)', $placeholders));
        $stmt->execute($slugs);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $bySlug = [];
        foreach ($rows as $row) {
            $bySlug[$row['slug']] = new Category((string) $row['name'], (string) $row['slug'], (int) $row['id']);
        }

        $missing = array_values(array_diff($slugs, array_keys($bySlug)));
        if ($missing !== []) {
            return ['categories' => [], 'errors' => ['categories' => sprintf('存在しないカテゴリ: %s', implode(', ', $missing))]];
        }

        $ordered = [];
        foreach ($slugs as $slug) {
            $ordered[] = $bySlug[$slug];
        }

        return ['categories' => $ordered, 'errors' => []];
    }

    private function formatPost(Post $post): array
    {
        return [
            'id' => $post->getId(),
            'author' => $this->formatAuthor($post->getAuthorId()),
            'title' => $post->getTitle(),
            'body' => $post->getContent(),
            'status' => $post->getStatus(),
            'commentCount' => $post->getCommentCount(),
            'categories' => array_map(
                fn (Category $category): array => [
                    'id' => $category->getId(),
                    'slug' => $category->getSlug(),
                    'name' => $category->getName(),
                ],
                $post->getCategories()
            ),
            'createdAt' => $post->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $post->getUpdatedAt()?->format(DATE_ATOM),
        ];
    }

    private function formatAuthor(?int $userId): ?array
    {
        if ($userId === null) {
            return null;
        }

        if (array_key_exists($userId, $this->authorCache)) {
            return $this->authorCache[$userId];
        }

        $user = $this->users->find($userId);
        if ($user === null || $user->isDeleted()) {
            return $this->authorCache[$userId] = null;
        }

        return $this->authorCache[$userId] = [
            'id' => $user->getId(),
            'name' => $user->getName(),
        ];
    }

    private function formatComment(Comment $comment): array
    {
        return [
            'id' => $comment->getId(),
            'postId' => $comment->getPostId(),
            'author' => $this->formatAuthor($comment->getUserId()),
            'body' => $comment->getContent(),
            'createdAt' => $comment->getCreatedAt()->format(DATE_ATOM),
        ];
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? ''));
        if ($base === '') {
            $base = 'post';
        }

        $candidate = trim($base, '-');
        if ($candidate === '') {
            $candidate = 'post';
        }

        $suffix = 1;
        while ($this->slugExists($candidate)) {
            $suffix++;
            $candidate = sprintf('%s-%d', $base, $suffix);
        }

        return $candidate;
    }

    private function slugExists(string $slug): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM posts WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);
        return (bool) $stmt->fetchColumn();
    }

    private function incrementCommentCount(int $postId, int $delta): void
    {
        $stmt = $this->pdo->prepare('UPDATE posts SET comment_count = GREATEST(0, comment_count + :delta) WHERE id = :id');
        $stmt->execute([':delta' => $delta, ':id' => $postId]);
    }

    private function extractResourceId(Request $request, string $resource): ?int
    {
        $pattern = match ($resource) {
            'comment' => '#^/comments/(\d+)$#',
            default => '#^/posts/(\d+)(?:/comments)?$#',
        };

        if (preg_match($pattern, $request->path, $matches) === 1) {
            return (int) $matches[1];
        }

        $key = $resource === 'comment' ? 'commentId' : 'id';
        $raw = $request->query[$key] ?? null;
        if ($raw === null) {
            return null;
        }

        $intVal = filter_var($raw, FILTER_VALIDATE_INT);
        return $intVal === false || $intVal <= 0 ? null : $intVal;
    }
}

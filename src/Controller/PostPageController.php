<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\PostFilter;
use App\Mapper\PostMapper;
use App\Service\TemplateRenderer;

final class PostPageController
{
    public function __construct(
        private PostMapper $posts,
        private TemplateRenderer $views,
        private AuthController $auth,
    ) {
    }

    public function index(Request $request): Response
    {
        $page = $this->intFromQuery($request, 'page', 1, 1, 50);
        $perPage = $this->intFromQuery($request, 'perPage', 10, 1, 50);
        $status = $this->auth->isAdmin() ? null : 'published';

        $filter = new PostFilter(
            page: $page,
            perPage: $perPage,
            query: null,
            status: $status,
            sort: 'createdAt',
            order: 'desc'
        );

        $result = $this->posts->findAll($filter);
        $total = $result['total'];
        $totalPages = (int) ceil(max(1, $total) / $filter->getPerPage());

        $html = $this->views->render('posts/index.php', [
            'posts' => $result['posts'],
            'page' => $filter->getPage(),
            'perPage' => $filter->getPerPage(),
            'total' => $total,
            'totalPages' => $totalPages,
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function search(Request $request): Response
    {
        $page = $this->intFromQuery($request, 'page', 1, 1, 50);
        $perPage = $this->intFromQuery($request, 'perPage', 10, 1, 50);
        $query = $this->stringOrNull($request->query['q'] ?? null);

        $status = $this->auth->isAdmin() ? null : 'published';

        $filter = new PostFilter(
            page: $page,
            perPage: $perPage,
            query: $query,
            status: $status,
            sort: 'createdAt',
            order: 'desc'
        );

        $result = $this->posts->findAll($filter);
        $total = $result['total'];
        $totalPages = (int) ceil(max(1, $total) / $filter->getPerPage());

        $html = $this->views->render('posts/search.php', [
            'query' => $query ?? '',
            'posts' => $result['posts'],
            'page' => $filter->getPage(),
            'perPage' => $filter->getPerPage(),
            'total' => $total,
            'totalPages' => $totalPages,
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function show(Request $request): Response
    {
        $postId = $this->extractPostId($request);
        if ($postId === null) {
            return new Response(404, '記事が見つかりません', ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $post = $this->posts->findById($postId);
        if ($post === null) {
            return new Response(404, '記事が見つかりません', ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        if (!$this->auth->isAdmin() && $post->getStatus() !== 'published') {
            return new Response(404, '記事が見つかりません', ['Content-Type' => 'text/plain; charset=UTF-8']);
        }

        $html = $this->views->render('posts/show.php', [
            'post' => $post,
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function intFromQuery(Request $request, string $key, int $default, int $min = 1, int $max = 50): int
    {
        $raw = $request->query[$key] ?? $default;
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === false) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function extractPostId(Request $request): ?int
    {
        if (preg_match('#^/blog/(\d+)$#', $request->path, $matches) === 1) {
            return (int) $matches[1];
        }

        $raw = $request->query['id'] ?? null;
        if ($raw === null) {
            return null;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);
        return $value === false || $value <= 0 ? null : (int) $value;
    }
}

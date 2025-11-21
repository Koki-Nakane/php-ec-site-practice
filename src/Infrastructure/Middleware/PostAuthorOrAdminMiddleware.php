<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Controller\AuthController;
use App\Http\Request;
use App\Http\Response;
use App\Mapper\PostMapper;
use App\Model\Post;

final class PostAuthorOrAdminMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthController $auth,
        private PostMapper $posts,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $postId = $this->resolvePostId($request);
        if ($postId === null) {
            return Response::json(['error' => 'invalid_post_id'], 400);
        }

        $user = $this->auth->requireUser();
        if ($user === null) {
            // AuthMiddleware で弾かれる前提だが、念のため防御的に処理
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $post = $this->posts->findById($postId);
        if ($post === null) {
            return Response::json(['error' => 'post_not_found'], 404);
        }

        if ($this->isOwner($post, $user->getId())) {
            return $next($request);
        }

        if ($this->auth->isAdmin()) {
            return $next($request);
        }

        return Response::json(['error' => 'forbidden'], 403);
    }

    private function resolvePostId(Request $request): ?int
    {
        if (preg_match('#^/posts/(\d+)(?:/.*)?$#', $request->path, $matches) === 1) {
            return (int) $matches[1];
        }

        $postId = $request->query['post_id'] ?? $request->body['post_id'] ?? null;
        if ($postId !== null) {
            $intVal = (int) $postId;
            return $intVal > 0 ? $intVal : null;
        }

        return null;
    }

    private function isOwner(Post $post, ?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        $authorId = $post->getAuthorId();

        return $authorId !== null && $authorId === $userId;
    }
}

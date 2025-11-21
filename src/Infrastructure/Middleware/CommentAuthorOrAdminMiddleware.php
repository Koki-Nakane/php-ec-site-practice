<?php

declare(strict_types=1);

namespace App\Infrastructure\Middleware;

use App\Contracts\MiddlewareInterface;
use App\Controller\AuthController;
use App\Http\Request;
use App\Http\Response;
use App\Model\User;
use PDO;
use PDOException;

final class CommentAuthorOrAdminMiddleware implements MiddlewareInterface
{
    public function __construct(
        private AuthController $auth,
        private PDO $pdo,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $commentId = $this->resolveCommentId($request);
        if ($commentId === null) {
            return Response::json(['error' => 'invalid_comment_id'], 400);
        }

        $user = $this->auth->requireUser();
        if ($user === null) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $comment = $this->fetchComment($commentId);
        if ($comment === null) {
            return Response::json(['error' => 'comment_not_found'], 404);
        }

        if ($user->isAdmin() || $this->isOwner($comment, $user)) {
            return $next($request);
        }

        return Response::json(['error' => 'forbidden'], 403);
    }

    private function resolveCommentId(Request $request): ?int
    {
        if (preg_match('#^/comments/(\d+)$#', $request->path, $matches) === 1) {
            return (int) $matches[1];
        }

        $raw = $request->query['commentId'] ?? null;
        if ($raw === null) {
            return null;
        }

        $intVal = filter_var($raw, FILTER_VALIDATE_INT);
        return $intVal === false || $intVal <= 0 ? null : $intVal;
    }

    private function fetchComment(int $commentId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT id, post_id, user_id FROM comments WHERE id = :id');
            $stmt->execute([':id' => $commentId]);
        } catch (PDOException) {
            return null;
        }

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        return [
            'id' => (int) $row['id'],
            'post_id' => (int) $row['post_id'],
            'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
        ];
    }

    private function isOwner(array $comment, User $user): bool
    {
        return isset($comment['user_id']) && (int) $comment['user_id'] === $user->getId();
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\ProductMapper;
use App\Service\CsrfTokenManager;
use App\Service\ReviewService;
use App\Service\TemplateRenderer;
use JsonException;

final class ProductReviewController
{
    public function __construct(
        private ProductMapper $products,
        private ReviewService $reviews,
        private TemplateRenderer $views,
        private CsrfTokenManager $csrfTokens,
        private AuthController $auth,
    ) {
    }

    public function show(Request $request): Response
    {
        $productId = $this->extractId($request->path, '#^/products/(\d+)$#');
        if ($productId === null) {
            return new Response(404, 'Not Found');
        }

        $product = $this->products->findActive($productId);
        if ($product === null) {
            return new Response(404, 'Not Found');
        }

        $page = $this->intFromQuery($request, 'page', 1, 1, 50);
        $perPage = $this->intFromQuery($request, 'perPage', 10, 1, 50);
        $reviewData = $this->reviews->listProductReviews($productId, $page, $perPage);

        $user = $this->auth->requireUser();
        $eligibility = null;
        $userReview = null;
        if ($user !== null) {
            $eligibility = $this->reviews->checkEligibility($productId, $user->getId());
            $userReview = $eligibility['review'];
        }

        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);

        $html = $this->views->render('products/show.php', [
            'product' => $product,
            'reviews' => $reviewData,
            'user' => $user,
            'eligibility' => $eligibility['status'] ?? null,
            'userReview' => $userReview,
            'reviewToken' => $this->csrfTokens->issue('review_form_' . $productId),
            'deleteToken' => $this->csrfTokens->issue('review_delete_' . $productId),
            'flash' => $flash,
            'isAdmin' => $this->auth->isAdmin(),
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function storeFromForm(Request $request): Response
    {
        $productId = $this->extractId($request->path, '#^/products/(\d+)/reviews/form$#');
        if ($productId === null) {
            return new Response(404, 'Not Found');
        }

        $product = $this->products->findActive($productId);
        if ($product === null) {
            return new Response(404, 'Not Found');
        }

        $user = $this->auth->requireUser();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $title = (string) ($request->body['title'] ?? '');
        $comment = (string) ($request->body['comment'] ?? '');
        $rating = (int) ($request->body['rating'] ?? 0);

        $result = $this->reviews->createReview($productId, $user->getId(), $title, $comment, $rating);
        if ($result['ok']) {
            $this->pushFlash('レビューを投稿しました。');
        } else {
            $this->pushFlash(current($result['errors']) ?: 'レビューを投稿できませんでした。');
        }

        return Response::redirect(sprintf('/products/%d', $product->getId()));
    }

    public function deleteFromForm(Request $request): Response
    {
        $reviewId = $this->extractId($request->path, '#^/reviews/(\d+)/delete$#');
        if ($reviewId === null) {
            return new Response(404, 'Not Found');
        }

        $user = $this->auth->requireUser();
        if ($user === null) {
            return Response::redirect('/login');
        }

        $review = $this->reviews->getReview($reviewId);
        if ($review === null) {
            $this->pushFlash('レビューが見つかりませんでした。');
            return Response::redirect('/');
        }

        $result = $this->reviews->deleteReview($reviewId, $user->getId(), $this->auth->isAdmin());
        if ($result['ok']) {
            $this->pushFlash('レビューを削除しました。');
        } else {
            $this->pushFlash('レビューを削除できませんでした。');
        }

        return Response::redirect(sprintf('/products/%d', $review->getProductId()));
    }

    public function indexApi(Request $request): Response
    {
        $productId = $this->extractId($request->path, '#^/products/(\d+)/reviews$#');
        if ($productId === null) {
            return Response::json(['error' => 'invalid_product_id'], 400);
        }

        if ($this->products->findActive($productId) === null) {
            return Response::json(['error' => 'product_not_found'], 404);
        }

        $page = $this->intFromQuery($request, 'page', 1, 1, 100);
        $perPage = $this->intFromQuery($request, 'perPage', 20, 1, 100);
        $data = $this->reviews->listProductReviews($productId, $page, $perPage);

        $items = array_map(
            fn (array $item): array => $this->reviews->formatReviewForApi($item['review'], $item['authorName']),
            $data['items']
        );

        return Response::json([
            'data' => $items,
            'meta' => [
                'total' => $data['total'],
                'averageRating' => $data['average'],
                'page' => $data['page'],
                'perPage' => $data['perPage'],
                'totalPages' => $data['totalPages'],
            ],
        ]);
    }

    public function storeApi(Request $request): Response
    {
        $productId = $this->extractId($request->path, '#^/products/(\d+)/reviews$#');
        if ($productId === null) {
            return Response::json(['error' => 'invalid_product_id'], 400);
        }

        if ($this->products->findActive($productId) === null) {
            return Response::json(['error' => 'product_not_found'], 404);
        }

        $user = $this->auth->requireUser();
        if ($user === null) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $payload = $this->decodeJson($request, $errorResponse);
        if ($errorResponse !== null) {
            return $errorResponse;
        }

        $title = (string) ($payload['title'] ?? '');
        $comment = (string) ($payload['comment'] ?? '');
        $rating = isset($payload['rating']) ? (int) $payload['rating'] : 0;

        $result = $this->reviews->createReview($productId, $user->getId(), $title, $comment, $rating);
        if (!$result['ok']) {
            return Response::json(['errors' => $result['errors']], 422);
        }

        $review = $result['review'];
        $authorName = $user->getName();

        return Response::json($this->reviews->formatReviewForApi($review, $authorName), 201)
            ->withHeader('Location', sprintf('/products/%d/reviews', $productId));
    }

    public function deleteApi(Request $request): Response
    {
        $reviewId = $this->extractId($request->path, '#^/reviews/(\d+)$#');
        if ($reviewId === null) {
            return Response::json(['error' => 'invalid_review_id'], 400);
        }

        $user = $this->auth->requireUser();
        if ($user === null) {
            return Response::json(['error' => 'unauthorized'], 401);
        }

        $result = $this->reviews->deleteReview($reviewId, $user->getId(), $this->auth->isAdmin());
        if (!$result['ok']) {
            $code = match ($result['error'] ?? 'not_found') {
                'forbidden' => 403,
                'not_found', 'already_deleted' => 404,
                default => 400,
            };
            return Response::json(['error' => $result['error'] ?? 'unknown_error'], $code);
        }

        return new Response(status: 204);
    }

    private function extractId(string $path, string $pattern): ?int
    {
        if (preg_match($pattern, $path, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }

    private function intFromQuery(Request $request, string $key, int $default, int $min, int $max): int
    {
        $value = $request->query[$key] ?? $default;
        $intValue = filter_var($value, FILTER_VALIDATE_INT);
        if ($intValue === false) {
            return $default;
        }

        return max($min, min($max, (int) $intValue));
    }

    /**
     * @return array<string,mixed>
     */
    private function decodeJson(Request $request, ?Response &$error): array
    {
        $error = null;
        $raw = $request->rawBody ?? '';
        if ($raw === '') {
            $error = Response::json(['error' => 'empty_body'], 400);
            return [];
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $error = Response::json(['error' => 'invalid_json', 'message' => $e->getMessage()], 400);
            return [];
        }

        if (!is_array($decoded)) {
            $error = Response::json(['error' => 'invalid_payload'], 400);
            return [];
        }

        return $decoded;
    }

    private function pushFlash(string $message): void
    {
        if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        $_SESSION['flash'][] = $message;
    }
}

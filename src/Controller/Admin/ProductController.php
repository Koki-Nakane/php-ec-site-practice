<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\ProductMapper;
use App\Model\Product;
use App\Service\CsrfTokenManager;
use App\Service\TemplateRenderer;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

final class ProductController
{
    public function __construct(
        private ProductMapper $products,
        private TemplateRenderer $views,
        private CsrfTokenManager $csrfTokens
    ) {
    }

    public function index(Request $request): Response
    {
        $items = $this->products->listForAdmin(null, 100, 0);

        $html = $this->views->render('admin/products/index.php', [
            'products' => $items,
            'flashes' => $this->takeFlashes(),
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function edit(Request $request): Response
    {
        $id = $this->requireProductId($request->query['id'] ?? null);
        if ($id === null) {
            $this->flash('error', '不正な商品IDが指定されました。');
            return Response::redirect('/admin/products', 303);
        }

        $product = $this->products->findIncludingDeleted($id);
        if ($product === null) {
            $this->flash('error', '指定された商品が見つかりません。');
            return Response::redirect('/admin/products', 303);
        }

        $form = $this->takeFormState($id);

        $html = $this->views->render('admin/products/edit.php', [
            'product' => $product,
            'flashes' => $this->takeFlashes(),
            'form' => $form,
            'updateToken' => $this->csrfTokens->issue($this->tokenId('update', $id)),
            'toggleToken' => $this->csrfTokens->issue($this->tokenId('toggle', $id)),
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function update(Request $request): Response
    {
        $id = $this->requireProductId($request->body['id'] ?? null);
        if ($id === null) {
            $this->flash('error', '不正なリクエストです。');
            return Response::redirect('/admin/products', 303);
        }

        $input = $this->sanitizeInput($request->body);
        [$errors, $normalized] = $this->validateInput($input);
        if ($errors !== []) {
            $this->rememberFormState($id, $input, $errors);
            return Response::redirect('/admin/products/edit?id=' . $id, 303);
        }

        $product = $this->products->findIncludingDeleted($id);
        if ($product === null) {
            $this->flash('error', '指定された商品が見つかりません。');
            return Response::redirect('/admin/products', 303);
        }

        try {
            $this->applyChanges($product, $normalized);
            $this->products->save($product);
        } catch (InvalidArgumentException|DomainException $e) {
            $this->flash('error', $e->getMessage());
            $this->rememberFormState($id, $input, [$e->getMessage()]);
            return Response::redirect('/admin/products/edit?id=' . $id, 303);
        }

        $this->flash('success', '商品情報を更新しました。');

        return Response::redirect('/admin/products/edit?id=' . $id, 303);
    }

    public function toggleActive(Request $request): Response
    {
        $id = $this->requireProductId($request->body['id'] ?? null);
        if ($id === null) {
            $this->flash('error', '不正なリクエストです。');
            return Response::redirect('/admin/products', 303);
        }

        $product = $this->products->findIncludingDeleted($id);
        if ($product === null) {
            $this->flash('error', '指定された商品が見つかりません。');
            return Response::redirect('/admin/products', 303);
        }

        $now = new DateTimeImmutable();
        if ($product->isActive()) {
            $this->products->disable($id, $now);
            $this->flash('success', '商品を非公開にしました。');
        } else {
            $this->products->enable($id, $now);
            $this->flash('success', '商品を公開しました。');
        }

        return Response::redirect('/admin/products/edit?id=' . $id, 303);
    }

    private function sanitizeInput(array $body): array
    {
        return [
            'name' => isset($body['name']) ? trim((string) $body['name']) : '',
            'price' => isset($body['price']) ? trim((string) $body['price']) : '',
            'stock' => isset($body['stock']) ? trim((string) $body['stock']) : '',
            'description' => isset($body['description']) ? trim((string) $body['description']) : '',
            'is_active' => $body['is_active'] ?? null,
        ];
    }

    /**
     * @return array{0:array<int,string>,1:array<string,mixed>}
     */
    private function validateInput(array $input): array
    {
        $errors = [];
        $normalized = [];

        if ($input['name'] === '') {
            $errors[] = '商品名は必須です。';
        } elseif (mb_strlen($input['name'], 'UTF-8') > 255) {
            $errors[] = '商品名は255文字以内で入力してください。';
        } else {
            $normalized['name'] = $input['name'];
        }

        $price = filter_var($input['price'], FILTER_VALIDATE_FLOAT);
        if ($price === false || $price < 0) {
            $errors[] = '価格には0以上の数値を入力してください。';
        } else {
            $normalized['price'] = (float) $price;
        }

        $stock = filter_var($input['stock'], FILTER_VALIDATE_INT);
        if ($stock === false || $stock < 0) {
            $errors[] = '在庫数には0以上の整数を入力してください。';
        } else {
            $normalized['stock'] = (int) $stock;
        }

        if (mb_strlen($input['description'], 'UTF-8') > 2000) {
            $errors[] = '商品説明は2000文字以内で入力してください。';
        } else {
            $normalized['description'] = $input['description'];
        }

        $normalized['is_active'] = filter_var($input['is_active'], FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return [$errors, $normalized];
    }

    private function applyChanges(Product $product, array $normalized): void
    {
        $product->rename($normalized['name']);
        $product->changePrice($normalized['price']);
        $product->changeStock($normalized['stock']);
        $product->changeDescription($normalized['description']);

        if ($normalized['is_active'] === true) {
            $product->activate();
        } elseif ($normalized['is_active'] === false) {
            $product->deactivate();
        }
    }

    private function requireProductId(mixed $raw): ?int
    {
        $id = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : $id;
    }

    private function flash(string $type, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['admin_flash'][] = ['type' => $type, 'message' => $message];
    }

    private function takeFlashes(): array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $flashes = $_SESSION['admin_flash'] ?? [];
        unset($_SESSION['admin_flash']);

        return $flashes;
    }

    private function rememberFormState(int $productId, array $input, array $errors): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION['admin_product_form'][$productId] = [
            'input' => $input,
            'errors' => $errors,
        ];
    }

    private function takeFormState(int $productId): ?array
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $form = $_SESSION['admin_product_form'][$productId] ?? null;
        unset($_SESSION['admin_product_form'][$productId]);

        return $form;
    }

    private function tokenId(string $action, int $productId): string
    {
        return sprintf('admin_product_%s_%d', $action, $productId);
    }
}

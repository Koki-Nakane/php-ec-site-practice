<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\ProductMapper;
use App\Model\Cart;
use App\Service\TemplateRenderer;

final class CartController
{
    public function __construct(
        private ProductMapper $products,
        private TemplateRenderer $views,
    ) {
    }

    public function show(Request $request): Response
    {
        $cart = ($_SESSION['cart'] ?? null);
        if (!($cart instanceof Cart)) {
            $cart = new Cart();
        }

        $html = $this->views->render('cart/show.php', [
            'cartItems' => $cart->getItems(),
            'totalPrice' => $cart->getTotalPrice(),
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function add(Request $request): Response
    {
        try {
            $productId = filter_var($request->body['product_id'] ?? null, FILTER_VALIDATE_INT);
            $quantity  = filter_var($request->body['quantity'] ?? null, FILTER_VALIDATE_INT);

            if ($productId === false || $productId <= 0 || $quantity === false || $quantity <= 0) {
                throw new \InvalidArgumentException('無効な商品IDまたは数量です。');
            }

            $product = $this->products->find($productId);
            if ($product === null) {
                throw new \RuntimeException('指定された商品が見つかりません。');
            }

            $cart = ($_SESSION['cart'] ?? null);
            if (!($cart instanceof Cart)) {
                $cart = new Cart();
            }

            $cart->addProduct($product, $quantity);
            $_SESSION['cart'] = $cart;

        } catch (\Throwable $e) {
            $_SESSION['error_message'] = $e->getMessage();
            return Response::redirect('/', 303);
        }

        return Response::redirect('/cart', 303);
    }
}

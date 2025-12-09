<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\ProductMapper;
use App\Service\CsrfTokenManager;
use App\Service\TemplateRenderer;

final class HomeController
{
    public function __construct(
        private ProductMapper $products,
        private CsrfTokenManager $csrfTokens,
        private TemplateRenderer $views,
    ) {
    }

    public function index(Request $request): Response
    {
        $items = $this->products->findAll();
        $isLoggedIn = isset($_SESSION['user_id']);
        $flash = $_SESSION['error_message'] ?? null;
        unset($_SESSION['error_message']);
        $csrfToken = $this->csrfTokens->issue('cart_form');

        $html = $this->views->render('home/index.php', [
            'items' => $items,
            'isLoggedIn' => $isLoggedIn,
            'flash' => $flash,
            'csrfToken' => $csrfToken,
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public function apiProducts(Request $request): Response
    {
        $items = $this->products->findAll();
        $rows = [];
        foreach ($items as $p) {
            $rows[] = [
                'id' => $p->getId(),
                'name' => $p->getName(),
                'price' => $p->getPrice(),
                'stock' => $p->getStock(),
                'description' => $p->getDescription(),
            ];
        }
        return Response::json(['items' => $rows]);
    }
}

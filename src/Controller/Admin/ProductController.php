<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Http\Request;
use App\Http\Response;
use App\Mapper\ProductMapper;
use App\Service\TemplateRenderer;

final class ProductController
{
    public function __construct(
        private ProductMapper $products,
        private TemplateRenderer $views
    ) {
    }

    public function index(Request $request): Response
    {
        $items = $this->products->listForAdmin(null, 100, 0);

        $html = $this->views->render('admin/products/index.php', [
            'products' => $items,
        ]);

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

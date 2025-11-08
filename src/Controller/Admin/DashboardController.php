<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Http\Request;
use App\Http\Response;
use App\Service\TemplateRenderer;

final class DashboardController
{
    public function __construct(private TemplateRenderer $views)
    {
    }

    public function index(Request $request): Response
    {
        $html = $this->views->render('admin/dashboard.php');

        return new Response(200, $html, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}

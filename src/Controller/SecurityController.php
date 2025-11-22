<?php

declare(strict_types=1);

namespace App\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Service\CsrfTokenManager;

final class SecurityController
{
    public function __construct(private CsrfTokenManager $csrfTokens)
    {
    }

    public function csrfToken(Request $request): Response
    {
        $token = $this->csrfTokens->issue('api_' . bin2hex(random_bytes(8)));

        return Response::json(['token' => $token], 200)
            ->withHeader('Cache-Control', 'no-store');
    }
}

<?php

declare(strict_types=1);

namespace App\Controller;

use App\Exception\ApiException;
use App\Http\Request;
use App\Http\Response;
use App\Service\ZipcloudClient;

final class PostalCodeController
{
    public function __construct(private ZipcloudClient $zipcloud)
    {
    }

    public function lookup(Request $request): Response
    {
        $postalCode = (string) ($request->query['postal_code'] ?? '');

        try {
            $result = $this->zipcloud->lookup($postalCode);
            return Response::json([
                'prefecture' => $result['prefecture'],
                'city' => $result['city'],
                'town' => $result['town'],
            ])->withHeader('Cache-Control', 'no-store');
        } catch (ApiException $e) {
            return Response::json(['error' => $e->getMessage()], $e->getStatusCode());
        } catch (\Throwable $e) {
            return Response::json(['error' => 'internal_server_error'], 500);
        }
    }
}

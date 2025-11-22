<?php

declare(strict_types=1);

namespace App\Http;

final class Request
{
    /** @param array<string,mixed> $attributes */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $headers = [],
        public readonly array $attributes = [],
        public readonly ?string $rawBody = null,
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path   = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

        // Build headers from $_SERVER
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
                $headers[$name] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['Content-Type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        /** @var array<string,mixed> $query */
        $query = $_GET;
        /** @var array<string,mixed> $body */
        $body = $_POST;

        $rawBody = file_get_contents('php://input');
        if ($rawBody === false) {
            $rawBody = null;
        }

        return new self(
            method: $method,
            path: $path,
            query: $query,
            body: $body,
            headers: $headers,
            rawBody: $rawBody,
        );
    }
}

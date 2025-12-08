<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Exception\ApiException;
use App\Service\ZipcloudClient;
use PHPUnit\Framework\TestCase;

final class ZipcloudClientTest extends TestCase
{
    public function testLookupParsesResult(): void
    {
        $client = new ZipcloudClient('https://example.test/api/search', 3, function (string $url): string {
            $this->assertStringContainsString('zipcode=1234567', $url);
            return json_encode([
                'status' => 200,
                'results' => [[
                    'address1' => '東京都',
                    'address2' => '千代田区',
                    'address3' => '千代田',
                ]],
                'message' => null,
            ], JSON_UNESCAPED_UNICODE);
        });

        $result = $client->lookup('123-4567');

        $this->assertSame('東京都', $result['prefecture']);
        $this->assertSame('千代田区', $result['city']);
        $this->assertSame('千代田', $result['town']);
    }

    public function testInvalidPostalCodeThrows(): void
    {
        $client = new ZipcloudClient('https://example.test/api/search', 3, fn (): string => '');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('郵便番号は7桁の数字で入力してください。');
        $client->lookup('123');
    }

    public function testApiErrorStatusThrows(): void
    {
        $client = new ZipcloudClient('https://example.test/api/search', 3, fn (): string => json_encode([
            'status' => 400,
            'message' => '入力エラー',
        ], JSON_UNESCAPED_UNICODE));

        try {
            $client->lookup('1234567');
            $this->fail('Expected exception was not thrown');
        } catch (ApiException $e) {
            $this->assertSame(400, $e->getStatusCode());
            $this->assertSame('入力エラー', $e->getMessage());
        }
    }

    public function testEmptyResultsThrowsNotFound(): void
    {
        $client = new ZipcloudClient('https://example.test/api/search', 3, fn (): string => json_encode([
            'status' => 200,
            'results' => [],
            'message' => null,
        ]));

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('該当する住所が見つかりませんでした。');
        $client->lookup('9876543');
    }

    public function testMalformedResponseThrows(): void
    {
        $client = new ZipcloudClient('https://example.test/api/search', 3, fn (): string => 'not-json');

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('郵便番号検索の応答が不正です。');
        $client->lookup('7654321');
    }
}

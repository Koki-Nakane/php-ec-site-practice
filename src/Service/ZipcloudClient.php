<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\ApiException;

final class ZipcloudClient
{
    private const DEFAULT_ENDPOINT = 'https://zipcloud.ibsnet.co.jp/api/search';
    private const DEFAULT_TIMEOUT = 3;

    /**
     * @param callable(string):mixed|null $httpGet Optional HTTP getter for testing.
     */
    public function __construct(
        private string $endpoint = self::DEFAULT_ENDPOINT,
        private int $timeoutSeconds = self::DEFAULT_TIMEOUT,
        private $httpGet = null,
    ) {
    }

    /**
     * @return array{prefecture:string,city:string,town:string}
     */
    public function lookup(string $postalCode): array
    {
        $normalized = preg_replace('/\D/', '', $postalCode) ?? '';
        if (strlen($normalized) !== 7) {
            throw new ApiException('郵便番号は7桁の数字で入力してください。', 400);
        }

        $url = $this->endpoint . '?zipcode=' . urlencode($normalized);
        $raw = $this->fetch($url);

        $data = json_decode($raw, true);
        if (!is_array($data) || !array_key_exists('status', $data)) {
            throw new ApiException('郵便番号検索の応答が不正です。', 502);
        }

        $status = (int) $data['status'];
        if ($status !== 200) {
            $message = is_string($data['message'] ?? null)
                ? $data['message']
                : '郵便番号検索に失敗しました。';
            throw new ApiException($message, 400);
        }

        $results = $data['results'] ?? null;
        if (!is_array($results) || $results === []) {
            throw new ApiException('該当する住所が見つかりませんでした。', 404);
        }

        $first = $results[0];
        if (!is_array($first) || !isset($first['address1'], $first['address2'], $first['address3'])) {
            throw new ApiException('郵便番号検索の結果が不正です。', 502);
        }

        return [
            'prefecture' => (string) $first['address1'],
            'city' => (string) $first['address2'],
            'town' => (string) $first['address3'],
        ];
    }

    private function fetch(string $url): string
    {
        if ($this->httpGet !== null) {
            $body = ($this->httpGet)($url);
            if (!is_string($body)) {
                throw new ApiException('郵便番号検索の応答が不正です。', 502);
            }

            return $body;
        }

        if (!function_exists('curl_init')) {
            throw new ApiException('cURLが利用できないため郵便番号検索を実行できません。', 500);
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new ApiException('郵便番号検索の初期化に失敗しました。', 500);
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'php-ec-practice/zipcloud',
        ]);

        $body = curl_exec($handle);
        if ($body === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new ApiException('郵便番号検索で通信エラーが発生しました。' . ($error !== '' ? ' ' . $error : ''), 502);
        }

        $httpStatus = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($httpStatus !== 200) {
            throw new ApiException('郵便番号検索でエラーが発生しました。(HTTP ' . $httpStatus . ')', 502);
        }

        return (string) $body;
    }
}

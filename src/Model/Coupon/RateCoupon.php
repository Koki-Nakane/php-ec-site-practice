<?php

declare(strict_types=1);

namespace App\Model\Coupon;

use InvalidArgumentException;

final class RateCoupon implements CouponInterface
{
    private string $code;
    private float $rate;

    /**
     * @param float $rate 0より大きく1以下（=100%）の値を期待します。0.15 で 15% 割引。
     */
    public function __construct(string $code, float $rate)
    {
        $code = trim($code);
        if ($code === '') {
            throw new InvalidArgumentException('クーポンコードを指定してください。');
        }

        if ($rate <= 0 || $rate > 1) {
            throw new InvalidArgumentException('割引率は 0 より大きく 1 以下の小数で指定してください。');
        }

        $this->code = $code;
        $this->rate = $rate;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function calculateDiscount(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }

        return round($subtotal * $this->rate, 2);
    }
}

<?php

declare(strict_types=1);

namespace App\Model\Coupon;

use InvalidArgumentException;

final class FixedAmountCoupon implements CouponInterface
{
    private string $code;
    private float $amount;

    public function __construct(string $code, float $amount)
    {
        $code = trim($code);
        if ($code === '') {
            throw new InvalidArgumentException('クーポンコードを指定してください。');
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('割引額には1円以上を指定してください。');
        }

        $this->code = $code;
        $this->amount = round($amount, 2);
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

        return min($this->amount, $subtotal);
    }
}

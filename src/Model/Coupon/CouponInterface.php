<?php

declare(strict_types=1);

namespace App\Model\Coupon;

interface CouponInterface
{
    public function getCode(): string;

    /**
     * @param float $subtotal 税抜きや送料前など、割引適用前の金額
     *
     * @return float 割引額（0以上）。カート側で必要に応じて下限・上限を調整します。
     */
    public function calculateDiscount(float $subtotal): float;
}

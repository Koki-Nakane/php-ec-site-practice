<?php

declare(strict_types=1);

namespace App\Model\Enum;

final class OrderStatus
{
    public const PENDING = 0;
    public const PROCESSING = 1;
    public const SHIPPED = 2;
    public const COMPLETED = 3;
    public const CANCELED = 4;

    /**
     * @return int[]
     */
    public static function all(): array
    {
        return [
            self::PENDING,
            self::PROCESSING,
            self::SHIPPED,
            self::COMPLETED,
            self::CANCELED,
        ];
    }

    public static function isValid(int $value): bool
    {
        return in_array($value, self::all(), true);
    }

    public static function label(int $value): string
    {
        return self::labels()[$value] ?? '不明';
    }

    /**
     * @return array<int,string>
     */
    public static function labels(): array
    {
        return [
            self::PENDING => '受付待ち',
            self::PROCESSING => '処理中',
            self::SHIPPED => '発送済み',
            self::COMPLETED => '完了',
            self::CANCELED => 'キャンセル',
        ];
    }
}

<?php

namespace App\Support;

class OrderStatuses
{
    public const PENDING = 1;

    public const ACCEPTED = 2;

    public const REJECTED = 3;

    public const DELIVERING = 4;

    public const COMPLETED = 5;

    /**
     * @return array<int, string>
     */
    public static function labels(): array
    {
        return [
            self::PENDING => 'Pending',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Rejected',
            self::DELIVERING => 'Delivering',
            self::COMPLETED => 'Completed',
        ];
    }

    public static function label(int|string|null $status): string
    {
        return self::labels()[(int) $status] ?? 'Unknown';
    }

    /**
     * @return array<int, string>
     */
    public static function typeLabels(): array
    {
        return [
            1 => 'Delivery',
            2 => 'Collection',
        ];
    }

    public static function typeLabel(int|string|null $type): string
    {
        return self::typeLabels()[(int) $type] ?? 'Unknown';
    }
}

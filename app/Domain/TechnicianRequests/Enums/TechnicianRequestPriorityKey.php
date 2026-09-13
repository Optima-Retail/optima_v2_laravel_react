<?php

declare(strict_types=1);

namespace App\Domain\TechnicianRequests\Enums;

enum TechnicianRequestPriorityKey: string
{
    case Urgent = 'urgent';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

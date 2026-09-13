<?php

declare(strict_types=1);

namespace App\Domain\TechnicianRequests\Enums;

enum TechnicianRequestStatusKind: string
{
    case Request = 'request';
    case Screening = 'screening';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

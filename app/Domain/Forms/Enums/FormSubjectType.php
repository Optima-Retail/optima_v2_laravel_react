<?php

declare(strict_types=1);

namespace App\Domain\Forms\Enums;

enum FormSubjectType: string
{
    case WorkOrder = 'work_order';
    case Technician = 'technician';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

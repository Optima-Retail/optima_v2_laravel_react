<?php

declare(strict_types=1);

namespace App\Domain\Forms\Enums;

enum FormTemplateOwnerType: string
{
    case Global = 'global';
    case Brand = 'brand';
    case Customer = 'customer';
    case Establishment = 'establishment';
    case Bible = 'bible';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Companies\Enums;

enum CompanyRelationshipStatus: string
{
    case Prospect = 'prospect';
    case Active = 'active';
    case Blocked = 'blocked';
    case Inactive = 'inactive';
    case Archived = 'archived';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

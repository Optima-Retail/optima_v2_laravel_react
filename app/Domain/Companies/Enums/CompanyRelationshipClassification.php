<?php

declare(strict_types=1);

namespace App\Domain\Companies\Enums;

enum CompanyRelationshipClassification: string
{
    case Commercial = 'commercial';
    case Intercompany = 'intercompany';
    case PublicAdministration = 'public_administration';
    case Bank = 'bank';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

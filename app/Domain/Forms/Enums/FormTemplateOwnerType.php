<?php

declare(strict_types=1);

namespace App\Domain\Forms\Enums;

/**
 * Template owner scope.
 *
 * Legacy: plantillas.modelo_id (brand/client/establishment/bible/global).
 * Bible is enum-only — prod only ever used a single biblias row (hardcoded id=1).
 */
enum FormTemplateOwnerType: string
{
    case Global = 'global';
    case Brand = 'brand';
    case Customer = 'customer';
    case Establishment = 'establishment';
    case Bible = 'bible';

    public function label(): string
    {
        return match ($this) {
            self::Global => 'Global',
            self::Brand => 'Brand',
            self::Customer => 'Customer',
            self::Establishment => 'Establishment',
            self::Bible => 'Bible',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

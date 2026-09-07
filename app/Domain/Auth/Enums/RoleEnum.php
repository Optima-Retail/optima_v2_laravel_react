<?php

declare(strict_types=1);

namespace App\Domain\Auth\Enums;

enum RoleEnum: string
{
    case Admin = 'admin';
    case User = 'user';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

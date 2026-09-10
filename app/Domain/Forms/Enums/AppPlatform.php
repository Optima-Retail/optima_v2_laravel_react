<?php

declare(strict_types=1);

namespace App\Domain\Forms\Enums;

/**
 * Client app that created or last touched a form.
 *
 * Legacy: `plataformas` table / `PlataformaEnum` (WEB=1, ANDROID=2, IOS=3).
 * Kept as a code enum only — no catalog table.
 */
enum AppPlatform: int
{
    case Web = 1;
    case Android = 2;
    case Ios = 3;

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Android => 'Android',
            self::Ios => 'iOS',
        };
    }

    /**
     * @return list<int>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'id' => $case->value,
                'label' => $case->label(),
            ],
            self::cases(),
        );
    }
}

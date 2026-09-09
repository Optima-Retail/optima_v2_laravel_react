<?php

declare(strict_types=1);

namespace App\Domain\Config\NumberingPatterns\Enums;

enum NumberingSegmentType: string
{
    case Letters = 'letters';
    case Symbols = 'symbols';
    case Year = 'year';
    case Sequence = 'sequence';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function requiresValue(): bool
    {
        return match ($this) {
            self::Letters, self::Symbols => true,
            self::Year, self::Sequence => false,
        };
    }

    public function requiresDigitLength(): bool
    {
        return $this === self::Sequence;
    }
}

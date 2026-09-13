<?php

declare(strict_types=1);

namespace App\Domain\Contracts\Enums;

enum ContractInvoicingAggregationFrequency: string
{
    case Monthly = 'monthly';
    case Bimonthly = 'bimonthly';
    case Quarterly = 'quarterly';
    case Annually = 'annually';
    case Biannually = 'biannually';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function monthsPerCycle(): int
    {
        return match ($this) {
            self::Monthly => 1,
            self::Bimonthly => 2,
            self::Quarterly => 3,
            self::Annually => 12,
            self::Biannually => 6,
        };
    }
}

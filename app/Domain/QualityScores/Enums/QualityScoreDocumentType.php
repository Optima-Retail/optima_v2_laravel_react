<?php

declare(strict_types=1);

namespace App\Domain\QualityScores\Enums;

enum QualityScoreDocumentType: string
{
    case Estimate = 'estimate';
    case WorkOrder = 'work_order';
    case Compliment = 'compliment';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\WorkOrders\Enums;

enum WorkOrderStage: string
{
    case Estimate = 'estimate';
    case WorkOrder = 'work_order';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

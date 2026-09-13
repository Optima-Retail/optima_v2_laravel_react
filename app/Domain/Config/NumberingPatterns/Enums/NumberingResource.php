<?php

declare(strict_types=1);

namespace App\Domain\Config\NumberingPatterns\Enums;

enum NumberingResource: string
{
    case Contracts = 'contracts';
    case Invoices = 'invoices';
    case Estimates = 'estimates';
    case WorkOrders = 'work_orders';
    case TechnicianRequests = 'technician_requests';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

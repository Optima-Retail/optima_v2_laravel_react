<?php

declare(strict_types=1);

namespace App\Domain\Companies\Enums;

enum WorkOrderGroupingBasis: string
{
    case Customer = 'customer';
    case Establishment = 'establishment';
    case WorkOrder = 'work_order';
}

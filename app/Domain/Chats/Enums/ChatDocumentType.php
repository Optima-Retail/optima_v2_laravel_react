<?php

declare(strict_types=1);

namespace App\Domain\Chats\Enums;

enum ChatDocumentType: string
{
    case WorkOrder = 'work_order';
    case Incident = 'incident';
    case Evaluation = 'evaluation';
    case TechnicianRequest = 'technician_request';
    case Technician = 'technician';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\SavedFilters\Enums;

/**
 * Main-sidebar list pages that may persist filter presets.
 * Config catalog indexes are intentionally excluded.
 */
enum SavedFilterPageKey: string
{
    case Companies = 'companies';
    case Clients = 'clients';
    case Suppliers = 'suppliers';
    case Technicians = 'technicians';
    case Establishments = 'establishments';
    case Contracts = 'contracts';
    case Estimates = 'estimates';
    case WorkOrders = 'work_orders';
    case Evaluations = 'evaluations';
    case Compliments = 'compliments';
    case Forms = 'forms';
    case FormTemplates = 'form_templates';
    case Incidents = 'incidents';
    case Brands = 'brands';
    case TechnicianIncidents = 'technician_incidents';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

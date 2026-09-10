<?php

declare(strict_types=1);

namespace App\Domain\Incidents\Support;

use App\Models\IncidentStatus;
use Illuminate\Support\Facades\DB;

/**
 * Acciones status rules from `incident_status_type_exclusions`
 * (managed in Config → Incident statuses).
 */
final class IncidentLineStatusRules
{
    /**
     * Status IDs that must not appear when adding an Acciones line for this type.
     *
     * @return list<int>
     */
    public function forbiddenStatusIdsForType(?int $incidentTypeId): array
    {
        if ($incidentTypeId === null || $incidentTypeId <= 0) {
            return [];
        }

        return DB::table('incident_status_type_exclusions')
            ->where('incident_type_id', $incidentTypeId)
            ->whereIn(
                'incident_status_id',
                IncidentStatus::query()->select('id'),
            )
            ->pluck('incident_status_id')
            ->map(static fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function isStatusAllowedForType(?int $incidentTypeId, int $statusId): bool
    {
        return ! in_array($statusId, $this->forbiddenStatusIdsForType($incidentTypeId), true);
    }

    /**
     * @param  list<array{id: int}>  $options
     * @return list<array{id: int}>
     */
    public function filterStatusOptions(?int $incidentTypeId, array $options): array
    {
        $forbidden = $this->forbiddenStatusIdsForType($incidentTypeId);

        if ($forbidden === []) {
            return $options;
        }

        return array_values(array_filter(
            $options,
            static fn (array $option): bool => ! in_array((int) $option['id'], $forbidden, true),
        ));
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Config\IncidentSubtypes\Services;

use App\Models\IncidentSubtype;
use App\Models\IncidentType;
use Illuminate\Support\Facades\DB;

final class IncidentSubtypeService
{
    /**
     * @return list<array{id: int, name: string, incident_type_id: int}>
     */
    public function forType(IncidentType $incidentType): array
    {
        return IncidentSubtype::query()
            ->where('incident_type_id', $incidentType->id)
            ->orderBy('name')
            ->get(['id', 'name', 'incident_type_id'])
            ->map(fn (IncidentSubtype $subtype): array => [
                'id' => $subtype->id,
                'name' => $subtype->name,
                'incident_type_id' => $subtype->incident_type_id,
            ])
            ->values()
            ->all();
    }

    /**
     * Replace the type's subtype set with the given rows (create / update / soft-delete).
     *
     * @param  list<array{id?: int|null, name: string}>  $rows
     * @return list<array{id: int, name: string, incident_type_id: int}>
     */
    public function syncForType(IncidentType $incidentType, array $rows): array
    {
        return DB::transaction(function () use ($incidentType, $rows): array {
            $keepIds = [];

            foreach ($rows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $payload = [
                    'name' => $name,
                    'incident_type_id' => $incidentType->id,
                ];

                $id = isset($row['id']) ? (int) $row['id'] : 0;

                if ($id > 0) {
                    $subtype = IncidentSubtype::query()
                        ->where('incident_type_id', $incidentType->id)
                        ->whereKey($id)
                        ->firstOrFail();
                    $subtype->update($payload);
                    $keepIds[] = $subtype->id;

                    continue;
                }

                $created = IncidentSubtype::query()->create($payload);
                $keepIds[] = $created->id;
            }

            IncidentSubtype::query()
                ->where('incident_type_id', $incidentType->id)
                ->when(
                    $keepIds === [],
                    fn ($query) => $query,
                    fn ($query) => $query->whereNotIn('id', $keepIds),
                )
                ->get()
                ->each(function (IncidentSubtype $subtype): void {
                    if (! $subtype->trashed()) {
                        $subtype->delete();
                    }
                });

            return $this->forType($incidentType);
        });
    }
}

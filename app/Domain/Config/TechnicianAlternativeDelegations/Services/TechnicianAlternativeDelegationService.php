<?php

declare(strict_types=1);

namespace App\Domain\Config\TechnicianAlternativeDelegations\Services;

use App\Models\CompanyRelationship;
use App\Models\TechnicianAlternativeDelegation;
use Illuminate\Support\Facades\DB;

final class TechnicianAlternativeDelegationService
{
    /**
     * @return list<array{id: int, delegation_id: int, delegation_label: string}>
     */
    public function forTechnician(CompanyRelationship $relationship): array
    {
        return TechnicianAlternativeDelegation::query()
            ->with('delegation:id,name')
            ->where('company_relationship_id', $relationship->id)
            ->orderBy('delegation_id')
            ->get()
            ->map(function (TechnicianAlternativeDelegation $row): array {
                return [
                    'id' => $row->id,
                    'delegation_id' => $row->delegation_id,
                    'delegation_label' => $row->delegation?->name ?? '',
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $delegationIds
     * @return list<array{id: int, delegation_id: int, delegation_label: string}>
     */
    public function syncForTechnician(CompanyRelationship $relationship, array $delegationIds): array
    {
        return DB::transaction(function () use ($relationship, $delegationIds): array {
            $wanted = [];
            foreach ($delegationIds as $id) {
                $id = (int) $id;
                if ($id > 0) {
                    $wanted[$id] = $id;
                }
            }
            $wantedIds = array_values($wanted);

            $existing = TechnicianAlternativeDelegation::withTrashed()
                ->where('company_relationship_id', $relationship->id)
                ->get();

            foreach ($existing as $row) {
                $rowDelegationId = (int) $row->delegation_id;

                if (! in_array($rowDelegationId, $wantedIds, true)) {
                    if (! $row->trashed()) {
                        $row->delete();
                    }

                    continue;
                }

                if ($row->trashed()) {
                    $row->restore();
                }
            }

            $existingIds = $existing->pluck('delegation_id')->map(fn ($id): int => (int) $id)->all();

            foreach ($wantedIds as $delegationId) {
                if (in_array($delegationId, $existingIds, true)) {
                    continue;
                }

                TechnicianAlternativeDelegation::query()->create([
                    'company_relationship_id' => $relationship->id,
                    'delegation_id' => $delegationId,
                ]);
            }

            return $this->forTechnician($relationship);
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Models\CompanyRelationship;
use App\Models\TechnicianRate;
use Illuminate\Support\Facades\DB;

final class TechnicianRateService
{
    /** @var list<string> */
    private const AMOUNT_KEYS = [
        'labor_weekday_amount',
        'labor_night_amount',
        'labor_weekend_amount',
        'labor_holiday_amount',
        'labor_urgent_amount',
        'travel_weekday_amount',
        'travel_night_amount',
        'travel_weekend_amount',
        'travel_holiday_amount',
        'travel_urgent_amount',
    ];

    /**
     * @return array{
     *     id: int|null,
     *     company_relationship_id: int,
     *     labor_weekday_amount: string,
     *     labor_night_amount: string,
     *     labor_weekend_amount: string,
     *     labor_holiday_amount: string,
     *     labor_urgent_amount: string,
     *     travel_weekday_amount: string,
     *     travel_night_amount: string,
     *     travel_weekend_amount: string,
     *     travel_holiday_amount: string,
     *     travel_urgent_amount: string
     * }
     */
    public function forTechnician(CompanyRelationship $relationship): array
    {
        /** @var TechnicianRate|null $rate */
        $rate = TechnicianRate::query()
            ->where('company_relationship_id', $relationship->id)
            ->first();

        return $this->toRow($relationship->id, $rate);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     id: int|null,
     *     company_relationship_id: int,
     *     labor_weekday_amount: string,
     *     labor_night_amount: string,
     *     labor_weekend_amount: string,
     *     labor_holiday_amount: string,
     *     labor_urgent_amount: string,
     *     travel_weekday_amount: string,
     *     travel_night_amount: string,
     *     travel_weekend_amount: string,
     *     travel_holiday_amount: string,
     *     travel_urgent_amount: string
     * }
     */
    public function upsert(CompanyRelationship $relationship, array $data): array
    {
        return DB::transaction(function () use ($relationship, $data): array {
            $attributes = [];

            foreach (self::AMOUNT_KEYS as $key) {
                $attributes[$key] = $data[$key] ?? 0;
            }

            /** @var TechnicianRate $rate */
            $rate = TechnicianRate::withTrashed()
                ->where('company_relationship_id', $relationship->id)
                ->first();

            if ($rate !== null) {
                if ($rate->trashed()) {
                    $rate->restore();
                }

                $rate->update($attributes);
            } else {
                $rate = TechnicianRate::query()->create([
                    'company_relationship_id' => $relationship->id,
                    ...$attributes,
                ]);
            }

            return $this->toRow($relationship->id, $rate->fresh() ?? $rate);
        });
    }

    /**
     * @return array{
     *     id: int|null,
     *     company_relationship_id: int,
     *     labor_weekday_amount: string,
     *     labor_night_amount: string,
     *     labor_weekend_amount: string,
     *     labor_holiday_amount: string,
     *     labor_urgent_amount: string,
     *     travel_weekday_amount: string,
     *     travel_night_amount: string,
     *     travel_weekend_amount: string,
     *     travel_holiday_amount: string,
     *     travel_urgent_amount: string
     * }
     */
    private function toRow(int $relationshipId, ?TechnicianRate $rate): array
    {
        $row = [
            'id' => $rate?->id,
            'company_relationship_id' => $relationshipId,
        ];

        foreach (self::AMOUNT_KEYS as $key) {
            $row[$key] = $rate !== null ? (string) $rate->{$key} : '0.00';
        }

        return $row;
    }
}

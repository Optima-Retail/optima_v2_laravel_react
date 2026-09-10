<?php

declare(strict_types=1);

namespace App\Domain\Companies\Services;

use App\Models\Company;
use App\Models\CompanySchedule;
use Illuminate\Support\Facades\DB;

final class CompanyScheduleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertForCompany(Company $company, array $data): CompanySchedule
    {
        return DB::transaction(function () use ($company, $data): CompanySchedule {
            $attributes = [];
            foreach ([
                'monday_opens', 'monday_closes',
                'tuesday_opens', 'tuesday_closes',
                'wednesday_opens', 'wednesday_closes',
                'thursday_opens', 'thursday_closes',
                'friday_opens', 'friday_closes',
                'saturday_opens', 'saturday_closes',
                'sunday_opens', 'sunday_closes',
            ] as $key) {
                $value = $data[$key] ?? null;
                if (is_string($value) && preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
                    $value .= ':00';
                }
                $attributes[$key] = filled($value) ? $value : null;
            }

            /** @var CompanySchedule $schedule */
            $schedule = CompanySchedule::query()->withTrashed()->firstOrNew([
                'company_id' => $company->id,
            ]);

            if ($schedule->trashed()) {
                $schedule->restore();
            }

            $schedule->fill($attributes);
            $schedule->company_id = $company->id;
            $schedule->save();

            return $schedule->fresh() ?? $schedule;
        });
    }

    /**
     * @return array<string, string|null>
     */
    public function toFormData(?CompanySchedule $schedule): array
    {
        $time = static function (mixed $value): ?string {
            if ($value === null || $value === '') {
                return null;
            }

            $string = (string) $value;

            return strlen($string) >= 5 ? substr($string, 0, 5) : $string;
        };

        return [
            'monday_opens' => $time($schedule?->monday_opens),
            'monday_closes' => $time($schedule?->monday_closes),
            'tuesday_opens' => $time($schedule?->tuesday_opens),
            'tuesday_closes' => $time($schedule?->tuesday_closes),
            'wednesday_opens' => $time($schedule?->wednesday_opens),
            'wednesday_closes' => $time($schedule?->wednesday_closes),
            'thursday_opens' => $time($schedule?->thursday_opens),
            'thursday_closes' => $time($schedule?->thursday_closes),
            'friday_opens' => $time($schedule?->friday_opens),
            'friday_closes' => $time($schedule?->friday_closes),
            'saturday_opens' => $time($schedule?->saturday_opens),
            'saturday_closes' => $time($schedule?->saturday_closes),
            'sunday_opens' => $time($schedule?->sunday_opens),
            'sunday_closes' => $time($schedule?->sunday_closes),
        ];
    }
}

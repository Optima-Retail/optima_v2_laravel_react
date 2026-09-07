<?php

declare(strict_types=1);

namespace App\Domain\Companies\Support;

use App\Models\Company;
use App\Models\User;

final class ActiveCompany
{
    public function forUser(?User $user): ?Company
    {
        if ($user === null) {
            return null;
        }

        if ($user->active_company_id !== null && $user->belongsToCompany((int) $user->active_company_id)) {
            return Company::query()->find($user->active_company_id);
        }

        $first = $user->companies()
            ->wherePivot('is_active', true)
            ->orderBy('companies.name')
            ->first();

        if ($first instanceof Company && $user->active_company_id !== $first->id) {
            $user->forceFill(['active_company_id' => $first->id])->save();
        }

        return $first;
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function membershipsForUser(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return $user->companies()
            ->wherePivot('is_active', true)
            ->orderBy('companies.name')
            ->get(['companies.id', 'companies.name'])
            ->map(fn (Company $company): array => [
                'id' => $company->id,
                'name' => $company->name,
            ])
            ->values()
            ->all();
    }

    public function switch(User $user, int $companyId): Company
    {
        abort_unless($user->belongsToCompany($companyId), 403);

        $company = Company::query()->findOrFail($companyId);
        $user->forceFill(['active_company_id' => $company->id])->save();

        return $company;
    }
}

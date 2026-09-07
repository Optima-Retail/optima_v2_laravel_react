<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Brand;
use App\Models\Company;
use App\Models\User;

trait InteractsWithCompanies
{
    protected function attachToCompany(User $user, Company $company, bool $active = true): User
    {
        $company->users()->syncWithoutDetaching([
            $user->id => ['is_active' => true],
        ]);

        if ($active) {
            $user->forceFill(['active_company_id' => $company->id])->save();
        }

        return $user->fresh() ?? $user;
    }

    protected function makeBrand(string $name = 'ACME'): Brand
    {
        return Brand::query()->create([
            'name' => strtoupper($name),
            'is_quality_control_contactable' => true,
            'send_debt_reminders' => true,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Companies\Enums\CompanyKind;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

final class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Optima Retail',
                'tradename' => 'OR',
                'slug' => 'or',
                'tax_id' => 'B66409087',
                'kind' => CompanyKind::OperatingCompany,
            ],
            [
                'name' => 'Optima Retail International Limited',
                'tradename' => 'ORIL',
                'slug' => 'oril',
                'tax_id' => 'IE4115369FH',
                'kind' => CompanyKind::OperatingCompany,
            ],
        ];

        $admin = User::query()->where('email', 'admin@optima.test')->first();
        $member = User::query()->where('email', 'user@optima.test')->first();

        foreach ($companies as $index => $attributes) {
            $company = Company::query()->updateOrCreate(
                ['tax_id' => $attributes['tax_id']],
                $attributes + ['is_active' => true],
            );

            if ($admin !== null) {
                $company->users()->syncWithoutDetaching([
                    $admin->id => ['is_active' => true],
                ]);

                if ($admin->active_company_id === null) {
                    $admin->forceFill(['active_company_id' => $company->id])->save();
                }
            }

            // Attach demo user to the first operating company only.
            if ($member !== null && $index === 0) {
                $company->users()->syncWithoutDetaching([
                    $member->id => ['is_active' => true],
                ]);

                if ($member->active_company_id === null) {
                    $member->forceFill(['active_company_id' => $company->id])->save();
                }
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            AdminUserSeeder::class,
            TenantSsoSeeder::class,
            CompanySeeder::class,
            BrandSeeder::class,
            WorkOrderTypeSeeder::class,
            ServiceTypeSeeder::class,
            GlobalServiceTypeSeeder::class,
            FormTypeSeeder::class,
            TechnicianAttendanceConfirmationTypeSeeder::class,
            FormStatusSeeder::class,
            PaymentMethodSeeder::class,
            PaymentDocumentSeeder::class,
            ClientPrioritySeeder::class,
            IncidentPrioritySeeder::class,
            IncidentTypeSeeder::class,
            IncidentSubtypeSeeder::class,
            IncidentStatusSeeder::class,
            TechnicianIncidentStatusSeeder::class,
            WorkOrderStatusSeeder::class,
            WorkOrderTechnicianStatusSeeder::class,
            ActionSeeder::class,
            KpiConfigurationSeeder::class,
            ComplimentTypeSeeder::class,
            ContractStatusSeeder::class,
            EvaluationStatusSeeder::class,
            TeamSeeder::class,
            LanguageSeeder::class,
            JobTitleSeeder::class,
            CostCenterSeeder::class,
            OtherExpenseTypeSeeder::class,
            TechnicianIncidentTypeSeeder::class,
            ExpenseTypeSeeder::class,
            IndirectCostTypeSeeder::class,
            ArticleSeeder::class,
            IntegrationSeeder::class,
            EstablishmentTypeSeeder::class,
            TimezoneSeeder::class,
            CountrySeeder::class,
            ProvinceSeeder::class,
            BankSeeder::class,
            SeriesSeeder::class,
            CurrencySeeder::class,
            DelegationSeeder::class,
            EstablishmentSeeder::class,
            FieldHelpSeeder::class,
        ]);
    }
}

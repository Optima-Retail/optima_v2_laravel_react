<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Forms\Enums\FormSubjectType;
use App\Domain\Forms\Enums\FormTemplateOwnerType;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Establishment;
use App\Models\Form;
use App\Models\FormTemplate;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\FormStatusSeeder;
use Database\Seeders\FormTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class FormPublicLinkTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FormTypeSeeder::class);
        $this->seed(FormStatusSeeder::class);
    }

    public function test_guest_can_view_public_form_when_not_creating(): void
    {
        $form = $this->makeForm(statusId: 2);

        $this->get(route('forms.public', $form->public_id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Forms/Public')
                ->where('form.public_id', $form->public_id)
                ->where('form.name', $form->name));
    }

    public function test_guest_cannot_view_public_form_while_creating(): void
    {
        $form = $this->makeForm(statusId: 1);

        $this->get(route('forms.public', $form->public_id))
            ->assertNotFound();
    }

    private function makeForm(int $statusId): Form
    {
        $owner = Company::factory()->create();
        $client = Company::factory()->create();
        $admin = User::factory()->create();
        $this->attachToCompany($admin, $owner);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $establishment = Establishment::factory()->create([
            'company_id' => $client->id,
        ]);

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
        ]);

        FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'Unused',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => false,
        ]);

        return Form::query()->create([
            'public_id' => Str::lower(Str::random(16)),
            'name' => 'Public form',
            'form_type_id' => 1,
            'form_status_id' => $statusId,
            'user_id' => $admin->id,
            'subject_type' => FormSubjectType::WorkOrder,
            'work_order_id' => $workOrder->id,
            'occurred_on' => now()->toDateString(),
            'app_platform_id' => 1,
            'was_edited' => false,
        ]);
    }
}

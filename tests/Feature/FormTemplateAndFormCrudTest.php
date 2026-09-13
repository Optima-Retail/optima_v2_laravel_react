<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Forms\Enums\FormSubjectType;
use App\Domain\Forms\Enums\FormTemplateOwnerType;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\Form;
use App\Models\FormField;
use App\Models\FormSection;
use App\Models\FormTemplate;
use App\Models\FormTemplateField;
use App\Models\FormTemplateSection;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\FormStatusSeeder;
use Database\Seeders\FormTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class FormTemplateAndFormCrudTest extends TestCase
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

    public function test_admin_can_create_template_with_sections_and_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $response = $this->actingAs($admin)
            ->post(route('form-templates.store'), [
                'name' => 'Preventive visit',
                'form_type_id' => 2,
                'owner_type' => FormTemplateOwnerType::Global->value,
                'is_default' => false,
                'sections' => [
                    [
                        'label' => 'General',
                        'sort_order' => 0,
                        'is_repeatable' => false,
                        'is_modal' => false,
                        'is_visible' => true,
                        'fields' => [
                            [
                                'type' => 'texto',
                                'label' => 'Notes',
                                'default_value' => 'n/a',
                                'sort_order' => 0,
                                'is_required' => true,
                                'is_repeatable' => false,
                                'is_visible' => true,
                                'is_locked' => false,
                            ],
                        ],
                    ],
                ],
            ]);

        $template = FormTemplate::query()->first();
        $this->assertNotNull($template);

        $response
            ->assertRedirect(route('form-templates.edit', $template))
            ->assertSessionHas('success', 'form_template_created_successfully');

        $this->assertSame('Preventive visit', $template->name);
        $this->assertSame($owner->id, $template->company_id);
        $this->assertSame(FormTemplateOwnerType::Global, $template->owner_type);
        $this->assertDatabaseHas('form_template_sections', [
            'form_template_id' => $template->id,
            'label' => 'General',
        ]);
        $this->assertDatabaseHas('form_template_fields', [
            'label' => 'Notes',
            'type' => 'texto',
            'default_value' => 'n/a',
        ]);
        $this->assertSame(1, FormTemplateSection::query()->count());
        $this->assertSame(1, FormTemplateField::query()->count());
    }

    public function test_admin_can_instantiate_form_from_template_for_work_order_and_advance_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $owner = Company::factory()->create();
        $client = Company::factory()->create();
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

        $template = FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'WO report',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => false,
        ]);

        $section = FormTemplateSection::query()->create([
            'form_template_id' => $template->id,
            'sort_order' => 0,
            'label' => 'Visit',
            'is_repeatable' => false,
            'is_modal' => false,
            'is_visible' => true,
        ]);

        FormTemplateField::query()->create([
            'form_template_section_id' => $section->id,
            'sort_order' => 0,
            'type' => 'texto',
            'label' => 'Summary',
            'default_value' => 'ok',
            'is_required' => false,
            'is_repeatable' => false,
            'is_visible' => true,
            'is_locked' => false,
            'is_cloned' => false,
        ]);

        $createResponse = $this->actingAs($admin)
            ->post(route('forms.store'), [
                'form_template_id' => $template->id,
                'subject_type' => FormSubjectType::WorkOrder->value,
                'work_order_id' => $workOrder->id,
                'form_status_id' => 1,
            ]);

        $form = Form::query()->first();
        $this->assertNotNull($form);

        $createResponse
            ->assertRedirect(route('forms.edit', $form))
            ->assertSessionHas('success', 'form_created_successfully');

        $this->assertSame(FormSubjectType::WorkOrder, $form->subject_type);
        $this->assertSame($workOrder->id, $form->work_order_id);
        $this->assertSame($template->id, $form->form_template_id);
        $this->assertSame(1, $form->form_status_id);
        $this->assertSame(1, FormSection::query()->where('form_id', $form->id)->count());
        $this->assertDatabaseHas('form_fields', [
            'label' => 'Summary',
            'value' => 'ok',
        ]);
        $this->assertSame(1, FormField::query()->count());

        $advanceResponse = $this->actingAs($admin)
            ->post(route('forms.advance', $form));

        $advanceResponse
            ->assertRedirect(route('forms.edit', $form))
            ->assertSessionHas('success', 'form_status_advanced_successfully');

        $this->assertSame(2, $form->fresh()?->form_status_id);
    }

    public function test_templates_from_other_companies_are_not_accessible(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $other = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $foreignTemplate = FormTemplate::query()->create([
            'company_id' => $other->id,
            'name' => 'Other company template',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('form-templates.edit', $foreignTemplate))
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson(route('form-templates.data'))
            ->assertOk()
            ->assertJsonMissing(['id' => $foreignTemplate->id]);
    }
}

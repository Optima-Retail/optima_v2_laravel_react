<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Forms\Enums\FormSubjectType;
use App\Domain\Forms\Enums\FormTemplateOwnerType;
use App\Domain\Forms\Events\FormCompleted;
use App\Listeners\SendFormCompletedNotification;
use App\Mail\FormCompletedMail;
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
use App\Models\WorkOrderType;
use Database\Seeders\FormStatusSeeder;
use Database\Seeders\FormTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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

    public function test_template_name_must_be_unique_within_company(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'Preventive visit',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('form-templates.store'), [
                'name' => 'Preventive visit',
                'form_type_id' => 2,
                'owner_type' => FormTemplateOwnerType::Global->value,
                'is_default' => false,
            ])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, FormTemplate::query()->where('name', 'Preventive visit')->count());
    }

    public function test_setting_a_template_as_default_unsets_other_defaults_for_same_type_and_language(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $existingDefault = FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'Existing default',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => true,
        ]);

        $other = FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'New default',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('form-templates.update', $other), [
                'name' => $other->name,
                'form_type_id' => 1,
                'owner_type' => FormTemplateOwnerType::Global->value,
                'is_default' => true,
            ])
            ->assertRedirect(route('form-templates.edit', $other));

        $this->assertTrue($other->fresh()?->is_default);
        $this->assertFalse($existingDefault->fresh()?->is_default);
    }

    public function test_field_label_is_required_unless_type_is_structural(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $payload = [
            'name' => 'Materials check',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global->value,
            'is_default' => false,
            'sections' => [
                [
                    'label' => 'General',
                    'sort_order' => 0,
                    'fields' => [
                        ['type' => 'texto', 'label' => '', 'sort_order' => 0],
                    ],
                ],
            ],
        ];

        $this->actingAs($admin)
            ->post(route('form-templates.store'), $payload)
            ->assertSessionHasErrors(['sections.0.fields.0.label']);

        $payload['sections'][0]['fields'][0]['type'] = 'materiales';

        $this->actingAs($admin)
            ->post(route('form-templates.store'), $payload)
            ->assertSessionDoesntHaveErrors(['sections.0.fields.0.label']);
    }

    public function test_conditional_field_id_must_belong_to_the_same_template_and_cannot_self_reference(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $template = FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'Conditional demo',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => false,
        ]);
        $section = FormTemplateSection::query()->create([
            'form_template_id' => $template->id,
            'sort_order' => 0,
            'label' => 'General',
            'is_repeatable' => false,
            'is_modal' => false,
            'is_visible' => true,
        ]);
        $trigger = FormTemplateField::query()->create([
            'form_template_section_id' => $section->id,
            'sort_order' => 0,
            'type' => 'si-no',
            'label' => 'Needs follow-up?',
            'is_required' => false,
            'is_repeatable' => false,
            'is_visible' => true,
            'is_locked' => false,
        ]);
        $dependent = FormTemplateField::query()->create([
            'form_template_section_id' => $section->id,
            'sort_order' => 1,
            'type' => 'texto',
            'label' => 'Follow-up notes',
            'is_required' => false,
            'is_repeatable' => false,
            'is_visible' => true,
            'is_locked' => false,
        ]);

        $foreignField = FormTemplateField::query()->create([
            'form_template_section_id' => FormTemplateSection::query()->create([
                'form_template_id' => FormTemplate::query()->create([
                    'company_id' => $owner->id,
                    'name' => 'Unrelated template',
                    'form_type_id' => 1,
                    'owner_type' => FormTemplateOwnerType::Global,
                    'is_default' => false,
                ])->id,
                'sort_order' => 0,
                'label' => 'Other',
                'is_repeatable' => false,
                'is_modal' => false,
                'is_visible' => true,
            ])->id,
            'sort_order' => 0,
            'type' => 'texto',
            'label' => 'Foreign field',
            'is_required' => false,
            'is_repeatable' => false,
            'is_visible' => true,
            'is_locked' => false,
        ]);

        $basePayload = [
            'name' => $template->name,
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global->value,
            'is_default' => false,
            'sections' => [
                [
                    'id' => $section->id,
                    'label' => $section->label,
                    'sort_order' => 0,
                    'fields' => [
                        [
                            'id' => $trigger->id,
                            'type' => 'si-no',
                            'label' => $trigger->label,
                            'sort_order' => 0,
                        ],
                        [
                            'id' => $dependent->id,
                            'type' => 'texto',
                            'label' => $dependent->label,
                            'sort_order' => 1,
                            'conditional_field_id' => $foreignField->id,
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($admin)
            ->put(route('form-templates.update', $template), $basePayload)
            ->assertSessionHasErrors(['sections.0.fields.1.conditional_field_id']);

        $selfReferencePayload = $basePayload;
        $selfReferencePayload['sections'][0]['fields'][1]['conditional_field_id'] = $dependent->id;

        $this->actingAs($admin)
            ->put(route('form-templates.update', $template), $selfReferencePayload)
            ->assertSessionHasErrors(['sections.0.fields.1.conditional_field_id']);

        $validPayload = $basePayload;
        $validPayload['sections'][0]['fields'][1]['conditional_field_id'] = $trigger->id;
        $validPayload['sections'][0]['fields'][1]['payload'] = ['value' => 'yes'];

        $this->actingAs($admin)
            ->put(route('form-templates.update', $template), $validPayload)
            ->assertRedirect(route('form-templates.edit', $template));

        $this->assertSame($trigger->id, $dependent->fresh()?->conditional_field_id);
        $this->assertSame(['value' => 'yes'], $dependent->fresh()?->payload);
    }

    public function test_admin_can_duplicate_a_template_with_its_sections_and_fields(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $template = FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'Preventive visit',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => true,
        ]);
        $section = FormTemplateSection::query()->create([
            'form_template_id' => $template->id,
            'sort_order' => 0,
            'label' => 'General',
            'is_repeatable' => false,
            'is_modal' => false,
            'is_visible' => true,
        ]);
        FormTemplateField::query()->create([
            'form_template_section_id' => $section->id,
            'sort_order' => 0,
            'type' => 'texto',
            'label' => 'Notes',
            'is_required' => true,
            'is_repeatable' => false,
            'is_visible' => true,
            'is_locked' => false,
        ]);

        $response = $this->actingAs($admin)->post(route('form-templates.duplicate', $template));

        $copy = FormTemplate::query()->where('name', 'Preventive visit (copy)')->first();
        $this->assertNotNull($copy);
        $response->assertRedirect(route('form-templates.edit', $copy));

        $this->assertFalse($copy->is_default);
        $this->assertSame(1, FormTemplateSection::query()->where('form_template_id', $copy->id)->count());
        $this->assertDatabaseHas('form_template_fields', [
            'form_template_section_id' => FormTemplateSection::query()->where('form_template_id', $copy->id)->value('id'),
            'label' => 'Notes',
            'type' => 'texto',
            'is_required' => true,
        ]);
        // The original stays untouched.
        $this->assertTrue($template->fresh()?->is_default);
    }

    public function test_template_suggestions_rank_establishment_matches_before_unlinked_templates_and_exclude_other_establishments(): void
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

        $establishment = Establishment::factory()->create(['company_id' => $client->id]);
        $otherEstablishment = Establishment::factory()->create(['company_id' => $client->id]);

        $sameType = WorkOrderType::query()->create(['name' => 'Electrical', 'code' => 'ELEC', 'color' => '#111111']);
        $otherType = WorkOrderType::query()->create(['name' => 'Plumbing', 'code' => 'PLUMB', 'color' => '#222222']);

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'work_order_type_id' => $sameType->id,
        ]);

        $global = FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'Global checklist',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => false,
        ]);
        $linkedOtherType = FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'Linked, other work order type',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => false,
        ]);
        $linkedOtherType->establishments()->attach($establishment->id, ['work_order_type_id' => $otherType->id]);
        $linkedSameType = FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'Linked, matching work order type',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => false,
        ]);
        $linkedSameType->establishments()->attach($establishment->id, ['work_order_type_id' => $sameType->id]);
        $linkedElsewhere = FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'Linked to a different establishment only',
            'form_type_id' => 1,
            'owner_type' => FormTemplateOwnerType::Global,
            'is_default' => false,
        ]);
        $linkedElsewhere->establishments()->attach($otherEstablishment->id);

        $response = $this->actingAs($admin)
            ->getJson(route('forms.template-suggestions', ['work_order_id' => $workOrder->id]))
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertSame([$linkedSameType->id, $linkedOtherType->id, $global->id], $ids);
        $this->assertNotContains($linkedElsewhere->id, $ids);
    }

    public function test_template_suggestions_reject_a_work_order_from_another_company(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $foreignWorkOrder = WorkOrder::factory()->workOrder()->create();

        $this->actingAs($admin)
            ->getJson(route('forms.template-suggestions', ['work_order_id' => $foreignWorkOrder->id]))
            ->assertNotFound();
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

    public function test_advancing_status_fails_when_a_required_field_is_empty(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $form = Form::query()->create([
            'public_id' => 'req-empty-001',
            'form_type_id' => 1,
            'form_status_id' => 1,
            'subject_type' => FormSubjectType::Technician,
            'user_id' => $admin->id,
            'occurred_on' => now()->toDateString(),
            'app_platform_id' => 1,
            'was_edited' => false,
        ]);
        $companyRelationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);
        $form->update(['company_relationship_id' => $companyRelationship->id]);

        $section = FormSection::query()->create([
            'form_id' => $form->id,
            'sort_order' => 0,
            'label' => 'General',
            'is_repeatable' => false,
            'is_modal' => false,
            'is_visible' => true,
        ]);
        FormField::query()->create([
            'form_section_id' => $section->id,
            'sort_order' => 0,
            'type' => 'texto',
            'label' => 'Notes',
            'value' => '',
            'is_required' => true,
            'is_repeatable' => false,
            'is_visible' => true,
            'is_locked' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('forms.advance', $form))
            ->assertSessionHasErrors(['sections.0.fields.0.value']);

        $this->assertSame(1, $form->fresh()?->form_status_id);
    }

    public function test_advancing_status_succeeds_once_required_fields_are_filled(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $companyRelationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);
        $form = Form::query()->create([
            'public_id' => 'req-filled-001',
            'form_type_id' => 1,
            'form_status_id' => 1,
            'subject_type' => FormSubjectType::Technician,
            'company_relationship_id' => $companyRelationship->id,
            'user_id' => $admin->id,
            'occurred_on' => now()->toDateString(),
            'app_platform_id' => 1,
            'was_edited' => false,
        ]);
        $section = FormSection::query()->create([
            'form_id' => $form->id,
            'sort_order' => 0,
            'label' => 'General',
            'is_repeatable' => false,
            'is_modal' => false,
            'is_visible' => true,
        ]);
        FormField::query()->create([
            'form_section_id' => $section->id,
            'sort_order' => 0,
            'type' => 'texto',
            'label' => 'Notes',
            'value' => 'All good',
            'is_required' => true,
            'is_repeatable' => false,
            'is_visible' => true,
            'is_locked' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('forms.advance', $form))
            ->assertRedirect(route('forms.edit', $form))
            ->assertSessionHas('success', 'form_status_advanced_successfully');

        $this->assertSame(2, $form->fresh()?->form_status_id);
    }

    public function test_a_required_field_hidden_by_a_conditional_dependency_is_not_required(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $companyRelationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);
        $form = Form::query()->create([
            'public_id' => 'req-cond-001',
            'form_type_id' => 1,
            'form_status_id' => 1,
            'subject_type' => FormSubjectType::Technician,
            'company_relationship_id' => $companyRelationship->id,
            'user_id' => $admin->id,
            'occurred_on' => now()->toDateString(),
            'app_platform_id' => 1,
            'was_edited' => false,
        ]);
        $section = FormSection::query()->create([
            'form_id' => $form->id,
            'sort_order' => 0,
            'label' => 'General',
            'is_repeatable' => false,
            'is_modal' => false,
            'is_visible' => true,
        ]);
        $trigger = FormField::query()->create([
            'form_section_id' => $section->id,
            'sort_order' => 0,
            'type' => 'si-no',
            'label' => 'Needs follow-up?',
            'value' => 'no',
            'is_required' => false,
            'is_repeatable' => false,
            'is_visible' => true,
            'is_locked' => false,
        ]);
        FormField::query()->create([
            'form_section_id' => $section->id,
            'sort_order' => 1,
            'type' => 'texto',
            'label' => 'Follow-up notes',
            'value' => '',
            'is_required' => true,
            'is_repeatable' => false,
            'is_visible' => true,
            'is_locked' => false,
            'conditional_field_id' => $trigger->id,
            'payload' => ['value' => 'yes'],
        ]);

        $this->actingAs($admin)
            ->post(route('forms.advance', $form))
            ->assertRedirect(route('forms.edit', $form))
            ->assertSessionHas('success', 'form_status_advanced_successfully');

        $this->assertSame(2, $form->fresh()?->form_status_id);
    }

    public function test_a_form_cannot_be_edited_or_advanced_once_it_reaches_its_final_status(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $companyRelationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);
        $form = Form::query()->create([
            'public_id' => 'closed-001',
            'form_type_id' => 1,
            'form_status_id' => 4,
            'subject_type' => FormSubjectType::Technician,
            'company_relationship_id' => $companyRelationship->id,
            'user_id' => $admin->id,
            'occurred_on' => now()->toDateString(),
            'app_platform_id' => 1,
            'was_edited' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('forms.update', $form), [
                'subject_type' => FormSubjectType::Technician->value,
                'company_relationship_id' => $companyRelationship->id,
            ])
            ->assertSessionHasErrors(['form_status_id']);

        $this->actingAs($admin)
            ->post(route('forms.advance', $form))
            ->assertSessionHasErrors(['form_status_id']);
    }

    public function test_uploading_an_image_field_file_stores_it_and_serves_it_back(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $companyRelationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);
        $form = Form::query()->create([
            'public_id' => 'upload-001',
            'form_type_id' => 1,
            'form_status_id' => 1,
            'subject_type' => FormSubjectType::Technician,
            'company_relationship_id' => $companyRelationship->id,
            'user_id' => $admin->id,
            'occurred_on' => now()->toDateString(),
            'app_platform_id' => 1,
            'was_edited' => false,
        ]);
        $section = FormSection::query()->create([
            'form_id' => $form->id,
            'sort_order' => 0,
            'label' => 'General',
            'is_repeatable' => false,
            'is_modal' => false,
            'is_visible' => true,
        ]);
        $field = FormField::query()->create([
            'form_section_id' => $section->id,
            'sort_order' => 0,
            'type' => 'imagen',
            'label' => 'Photo',
            'value' => '',
            'is_required' => false,
            'is_repeatable' => false,
            'is_visible' => true,
            'is_locked' => false,
        ]);

        $file = UploadedFile::fake()->create('photo.jpg', 50, 'image/jpeg');

        $response = $this->actingAs($admin)
            ->post(route('forms.fields.upload', ['form' => $form, 'field' => $field]), [
                'file' => $file,
                'target' => 'value',
            ])
            ->assertOk();

        $path = $response->json('value');
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame($path, $field->fresh()?->value);

        $this->actingAs($admin)
            ->get(route('forms.fields.file', ['form' => $form, 'field' => $field]))
            ->assertOk();
    }

    public function test_completing_a_work_order_form_dispatches_the_completed_event(): void
    {
        Event::fake([FormCompleted::class]);

        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $workOrder = WorkOrder::factory()->workOrder()->create(['owner_company_id' => $owner->id]);
        $form = Form::query()->create([
            'public_id' => 'complete-001',
            'form_type_id' => 1,
            'form_status_id' => 3,
            'subject_type' => FormSubjectType::WorkOrder,
            'work_order_id' => $workOrder->id,
            'user_id' => $admin->id,
            'occurred_on' => now()->toDateString(),
            'app_platform_id' => 1,
            'was_edited' => false,
        ]);

        $this->actingAs($admin)
            ->post(route('forms.advance', $form))
            ->assertRedirect(route('forms.edit', $form));

        $this->assertSame(4, $form->fresh()?->form_status_id);
        Event::assertDispatched(FormCompleted::class, fn (FormCompleted $event): bool => $event->form->is($form));
    }

    public function test_advancing_to_a_non_final_status_does_not_dispatch_the_completed_event(): void
    {
        Event::fake([FormCompleted::class]);

        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $workOrder = WorkOrder::factory()->workOrder()->create(['owner_company_id' => $owner->id]);
        $form = Form::query()->create([
            'public_id' => 'not-final-001',
            'form_type_id' => 1,
            'form_status_id' => 1,
            'subject_type' => FormSubjectType::WorkOrder,
            'work_order_id' => $workOrder->id,
            'user_id' => $admin->id,
            'occurred_on' => now()->toDateString(),
            'app_platform_id' => 1,
            'was_edited' => false,
        ]);

        $this->actingAs($admin)->post(route('forms.advance', $form));

        Event::assertNotDispatched(FormCompleted::class);
    }

    public function test_send_form_completed_notification_emails_the_work_orders_responsible_user(): void
    {
        Mail::fake();
        Storage::fake('local');

        $responsibleUser = User::factory()->create(['email' => 'responsible@example.test']);
        $owner = Company::factory()->create();
        $workOrder = WorkOrder::factory()->workOrder()->create([
            'owner_company_id' => $owner->id,
            'responsible_user_id' => $responsibleUser->id,
        ]);
        $creator = User::factory()->create();
        $form = Form::query()->create([
            'public_id' => 'notify-001',
            'name' => 'Site inspection',
            'form_type_id' => 1,
            'form_status_id' => 4,
            'subject_type' => FormSubjectType::WorkOrder,
            'work_order_id' => $workOrder->id,
            'user_id' => $creator->id,
            'occurred_on' => now()->toDateString(),
            'app_platform_id' => 1,
            'was_edited' => false,
        ]);

        app(SendFormCompletedNotification::class)->handle(new FormCompleted($form));

        Mail::assertSent(
            FormCompletedMail::class,
            fn (FormCompletedMail $mail): bool => $mail->hasTo('responsible@example.test') && $mail->form->is($form),
        );
    }

    public function test_send_form_completed_notification_does_nothing_for_technician_subject_forms(): void
    {
        Mail::fake();

        $companyRelationship = CompanyRelationship::factory()->create([
            'kind' => CompanyRelationshipKind::Technician,
        ]);
        $creator = User::factory()->create();
        $form = Form::query()->create([
            'public_id' => 'notify-tech-001',
            'form_type_id' => 1,
            'form_status_id' => 4,
            'subject_type' => FormSubjectType::Technician,
            'company_relationship_id' => $companyRelationship->id,
            'user_id' => $creator->id,
            'occurred_on' => now()->toDateString(),
            'app_platform_id' => 1,
            'was_edited' => false,
        ]);

        app(SendFormCompletedNotification::class)->handle(new FormCompleted($form));

        Mail::assertNothingSent();
    }

    public function test_downloading_a_forms_pdf_returns_a_pdf_response(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $owner = Company::factory()->create();
        $this->attachToCompany($admin, $owner);

        $companyRelationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);
        $form = Form::query()->create([
            'public_id' => 'pdf-001',
            'name' => 'Weekly checklist',
            'form_type_id' => 1,
            'form_status_id' => 1,
            'subject_type' => FormSubjectType::Technician,
            'company_relationship_id' => $companyRelationship->id,
            'user_id' => $admin->id,
            'occurred_on' => now()->toDateString(),
            'app_platform_id' => 1,
            'was_edited' => false,
        ]);
        $section = FormSection::query()->create([
            'form_id' => $form->id,
            'sort_order' => 0,
            'label' => 'General',
            'is_repeatable' => false,
            'is_modal' => false,
            'is_visible' => true,
        ]);
        FormField::query()->create([
            'form_section_id' => $section->id,
            'sort_order' => 0,
            'type' => 'texto',
            'label' => 'Notes',
            'value' => 'Everything checked out fine.',
            'is_required' => false,
            'is_repeatable' => false,
            'is_visible' => true,
            'is_locked' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('forms.pdf', $form));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
    }
}

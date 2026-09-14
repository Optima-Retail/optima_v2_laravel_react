<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Forms\Enums\FormTemplateOwnerType;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\EstablishmentAttachment;
use App\Models\EstablishmentFormTemplate;
use App\Models\FormTemplate;
use App\Models\FormType;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrderType;
use Database\Seeders\FormTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class EstablishmentAttachmentsAndTemplatesTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(FormTypeSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_upload_public_and_private_attachments_and_sync_template_links(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        [$establishment, $owner] = $this->makeEstablishmentFor($admin);
        $workOrderType = WorkOrderType::query()->create([
            'name' => 'Preventive',
            'code' => 'PREV',
            'color' => '#aabbcc',
        ]);
        $formType = FormType::query()->firstOrFail();
        $template = FormTemplate::query()->create([
            'company_id' => $owner->id,
            'name' => 'Site checklist',
            'form_type_id' => $formType->id,
            'owner_type' => FormTemplateOwnerType::Global->value,
            'is_default' => false,
        ]);

        $this->actingAs($admin)
            ->get("/establishments/{$establishment->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Establishments/Edit')
                ->has('attachments', 0)
                ->where('can.view_attachments', true)
                ->where('can.view_private_attachments', true)
                ->has('workOrderTypeOptions')
                ->has('formTemplateOptions'));

        $publicFile = UploadedFile::fake()->create('public-note.pdf', 40, 'application/pdf');
        $this->actingAs($admin)
            ->post("/establishments/{$establishment->id}/attachments", [
                'file' => $publicFile,
                'is_private' => false,
            ])
            ->assertRedirect(route('establishments.edit', ['establishment' => $establishment, 'tab' => 'attachments']))
            ->assertSessionHas('success', 'establishment_attachment_uploaded_successfully');

        $privateFile = UploadedFile::fake()->create('private-note.pdf', 50, 'application/pdf');
        $this->actingAs($admin)
            ->post("/establishments/{$establishment->id}/attachments", [
                'file' => $privateFile,
                'is_private' => true,
            ])
            ->assertRedirect(route('establishments.edit', ['establishment' => $establishment, 'tab' => 'attachments']));

        $this->assertSame(2, EstablishmentAttachment::query()->where('establishment_id', $establishment->id)->count());
        $private = EstablishmentAttachment::query()
            ->where('establishment_id', $establishment->id)
            ->where('is_private', true)
            ->firstOrFail();
        Storage::disk('local')->assertExists($private->path);

        $this->actingAs($admin)
            ->put("/establishments/{$establishment->id}", [
                'company_id' => $establishment->company_id,
                'name' => $establishment->name,
                'code' => $establishment->code,
                'collaborator_ids' => [],
                'form_template_links' => [
                    [
                        'form_template_id' => $template->id,
                        'work_order_type_id' => $workOrderType->id,
                    ],
                ],
            ])
            ->assertRedirect(route('establishments.edit', $establishment))
            ->assertSessionHas('success', 'establishment_updated_successfully');

        $this->assertDatabaseHas('establishment_form_template', [
            'establishment_id' => $establishment->id,
            'form_template_id' => $template->id,
            'work_order_type_id' => $workOrderType->id,
        ]);
        $this->assertSame(1, EstablishmentFormTemplate::query()->where('establishment_id', $establishment->id)->count());

        $this->actingAs($admin)
            ->get("/establishments/{$establishment->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('attachments', 2)
                ->where('establishment.form_template_links.0.form_template_id', $template->id)
                ->where('establishment.form_template_links.0.work_order_type_id', $workOrderType->id));
    }

    public function test_private_attachments_are_hidden_without_private_permission(): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(RoleEnum::User->value, config('auth.defaults.guard', 'web'));
        $role->syncPermissions([
            'establishments.view',
            'establishments.update',
            'establishments.view-attachments',
            'establishments.upload-attachments',
            'establishments.download-attachments',
            'establishments.delete-attachments',
        ]);
        $user->assignRole($role);

        [$establishment] = $this->makeEstablishmentFor($user);

        $public = $establishment->attachments()->create([
            'name' => 'public.pdf',
            'path' => 'establishments/'.$establishment->id.'/attachments/public.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'is_private' => false,
            'uploaded_by' => $user->id,
        ]);
        $private = $establishment->attachments()->create([
            'name' => 'private.pdf',
            'path' => 'establishments/'.$establishment->id.'/attachments/private.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'is_private' => true,
            'uploaded_by' => $user->id,
        ]);
        Storage::disk('local')->put($public->path, 'pdf');
        Storage::disk('local')->put($private->path, 'secret');

        $this->actingAs($user)
            ->get("/establishments/{$establishment->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Establishments/Edit')
                ->has('attachments', 1)
                ->where('attachments.0.name', 'public.pdf')
                ->where('can.view_private_attachments', false));

        $this->actingAs($user)
            ->post("/establishments/{$establishment->id}/attachments", [
                'file' => UploadedFile::fake()->create('secret2.pdf', 20, 'application/pdf'),
                'is_private' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->get("/establishments/{$establishment->id}/attachments/{$private->id}/download")
            ->assertForbidden();

        $this->actingAs($user)
            ->delete("/establishments/{$establishment->id}/attachments/{$private->id}")
            ->assertForbidden();
    }

    /**
     * @return array{0: Establishment, 1: Company}
     */
    private function makeEstablishmentFor(User $user): array
    {
        $owner = Company::factory()->create();
        $client = Company::factory()->create();
        $this->attachToCompany($user, $owner);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $establishment = Establishment::factory()->create([
            'company_id' => $client->id,
            'name' => 'North Depot',
        ]);

        return [$establishment, $owner];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class EstimateAttachmentsTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_upload_public_and_private_estimate_attachments(): void
    {
        [$admin, $estimate] = $this->makeEstimateForAdmin();

        $this->actingAs($admin)
            ->get("/estimates/{$estimate->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Edit')
                ->has('attachments', 0)
                ->where('can.view_attachments', true)
                ->where('can.view_private_attachments', true));

        $this->actingAs($admin)
            ->post("/estimates/{$estimate->id}/attachments", [
                'file' => UploadedFile::fake()->create('public-note.pdf', 40, 'application/pdf'),
                'is_private' => false,
            ])
            ->assertRedirect(route('estimates.edit', ['estimate' => $estimate, 'tab' => 'attachments']))
            ->assertSessionHas('success', 'estimate_attachment_uploaded_successfully');

        $this->actingAs($admin)
            ->post("/estimates/{$estimate->id}/attachments", [
                'file' => UploadedFile::fake()->create('private-note.pdf', 50, 'application/pdf'),
                'is_private' => true,
            ])
            ->assertRedirect(route('estimates.edit', ['estimate' => $estimate, 'tab' => 'attachments']));

        $this->assertSame(2, WorkOrderAttachment::query()->where('work_order_id', $estimate->id)->count());
        $private = WorkOrderAttachment::query()
            ->where('work_order_id', $estimate->id)
            ->where('is_private', true)
            ->firstOrFail();
        Storage::disk('local')->assertExists($private->path);

        $this->actingAs($admin)
            ->get("/estimates/{$estimate->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Edit')
                ->has('attachments', 2));
    }

    public function test_private_estimate_attachments_are_hidden_without_private_permission(): void
    {
        [$admin, $estimate, $company] = $this->makeEstimateForAdmin();

        $user = User::factory()->create();
        $role = Role::findOrCreate(RoleEnum::User->value, config('auth.defaults.guard', 'web'));
        $role->syncPermissions([
            'estimates.view',
            'estimates.update',
            'estimates.view-attachments',
            'estimates.upload-attachments',
            'estimates.download-attachments',
            'estimates.delete-attachments',
        ]);
        $user->assignRole($role);
        $this->attachToCompany($user, $company);

        $public = $estimate->attachments()->create([
            'name' => 'public.pdf',
            'path' => 'work-orders/'.$estimate->id.'/attachments/public.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'is_private' => false,
            'uploaded_by' => $admin->id,
        ]);
        $private = $estimate->attachments()->create([
            'name' => 'private.pdf',
            'path' => 'work-orders/'.$estimate->id.'/attachments/private.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'is_private' => true,
            'uploaded_by' => $admin->id,
        ]);
        Storage::disk('local')->put($public->path, 'pdf');
        Storage::disk('local')->put($private->path, 'secret');

        $this->actingAs($user)
            ->get("/estimates/{$estimate->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Edit')
                ->has('attachments', 1)
                ->where('attachments.0.name', 'public.pdf')
                ->where('can.view_private_attachments', false));

        $this->actingAs($user)
            ->post("/estimates/{$estimate->id}/attachments", [
                'file' => UploadedFile::fake()->create('secret2.pdf', 20, 'application/pdf'),
                'is_private' => true,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->get("/estimates/{$estimate->id}/attachments/{$private->id}/download")
            ->assertForbidden();

        $this->actingAs($user)
            ->delete("/estimates/{$estimate->id}/attachments/{$private->id}")
            ->assertForbidden();
    }

    public function test_estimate_notes_persist_as_rich_text_with_alerts(): void
    {
        [$admin, $estimate] = $this->makeEstimateForAdmin();

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => $estimate->subject,
                'status_id' => $estimate->status_id,
                'establishment_id' => $estimate->establishment_id,
                'work_order_type_id' => $estimate->work_order_type_id,
                'is_urgent' => false,
                'notes' => '<p>Public <strong>note</strong></p>',
                'internal_notes' => '<p>Private <em>remark</em></p>',
                'notes_alert' => true,
                'internal_notes_alert' => true,
            ])
            ->assertRedirect(route('estimates.edit', $estimate))
            ->assertSessionHas('success', 'estimate_updated_successfully');

        $estimate->refresh();

        $this->assertSame('<p>Public <strong>note</strong></p>', $estimate->notes);
        $this->assertSame('<p>Private <em>remark</em></p>', $estimate->internal_notes);
        $this->assertTrue($estimate->notes_alert);
        $this->assertTrue($estimate->internal_notes_alert);

        $this->actingAs($admin)
            ->get("/estimates/{$estimate->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Edit')
                ->where('estimate.notes', '<p>Public <strong>note</strong></p>')
                ->where('estimate.notes_alert', true)
                ->where('estimate.internal_notes_alert', true));
    }

    /**
     * @return array{0: User, 1: WorkOrder, 2: Company}
     */
    private function makeEstimateForAdmin(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $company = Company::factory()->create();
        $client = Company::factory()->create();
        $this->attachToCompany($admin, $company);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $status = new WorkOrderStatus;
        $status->forceFill([
            'id' => 101,
            'name' => 'Pendiente',
            'kind' => WorkOrderStage::Estimate,
            'lifecycle' => 1,
            'is_open' => true,
            'is_default' => true,
            'confirms_estimate' => false,
            'rejects_to_estimate' => false,
            'is_post_confirm_default' => false,
            'sets_sent_at' => false,
        ])->save();

        $establishment = Establishment::factory()->create([
            'company_id' => $client->id,
            'name' => 'Store A',
        ]);

        $type = WorkOrderType::query()->create([
            'name' => 'Corrective',
            'code' => 'COR',
            'color' => '#aabbcc',
        ]);

        $estimate = WorkOrder::factory()->create([
            'subject' => 'Estimate with files',
            'stage' => WorkOrderStage::Estimate->value,
            'status_id' => $status->id,
            'establishment_id' => $establishment->id,
            'work_order_type_id' => $type->id,
            'is_urgent' => false,
            'code' => 'EST-ATT-1',
        ]);

        return [$admin, $estimate, $company];
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\ContractStatus;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class ContractAttachmentsTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_upload_download_and_delete_contract_attachments(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $contract = $this->makeContractFor($admin);

        $this->actingAs($admin)
            ->get("/contracts/{$contract->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracts/Edit')
                ->has('attachments', 0)
                ->where('can.view_attachments', true)
                ->where('can.upload_attachments', true)
                ->where('can.download_attachments', true)
                ->where('can.delete_attachments', true));

        $file = UploadedFile::fake()->create('contract-note.pdf', 120, 'application/pdf');

        $this->actingAs($admin)
            ->post("/contracts/{$contract->id}/attachments", [
                'file' => $file,
            ])
            ->assertRedirect(route('contracts.edit', ['contract' => $contract, 'tab' => 'attachments']))
            ->assertSessionHas('success', 'contract_attachment_uploaded_successfully');

        $attachment = ContractAttachment::query()->where('contract_id', $contract->id)->firstOrFail();
        $this->assertSame('contract-note.pdf', $attachment->name);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($admin)
            ->get("/contracts/{$contract->id}/attachments/{$attachment->id}/download")
            ->assertOk();

        $this->actingAs($admin)
            ->delete("/contracts/{$contract->id}/attachments/{$attachment->id}")
            ->assertRedirect(route('contracts.edit', ['contract' => $contract, 'tab' => 'attachments']))
            ->assertSessionHas('success', 'contract_attachment_deleted_successfully');

        $this->assertSoftDeleted($attachment);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_user_without_attachment_permissions_is_forbidden(): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(RoleEnum::User->value, config('auth.defaults.guard', 'web'));
        $role->syncPermissions([
            'contracts.view',
            'contracts.update',
        ]);
        $user->assignRole($role);

        $contract = $this->makeContractFor($user);
        $attachment = $contract->attachments()->create([
            'name' => 'secret.pdf',
            'path' => 'contracts/'.$contract->id.'/attachments/secret.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'uploaded_by' => $user->id,
        ]);
        Storage::disk('local')->put($attachment->path, 'pdf');

        $this->actingAs($user)
            ->get("/contracts/{$contract->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contracts/Edit')
                ->has('attachments', 0)
                ->where('can.view_attachments', false)
                ->where('can.upload_attachments', false)
                ->where('can.download_attachments', false)
                ->where('can.delete_attachments', false));

        $this->actingAs($user)
            ->post("/contracts/{$contract->id}/attachments", [
                'file' => UploadedFile::fake()->create('note.pdf', 20, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->get("/contracts/{$contract->id}/attachments/{$attachment->id}/download")
            ->assertForbidden();

        $this->actingAs($user)
            ->delete("/contracts/{$contract->id}/attachments/{$attachment->id}")
            ->assertForbidden();
    }

    private function makeContractFor(User $user): Contract
    {
        $company = Company::factory()->create();
        $client = Company::factory()->create();
        $this->attachToCompany($user, $company);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $status = ContractStatus::query()->create([
            'name' => 'Open',
            'color' => '#f6eac2',
            'lifecycle' => 1,
            'is_open' => true,
        ]);

        return Contract::query()->create([
            'code' => 'C00001',
            'description' => 'With files',
            'company_id' => $client->id,
            'responsible_user_id' => $user->id,
            'contract_status_id' => $status->id,
        ]);
    }
}

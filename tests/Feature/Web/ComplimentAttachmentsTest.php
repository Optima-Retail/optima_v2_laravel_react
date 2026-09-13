<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Compliments\Enums\ComplimentSubjectType;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Compliment;
use App\Models\ComplimentAttachment;
use App\Models\ComplimentType;
use App\Models\Establishment;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\ComplimentTypeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class ComplimentAttachmentsTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ComplimentTypeSeeder::class);
        Storage::fake('local');
    }

    public function test_admin_can_upload_download_and_delete_compliment_attachments(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $compliment = $this->makeComplimentFor($admin);

        $this->actingAs($admin)
            ->get("/compliments/{$compliment->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Compliments/Edit')
                ->has('attachments', 0)
                ->where('can.view_attachments', true)
                ->where('can.upload_attachments', true)
                ->where('can.download_attachments', true)
                ->where('can.delete_attachments', true));

        $file = UploadedFile::fake()->create('note.pdf', 120, 'application/pdf');

        $this->actingAs($admin)
            ->post("/compliments/{$compliment->id}/attachments", [
                'file' => $file,
            ])
            ->assertRedirect(route('compliments.edit', $compliment))
            ->assertSessionHas('success', 'compliment_attachment_uploaded_successfully');

        $attachment = ComplimentAttachment::query()->where('compliment_id', $compliment->id)->firstOrFail();
        $this->assertSame('note.pdf', $attachment->name);
        Storage::disk('local')->assertExists($attachment->path);

        $this->actingAs($admin)
            ->get("/compliments/{$compliment->id}/attachments/{$attachment->id}/download")
            ->assertOk();

        $this->actingAs($admin)
            ->delete("/compliments/{$compliment->id}/attachments/{$attachment->id}")
            ->assertRedirect(route('compliments.edit', $compliment))
            ->assertSessionHas('success', 'compliment_attachment_deleted_successfully');

        $this->assertSoftDeleted($attachment);
        Storage::disk('local')->assertMissing($attachment->path);
    }

    public function test_create_can_include_optional_attachment(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        [, $establishment, $recipient, $type] = $this->seedComplimentContext($admin);

        $response = $this->actingAs($admin)
            ->post(route('compliments.store'), [
                'subject_type' => ComplimentSubjectType::Establishment->value,
                'establishment_id' => $establishment->id,
                'compliment_type_id' => $type->id,
                'comment' => 'With file',
                'user_ids' => [$recipient->id],
                'score' => 2,
                'file' => UploadedFile::fake()->create('create-note.pdf', 40, 'application/pdf'),
            ]);

        $compliment = Compliment::query()->firstOrFail();
        $response->assertRedirect(route('compliments.edit', $compliment));

        $attachment = ComplimentAttachment::query()->where('compliment_id', $compliment->id)->firstOrFail();
        $this->assertSame('create-note.pdf', $attachment->name);
        Storage::disk('local')->assertExists($attachment->path);
    }

    public function test_user_without_attachment_permissions_is_forbidden(): void
    {
        $user = User::factory()->create();
        $role = Role::findOrCreate(RoleEnum::User->value, config('auth.defaults.guard', 'web'));
        $role->syncPermissions([
            'compliments.view',
            'compliments.update',
        ]);
        $user->assignRole($role);

        $compliment = $this->makeComplimentFor($user);
        $attachment = $compliment->attachments()->create([
            'name' => 'secret.pdf',
            'path' => 'compliments/'.$compliment->id.'/attachments/secret.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 10,
            'uploaded_by' => $user->id,
        ]);
        Storage::disk('local')->put($attachment->path, 'pdf');

        $this->actingAs($user)
            ->get("/compliments/{$compliment->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Compliments/Edit')
                ->has('attachments', 0)
                ->where('can.view_attachments', false)
                ->where('can.upload_attachments', false)
                ->where('can.download_attachments', false)
                ->where('can.delete_attachments', false));

        $this->actingAs($user)
            ->post("/compliments/{$compliment->id}/attachments", [
                'file' => UploadedFile::fake()->create('note.pdf', 20, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->get("/compliments/{$compliment->id}/attachments/{$attachment->id}/download")
            ->assertForbidden();
    }

    private function makeComplimentFor(User $user): Compliment
    {
        [, $establishment, $recipient, $type] = $this->seedComplimentContext($user);

        $compliment = Compliment::query()->create([
            'subject_type' => ComplimentSubjectType::Establishment,
            'establishment_id' => $establishment->id,
            'compliment_type_id' => $type->id,
            'comment' => 'Existing',
        ]);

        $compliment->users()->attach($recipient->id, ['score' => 1]);

        return $compliment;
    }

    /**
     * @return array{0: Company, 1: Establishment, 2: User, 3: ComplimentType}
     */
    private function seedComplimentContext(User $actor): array
    {
        $owner = Company::factory()->create();
        $client = Company::factory()->create();
        $this->attachToCompany($actor, $owner);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $owner->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        $establishment = Establishment::factory()->create([
            'company_id' => $client->id,
        ]);

        $recipient = User::factory()->create();
        $this->attachToCompany($recipient, $owner, active: false);

        $type = ComplimentType::query()->findOrFail(1);

        return [$owner, $establishment, $recipient, $type];
    }
}

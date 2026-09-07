<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Brand;
use App\Models\BrandMessage;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class BrandMessagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_send_text_and_file_brand_messages(): void
    {
        Storage::fake('local');

        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $brand = Brand::query()->create([
            'name' => 'ACME',
            'is_quality_control_contactable' => true,
            'send_debt_reminders' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('brands.messages.store', $brand), [
                'body' => 'Kickoff notes',
            ])
            ->assertRedirect(route('brands.edit', $brand))
            ->assertSessionHas('success', 'brand_message_sent_successfully');

        $this->assertDatabaseHas('brand_messages', [
            'brand_id' => $brand->id,
            'user_id' => $admin->id,
            'body' => 'Kickoff notes',
            'type' => 'text',
        ]);

        $file = UploadedFile::fake()->image('photo.png');

        $this->actingAs($admin)
            ->post(route('brands.messages.store', $brand), [
                'file' => $file,
            ])
            ->assertRedirect(route('brands.edit', $brand))
            ->assertSessionHas('success', 'brand_message_sent_successfully');

        $attachment = BrandMessage::query()
            ->where('brand_id', $brand->id)
            ->where('type', 'image')
            ->firstOrFail();

        $this->assertNotNull($attachment->attachment_path);
        Storage::disk('local')->assertExists($attachment->attachment_path);

        $this->actingAs($admin)
            ->get(route('brands.messages.file', [$brand, $attachment]))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('brands.edit', $brand))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Brands/Edit')
                ->has('messages', 2)
                ->where('can.view_messages', true)
                ->where('can.send_messages', true)
                ->where('can.view_message_files', true)
                ->where('can.download_message_files', true)
                ->where('can.send_message_files', true)
            );
    }

    public function test_empty_message_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $brand = Brand::query()->create([
            'name' => 'BETA',
            'is_quality_control_contactable' => true,
            'send_debt_reminders' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('brands.edit', $brand))
            ->post(route('brands.messages.store', $brand), [
                'body' => '   ',
            ])
            ->assertRedirect(route('brands.edit', $brand))
            ->assertSessionHasErrors('body');
    }

    public function test_message_permissions_are_enforced(): void
    {
        Storage::fake('local');

        $brand = Brand::query()->create([
            'name' => 'GAMMA',
            'is_quality_control_contactable' => true,
            'send_debt_reminders' => true,
        ]);

        $editor = User::factory()->create();
        $role = Role::findOrCreate('brand-editor', config('auth.defaults.guard', 'web'));
        $role->syncPermissions([
            'brands.view',
            'brands.update',
            'brands.view-messages',
            'brands.send-messages',
        ]);
        $editor->assignRole($role);

        $author = User::factory()->create();
        $author->assignRole(RoleEnum::Admin->value);

        $attachment = BrandMessage::query()->create([
            'brand_id' => $brand->id,
            'user_id' => $author->id,
            'body' => null,
            'type' => 'pdf',
            'attachment_path' => 'brand-messages/1/doc.pdf',
            'attachment_name' => 'doc.pdf',
        ]);

        Storage::disk('local')->put($attachment->attachment_path, 'pdf-bytes');

        BrandMessage::query()->create([
            'brand_id' => $brand->id,
            'user_id' => $author->id,
            'body' => 'Visible text',
            'type' => 'text',
            'attachment_path' => null,
            'attachment_name' => null,
        ]);

        $this->actingAs($editor)
            ->get(route('brands.edit', $brand))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Brands/Edit')
                ->has('messages', 1)
                ->where('messages.0.body', 'Visible text')
                ->where('can.view_messages', true)
                ->where('can.send_messages', true)
                ->where('can.view_message_files', false)
                ->where('can.download_message_files', false)
                ->where('can.send_message_files', false)
            );

        $this->actingAs($editor)
            ->post(route('brands.messages.store', $brand), [
                'file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            ])
            ->assertForbidden();

        $this->actingAs($editor)
            ->get(route('brands.messages.file', [$brand, $attachment]))
            ->assertForbidden();
    }

    public function test_discovered_brand_message_permissions_appear_on_roles_create(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get(route('config.roles.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Roles/Create')
                ->where('permissionGroups', function (mixed $groups): bool {
                    $groups = collect($groups);

                    $brands = $groups->firstWhere('resource', 'brands');

                    if (! is_array($brands) || ! isset($brands['permissions']) || ! is_array($brands['permissions'])) {
                        return false;
                    }

                    $permissions = $brands['permissions'];

                    return in_array('brands.view-messages', $permissions, true)
                        && in_array('brands.send-messages', $permissions, true)
                        && in_array('brands.view-message-files', $permissions, true)
                        && in_array('brands.download-message-files', $permissions, true)
                        && in_array('brands.send-message-files', $permissions, true);
                })
            );
    }
}

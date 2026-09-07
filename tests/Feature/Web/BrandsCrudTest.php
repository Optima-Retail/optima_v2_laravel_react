<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Brand;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class BrandsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_brands(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);
        $manager = User::factory()->create();
        $collaborator = User::factory()->create();

        $this->actingAs($admin)
            ->get('/brands')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Brands/Index')
                ->has('filters')
                ->has('can.create')
                ->missing('brands'));

        $this->actingAs($admin)
            ->post('/brands', [
                'name' => 'Acme',
                'account_manager_id' => $manager->id,
                'commercial_manager_id' => null,
                'collaborator_ids' => [$collaborator->id],
                'loyalty_meeting_frequency' => 'Monthly',
                'is_quality_control_contactable' => true,
                'send_debt_reminders' => true,
            ])
            ->assertRedirect(route('brands.index'))
            ->assertSessionHas('success', 'brand_created_successfully');

        $brand = Brand::query()->where('name', 'ACME')->firstOrFail();
        $this->assertDatabaseHas('brand_collaborators', [
            'brand_id' => $brand->id,
            'user_id' => $collaborator->id,
        ]);

        $this->actingAs($admin)
            ->getJson('/brands/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $brand->id);

        $this->actingAs($admin)
            ->getJson('/brands/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Acme',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $brand->id);

        $this->actingAs($admin)
            ->put("/brands/{$brand->id}", [
                'name' => 'Acme Europe',
                'account_manager_id' => $manager->id,
                'commercial_manager_id' => $manager->id,
                'collaborator_ids' => [],
                'loyalty_meeting_frequency' => 'Quarterly',
                'is_quality_control_contactable' => false,
                'send_debt_reminders' => false,
            ])
            ->assertRedirect(route('brands.index'))
            ->assertSessionHas('success', 'brand_updated_successfully');

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'ACME EUROPE',
            'commercial_manager_id' => $manager->id,
            'is_quality_control_contactable' => false,
            'send_debt_reminders' => false,
        ]);
        $this->assertDatabaseMissing('brand_collaborators', [
            'brand_id' => $brand->id,
            'user_id' => $collaborator->id,
        ]);

        $this->actingAs($admin)
            ->delete("/brands/{$brand->id}")
            ->assertRedirect(route('brands.index'))
            ->assertSessionHas('success', 'brand_deleted_successfully');

        $this->assertSoftDeleted($brand);
    }

    public function test_user_without_permission_cannot_view_brands(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/brands')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/brands/data')
            ->assertForbidden();
    }

    public function test_brands_remain_listable_without_company_context(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->assertNull($admin->active_company_id);
        $this->assertFalse($admin->companies()->exists());

        $this->actingAs($admin)
            ->get('/brands')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Brands/Index')
                ->where('auth.company', null)
                ->has('auth.companies', 0));
    }
}

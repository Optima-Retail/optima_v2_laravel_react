<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\RatingType;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RatingTypesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_rating_types(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/rating-types')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/RatingTypes/Index')
                ->has('filters')
                ->has('can.create')
                ->missing('ratingTypes'));

        $this->actingAs($admin)
            ->post('/config/rating-types', [
                'name' => 'Estrellas',
                'code' => 'stars',
                'max_score' => 5,
            ])
            ->assertRedirect(route('config.rating-types.index'))
            ->assertSessionHas('success', 'rating_type_created_successfully');

        $ratingType = RatingType::query()->where('code', 'stars')->firstOrFail();

        $this->actingAs($admin)
            ->getJson('/config/rating-types/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $ratingType->id)
            ->assertJsonPath('data.0.code', 'stars');

        $this->actingAs($admin)
            ->getJson('/config/rating-types/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'Estrellas',
                'sort' => [
                    ['field' => 'name', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $ratingType->id);

        $this->actingAs($admin)
            ->put("/config/rating-types/{$ratingType->id}", [
                'name' => 'Stars',
                'code' => 'stars',
                'max_score' => 5,
            ])
            ->assertRedirect(route('config.rating-types.index'))
            ->assertSessionHas('success', 'rating_type_updated_successfully');

        $this->assertDatabaseHas('rating_types', [
            'id' => $ratingType->id,
            'name' => 'Stars',
            'code' => 'stars',
            'max_score' => 5,
        ]);

        $this->actingAs($admin)
            ->delete("/config/rating-types/{$ratingType->id}")
            ->assertRedirect(route('config.rating-types.index'))
            ->assertSessionHas('success', 'rating_type_deleted_successfully');

        $this->assertSoftDeleted($ratingType);
    }

    public function test_user_without_permission_cannot_view_rating_types(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/rating-types')
            ->assertForbidden();

        $this->actingAs($viewer)
            ->getJson('/config/rating-types/data')
            ->assertForbidden();
    }
}

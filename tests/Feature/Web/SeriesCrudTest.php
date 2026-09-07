<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Series;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class SeriesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_series(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/series')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Series/Index')
                ->has('filters')
                ->has('can.create')
                ->has('can.update')
                ->has('can.delete')
                ->missing('series'));

        $this->actingAs($admin)
            ->post('/config/series', [
                'key' => 'A',
                'color' => '#ffffff',
                'is_selectable' => true,
                'credit_note_series_id' => null,
            ])
            ->assertRedirect(route('config.series.index'))
            ->assertSessionHas('success', 'series_created_successfully');

        $series = Series::query()->where('key', 'A')->firstOrFail();

        $credit = Series::query()->create([
            'key' => 'ZETA',
            'color' => '#ffffff',
            'is_selectable' => true,
            'credit_note_series_id' => null,
        ]);

        $this->actingAs($admin)
            ->getJson('/config/series/data')
            ->assertOk()
            ->assertJsonPath('last_row', 2)
            ->assertJsonPath('data.0.key', 'A');

        $this->actingAs($admin)
            ->getJson('/config/series/data?'.http_build_query([
                'page' => 1,
                'size' => 10,
                'search' => 'ZETA',
                'sort' => [
                    ['field' => 'key', 'dir' => 'asc'],
                ],
            ]))
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $credit->id);

        $this->actingAs($admin)
            ->put("/config/series/{$series->id}", [
                'key' => 'A',
                'color' => '#f5f5f5',
                'is_selectable' => true,
                'credit_note_series_id' => $credit->id,
            ])
            ->assertRedirect(route('config.series.index'))
            ->assertSessionHas('success', 'series_updated_successfully');

        $this->assertDatabaseHas('series', [
            'id' => $series->id,
            'color' => '#f5f5f5',
            'credit_note_series_id' => $credit->id,
        ]);

        $this->actingAs($admin)
            ->delete("/config/series/{$series->id}")
            ->assertRedirect(route('config.series.index'))
            ->assertSessionHas('success', 'series_deleted_successfully');

        $this->assertSoftDeleted($series);
    }

    public function test_user_without_permission_cannot_view_series(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/series')
            ->assertForbidden();
    }
}

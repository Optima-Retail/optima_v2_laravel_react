<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\SavedFilters\Enums\SavedFilterPageKey;
use App\Models\SavedFilter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SavedFilterApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upsert_list_set_default_and_delete_saved_filters(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/saved-filters', [
                'name' => 'Open clients',
                'page_key' => SavedFilterPageKey::Clients->value,
                'filters' => [
                    'status' => 'active',
                    'search' => '',
                    'created_from' => '2026-01-01',
                ],
                'is_default' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Open clients')
            ->assertJsonPath('data.is_default', true)
            ->assertJsonPath('data.filters.status', 'active')
            ->assertJsonPath('data.filters.created_from', '2026-01-01')
            ->assertJsonMissingPath('data.filters.search');

        $filterId = (int) SavedFilter::query()->value('id');

        $this->actingAs($user)
            ->postJson('/saved-filters', [
                'name' => 'Blocked',
                'page_key' => SavedFilterPageKey::Clients->value,
                'filters' => ['status' => 'blocked'],
                'is_default' => true,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('saved_filters', [
            'id' => $filterId,
            'is_default' => false,
        ]);

        $this->actingAs($user)
            ->getJson('/saved-filters?page_key='.SavedFilterPageKey::Clients->value)
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->actingAs($other)
            ->getJson('/saved-filters?page_key='.SavedFilterPageKey::Clients->value)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->actingAs($user)
            ->patchJson("/saved-filters/{$filterId}/default", ['is_default' => true])
            ->assertOk()
            ->assertJsonPath('data.is_default', true);

        $this->actingAs($user)
            ->deleteJson("/saved-filters/{$filterId}")
            ->assertOk();

        $this->assertSoftDeleted('saved_filters', ['id' => $filterId]);
    }

    public function test_rejects_config_page_keys(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/saved-filters', [
                'name' => 'Banks',
                'page_key' => 'banks',
                'filters' => ['search' => 'x'],
            ])
            ->assertUnprocessable();
    }
}

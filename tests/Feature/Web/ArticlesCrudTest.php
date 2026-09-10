<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\Article;
use App\Models\Language;
use App\Models\User;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ArticlesCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LanguageSeeder::class);
    }

    public function test_admin_can_manage_articles(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $spanishId = Language::query()->where('code', 'es')->value('id');
        $englishId = Language::query()->where('code', 'en')->value('id');

        $this->actingAs($admin)
            ->get('/config/articles')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Articles/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->get('/config/articles/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Config/Articles/Create')
                ->has('languageOptions')
                ->has('clientOptions'));

        $this->actingAs($admin)
            ->post('/config/articles', [
                'code' => 'TEST-ART',
                'is_deletable' => true,
                'translations' => [
                    [
                        'language_id' => $spanishId,
                        'name' => 'Artículo de prueba',
                        'description' => 'Descripción ES',
                    ],
                    [
                        'language_id' => $englishId,
                        'name' => 'Test article',
                        'description' => 'Description EN',
                    ],
                ],
                'clients' => [],
            ])
            ->assertRedirect(route('config.articles.index'))
            ->assertSessionHas('success', 'article_created_successfully');

        $article = Article::query()->where('code', 'TEST-ART')->firstOrFail();

        $this->assertDatabaseHas('article_languages', [
            'article_id' => $article->id,
            'language_id' => $spanishId,
            'name' => 'Artículo de prueba',
        ]);

        $this->actingAs($admin)
            ->getJson('/config/articles/data')
            ->assertOk()
            ->assertJsonPath('last_row', 1)
            ->assertJsonPath('data.0.id', $article->id)
            ->assertJsonPath('data.0.code', 'TEST-ART')
            ->assertJsonPath('data.0.name', 'Artículo de prueba');

        $this->actingAs($admin)
            ->put("/config/articles/{$article->id}", [
                'code' => 'TEST-ART-2',
                'is_deletable' => true,
                'translations' => [
                    [
                        'language_id' => $spanishId,
                        'name' => 'Actualizado',
                        'description' => 'Nueva descripción',
                    ],
                ],
                'clients' => [],
            ])
            ->assertRedirect(route('config.articles.index'))
            ->assertSessionHas('success', 'article_updated_successfully');

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'code' => 'TEST-ART-2',
        ]);

        $this->actingAs($admin)
            ->delete("/config/articles/{$article->id}")
            ->assertRedirect(route('config.articles.index'))
            ->assertSessionHas('success', 'article_deleted_successfully');

        $this->assertSoftDeleted('articles', ['id' => $article->id]);
    }

    public function test_non_deletable_article_cannot_be_destroyed(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $article = Article::query()->create([
            'code' => 'LOCKED',
            'is_deletable' => false,
        ]);

        $article->languages()->create([
            'language_id' => 1,
            'name' => 'Locked',
            'description' => 'Locked',
        ]);

        $this->actingAs($admin)
            ->delete("/config/articles/{$article->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('articles', [
            'id' => $article->id,
            'deleted_at' => null,
        ]);
    }

    public function test_user_without_permission_cannot_view_articles(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $this->actingAs($viewer)
            ->get('/config/articles')
            ->assertForbidden();
    }
}

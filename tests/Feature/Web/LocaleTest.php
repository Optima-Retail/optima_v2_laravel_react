<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\User;
use App\Support\Locale;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_shares_default_locale_and_supported_locales(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('locale', Locale::DEFAULT)
                ->where('supportedLocales', Locale::options())
            );
    }

    public function test_guest_can_switch_locale_and_persist_it_in_session(): void
    {
        $this->from('/')
            ->put(route('locale.update'), ['locale' => 'es'])
            ->assertRedirect('/')
            ->assertSessionHas(Locale::SESSION_KEY, 'es')
            ->assertCookie(Locale::COOKIE_KEY, 'es');

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'es'));
    }

    public function test_invalid_locale_is_rejected(): void
    {
        $this->from('/')
            ->put(route('locale.update'), ['locale' => 'fr'])
            ->assertRedirect('/')
            ->assertSessionHasErrors('locale');
    }

    public function test_authenticated_user_locale_is_persisted(): void
    {
        $admin = User::factory()->create(['locale' => 'en']);
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->from('/dashboard')
            ->put(route('locale.update'), ['locale' => 'es'])
            ->assertRedirect('/dashboard')
            ->assertSessionHas(Locale::SESSION_KEY, 'es');

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'locale' => 'es',
        ]);

        $this->actingAs($admin->fresh())
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'es')
                ->where('auth.user.locale', 'es')
            );
    }
}

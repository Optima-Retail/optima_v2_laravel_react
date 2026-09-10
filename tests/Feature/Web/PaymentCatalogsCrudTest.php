<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Models\PaymentDocument;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PaymentCatalogsCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_manage_payment_methods(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->get('/config/payment-methods')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Config/PaymentMethods/Index'));

        $this->actingAs($admin)
            ->post('/config/payment-methods', [
                'name' => 'Net 10',
                'due_count' => 1,
                'days' => 10,
                'code' => 'N10',
            ])
            ->assertRedirect(route('config.payment-methods.index'))
            ->assertSessionHas('success', 'payment_method_created_successfully');

        $method = PaymentMethod::query()->where('name', 'Net 10')->firstOrFail();

        $this->actingAs($admin)
            ->put("/config/payment-methods/{$method->id}", [
                'name' => 'Net 10 Updated',
                'due_count' => 1,
                'days' => 10,
                'code' => 'N10',
            ])
            ->assertRedirect(route('config.payment-methods.index'));

        $this->assertDatabaseHas('payment_methods', [
            'id' => $method->id,
            'name' => 'Net 10 Updated',
        ]);
    }

    public function test_admin_can_manage_payment_documents(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $this->actingAs($admin)
            ->post('/config/payment-documents', [
                'name' => 'Wire transfer',
            ])
            ->assertRedirect(route('config.payment-documents.index'))
            ->assertSessionHas('success', 'payment_document_created_successfully');

        $document = PaymentDocument::query()->where('name', 'Wire transfer')->firstOrFail();

        $this->actingAs($admin)
            ->delete("/config/payment-documents/{$document->id}")
            ->assertRedirect(route('config.payment-documents.index'));

        $this->assertSoftDeleted($document);
    }
}

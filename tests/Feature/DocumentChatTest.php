<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Chats\Enums\ChatDocumentType;
use App\Domain\Chats\Services\DocumentChatService;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Establishment;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChat;
use App\Models\WorkOrderChatMessage;
use App\Models\WorkOrderStatus;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class DocumentChatTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_creating_a_work_order_creates_a_chat_row(): void
    {
        [$admin, $establishment] = $this->seedWorkOrderContext();

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'responsible_user_id' => $admin->id,
            'subject' => 'Chat auto-create',
        ]);

        $this->assertDatabaseHas('work_order_chats', [
            'work_order_id' => $workOrder->id,
        ]);
    }

    public function test_admin_can_post_a_message_that_appears_in_the_payload(): void
    {
        [$admin, $establishment] = $this->seedWorkOrderContext();

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'responsible_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->postJson("/document-chats/work_order/{$workOrder->id}/messages", [
                'body' => 'Hello chat',
                'is_private' => false,
            ])
            ->assertCreated()
            ->assertJsonPath('message.body', 'Hello chat')
            ->assertJsonPath('message.type', 'text');

        $this->actingAs($admin)
            ->getJson("/document-chats/work_order/{$workOrder->id}/messages")
            ->assertOk()
            ->assertJsonPath('messages.0.body', 'Hello chat');
    }

    public function test_private_messages_are_hidden_from_non_internal_users(): void
    {
        [$admin, $establishment, $company] = $this->seedWorkOrderContext();
        $admin->forceFill(['is_internal_employee' => true])->save();

        $external = User::factory()->create(['is_internal_employee' => false]);
        $external->assignRole(RoleEnum::Admin->value);
        $this->attachToCompany($external, $company);

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'responsible_user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->postJson("/document-chats/work_order/{$workOrder->id}/messages", [
                'body' => 'Internal only',
                'is_private' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('message.is_private', true);

        $this->actingAs($admin)
            ->postJson("/document-chats/work_order/{$workOrder->id}/messages", [
                'body' => 'Public note',
                'is_private' => false,
            ])
            ->assertCreated();

        $this->actingAs($external)
            ->getJson("/document-chats/work_order/{$workOrder->id}/messages")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Public note')
            ->assertJsonPath('can_see_private', false);

        $this->actingAs($admin)
            ->getJson("/document-chats/work_order/{$workOrder->id}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'messages')
            ->assertJsonPath('can_see_private', true);
    }

    public function test_mute_clears_unread_and_blocks_new_unread_fanout(): void
    {
        [$admin, $establishment, $company] = $this->seedWorkOrderContext();

        $collaborator = User::factory()->create();
        $collaborator->assignRole(RoleEnum::Admin->value);
        $this->attachToCompany($collaborator, $company);

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'responsible_user_id' => $collaborator->id,
        ]);

        $chat = WorkOrderChat::query()->where('work_order_id', $workOrder->id)->firstOrFail();

        $this->actingAs($admin)
            ->postJson("/document-chats/work_order/{$workOrder->id}/messages", [
                'body' => 'Ping',
            ])
            ->assertCreated();

        $this->assertTrue(
            $chat->unreadUsers()->where('users.id', $collaborator->id)->exists(),
        );

        $this->actingAs($collaborator)
            ->postJson("/document-chats/work_order/{$workOrder->id}/mute")
            ->assertOk()
            ->assertJsonPath('is_muted', true);

        $this->assertFalse(
            $chat->fresh()->unreadUsers()->where('users.id', $collaborator->id)->exists(),
        );

        $this->actingAs($admin)
            ->postJson("/document-chats/work_order/{$workOrder->id}/messages", [
                'body' => 'After mute',
            ])
            ->assertCreated();

        $this->assertFalse(
            $chat->fresh()->unreadUsers()->where('users.id', $collaborator->id)->exists(),
        );

        $payload = app(DocumentChatService::class)->payload(
            ChatDocumentType::WorkOrder,
            (int) $workOrder->id,
            $collaborator,
        );

        $this->assertTrue($payload['is_muted']);
        $this->assertFalse($payload['has_unread']);
    }

    public function test_attachment_upload_creates_file_message(): void
    {
        Storage::fake('local');

        [$admin, $establishment] = $this->seedWorkOrderContext();

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'responsible_user_id' => $admin->id,
        ]);

        $file = UploadedFile::fake()->create('notes.pdf', 120, 'application/pdf');

        $response = $this->actingAs($admin)
            ->post("/document-chats/work_order/{$workOrder->id}/messages/attachments", [
                'file' => $file,
                'is_private' => false,
            ], [
                'Accept' => 'application/json',
            ])
            ->assertCreated()
            ->assertJsonPath('message.type', 'file');

        $messageId = $response->json('message.id');
        $this->assertNotNull($messageId);

        $this->assertDatabaseHas('work_order_chat_messages', [
            'id' => $messageId,
            'type' => 'file',
        ]);

        $this->assertDatabaseCount('work_order_chat_message_attachments', 1);
        $this->assertSame(1, WorkOrderChatMessage::query()->whereKey($messageId)->count());
    }

    public function test_status_change_writes_history_row_and_system_chat_message(): void
    {
        [$admin, $establishment] = $this->seedWorkOrderContext();

        $open = WorkOrderStatus::query()->firstOrCreate(
            [
                'name' => 'Recibida - OK por Organizar',
                'kind' => WorkOrderStage::WorkOrder,
            ],
            [
                'color' => '#f6eac2',
                'lifecycle' => 2,
                'is_open' => true,
            ],
        );

        $next = WorkOrderStatus::query()->firstOrCreate(
            [
                'name' => 'En curso',
                'kind' => WorkOrderStage::WorkOrder,
            ],
            [
                'color' => '#c2eaf6',
                'lifecycle' => 3,
                'is_open' => true,
            ],
        );

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'responsible_user_id' => $admin->id,
            'status_id' => $open->id,
            'subject' => 'Status history WO',
        ]);

        $this->actingAs($admin)
            ->put("/work-orders/{$workOrder->id}", [
                'subject' => 'Status history WO',
                'status_id' => $next->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => $workOrder->code,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('status_change_histories', [
            'document_type' => 'work_order',
            'document_id' => $workOrder->id,
            'old_status_id' => $open->id,
            'new_status_id' => $next->id,
            'user_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('work_order_chat_messages', [
            'type' => 'system',
            'body' => 'sistema.global.cambio_estado',
        ]);

        $payload = app(DocumentChatService::class)->payload(
            ChatDocumentType::WorkOrder,
            (int) $workOrder->id,
            $admin,
        );

        $system = collect($payload['messages'])->firstWhere('type', 'system');
        $this->assertNotNull($system);
        $this->assertStringContainsString('En curso', (string) $system['body']);
        $this->assertStringContainsString($admin->name, (string) $system['body']);
    }

    public function test_non_internal_users_still_see_system_history_messages(): void
    {
        [$admin, $establishment, $company] = $this->seedWorkOrderContext();

        $viewer = User::factory()->create(['is_internal_employee' => false]);
        $viewer->assignRole(RoleEnum::Admin->value);
        $this->attachToCompany($viewer, $company);

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'responsible_user_id' => $admin->id,
        ]);

        $chats = app(DocumentChatService::class);
        $chats->postSystem(
            ChatDocumentType::WorkOrder,
            (int) $workOrder->id,
            'sistema.global.cambio_estado',
            [
                'usuario' => $admin->name,
                'estado_origen' => 'A',
                'estado_final' => 'B',
            ],
            isPrivate: true,
            actor: $admin,
        );

        $this->actingAs($admin)
            ->postJson("/document-chats/work_order/{$workOrder->id}/messages", [
                'body' => 'Internal only',
                'is_private' => true,
            ])
            ->assertCreated();

        $payload = $chats->payload(ChatDocumentType::WorkOrder, (int) $workOrder->id, $viewer);

        $this->assertFalse($payload['can_see_private']);
        $this->assertTrue(
            collect($payload['messages'])->contains(fn ($message) => $message['type'] === 'system'),
        );
        $this->assertFalse(
            collect($payload['messages'])->contains(fn ($message) => $message['body'] === 'Internal only'),
        );
    }

    /**
     * @return array{0: User, 1: Establishment, 2: Company}
     */
    private function seedWorkOrderContext(): array
    {
        $admin = User::factory()->create(['is_internal_employee' => true]);
        $admin->assignRole(RoleEnum::Admin->value);

        $company = Company::factory()->create();
        $client = Company::factory()->create();
        $this->attachToCompany($admin, $company);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
        ]);

        WorkOrderStatus::query()->firstOrCreate(
            [
                'name' => 'Recibida - OK por Organizar',
                'kind' => WorkOrderStage::WorkOrder,
            ],
            [
                'color' => '#f6eac2',
                'lifecycle' => 2,
                'is_open' => true,
            ],
        );

        $establishment = Establishment::query()->create([
            'company_id' => $client->id,
            'name' => 'Store Chat',
            'code' => 'SCH',
        ]);

        return [$admin, $establishment, $company];
    }
}

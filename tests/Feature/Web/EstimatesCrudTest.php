<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Domain\Auth\Enums\RoleEnum;
use App\Domain\Companies\Enums\CompanyRelationshipKind;
use App\Domain\Config\NumberingPatterns\Enums\NumberingResource;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Article;
use App\Models\ArticleClient;
use App\Models\ArticleLanguage;
use App\Models\Brand;
use App\Models\Company;
use App\Models\CompanyRelationship;
use App\Models\Currency;
use App\Models\Delegation;
use App\Models\Establishment;
use App\Models\Language;
use App\Models\NumberingPattern;
use App\Models\TaskToPerform;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderStatusTransition;
use App\Models\WorkOrderType;
use Carbon\Carbon;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InteractsWithCompanies;
use Tests\TestCase;

final class EstimatesCrudTest extends TestCase
{
    use InteractsWithCompanies;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(LanguageSeeder::class);
    }

    public function test_admin_can_create_update_and_delete_estimates(): void
    {
        [$admin, $establishment, $pending, , , , , $type] = $this->seedContext();

        $this->actingAs($admin)
            ->get('/estimates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Index')
                ->has('filters')
                ->has('can.create'));

        $this->actingAs($admin)
            ->post('/estimates', [
                'subject' => 'Replace filter',
                'status_id' => $pending->id,
                'establishment_id' => $establishment->id,
                'work_order_type_id' => $type->id,
                'is_urgent' => false,
                'code' => 'EST-100',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'estimate_created_successfully');

        $estimate = WorkOrder::query()->where('subject', 'Replace filter')->firstOrFail();

        $this->assertTrue($estimate->isEstimate());
        $this->assertSame($pending->id, $estimate->status_id);
        $this->assertNull($estimate->confirmed_at);

        $this->actingAs($admin)
            ->getJson('/estimates/data')
            ->assertOk()
            ->assertJsonPath('data.0.id', $estimate->id)
            ->assertJsonPath('data.0.stage', 'estimate');

        $this->actingAs($admin)
            ->get("/estimates/{$estimate->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Edit')
                ->where('estimate.subject', 'Replace filter')
                ->where('estimate.status_is_open', true)
                ->has('estimate.currency_id')
                ->has('estimate.currency_label')
                ->has('estimate.created_at')
                ->where('fields_locked', false)
                ->where('can.update_closed', true));

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Replace filter updated',
                'status_id' => $pending->id,
                'establishment_id' => $establishment->id,
                'work_order_type_id' => $type->id,
                'is_urgent' => true,
                'code' => 'EST-100',
            ])
            ->assertRedirect(route('estimates.edit', $estimate))
            ->assertSessionHas('success', 'estimate_updated_successfully');

        $this->assertDatabaseHas('work_orders', [
            'id' => $estimate->id,
            'subject' => 'Replace filter updated',
            'is_urgent' => true,
            'stage' => 'estimate',
        ]);

        $this->actingAs($admin)
            ->delete("/estimates/{$estimate->id}")
            ->assertRedirect(route('estimates.index'))
            ->assertSessionHas('success', 'estimate_deleted_successfully');

        $this->assertSoftDeleted($estimate);
    }

    public function test_create_page_exposes_enriched_establishment_options(): void
    {
        [$admin, $establishment, , , , , , , $currency] = $this->seedContext();

        $this->actingAs($admin)
            ->get('/estimates/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Create')
                ->where('establishmentOptions.0.id', $establishment->id)
                ->where('establishmentOptions.0.company_name', 'Client Co')
                ->where('establishmentOptions.0.brand_name', 'Acme Brand')
                ->where('establishmentOptions.0.currency_id', $currency->id)
                ->where('establishmentOptions.0.currency_label', 'Euro')
                ->where('establishmentOptions.0.company_logo_url', null));
    }

    public function test_estimate_create_auto_assigns_default_status_due_at_and_currency(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        [$admin, $establishment, $pending, , , , , $type, $currency] = $this->seedContext();

        $this->actingAs($admin)
            ->post('/estimates', [
                'subject' => 'Auto defaults',
                'establishment_id' => $establishment->id,
                'work_order_type_id' => $type->id,
                'is_urgent' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'estimate_created_successfully');

        $estimate = WorkOrder::query()->where('subject', 'Auto defaults')->firstOrFail();

        $this->assertSame($pending->id, $estimate->status_id);
        $this->assertSame($currency->id, $estimate->currency_id);
        $this->assertSame($establishment->delegation_id, $estimate->delegation_id);
        $this->assertNotNull($estimate->due_at);
        $this->assertTrue($estimate->due_at->equalTo(Carbon::parse('2026-09-16 10:00:00')));

        Carbon::setTestNow();
    }

    public function test_estimate_codes_increment_even_when_client_sends_peeked_code(): void
    {
        Carbon::setTestNow('2026-09-15 10:00:00');

        [$admin, $establishment, , , , , $company, $type] = $this->seedContext();

        $this->actingAs($admin)
            ->put('/config/numbering-patterns/resource/estimates', [
                'segments' => [
                    ['type' => 'letters', 'value' => 'PR'],
                    ['type' => 'year', 'digit_length' => 2],
                    ['type' => 'letters', 'value' => '/'],
                    ['type' => 'sequence', 'digit_length' => 5],
                ],
                'reset_yearly' => false,
                'is_active' => true,
            ])
            ->assertRedirect();

        $peeked = 'PR26/00001';

        $this->actingAs($admin)
            ->post('/estimates', [
                'subject' => 'First numbered',
                'establishment_id' => $establishment->id,
                'work_order_type_id' => $type->id,
                'is_urgent' => false,
                'code' => $peeked,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'estimate_created_successfully');

        $this->actingAs($admin)
            ->post('/estimates', [
                'subject' => 'Second numbered',
                'establishment_id' => $establishment->id,
                'work_order_type_id' => $type->id,
                'is_urgent' => false,
                'code' => $peeked,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'estimate_created_successfully');

        $first = WorkOrder::query()->where('subject', 'First numbered')->firstOrFail();
        $second = WorkOrder::query()->where('subject', 'Second numbered')->firstOrFail();

        $this->assertSame('PR26/00001', $first->code);
        $this->assertSame('PR26/00002', $second->code);

        $pattern = NumberingPattern::query()
            ->where('company_id', $company->id)
            ->where('resource', NumberingResource::Estimates->value)
            ->firstOrFail();

        $this->assertSame(2, $pattern->last_sequence);

        Carbon::setTestNow();
    }

    public function test_estimate_create_seeds_task_from_subject_and_edit_exposes_tasks(): void
    {
        [$admin, $establishment, , , , , , $type] = $this->seedContext();

        $this->actingAs($admin)
            ->post('/estimates', [
                'subject' => 'Replace HVAC filter',
                'establishment_id' => $establishment->id,
                'work_order_type_id' => $type->id,
                'is_urgent' => false,
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'estimate_created_successfully');

        $estimate = WorkOrder::query()->where('subject', 'Replace HVAC filter')->firstOrFail();

        $this->assertDatabaseHas('tasks_to_perform', [
            'document_id' => $estimate->id,
            'document_type' => 'estimate',
            'title' => 'Replace HVAC filter',
            'description' => 'Replace HVAC filter',
            'is_completed' => 0,
        ]);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Replace HVAC filter',
                'status_id' => $estimate->status_id,
                'establishment_id' => $establishment->id,
                'work_order_type_id' => $type->id,
                'is_urgent' => false,
                'code' => $estimate->code,
                'tasks' => [
                    [
                        'title' => 'Inspect unit',
                        'description' => 'Check airflow',
                        'is_completed' => false,
                    ],
                    [
                        'title' => 'Replace filter',
                        'description' => 'Install new filter',
                        'is_completed' => false,
                    ],
                ],
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $this->assertSame(
            2,
            TaskToPerform::query()
                ->where('document_id', $estimate->id)
                ->where('document_type', 'estimate')
                ->count(),
        );
        $this->assertDatabaseHas('tasks_to_perform', [
            'document_id' => $estimate->id,
            'document_type' => 'estimate',
            'title' => 'Inspect unit',
        ]);

        $this->actingAs($admin)
            ->get("/estimates/{$estimate->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Edit')
                ->has('estimate.tasks', 2)
                ->where('estimate.tasks.0.title', 'Inspect unit'));
    }

    public function test_estimate_billing_line_articles_come_from_article_clients(): void
    {
        [$admin, $establishment, $pending, , , , $company, $type] = $this->seedContext();

        $relationship = CompanyRelationship::query()
            ->where('owner_company_id', $company->id)
            ->where('related_company_id', $establishment->company_id)
            ->where('kind', CompanyRelationshipKind::Customer)
            ->firstOrFail();

        $linked = Article::query()->create(['code' => 'CLI-ART', 'is_deletable' => true]);
        $other = Article::query()->create(['code' => 'OTHER-ART', 'is_deletable' => true]);

        $languageId = (int) Language::query()->where('code', 'en')->value('id');

        ArticleLanguage::query()->create([
            'article_id' => $linked->id,
            'language_id' => $languageId,
            'name' => 'Client article',
            'description' => 'From client catalog',
        ]);
        ArticleLanguage::query()->create([
            'article_id' => $other->id,
            'language_id' => $languageId,
            'name' => 'Other article',
            'description' => 'Not for this client',
        ]);

        ArticleClient::query()->create([
            'article_id' => $linked->id,
            'company_relationship_id' => $relationship->id,
            'sale_price' => 42.50,
        ]);

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'work_order_type_id' => $type->id,
            'subject' => 'Articles scoped',
            'code' => 'EST-ART',
        ]);

        $this->actingAs($admin)
            ->get("/estimates/{$estimate->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Edit')
                ->has('articleOptions', 1)
                ->where('articleOptions.0.id', $linked->id)
                ->where('articleOptions.0.unit_price', '42.50')
                ->where('articleOptions.0.description', 'From client catalog')
                ->where('articleOptions.0.code', 'CLI-ART'));

        $this->assertDatabaseHas('articles', ['id' => $other->id]);
    }

    public function test_admin_can_download_estimate_pdf(): void
    {
        [$admin, $establishment, $pending, , , , , $type] = $this->seedContext();

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'is_estimate' => true,
            'is_work_order' => false,
            'work_order_type_id' => $type->id,
            'subject' => 'PDF estimate',
            'code' => 'EST-PDF-1',
            'estimate_num' => 'EST-PDF-1',
        ]);

        $response = $this->actingAs($admin)
            ->get(route('estimates.pdf', $estimate));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('inline', (string) $response->headers->get('content-disposition'));
        $this->assertNotEmpty($response->getContent());
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_pure_work_order_cannot_download_estimate_pdf(): void
    {
        [$admin, $establishment, , , $received, , , $type] = $this->seedContext();

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $received->id,
            'work_order_type_id' => $type->id,
            'subject' => 'Not an estimate',
            'code' => 'OT-NO-PDF',
        ]);

        $this->actingAs($admin)
            ->get('/estimates/'.$workOrder->id.'/pdf')
            ->assertNotFound();
    }

    public function test_approving_an_estimate_confirms_it_and_redirects_to_work_orders(): void
    {
        [$admin, $establishment, $pending, $approved, $received] = $this->seedContext();

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Quote to confirm',
            'code' => 'PR26/00001',
        ]);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Quote to confirm',
                'status_id' => $approved->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => $estimate->code,
            ])
            ->assertRedirect(route('estimates.edit', $estimate))
            ->assertSessionHas('success', 'work_order_confirmed_successfully');

        $estimate->refresh();

        $this->assertTrue($estimate->isConfirmedWorkOrder());
        $this->assertSame($received->id, $estimate->status_id);
        $this->assertNotNull($estimate->confirmed_at);
        $this->assertTrue($estimate->is_estimate);
        $this->assertTrue($estimate->is_work_order);
        $this->assertSame('PR26/00001', $estimate->estimate_num);
        $this->assertNotNull($estimate->work_order_num);
        $this->assertSame($estimate->work_order_num, $estimate->code);
        $this->assertSame('Quote to confirm', (string) $estimate->subject);
        $this->assertNotSame(7, $approved->id);
        $this->assertNotSame(14, $received->id);
    }

    public function test_entering_sent_and_closed_statuses_stamps_dates_without_legacy_ids(): void
    {
        [$admin, $establishment, $pending] = $this->seedContext();
        $sent = $this->makeStatus(606, WorkOrderStage::Estimate, 'Sent to client', 5, true, ['sets_sent_at' => true]);
        $closed = $this->makeStatus(505, WorkOrderStage::Estimate, 'Closed estimate', 9, false);

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Stamp dates',
            'code' => 'EST-SENT',
        ]);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Stamp dates',
                'status_id' => $sent->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-SENT',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertNotNull($estimate->sent_at);
        $this->assertNull($estimate->closed_at);
        $this->assertNotSame(5, $sent->id);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Stamp dates',
                'status_id' => $closed->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-SENT',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertNotNull($estimate->closed_at);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Stamp dates reopened',
                'status_id' => $pending->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-SENT',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertNull($estimate->closed_at);
        $this->assertSame('Stamp dates reopened', $estimate->subject);
    }

    public function test_closed_estimate_rejects_field_changes_without_update_closed(): void
    {
        [$admin, $establishment, $pending, , , , $company] = $this->seedContext();
        $closed = $this->makeStatus(505, WorkOrderStage::Estimate, 'Closed estimate', 9, false);

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $closed->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Locked quote',
            'code' => 'EST-LOCK',
        ]);

        $editor = User::factory()->create();
        $editor->givePermissionTo(['estimates.view', 'estimates.update', 'estimates.create']);
        $this->attachToCompany($editor, $company);

        $this->actingAs($editor)
            ->from("/estimates/{$estimate->id}/edit")
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Hacked subject',
                'status_id' => $closed->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-LOCK',
            ])
            ->assertRedirect("/estimates/{$estimate->id}/edit")
            ->assertSessionHasErrors('subject');

        $estimate->refresh();
        $this->assertSame('Locked quote', $estimate->subject);

        $this->actingAs($editor)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Locked quote',
                'status_id' => $pending->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-LOCK',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertSame($pending->id, $estimate->status_id);
        $this->assertSame('Locked quote', $estimate->subject);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Admin can edit closed',
                'status_id' => $closed->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-LOCK',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertSame('Admin can edit closed', $estimate->subject);
    }

    public function test_forbidden_status_transition_is_rejected_and_justification_is_required(): void
    {
        [$admin, $establishment, $pending] = $this->seedContext();
        $sent = $this->makeStatus(606, WorkOrderStage::Estimate, 'Sent to client', 5, true, ['sets_sent_at' => true]);
        $other = $this->makeStatus(707, WorkOrderStage::Estimate, 'Other estimate', 8, true);

        WorkOrderStatusTransition::query()->create([
            'from_status_id' => $pending->id,
            'to_status_id' => $sent->id,
            'requires_confirmation' => false,
            'requires_justification' => true,
        ]);

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Transition check',
            'code' => 'EST-TR',
        ]);

        $this->actingAs($admin)
            ->from("/estimates/{$estimate->id}/edit")
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Transition check',
                'status_id' => $other->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-TR',
            ])
            ->assertRedirect("/estimates/{$estimate->id}/edit")
            ->assertSessionHasErrors('status_id');

        $this->actingAs($admin)
            ->from("/estimates/{$estimate->id}/edit")
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Transition check',
                'status_id' => $sent->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-TR',
            ])
            ->assertRedirect("/estimates/{$estimate->id}/edit")
            ->assertSessionHasErrors('status_justification');

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Transition check',
                'status_id' => $sent->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => 'EST-TR',
                'status_justification' => 'Client asked to send this quote.',
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();
        $this->assertSame($sent->id, $estimate->status_id);
        $this->assertNotNull($estimate->sent_at);
    }

    public function test_admin_can_bulk_change_estimate_status(): void
    {
        [$admin, $establishment, $pending] = $this->seedContext();
        $sent = $this->makeStatus(616, WorkOrderStage::Estimate, 'Sent bulk', 5, true, ['sets_sent_at' => true]);

        WorkOrderStatusTransition::query()->create([
            'from_status_id' => $pending->id,
            'to_status_id' => $sent->id,
            'requires_confirmation' => false,
            'requires_justification' => false,
        ]);

        $first = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Bulk one',
            'code' => 'EST-B1',
        ]);
        $second = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Bulk two',
            'code' => 'EST-B2',
        ]);

        $this->actingAs($admin)
            ->get('/estimates')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Index')
                ->has('statusOptions'));

        $this->actingAs($admin)
            ->postJson('/estimates/bulk-status', [
                'ids' => [$first->id, $second->id],
                'status_id' => $sent->id,
            ])
            ->assertOk()
            ->assertJsonPath('updated', 2)
            ->assertJsonPath('failed', []);

        $this->assertSame($sent->id, $first->fresh()->status_id);
        $this->assertSame($sent->id, $second->fresh()->status_id);
        $this->assertNotNull($first->fresh()->sent_at);
    }

    public function test_estimate_routes_reject_confirmed_work_orders(): void
    {
        [$admin, $establishment, , , $received] = $this->seedContext();

        $workOrder = WorkOrder::factory()->workOrder()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $received->id,
            'subject' => 'Already OT',
        ]);

        $this->actingAs($admin)
            ->get("/estimates/{$workOrder->id}/edit")
            ->assertNotFound();
    }

    public function test_confirmed_estimate_remains_visible_in_estimates_list(): void
    {
        [$admin, $establishment, $pending, $approved, $received] = $this->seedContext();

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'subject' => 'Quote stays listed',
            'code' => 'PR-LIST-1',
            'owner_company_id' => $admin->fresh()?->active_company_id,
        ]);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Quote stays listed',
                'status_id' => $approved->id,
                'establishment_id' => $establishment->id,
                'is_urgent' => false,
                'code' => $estimate->code,
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $estimate->refresh();

        $this->assertTrue($estimate->is_estimate);
        $this->assertTrue($estimate->is_work_order);
        $this->assertTrue($estimate->isConfirmedWorkOrder());

        $this->actingAs($admin)
            ->getJson('/estimates/data?pending=')
            ->assertOk()
            ->assertJsonFragment(['id' => $estimate->id, 'is_estimate' => true]);

        $this->actingAs($admin)
            ->get("/estimates/{$estimate->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Estimates/Edit')
                ->where('confirmed_as_work_order', true)
                ->where('fields_locked', true)
                ->where('related_work_order_url', route('work-orders.edit', $estimate))
                ->where('can.update', false));

        $this->actingAs($admin)
            ->get("/work-orders/{$estimate->id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('WorkOrders/Edit')
                ->where('related_estimate_url', route('estimates.edit', $estimate)));
    }

    public function test_user_without_permission_cannot_view_estimates(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole(RoleEnum::User->value);

        $company = Company::factory()->create();
        $this->attachToCompany($viewer, $company);

        $this->actingAs($viewer)
            ->get('/estimates')
            ->assertForbidden();
    }

    public function test_technician_search_returns_active_technicians_for_owner(): void
    {
        [$admin, $establishment, , , , , $company] = $this->seedContext();

        $techCompany = Company::factory()->create([
            'name' => 'Tech Co',
            'tradename' => 'FastFix',
            'phone' => '600111222',
            'is_active' => true,
            'latitude' => 40.42,
            'longitude' => -3.70,
        ]);

        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => $techCompany->id,
            'kind' => CompanyRelationshipKind::Technician,
            'optima_score' => 8.5,
            'customer_score' => 9.0,
            'average_score' => 8.75,
            'has_health_and_safety' => true,
        ]);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => Company::factory()->create(['name' => 'Other Tech', 'is_active' => false])->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $establishment->forceFill([
            'latitude' => 40.4168,
            'longitude' => -3.7038,
        ])->save();

        DB::table('establishment_technician_blacklist')->insert([
            'establishment_id' => $establishment->id,
            'company_relationship_id' => $relationship->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($admin)
            ->getJson('/estimates/technician-search?'.http_build_query([
                'establishment_id' => $establishment->id,
                'tab' => 'all',
                'search' => 'FastFix',
            ]))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $relationship->id)
            ->assertJsonPath('data.0.label', 'FastFix')
            ->assertJsonPath('data.0.phone', '600111222')
            ->assertJsonPath('data.0.is_blacklisted', true)
            ->assertJsonPath('meta.establishment_id', $establishment->id)
            ->assertJsonPath('meta.has_coordinates', true)
            ->assertJsonPath('data.0.distance_km', 0.5);
    }

    public function test_readding_a_soft_deleted_technician_restores_the_row(): void
    {
        [$admin, $establishment, $pending, , , , $company, $type] = $this->seedContext();

        $techCompany = Company::factory()->create(['name' => 'Restore Tech', 'is_active' => true]);
        $relationship = CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => $techCompany->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $this->actingAs($admin)
            ->post('/estimates', [
                'subject' => 'Technician restore',
                'status_id' => $pending->id,
                'establishment_id' => $establishment->id,
                'work_order_type_id' => $type->id,
                'is_urgent' => false,
                'technicians' => [
                    [
                        'company_relationship_id' => $relationship->id,
                        'is_selected' => true,
                        'quote_net_amount' => 10,
                        'quoted_at' => '2026-09-15',
                        'quote_total_euros' => 10,
                    ],
                ],
            ])
            ->assertRedirect();

        $estimate = WorkOrder::query()->where('subject', 'Technician restore')->firstOrFail();
        $row = $estimate->technicians()->firstOrFail();
        $row->delete();

        $this->assertSoftDeleted($row);

        $this->actingAs($admin)
            ->put("/estimates/{$estimate->id}", [
                'subject' => 'Technician restore',
                'status_id' => $pending->id,
                'establishment_id' => $establishment->id,
                'work_order_type_id' => $type->id,
                'is_urgent' => false,
                'technicians' => [
                    [
                        'company_relationship_id' => $relationship->id,
                        'is_selected' => true,
                        'quote_net_amount' => 28,
                        'quoted_at' => '2026-09-16',
                        'quote_total_euros' => 28,
                    ],
                ],
            ])
            ->assertRedirect(route('estimates.edit', $estimate));

        $restored = $estimate->technicians()->where('company_relationship_id', $relationship->id)->first();
        $this->assertNotNull($restored);
        $this->assertNull($restored->deleted_at);
        $this->assertSame('28.00', (string) $restored->quote_net_amount);
        $this->assertSame($row->id, $restored->id);
    }

    public function test_estimate_header_totals_follow_billing_lines_and_selected_technician(): void
    {
        [$admin, $establishment, $pending, , , , $company, $type] = $this->seedContext();

        $article = Article::query()->create([
            'code' => 'HDR-ART',
            'is_deletable' => true,
        ]);

        $techCompany = Company::factory()->create(['name' => 'Cost Tech', 'is_active' => true]);
        $selected = CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => $techCompany->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);
        $otherTech = Company::factory()->create(['name' => 'Other Tech', 'is_active' => true]);
        $unselected = CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => $otherTech->id,
            'kind' => CompanyRelationshipKind::Technician,
        ]);

        $this->actingAs($admin)
            ->post('/estimates', [
                'subject' => 'Header totals',
                'status_id' => $pending->id,
                'establishment_id' => $establishment->id,
                'work_order_type_id' => $type->id,
                'is_urgent' => false,
                'lines' => [
                    [
                        'article_id' => $article->id,
                        'description' => 'Line 1',
                        'quantity' => 2,
                        'unit_price' => 50,
                    ],
                    [
                        'article_id' => $article->id,
                        'description' => 'Line 2',
                        'quantity' => 1,
                        'unit_price' => 25,
                    ],
                ],
                'technicians' => [
                    [
                        'company_relationship_id' => $selected->id,
                        'is_selected' => true,
                        'quote_net_amount' => 40,
                        'quote_total_euros' => 40,
                    ],
                    [
                        'company_relationship_id' => $unselected->id,
                        'is_selected' => false,
                        'quote_net_amount' => 999,
                        'quote_total_euros' => 999,
                    ],
                ],
            ])
            ->assertRedirect();

        $estimate = WorkOrder::query()->where('subject', 'Header totals')->firstOrFail();

        $this->assertSame('125.00', (string) $estimate->net_amount);
        $this->assertSame('125.00', (string) $estimate->total_euros);
        $this->assertSame('40.00', (string) $estimate->cost_amount);
        $this->assertSame('85.00', (string) $estimate->margin_amount);
    }

    /**
     * @return array{0: User, 1: Establishment, 2: WorkOrderStatus, 3: WorkOrderStatus, 4: WorkOrderStatus, 5: WorkOrderStatus, 6: Company, 7: WorkOrderType}
     */
    private function seedContext(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleEnum::Admin->value);

        $company = Company::factory()->create();
        $client = Company::factory()->create(['name' => 'Client Co']);
        $this->attachToCompany($admin, $company);

        $brand = Brand::query()->create(['name' => 'Acme Brand']);

        CompanyRelationship::factory()->create([
            'owner_company_id' => $company->id,
            'related_company_id' => $client->id,
            'kind' => CompanyRelationshipKind::Customer,
            'brand_id' => $brand->id,
        ]);

        $pending = $this->makeStatus(
            101,
            WorkOrderStage::Estimate,
            'Pendiente',
            1,
            true,
            ['is_default' => true],
        );
        $approved = $this->makeStatus(
            202,
            WorkOrderStage::Estimate,
            'Aprobado',
            6,
            false,
            ['confirms_estimate' => true],
        );
        $received = $this->makeStatus(
            303,
            WorkOrderStage::WorkOrder,
            'Recibida - OK por Organizar',
            2,
            true,
            ['is_post_confirm_default' => true],
        );
        $rejected = $this->makeStatus(
            404,
            WorkOrderStage::WorkOrder,
            'Rechazada - Presupuesto',
            2,
            false,
            ['rejects_to_estimate' => true],
        );

        $currency = Currency::query()->create([
            'name' => 'Euro',
            'code' => 'EUR',
        ]);

        $delegation = Delegation::query()->create([
            'name' => 'Madrid',
            'company_id' => $client->id,
            'currency_id' => $currency->id,
            'cost_includes_vat' => false,
            'recovers_vat' => true,
        ]);

        $establishment = Establishment::query()->create([
            'company_id' => $client->id,
            'delegation_id' => $delegation->id,
            'name' => 'Store 1',
            'code' => 'S1',
        ]);

        $type = WorkOrderType::query()->create([
            'name' => 'Corrective',
            'code' => 'COR',
            'color' => '#aabbcc',
        ]);

        return [$admin, $establishment, $pending, $approved, $received, $rejected, $company, $type, $currency];
    }

    /**
     * @param  array<string, bool>  $flags
     */
    private function makeStatus(
        int $id,
        WorkOrderStage $kind,
        string $name,
        int $lifecycle,
        bool $isOpen,
        array $flags = [],
    ): WorkOrderStatus {
        $status = new WorkOrderStatus;
        $status->forceFill([
            'id' => $id,
            'name' => $name,
            'kind' => $kind,
            'lifecycle' => $lifecycle,
            'is_open' => $isOpen,
            'is_default' => $flags['is_default'] ?? false,
            'confirms_estimate' => $flags['confirms_estimate'] ?? false,
            'rejects_to_estimate' => $flags['rejects_to_estimate'] ?? false,
            'is_post_confirm_default' => $flags['is_post_confirm_default'] ?? false,
            'sets_sent_at' => $flags['sets_sent_at'] ?? false,
        ])->save();

        return $status;
    }
}

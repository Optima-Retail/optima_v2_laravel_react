<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\QualityScores\Enums\QualityActionId;
use App\Domain\QualityScores\Enums\QualityScoreDocumentType;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Domain\WorkOrders\Services\WorkOrderService;
use App\Models\Company;
use App\Models\Establishment;
use App\Models\User;
use App\Models\UserActionScore;
use App\Models\WorkOrder;
use App\Models\WorkOrderStatus;
use Database\Seeders\ActionSeeder;
use Database\Seeders\KpiConfigurationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class QualityScoreWorkOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ActionSeeder::class);
        $this->seed(KpiConfigurationSeeder::class);
    }

    public function test_sending_an_estimate_writes_user_action_score_and_credits_quality_score(): void
    {
        $responsible = User::factory()->create(['quality_score' => 0]);
        $pending = $this->makeStatus(1, WorkOrderStage::Estimate, true);
        $sent = $this->makeStatus(5, WorkOrderStage::Estimate, true);
        $establishment = Establishment::factory()->create();

        $estimate = WorkOrder::factory()->create([
            'establishment_id' => $establishment->id,
            'status_id' => $pending->id,
            'stage' => WorkOrderStage::Estimate,
            'responsible_user_id' => $responsible->id,
            'subject' => 'Quote to send',
            'created_at' => now()->subHours(10),
        ]);

        $owner = $establishment->company;
        $service = app(WorkOrderService::class);

        // Ensure company access path: use party company as owner-accessible via attachment
        // WorkOrderService.create/update needs ActiveCompany owner — call processor path via update with fake owner
        $operating = Company::factory()->operating()->create();
        $this->actingAs($responsible);

        $service->update($operating, $estimate, [
            'subject' => 'Quote to send',
            'status_id' => $sent->id,
            'establishment_id' => $establishment->id,
            'responsible_user_id' => $responsible->id,
            'is_urgent' => false,
            'code' => $estimate->code,
            'collaborator_ids' => [],
            'lines' => [],
            'technicians' => [],
        ]);

        $estimate->refresh();
        $this->assertNotNull($estimate->sent_at);

        $score = UserActionScore::query()
            ->where('document_type', QualityScoreDocumentType::Estimate->value)
            ->where('document_id', $estimate->id)
            ->where('action_id', QualityActionId::TiempoEnvioPresupuesto->value)
            ->first();

        $this->assertNotNull($score);
        $this->assertSame($responsible->id, $score->user_id);

        $responsible->refresh();
        $this->assertGreaterThan(0, (float) $responsible->quality_score);

        $ledger = $responsible->qualityScoreLedger()->first();
        $this->assertNotNull($ledger);
        $this->assertSame(QualityActionId::TiempoEnvioPresupuesto->value, $ledger->action_id);
        $this->assertSame(0.0, (float) $ledger->previous_score);
        $this->assertSame((float) $responsible->quality_score, (float) $ledger->new_score);
        $this->assertSame((float) $ledger->delta, (float) $ledger->new_score - (float) $ledger->previous_score);
    }

    public function test_manual_quality_score_adjust_updates_balance_like_prod_saldo(): void
    {
        $user = User::factory()->create([
            'quality_score' => 10,
            'balance' => 5,
        ]);

        $user->addQualityScore(3, QualityActionId::CambioPuntuacionQc->value, $user->id);

        $user->refresh();
        $this->assertSame(13.0, (float) $user->quality_score);
        $this->assertSame(8.0, (float) $user->balance);
        $this->assertDatabaseHas('quality_score_ledger', [
            'user_id' => $user->id,
            'action_id' => QualityActionId::CambioPuntuacionQc->value,
            'previous_score' => 10,
            'new_score' => 13,
            'delta' => 3,
        ]);
    }

    private function makeStatus(int $id, WorkOrderStage $kind, bool $isOpen): WorkOrderStatus
    {
        $status = new WorkOrderStatus;
        $status->forceFill([
            'id' => $id,
            'name' => 'Status '.$id,
            'kind' => $kind,
            'lifecycle' => 1,
            'is_open' => $isOpen,
        ])->save();

        return $status;
    }
}

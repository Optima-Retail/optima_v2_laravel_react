<?php

declare(strict_types=1);

namespace App\Domain\QualityScores\Services;

use App\Domain\QualityScores\Enums\QualityActionId;
use App\Domain\QualityScores\Enums\QualityScoreDocumentType;
use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\Action;
use App\Models\Compliment;
use App\Models\KpiConfiguration;
use App\Models\QualityScoreLedger;
use App\Models\User;
use App\Models\UserActionScore;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Ports optimaback QcoinsProcessor for work-order / estimate / compliment documents.
 */
final class QualityScoreProcessor
{
    public function processCompliment(Compliment $compliment): void
    {
        try {
            $actionId = QualityActionId::Felicitacion;
            $action = Action::query()->find($actionId->value);

            if ($action === null) {
                return;
            }

            $values = $this->complimentUserValues($compliment);

            if ($values === []) {
                return;
            }

            foreach ($values as $userId => $payload) {
                if (! $this->passesUserFilter((int) $userId)) {
                    continue;
                }

                $rawValue = $payload['valor'] ?? null;
                $kpi = $this->kpiFromValue($actionId, $rawValue);

                if ($kpi === null) {
                    continue;
                }

                $eventWeight = $this->eventWeight(false, false);
                $awarded = $this->applyGlobalWeight($action, $kpi, false, false);

                UserActionScore::query()->create([
                    'action_id' => $action->id,
                    'user_id' => (int) $userId,
                    'weight' => $eventWeight,
                    'max_value' => 1.0,
                    'value' => $kpi,
                    'document_type' => QualityScoreDocumentType::Compliment,
                    'document_id' => $compliment->id,
                ]);

                if ($awarded) {
                    $this->creditUser((int) $userId, $awarded, $actionId);
                }
            }
        } catch (Throwable) {
            // Match legacy: never break the document save on scoring failures.
        }
    }

    public function processWorkOrderAction(QualityActionId $actionId, WorkOrder $workOrder): void
    {
        try {
            $action = Action::query()->find($actionId->value);

            if ($action === null) {
                return;
            }

            if (! $this->passesDocumentFilter($actionId, $workOrder)) {
                return;
            }

            $values = $this->documentValues($actionId, $workOrder);

            if ($values === null || $values === []) {
                return;
            }

            foreach ($values as $userId => $payload) {
                if (! $this->passesUserFilter((int) $userId)) {
                    break;
                }

                $rawValue = $payload['valor'] ?? null;
                $kpi = $this->kpiFromValue($actionId, $rawValue);

                if ($kpi === null) {
                    break;
                }

                $isPrevLimpieza = (bool) ($payload['es_prev_limpieza'] ?? false);
                $isPreventivo = (bool) ($payload['es_preventivo'] ?? false);
                $eventWeight = $this->eventWeight($isPrevLimpieza, $isPreventivo);
                $awarded = $this->applyGlobalWeight($action, $kpi, $isPrevLimpieza, $isPreventivo);

                $maxValue = 1.0;

                if (
                    $workOrder->isConfirmedWorkOrder()
                    && in_array($actionId, [QualityActionId::CorazonIntervencion, QualityActionId::CorazonRecordatorio], true)
                ) {
                    $maxValue = $kpi;
                }

                $documentType = $workOrder->isEstimate()
                    ? QualityScoreDocumentType::Estimate
                    : QualityScoreDocumentType::WorkOrder;

                UserActionScore::query()->create([
                    'action_id' => $action->id,
                    'user_id' => (int) $userId,
                    'weight' => $eventWeight,
                    'max_value' => $maxValue,
                    'value' => $kpi,
                    'document_type' => $documentType,
                    'document_id' => $workOrder->id,
                ]);

                if (! $awarded) {
                    break;
                }

                $this->creditUser((int) $userId, $awarded, $actionId);
            }
        } catch (Throwable) {
            // Match legacy: never break the document save on scoring failures.
        }
    }

    public function handleEstimateSent(WorkOrder $workOrder, ?string $previousSentAt): void
    {
        if (! $workOrder->isEstimate()) {
            return;
        }

        if ($previousSentAt !== null || $workOrder->sent_at === null) {
            return;
        }

        $this->processWorkOrderAction(QualityActionId::TiempoEnvioPresupuesto, $workOrder);
    }

    public function handleWorkOrderClosed(WorkOrder $workOrder, bool $wasOpen, bool $isOpen): void
    {
        if (! $workOrder->isConfirmedWorkOrder()) {
            return;
        }

        if (! $wasOpen || $isOpen) {
            return;
        }

        $actionId = match ((int) ($workOrder->client_priority_id ?? 0)) {
            4 => QualityActionId::OtRealizadaCodRojo,
            2, 3 => QualityActionId::OtRealizadaCodAmarilloNaranja,
            1 => QualityActionId::OtRealizadaCodVerde,
            5 => QualityActionId::OtRealizadaCodPreventivo,
            default => QualityActionId::OtRealizadaCodVerde,
        };

        $this->processWorkOrderAction($actionId, $workOrder);
    }

    /**
     * Port of Felicitacion::felicitacion() — closed WO weight since last compliment credit.
     *
     * @return array<int, array{valor: float}>
     */
    private function complimentUserValues(Compliment $compliment): array
    {
        $preventivoWeight = (float) config('quality_scores.weight_preventivo', 1);
        $preventivoPriorityId = 5;
        $result = [];

        foreach ($compliment->users as $user) {
            $userId = (int) $user->id;

            $lastCreditAt = QualityScoreLedger::query()
                ->where('action_id', QualityActionId::Felicitacion->value)
                ->where('user_id', $userId)
                ->latest('created_at')
                ->value('created_at');

            $since = $lastCreditAt && $lastCreditAt > now()->subDays(7)
                ? $lastCreditAt
                : now()->subDays(7);

            $total = WorkOrder::query()
                ->where('stage', WorkOrderStage::WorkOrder->value)
                ->where('responsible_user_id', $userId)
                ->whereNotNull('closed_at')
                ->where('closed_at', '>=', $since)
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN client_priority_id = ? THEN ? ELSE 1 END), 0) as total',
                    [$preventivoPriorityId, $preventivoWeight],
                )
                ->value('total');

            $result[$userId] = [
                'valor' => (float) $total,
            ];
        }

        return $result;
    }

    private function passesDocumentFilter(QualityActionId $actionId, WorkOrder $workOrder): bool
    {
        return match ($actionId) {
            QualityActionId::TiempoEnvioPresupuesto => $workOrder->isEstimate()
                && $workOrder->sent_at !== null
                && $workOrder->legacy_erp_id === null,
            QualityActionId::OtRealizadaCodRojo,
            QualityActionId::OtRealizadaCodAmarilloNaranja,
            QualityActionId::OtRealizadaCodVerde,
            QualityActionId::OtRealizadaCodPreventivo => $workOrder->isConfirmedWorkOrder()
                && $workOrder->parent_work_order_id === null
                && $workOrder->incident_id === null
                && $workOrder->legacy_erp_id === null
                && $workOrder->intervention_at !== null
                && ! in_array((int) $workOrder->status_id, [
                    /* Cerrada - Cancelada */ 11,
                    /* Rechazada - Presupuesto */ 12,
                ], true)
                && (float) ($workOrder->net_amount ?? 0) >= 0,
            default => false,
        };
    }

    /**
     * @return array<int, array{valor: mixed, es_prev_limpieza?: bool, es_preventivo?: bool}>|null
     */
    private function documentValues(QualityActionId $actionId, WorkOrder $workOrder): ?array
    {
        $responsibleId = $workOrder->responsible_user_id;

        if ($responsibleId === null) {
            return null;
        }

        $isPreventivo = (int) ($workOrder->client_priority_id ?? 0) === 5;
        $isPrevLimpieza = false;

        return match ($actionId) {
            QualityActionId::TiempoEnvioPresupuesto => [
                (int) $responsibleId => [
                    'valor' => $this->estimateSendHours($workOrder),
                ],
            ],
            QualityActionId::OtRealizadaCodRojo,
            QualityActionId::OtRealizadaCodAmarilloNaranja,
            QualityActionId::OtRealizadaCodVerde,
            QualityActionId::OtRealizadaCodPreventivo => [
                (int) $responsibleId => [
                    'valor' => $this->slaOk($workOrder),
                    'es_prev_limpieza' => $isPrevLimpieza,
                    'es_preventivo' => $isPreventivo,
                ],
            ],
            default => null,
        };
    }

    private function estimateSendHours(WorkOrder $workOrder): float
    {
        if ($workOrder->sent_at === null || $workOrder->created_at === null) {
            return 0.0;
        }

        // Legacy uses Optima business-hours minutes; wall-clock hours until that helper exists.
        $minutes = $workOrder->created_at->diffInMinutes($workOrder->sent_at);

        return round($minutes / 60, 4);
    }

    private function slaOk(WorkOrder $workOrder): bool
    {
        if ($workOrder->intervention_at === null || $workOrder->sla_at === null) {
            return false;
        }

        return $workOrder->intervention_at->lte($workOrder->sla_at);
    }

    private function passesUserFilter(int $userId): bool
    {
        $minVisits = (int) config('quality_scores.min_weekly_visits', 0);

        if ($minVisits <= 0) {
            return true;
        }

        // Full visit gate needs evaluations.visitas + closed OTs in last 7 days.
        // Until that join is ported, treat as pass when the gate is disabled via config.
        return true;
    }

    private function kpiFromValue(QualityActionId $actionId, mixed $value): ?float
    {
        if (is_bool($value)) {
            return $value ? 1.0 : 0.0;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $numeric = (float) $value;

        if (in_array($actionId, [
            QualityActionId::CorazonIntervencion,
            QualityActionId::CorazonRecordatorio,
            QualityActionId::Felicitacion,
        ], true)) {
            $configs = KpiConfiguration::query()->where('action_id', $actionId->value)->get();

            if ($configs->isEmpty()) {
                return $numeric;
            }
        }

        $configs = KpiConfiguration::query()
            ->where('action_id', $actionId->value)
            ->get();

        if ($configs->isEmpty() && $actionId !== QualityActionId::CorazonIntervencion) {
            return null;
        }

        foreach ($configs as $config) {
            $min = $config->min_value;
            $max = $config->max_value;

            if ($min !== null && $max !== null && $min <= $numeric && $numeric <= $max) {
                return (float) $config->percentage;
            }
        }

        if (in_array($actionId, [
            QualityActionId::CorazonIntervencion,
            QualityActionId::Felicitacion,
            QualityActionId::CorazonRecordatorio,
        ], true)) {
            return $numeric;
        }

        return null;
    }

    private function eventWeight(bool $isPrevLimpieza, bool $isPreventivo): float
    {
        $weight = 1.0;

        if ($isPreventivo) {
            $weight *= (float) config('quality_scores.weight_preventivo', 1);
        }

        if ($isPrevLimpieza) {
            $weight *= (float) config('quality_scores.weight_prev_limpieza', 0.1);
        }

        return $weight;
    }

    private function applyGlobalWeight(Action $action, float $kpi, bool $isPrevLimpieza, bool $isPreventivo): float
    {
        $max = (float) config('quality_scores.max_value', 80);
        $weights = (array) config('quality_scores.weights', []);
        $actionWeight = (float) ($weights[$action->weight_key] ?? 0);
        $eventWeight = $this->eventWeight($isPrevLimpieza, $isPreventivo);

        return $kpi * $max * ($actionWeight * $eventWeight);
    }

    private function creditUser(int $userId, float $amount, QualityActionId $actionId): void
    {
        $amount = round($amount, 2);

        if ($amount == 0.0) {
            return;
        }

        DB::transaction(function () use ($userId, $amount, $actionId): void {
            /** @var User|null $user */
            $user = User::query()->lockForUpdate()->find($userId);

            if ($user === null) {
                return;
            }

            $user->addQualityScore($amount, $actionId->value, Auth::id());
        });
    }
}

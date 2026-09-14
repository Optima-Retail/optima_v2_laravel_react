<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\WorkOrders\Enums\WorkOrderStage;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderStatusTransition;
use Illuminate\Database\Seeder;

/**
 * OT + presupuesto statuses from optimaback EstadoOTEnum / EstadoPresupuestoEnum.
 * Legacy IDs preserved. Kind distinguishes the two former catalogs.
 */
final class WorkOrderStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['id' => 1, 'kind' => WorkOrderStage::Estimate, 'name' => 'Pendiente', 'color' => null, 'lifecycle' => 1, 'is_open' => true, 'is_default' => true, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 3, 'kind' => WorkOrderStage::Estimate, 'name' => 'OK Operaciones', 'color' => null, 'lifecycle' => 2, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 4, 'kind' => WorkOrderStage::Estimate, 'name' => 'OK Controllers', 'color' => null, 'lifecycle' => 3, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 115, 'kind' => WorkOrderStage::Estimate, 'name' => 'OK Manager', 'color' => null, 'lifecycle' => 4, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 5, 'kind' => WorkOrderStage::Estimate, 'name' => 'Enviado a Cliente', 'color' => null, 'lifecycle' => 5, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => true],
            ['id' => 6, 'kind' => WorkOrderStage::Estimate, 'name' => 'Negociación / Revisión', 'color' => null, 'lifecycle' => 6, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 7, 'kind' => WorkOrderStage::Estimate, 'name' => 'Aprobado', 'color' => null, 'lifecycle' => 6, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => true, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 8, 'kind' => WorkOrderStage::Estimate, 'name' => 'Rechazado por Precio', 'color' => null, 'lifecycle' => 6, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 9, 'kind' => WorkOrderStage::Estimate, 'name' => 'Rechazado por Cancelación de Trabajos', 'color' => null, 'lifecycle' => 6, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 10, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Abierta - Establecimiento', 'color' => '#f6eac2', 'lifecycle' => 1, 'is_open' => true, 'is_default' => true, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 11, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Cerrada - Cancelada', 'color' => '#edeae5', 'lifecycle' => 2, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 12, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Rechazada - Presupuesto', 'color' => '#edeae5', 'lifecycle' => 2, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => true, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 13, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Agrupar Siguiente Visita', 'color' => '#edeae5', 'lifecycle' => 2, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 14, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Recibida - OK por Organizar', 'color' => '#f6eac2', 'lifecycle' => 2, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => true, 'sets_sent_at' => false],
            ['id' => 15, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Pend.Info Establecimiento', 'color' => '#ffdac1', 'lifecycle' => 3, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 16, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'En Espera de Material', 'color' => '#ffdac1', 'lifecycle' => 3, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 17, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'En Espera del Cliente', 'color' => '#ffdac1', 'lifecycle' => 3, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 18, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'En Progreso', 'color' => '#a9cef0', 'lifecycle' => 3, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 19, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Finalizada - Cliente No informado', 'color' => '#97c1a9', 'lifecycle' => 4, 'is_open' => true, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 20, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Finalizada', 'color' => '#97c1a9', 'lifecycle' => 5, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 21, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Esperando Manager', 'color' => '#dfccf1', 'lifecycle' => 6, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 22, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'OK Controllers', 'color' => '#dfccf1', 'lifecycle' => 6, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 23, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'PO Reclamada', 'color' => '#dfccf1', 'lifecycle' => 7, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 24, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Aceptada', 'color' => '#c7dbda', 'lifecycle' => 7, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 183, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Aceptada No Facturable', 'color' => '#c7dbda', 'lifecycle' => 7, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 25, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Facturada', 'color' => '#c7dbda', 'lifecycle' => 8, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
            ['id' => 132, 'kind' => WorkOrderStage::WorkOrder, 'name' => 'Abonada para Refacturar', 'color' => '#c7dbda', 'lifecycle' => 7, 'is_open' => false, 'is_default' => false, 'confirms_estimate' => false, 'rejects_to_estimate' => false, 'is_post_confirm_default' => false, 'sets_sent_at' => false],
        ];

        foreach ($statuses as $status) {
            WorkOrderStatus::query()->updateOrCreate(
                ['id' => $status['id']],
                [
                    'kind' => $status['kind'],
                    'name' => $status['name'],
                    'color' => $status['color'],
                    'lifecycle' => $status['lifecycle'],
                    'is_open' => $status['is_open'],
                    'is_default' => $status['is_default'],
                    'confirms_estimate' => $status['confirms_estimate'],
                    'rejects_to_estimate' => $status['rejects_to_estimate'],
                    'is_post_confirm_default' => $status['is_post_confirm_default'],
                    'sets_sent_at' => $status['sets_sent_at'],
                ],
            );
        }

        $this->seedLifecycleTransitions();
    }

    private function seedLifecycleTransitions(): void
    {
        foreach (WorkOrderStage::cases() as $kind) {
            $rows = WorkOrderStatus::query()
                ->kind($kind)
                ->orderBy('lifecycle')
                ->orderBy('id')
                ->get();

            if ($rows->count() < 2) {
                continue;
            }

            $closed = $rows->filter(fn (WorkOrderStatus $status): bool => ! $status->is_open);

            foreach ($rows as $index => $from) {
                $next = $rows->get($index + 1);

                if ($next instanceof WorkOrderStatus) {
                    $this->edge((int) $from->id, (int) $next->id, $next->confirms_estimate || $next->rejects_to_estimate);
                }

                if ($from->is_open) {
                    foreach ($closed as $to) {
                        if ((int) $to->id === (int) $from->id) {
                            continue;
                        }

                        $this->edge(
                            (int) $from->id,
                            (int) $to->id,
                            $to->confirms_estimate || $to->rejects_to_estimate,
                        );
                    }
                }
            }
        }
    }

    private function edge(int $fromId, int $toId, bool $confirm): void
    {
        WorkOrderStatusTransition::query()->updateOrCreate(
            [
                'from_status_id' => $fromId,
                'to_status_id' => $toId,
            ],
            [
                'requires_confirmation' => $confirm,
                'requires_justification' => false,
            ],
        );
    }
}

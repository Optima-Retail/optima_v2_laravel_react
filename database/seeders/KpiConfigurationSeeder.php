<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\QualityScores\Enums\QualityActionId;
use App\Models\KpiConfiguration;
use Illuminate\Database\Seeder;

/**
 * Legacy `kpi_configuraciones` bands from optimaback migrations.
 */
final class KpiConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [QualityActionId::NotaQc, 0, 4.49, 0],
            [QualityActionId::NotaQc, 4.5, 4.69, 0.5],
            [QualityActionId::NotaQc, 4.7, 5, 1],

            [QualityActionId::TiempoResolucionIncidenciaQc, 5.51, 100, 0],
            [QualityActionId::TiempoResolucionIncidenciaQc, 5.01, 5.5, 0.55],
            [QualityActionId::TiempoResolucionIncidenciaQc, 4.51, 5, 0.7],
            [QualityActionId::TiempoResolucionIncidenciaQc, 4.01, 4.5, 0.85],
            [QualityActionId::TiempoResolucionIncidenciaQc, 0, 4, 1],

            [QualityActionId::TiempoEnvioPresupuesto, 88.01, 1000, 0],
            [QualityActionId::TiempoEnvioPresupuesto, 78.01, 88, 0.5],
            [QualityActionId::TiempoEnvioPresupuesto, 68.01, 78, 0.6],
            [QualityActionId::TiempoEnvioPresupuesto, 58.01, 68, 0.7],
            [QualityActionId::TiempoEnvioPresupuesto, 48.01, 58, 0.8],
            [QualityActionId::TiempoEnvioPresupuesto, 40.01, 48, 0.9],
            [QualityActionId::TiempoEnvioPresupuesto, 0, 40, 1],

            [QualityActionId::RespuestaQc, 75, 100, 1],
            [QualityActionId::RespuestaQc, 70, 74.99, 0.8],
            [QualityActionId::RespuestaQc, 0, 69.99, 0],

            ...$this->slaBands(QualityActionId::OtRealizadaCodRojo),
            ...$this->slaBands(QualityActionId::OtRealizadaCodAmarilloNaranja),
            ...$this->slaBands(QualityActionId::OtRealizadaCodVerde),
            ...$this->slaBands(QualityActionId::OtRealizadaCodPreventivo),
        ];

        KpiConfiguration::query()->delete();

        foreach ($rows as [$action, $min, $max, $percentage]) {
            KpiConfiguration::query()->create([
                'action_id' => $action->value,
                'min_value' => $min,
                'max_value' => $max,
                'percentage' => $percentage,
            ]);
        }
    }

    /**
     * @return list<array{0: QualityActionId, 1: float, 2: float, 3: float}>
     */
    private function slaBands(QualityActionId $action): array
    {
        return [
            [$action, 90, 100, 1],
            [$action, 85, 89.9, 0.9],
            [$action, 80, 84.9, 0.8],
            [$action, 75, 79.9, 0.7],
            [$action, 70, 74.9, 0.6],
            [$action, 0, 69.9, 0],
        ];
    }
}

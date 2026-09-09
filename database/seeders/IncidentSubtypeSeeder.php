<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\IncidentSubtype;
use Illuminate\Database\Seeder;

/**
 * Catalog from legacy `incidencia_qc_subtipos` / IncidenciaQcSubtipoEnum (IDs 1–45).
 * Skips legacy `es_externo` (dead in prod).
 */
final class IncidentSubtypeSeeder extends Seeder
{
    public function run(): void
    {
        $subtypes = [
            ['id' => 1, 'name' => 'Falta de comunicación', 'incident_type_id' => 10],
            ['id' => 2, 'name' => 'Impuntualidad', 'incident_type_id' => 10],
            ['id' => 3, 'name' => 'Mala actitud/servicio del técnico', 'incident_type_id' => 10],
            ['id' => 4, 'name' => 'Repetición de visita', 'incident_type_id' => 10],
            ['id' => 5, 'name' => 'Costes muy elevados', 'incident_type_id' => 10],
            ['id' => 6, 'name' => 'No comprenden la facturación', 'incident_type_id' => 10],
            ['id' => 7, 'name' => 'Capacidad resolutiva', 'incident_type_id' => 10],
            ['id' => 8, 'name' => 'Mala preparación de las reuniones', 'incident_type_id' => 10],
            ['id' => 9, 'name' => 'Planificación de preventivos', 'incident_type_id' => 10],
            ['id' => 10, 'name' => 'Falta de comunicación', 'incident_type_id' => 11],
            ['id' => 11, 'name' => 'Impuntualidad', 'incident_type_id' => 11],
            ['id' => 12, 'name' => 'Mala actitud/servicio del técnico', 'incident_type_id' => 11],
            ['id' => 13, 'name' => 'Nueva petición', 'incident_type_id' => 11],
            ['id' => 14, 'name' => 'Repetición de visita', 'incident_type_id' => 11],
            ['id' => 15, 'name' => 'Reclamación de facturas de clientes', 'incident_type_id' => 12],
            ['id' => 16, 'name' => "Solicitud de PO's", 'incident_type_id' => 12],
            ['id' => 17, 'name' => 'No hay informes para poder facturar', 'incident_type_id' => 12],
            ['id' => 18, 'name' => 'Subir costes a la plataforma del cliente', 'incident_type_id' => 12],
            ['id' => 19, 'name' => 'Precio pactado', 'incident_type_id' => 13],
            ['id' => 20, 'name' => 'Enviar técnico fuera de su rango de actuación', 'incident_type_id' => 13],
            ['id' => 21, 'name' => 'Pactar Km', 'incident_type_id' => 13],
            ['id' => 22, 'name' => 'Enviar técnico de PCI a un PCI ocular', 'incident_type_id' => 13],
            ['id' => 23, 'name' => 'OT con trabajo hecho en cerrada/cancelado', 'incident_type_id' => 13],
            ['id' => 24, 'name' => 'Error en facturación por falta de info en OT', 'incident_type_id' => 13],
            ['id' => 25, 'name' => 'Uso de técnico en gris para preventivo / cod. Verde', 'incident_type_id' => 13],
            ['id' => 26, 'name' => 'Uso de técnico en negro', 'incident_type_id' => 13],
            ['id' => 27, 'name' => 'Uso de técnico fuera de su especialidad', 'incident_type_id' => 13],
            ['id' => 28, 'name' => 'Otros', 'incident_type_id' => 13],
            ['id' => 29, 'name' => 'Presupuesto Elevado', 'incident_type_id' => 11],
            ['id' => 30, 'name' => 'Presupuestos', 'incident_type_id' => 14],
            ['id' => 31, 'name' => 'Intervención en establecimiento', 'incident_type_id' => 14],
            ['id' => 32, 'name' => 'Contestación al cliente', 'incident_type_id' => 14],
            ['id' => 33, 'name' => 'Comentario a añadir en próxima intervención', 'incident_type_id' => 14],
            ['id' => 34, 'name' => 'Preventiva no respondida', 'incident_type_id' => 15],
            ['id' => 35, 'name' => 'Espera de Material', 'incident_type_id' => 15],
            ['id' => 36, 'name' => 'Espera cliente', 'incident_type_id' => 15],
            ['id' => 37, 'name' => 'Impuntualidad', 'incident_type_id' => 15],
            ['id' => 38, 'name' => 'Mala actitud/servicio del técnico', 'incident_type_id' => 15],
            ['id' => 39, 'name' => 'Presupuesto', 'incident_type_id' => 15],
            ['id' => 40, 'name' => 'Repetición de visita', 'incident_type_id' => 15],
            ['id' => 41, 'name' => 'Cancelación de asistencia técnico', 'incident_type_id' => 11],
            ['id' => 42, 'name' => 'Disconformidad del cliente para facturar', 'incident_type_id' => 12],
            ['id' => 43, 'name' => 'Error facturación sociedades', 'incident_type_id' => 16],
            ['id' => 44, 'name' => "Creación / Cancelación OT's preventivos", 'incident_type_id' => 16],
            ['id' => 45, 'name' => 'Seguimiento cliente nuevo sin actividad', 'incident_type_id' => 16],
        ];

        foreach ($subtypes as $subtype) {
            IncidentSubtype::query()->updateOrCreate(
                ['id' => $subtype['id']],
                [
                    'name' => $subtype['name'],
                    'incident_type_id' => $subtype['incident_type_id'],
                ],
            );
        }
    }
}

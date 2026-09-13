<?php

declare(strict_types=1);

namespace App\Domain\QualityScores\Enums;

/**
 * Legacy AccionEnum IDs (acciones) — preserved for seeder and import.
 */
enum QualityActionId: int
{
    case NotaQc = 1;
    case RespuestaQc = 2;
    case TiempoResolucionIncidenciaQc = 3;
    case TiempoEnvioPresupuesto = 4;
    case OtRealizadaCodRojo = 5;
    case OtRealizadaCodAmarilloNaranja = 6;
    case OtRealizadaCodVerde = 7;
    case OtRealizadaCodPreventivo = 8;
    case CorazonIntervencion = 9;
    case CorazonRecordatorio = 10;
    case Felicitacion = 11;
    case VisitaCentral = 12;
    case ReunionRecurrentes = 13;
    case CambioPuntuacionQc = 14;
    case ChequeoOt = 15;

    public function weightKey(): string
    {
        return match ($this) {
            self::NotaQc => 'qcoins_peso_nota_qc',
            self::RespuestaQc => 'qcoins_peso_respuesta_qc',
            self::TiempoResolucionIncidenciaQc => 'qcoins_peso_tiempo_resolucion_incidencia_qc',
            self::TiempoEnvioPresupuesto => 'qcoins_peso_tiempo_de_envio_presupuesto',
            self::OtRealizadaCodRojo => 'qcoins_peso_ot_realizada_cod_rojo',
            self::OtRealizadaCodAmarilloNaranja => 'qcoins_peso_ot_realizada_cod_amarilla_mas_naranja',
            self::OtRealizadaCodVerde => 'qcoins_peso_ot_realizada_cod_verde',
            self::OtRealizadaCodPreventivo => 'qcoins_peso_ot_realizada_cod_preventivo',
            self::CorazonIntervencion, self::CorazonRecordatorio => 'qcoins_peso_corazon',
            self::Felicitacion => 'qcoins_peso_felicitacion',
            self::VisitaCentral => 'visita_central',
            self::ReunionRecurrentes => 'reunion_recurrentes',
            self::CambioPuntuacionQc => 'cambio_puntuacion_qc',
            self::ChequeoOt => 'chequeo_ot',
        };
    }
}

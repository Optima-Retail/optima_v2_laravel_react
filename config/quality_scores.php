<?php

declare(strict_types=1);

return [
    /*
    | Legacy `constantes` used by QcoinsProcessor.
    | Values from optimaback seed/migrations (later weight insert + valor_maximo).
    */
    'max_value' => (float) env('QUALITY_SCORE_MAX_VALUE', 80),
    'min_weekly_visits' => (int) env('QUALITY_SCORE_MIN_WEEKLY_VISITS', 0),
    'weight_preventivo' => (float) env('QUALITY_SCORE_WEIGHT_PREVENTIVO', 1),
    'weight_prev_limpieza' => (float) env('QUALITY_SCORE_WEIGHT_PREV_LIMPIEZA', 0.1),

    'weights' => [
        'qcoins_peso_nota_qc' => 0.077,
        'qcoins_peso_respuesta_qc' => 0.077,
        'qcoins_peso_tiempo_resolucion_incidencia_qc' => 0.308,
        'qcoins_peso_felicitacion' => 0.154,
        'qcoins_peso_tiempo_de_envio_presupuesto' => 0.077,
        'qcoins_peso_ot_realizada_cod_rojo' => 0.308,
        'qcoins_peso_ot_realizada_cod_amarilla_mas_naranja' => 0.308,
        'qcoins_peso_ot_realizada_cod_verde' => 0.308,
        'qcoins_peso_ot_realizada_cod_preventivo' => 0.308,
        'qcoins_peso_corazon' => 0.0,
        'qcoins_peso_prev_limpieza' => 0.1,
        'visita_central' => 0.0,
        'reunion_recurrentes' => 0.0,
        'cambio_puntuacion_qc' => 1.0,
        'chequeo_ot' => 0.0,
    ],
];

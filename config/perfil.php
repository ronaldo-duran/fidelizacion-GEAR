<?php

declare(strict_types=1);

/*
| Historial y perfil del cliente en la PWA (E8-05). Reflejado en
| tests/Fixtures/historial-perfil-escenarios.json; una prueba verifica que
| coincidan.
*/

return [
    // Días antes del vencimiento de un lote en que el historial lo avisa.
    // TODO(cliente): supuesto del fixture (history.expiry_notice_days), igual al primer aviso de descenso.
    'aviso_vencimiento_dias' => 30,

    // Avisos por correo que el cliente puede apagar (regla 9). La bienvenida y
    // la confirmación de la suscripción se mandan siempre.
    'avisos_correo' => [
        'subida_nivel' => 'Cuando subo de nivel',
        'aviso_descenso' => 'Cuando voy a bajar de nivel',
        'membresia_por_vencer' => 'Cuando mi suscripción va a vencer',
        'beneficio_usado' => 'Cuando uso un beneficio',
    ],

    // Documentos del programa (regla 11). Vacíos: la fila dice "Disponible pronto".
    // TODO(cliente): el cliente entrega el reglamento y la política de datos (decisión 13 del issue #1).
    'reglamento_url' => env('REGLAMENTO_URL', ''),
    'politica_datos_url' => env('POLITICA_DATOS_URL', ''),
];

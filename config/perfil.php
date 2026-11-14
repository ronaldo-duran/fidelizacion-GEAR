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
];

<?php

declare(strict_types=1);

/*
 * API pública para los comercios (E9-01). Los números son supuestos de la
 * spec y viven también en tests/Fixtures/api-escenarios.json.
 */
return [
    // TODO(cliente): topes por credencial (decisión 7 de la spec, supuesto).
    'requests_per_minute' => 120,
    'customer_lookups_per_minute' => 60,

    // Horas en que el token viejo sigue sirviendo después de rotar (regla 4, supuesto).
    'rotation_grace_hours' => 24,

    // Monto máximo de una compra, en pesos enteros (regla 9, supuesto).
    'max_amount' => 999_999_999_999,
];

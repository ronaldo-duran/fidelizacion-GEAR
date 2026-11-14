<?php

declare(strict_types=1);

/*
 * API pública para los comercios (E9-01). Los números son supuestos de la
 * spec y viven también en tests/Fixtures/api-escenarios.json.
 */
return [
    // Horas en que el token viejo sigue sirviendo después de rotar (regla 4, supuesto).
    'rotation_grace_hours' => 24,
];

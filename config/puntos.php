<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Reversal window
    |--------------------------------------------------------------------------
    |
    | Calendar days, in America/Bogota, during which a purchase can still be
    | reversed (E3-02). The value must match `reversal_window_days` in
    | tests/Fixtures/movimientos-escenarios.json; a test checks it.
    |
    */

    'reversal_window_days' => 30,
];

<?php

declare(strict_types=1);

/*
| Nightly processes (E3-05): expiry, level re-evaluation, demotion notices
| and balance check, in that order, at this time in America/Bogota.
*/

return [
    'hora' => env('NOCTURNO_HORA', '01:30'),
    'cola' => 'nocturno',
];

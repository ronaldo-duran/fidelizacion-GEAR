<?php

declare(strict_types=1);

/*
| Datos de muestra para presentar el programa (clientes y compras de ejemplo,
| con historia del mes en curso y del anterior). Solo en local o en el
| servidor de demo, nunca en producción: el seeder los ignora ahí aunque la
| variable esté encendida.
*/

return [
    'sembrar' => (bool) env('SEMBRAR_DATOS_DE_MUESTRA', false),
];

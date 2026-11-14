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

    // La contraseña de los tres usuarios sembrados. En local y en las pruebas, si falta, es
    // `password`. En cualquier otro entorno (staging, demostración) es obligatoria: un servidor
    // alcanzable desde internet nunca queda con una contraseña conocida.
    'clave_usuarios' => env('SEMILLA_CLAVE'),
];

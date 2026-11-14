<?php

declare(strict_types=1);

/*
 * Sanctum solo autentica la API pública con tokens de las credenciales de
 * los comercios (E9-01). Sin el modo de cookies para SPA: ningún dominio es
 * "stateful" y ningún guard de sesión entra, porque una sesión del navegador
 * pasaría con un token transitorio que tiene todos los alcances.
 */
return [
    'stateful' => [],

    'guard' => [],

    // El vencimiento lo pone cada token: la rotación le da al viejo su ventana de gracia.
    'expiration' => null,

    // Prefijo para que los escáneres de secretos reconozcan un token publicado por error.
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', 'clubar_'),
];

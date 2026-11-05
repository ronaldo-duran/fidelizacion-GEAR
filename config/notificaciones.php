<?php

declare(strict_types=1);

/*
| Motor de notificaciones (E7-01). Espejo de
| tests/Fixtures/notificaciones-escenarios.json: una prueba verifica que
| coinciden. Los textos son los iniciales de las plantillas; el
| administrador los edita en el panel. Los eventos con origen en
| pagos_membresia y avisos_renovacion los dispara E6-01 cuando exista.
*/

return [
    'cola' => 'notificaciones',

    // TODO(cliente): hora de envío de los avisos nocturnos (supuesto del fixture).
    'hora_avisos' => env('NOTIFICACIONES_HORA_AVISOS', '08:00'),

    'intentos' => 3,

    'limite_app' => 30,

    'eventos' => [
        'bienvenida' => [
            'categoria' => 'transaccional',
            'origen' => 'clientes',
            'variables' => ['nombre', 'nivel', 'enlace'],
            'asunto' => 'Te damos la bienvenida al Club Aponte Rivera',
            'cuerpo' => "Hola, {nombre}.\n\nYa eres parte del Club Aponte Rivera. Empiezas en el nivel {nivel} y "
                . "cada compra en nuestros comercios te acerca al siguiente.\n\n"
                . 'Entra cuando quieras a ver tus beneficios: {enlace}',
        ],
        'subida_nivel' => [
            'categoria' => 'transaccional',
            'origen' => 'cambios_nivel',
            'variables' => ['nombre', 'nivel_anterior', 'nivel', 'enlace'],
            'asunto' => 'Subiste a {nivel}',
            'cuerpo' => "Hola, {nombre}.\n\nPasaste de {nivel_anterior} a {nivel}. Tus beneficios nuevos ya están "
                . "disponibles en todos los comercios del Club.\n\nMíralos aquí: {enlace}",
        ],
        'aviso_descenso' => [
            'categoria' => 'transaccional',
            'origen' => 'avisos_descenso',
            'variables' => [
                'nombre',
                'nivel',
                'nivel_resultante',
                'puntos_que_vencen',
                'fecha_vencimiento',
                'dias_antes',
                'enlace',
            ],
            'asunto' => 'Tus puntos vencen el {fecha_vencimiento}',
            'cuerpo' => "Hola, {nombre}.\n\nEl {fecha_vencimiento} vencen {puntos_que_vencen} puntos. Si no sumas "
                . "más antes de esa fecha, pasarás de {nivel} a {nivel_resultante}.\n\nRevisa tu historial: {enlace}",
        ],
        'membresia_confirmada' => [
            'categoria' => 'transaccional',
            'origen' => 'pagos_membresia',
            'variables' => ['nombre', 'nivel', 'plan', 'monto', 'vigente_hasta', 'enlace'],
            'asunto' => 'Tu membresía {plan} está activa',
            'cuerpo' => "Hola, {nombre}.\n\nRecibimos tu pago de {monto}. Eres {nivel} hasta el {vigente_hasta}, y "
                . "tus puntos siguen contando.\n\nMira tus beneficios: {enlace}",
        ],
        'membresia_por_vencer' => [
            'categoria' => 'transaccional',
            'origen' => 'avisos_renovacion',
            'variables' => ['nombre', 'nivel', 'vigente_hasta', 'dias_antes', 'enlace'],
            'asunto' => 'Tu membresía vence el {vigente_hasta}',
            'cuerpo' => "Hola, {nombre}.\n\nTu membresía {nivel} vence el {vigente_hasta}. Si la renuevas antes, no "
                . "pierdes ningún día.\n\nRenueva aquí: {enlace}",
        ],
        'beneficio_usado' => [
            'categoria' => 'transaccional',
            'origen' => 'redenciones',
            'variables' => ['nombre', 'beneficio', 'comercio', 'valor', 'fecha', 'enlace'],
            'asunto' => 'Usaste tu beneficio en {comercio}',
            'cuerpo' => "Hola, {nombre}.\n\nEl {fecha} usaste «{beneficio}» en {comercio} y te ahorraste {valor}."
                . "\n\nSi no fuiste tú, escríbenos respondiendo este correo.\n\nTus beneficios: {enlace}",
        ],
    ],
];

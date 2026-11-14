<?php

declare(strict_types=1);

use Illuminate\Routing\Route as Ruta;
use Illuminate\Support\Facades\Route;
use Symfony\Component\Yaml\Yaml;

// E9-01, regla 13: cada ruta de /api/v1 está en docs/api/openapi.yaml, y nada sobra allí.

it('documents every route of /api/v1 in the OpenAPI file and nothing else', function (): void {
    $openapi = Yaml::parseFile(dirname(__DIR__, 2) . '/docs/api/openapi.yaml');
    $normalizar = static fn (string $ruta): string => (string) preg_replace('/\{[^}]+\}/', '{}', $ruta);
    $documentadas = [];

    foreach ($openapi['paths'] as $ruta => $metodos) {
        foreach (array_keys($metodos) as $metodo) {
            $documentadas[] = mb_strtoupper($metodo) . ' ' . $normalizar('/api/v1' . $ruta);
        }
    }

    $registradas = collect(Route::getRoutes()->getRoutes())
        ->filter(static fn (Ruta $ruta): bool => str_starts_with($ruta->uri(), 'api/v1/'))
        ->flatMap(static fn (Ruta $ruta): array => array_map(
            static fn (string $metodo): string => "{$metodo} " . $normalizar('/' . $ruta->uri()),
            array_diff($ruta->methods(), ['HEAD']),
        ))
        ->all();

    sort($documentadas);
    sort($registradas);

    expect($openapi['openapi'])->toBe('3.1.0')
        ->and($registradas)->toHaveCount(7)
        ->and($documentadas)->toBe($registradas);
});

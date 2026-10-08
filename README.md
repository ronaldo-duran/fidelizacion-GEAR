# Plataforma de Fidelización — Grupo Aponte Rivera

Programa de fidelización multicomercio (Club Aponte Rivera) con niveles,
membresías y beneficios. Laravel 13 + Filament 5 + Livewire 4 +
PostgreSQL 16.

## Requisitos
PHP 8.3, Composer, Node 22, PostgreSQL 16, Redis.

## Arranque
```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
createdb fidelizacion && createdb fidelizacion_test
php artisan migrate --seed
composer run dev
```

- PWA del cliente: http://localhost:8000
- Panel administrativo: http://localhost:8000/admin

## Antes de abrir un PR
```bash
./vendor/bin/pint && ./vendor/bin/phpstan analyse && ./vendor/bin/pest
```

## Documentación
- `CLAUDE.md` — reglas duras del proyecto. Leerlo antes de tocar código.
- `docs/plan-de-trabajo.md` — plan por semanas, hitos y reparto.
- `docs/diseno/` — identidad visual, pantallas aprobadas y componentes.
- `specs/` — especificación de cada feature.
- `tests/Fixtures/` — reglas de negocio con números reales.

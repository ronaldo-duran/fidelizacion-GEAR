# Plataforma de Fidelización — Grupo Aponte Rivera

Programa de fidelización multicomercio (Club Aponte Rivera) con niveles,
membresías y beneficios. Laravel 13 + Filament 5 + Livewire 4 +
PostgreSQL 16.

## Requisitos

PHP 8.3, Composer, Node 22, PostgreSQL 16, Redis. Las pruebas también corren en SQL Server 2022 (en el CI).

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

## Antes de subir un cambio

```bash
composer lint && npm run lint && composer test && npm run test:e2e
```

Los commits siguen Conventional Commits y la versión de `package.json` sube con cada cambio de producto
(ver `CLAUDE.md` §5). Husky lo valida al hacer commit; el CI, en cada push.

## Documentación

- `CLAUDE.md` — reglas duras del proyecto. Leerlo antes de tocar código.
- `docs/plan-de-trabajo.md` — plan por semanas, hitos y reparto.
- `docs/diseno/` — identidad visual, pantallas aprobadas y componentes.
- `specs/` — especificación de cada feature.
- `tests/Fixtures/` — reglas de negocio con números reales.

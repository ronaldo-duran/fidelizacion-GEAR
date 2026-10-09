## Qué resuelve

Closes #

## Spec

`specs/`

## Checklist

- [ ] `composer lint` y `npm run lint` en verde
- [ ] `composer test` y `npm run test:e2e` en verde
- [ ] Versión de `package.json` actualizada (o etiqueta `sin-version` si no aplica)
- [ ] Menos de 400 líneas de producción (o etiqueta `pr-grande` con la razón abajo)
- [ ] Los criterios de aceptación de la spec tienen prueba
- [ ] Reglas de negocio con números en fixture, no en el código
- [ ] Funciona en PostgreSQL y SQL Server (CLAUDE.md 3.13)

## Reglas duras tocadas

Marcar solo si el cambio toca alguna. Si marcas una, explica abajo cómo
se respetó.

- [ ] Versionado de reglas
- [ ] Snapshot de regla en movimientos
- [ ] Inmutabilidad del libro de puntos
- [ ] Cálculo de nivel efectivo
- [ ] Idempotencia de la API
- [ ] Manejo de montos en pesos

## Cómo probarlo

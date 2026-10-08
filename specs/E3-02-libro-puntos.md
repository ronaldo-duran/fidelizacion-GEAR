# E3-02 · Libro de puntos

**Épica:** E3 · **Horas estimadas:** 16 · **Riesgo:** alto

## Objetivo
Registrar todo movimiento de puntos de forma inmutable y auditable, con
el saldo del cliente siempre consistente.

## Fuera de alcance
Pantalla de cajero (E4-02). Caducidad nocturna (E3-05).

## Reglas de negocio
1. `movimientos_puntos` es append-only: nunca UPDATE ni DELETE.
2. Todo movimiento guarda `regla_id` y `regla_version` aplicadas.
3. El saldo en `clientes` se actualiza dentro de la misma transacción de
   base de datos que inserta el movimiento.
4. Anular una compra inserta un movimiento `reversa` negativo que
   referencia la transacción original. El movimiento original no se toca.
5. La reversa solo se admite hasta 30 días después de la compra.
6. El saldo recalculado desde cero debe coincidir siempre con el saldo
   materializado.

## Criterios de aceptación
- [ ] Dado un movimiento insertado, cuando se intenta actualizarlo o
      borrarlo por el modelo, entonces la operación falla.
- [ ] Dada una acumulación de N puntos, cuando se completa, entonces el
      saldo del cliente aumentó exactamente N dentro de la misma
      transacción.
- [ ] Dada una transacción anulada, cuando se procesa la reversa,
      entonces existe un movimiento negativo que la referencia, el
      original sigue intacto y el saldo refleja la resta.
- [ ] Dada una transacción de hace 31 días, cuando se intenta revertir,
      entonces se rechaza.
- [ ] Dado un cliente con movimientos de todos los tipos, cuando se
      recalcula el saldo desde cero, entonces coincide con el saldo
      materializado.
- [ ] Dado un fallo a mitad de la operación, cuando se hace rollback,
      entonces no queda ni movimiento ni cambio de saldo.

## Fixtures
`tests/Fixtures/movimientos-escenarios.json`

## Reglas duras de CLAUDE.md que aplican
- 3.3 Snapshot de regla
- 3.4 Libro inmutable
- 3.9 Pesos enteros

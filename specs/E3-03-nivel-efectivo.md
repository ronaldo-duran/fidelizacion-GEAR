# E3-03 · Nivel efectivo del cliente

**Épica:** E3 · **Horas estimadas:** 10 · **Riesgo:** alto

## Objetivo
Calcular el nivel de cada cliente a partir de sus tres fuentes posibles,
con ascenso inmediato y descenso controlado.

## Fuera de alcance
Notificaciones de cambio de nivel (E7).

## Reglas de negocio
1. El nivel efectivo es el más alto entre `nivel_por_puntos`,
   `nivel_por_membresia` y `nivel_por_evento`, considerando solo los
   vigentes.
2. Las tres fuentes se guardan en campos separados. Nunca se colapsan.
3. El ascenso se evalúa de forma síncrona al registrar la compra.
4. El descenso solo ocurre en el job nocturno, nunca en una petición de
   usuario.
5. `proteccion_hasta` da 3 meses de gracia tras caer bajo el umbral.
6. Al vencerse una membresía o un evento, el cliente cae al nivel que le
   corresponda por sus otras fuentes vigentes, no al nivel de entrada.

## Criterios de aceptación
- [ ] Dado un cliente Oro por puntos y Diamante por membresía vigente,
      cuando se consulta su nivel, entonces es Diamante.
- [ ] Dado ese mismo cliente, cuando vence la membresía, entonces queda
      en Oro, no en Bronce.
- [ ] Dado un cliente que cruza el umbral de Oro con una compra, cuando
      termina la petición, entonces su nivel efectivo ya es Oro.
- [ ] Dado un cliente cuyo saldo cae bajo el umbral, cuando corre el job
      nocturno dentro del periodo de protección, entonces no baja.
- [ ] Dado ese mismo cliente pasada la protección, cuando corre el job,
      entonces baja y queda registrado el cambio con su fecha.
- [ ] Ningún camino de código baja el nivel dentro de una petición HTTP.

## Fixtures
`tests/Fixtures/niveles-escenarios.json`

## Reglas duras de CLAUDE.md que aplican
- 3.5 Tres fuentes separadas
- 3.6 Ascenso síncrono, descenso nocturno

# E6-01 · Membresías y pagos en línea

**Épica:** E6 · **Horas estimadas:** 26 · **Riesgo:** alto

## Objetivo

Que el cliente compre o renueve desde la PWA una membresía que le da un
nivel mientras esté vigente, pagando en línea con una pasarela colombiana,
y que el sistema la active solo cuando la pasarela confirme el pago.

## Fuera de alcance

- Cobro recurrente automático o tarjeta guardada (CLAUDE.md §8). La
  renovación es un pago nuevo que el cliente inicia desde la PWA.
- Enviar el aviso de renovación por correo o in-app (E7-01). Aquí solo se
  registra qué aviso corresponde a quién, igual que los avisos de descenso
  de E3-05.
- Bajar el nivel cuando vence la membresía: lo hace la evaluación nocturna
  (E3-03, E3-05), que ya lee `membresia_vigente_hasta`.
- Reembolsos y contracargos iniciados desde el panel. Si la pasarela avisa
  de uno, se registra para revisión (regla 9); la reversa del periodo es
  manual y queda para cuando el cliente la pida.
- Facturación electrónica DIAN e integración contable (CLAUDE.md §8).
- El tablero con el origen de los Diamante (E10-01 ya lo muestra con
  `nivel_por_membresia`).

## Reglas de negocio

1. **Planes.** Un plan tiene nombre, nivel que otorga, duración en meses y
   precio en pesos enteros. Los planes iniciales y sus precios están en
   `tests/Fixtures/membresias-escenarios.json` (issue #1, decisión 8: solo
   Diamante, mensual y anual). Un plan no se borra: se desactiva.
2. **Los puntos son estatus** (regla dura 3.1). Pagar una membresía no
   suma, resta ni toca `saldo_puntos`. La pantalla lo dice: los puntos
   siguen contando.
3. **El webhook firmado es la única fuente de confirmación.** El regreso
   del cliente desde la pasarela nunca activa nada: solo muestra el estado
   que ya tiene el pago en la base de datos.
4. **Verificación de cada notificación**, en este orden:
   1. La firma es válida con el secreto de eventos de la pasarela. Si no,
      se rechaza (401) y se registra.
   2. La referencia corresponde a un pago nuestro. Si no, se registra y se
      responde 200 para que la pasarela deje de reintentar.
   3. Si el estado es aprobado, el monto y la moneda (COP) coinciden con
      los del pago. Si no, se registra y el pago no se aplica.
5. **Idempotencia por restricción de base de datos** (regla dura 3.7):
   - `pagos_membresia.referencia` es única y la genera el servidor.
   - El periodo de membresía tiene `pago_id` único: un pago aprobado crea
     como máximo un periodo, aunque el webhook llegue dos veces o en
     paralelo.
   - El estado del pago se cambia con la fila bloqueada
     (`lockForUpdate`). Un webhook repetido encuentra el pago ya aprobado y
     responde 200 sin hacer nada.
6. **Vigencia de un periodo.** Empieza el día en que se aprueba el pago, o
   el día siguiente al fin de la membresía vigente si el cliente renueva
   antes de que venza (no pierde días). Termina el día anterior a la misma
   fecha N meses después, sin desbordar al mes siguiente. Los casos están
   en el fixture.
7. **Al aprobar un pago**, en una sola transacción:
   - se crea el periodo en `membresias`;
   - `clientes.nivel_por_membresia` y `membresia_vigente_hasta` quedan con
     el nivel del plan y el fin del periodo más lejano;
   - se llama a `RaiseNivelEfectivo` con motivo `ascenso_membresia`
     (E3-03).

   Así el ascenso es síncrono (regla dura 3.6): cuando la PWA consulta el
   pago, el cliente ya ve su nivel nuevo.

8. **Un pago aprobado no se deshace solo.** Un rechazo o anulación que
   llegue después de aprobado se registra para revisión. No acorta la
   membresía ni baja el nivel. Un segundo cobro aprobado de la misma
   referencia (otra transacción) también va a revisión, sin otro periodo.
   Un rechazo, en cambio, no es final: Wompi deja reintentar con la misma
   referencia, y si ese intento se aprueba, el pago se aplica.
9. **Dos pagos aprobados** del mismo cliente se apilan como periodos
   consecutivos. Es dinero real que el cliente pagó, así que no se
   descarta. Un plan de otro nivel se rechaza mientras haya una membresía
   vigente de un nivel distinto, porque el cliente tiene un solo
   `nivel_por_membresia`.
10. **Aviso de renovación.** Para cada número de días de
    `renewal.notice_days_before` del fixture (supuesto), se registra un
    aviso al cliente cuya membresía termina en exactamente esos días y que
    no tiene otro periodo pagado después. E7-01 lo envía con el enlace a
    la pantalla de suscripción, donde el cliente paga de nuevo.
11. **Dinero** (regla dura 3.9): `precio_cop` y `monto_cop` en pesos
    enteros. La conversión a la unidad de la pasarela (centavos en Wompi)
    ocurre solo dentro del adaptador de la pasarela.
12. **Pasarela.** El cliente aún no elige entre Wompi y Mercado Pago (issue
    #1, decisión 9; comentario en #20). El dominio no depende de cuál: todo
    pasa por un contrato `PasarelaPagos` con un adaptador por pasarela.

## Criterios de aceptación

- [ ] Dado un pago pendiente, cuando llega un webhook aprobado, válido y
      con el monto esperado, entonces se crea un periodo y el cliente queda
      con `nivel_por_membresia` y `nivel_efectivo` del plan en la misma
      petición.
- [ ] Dado ese mismo webhook, cuando llega otra vez (o dos en paralelo),
      entonces la vigencia no se extiende dos veces y la respuesta es 200.
- [ ] Dado un webhook con firma inválida, cuando llega, entonces se
      responde 401, queda registrado y el pago no cambia.
- [ ] Dado un webhook aprobado con un monto o moneda distintos, entonces
      queda registrado y el pago sigue pendiente.
- [ ] Dado un pago rechazado, entonces queda `rechazado` y no hay periodo.
- [ ] Dado un pago aprobado, cuando llega un rechazo tardío, entonces el
      pago sigue aprobado y el evento queda registrado para revisión.
- [ ] Dado cualquier pago, cuando se aprueba, entonces `saldo_puntos` y
      `movimientos_puntos` no cambian.
- [ ] Dada una membresía vigente, cuando el cliente renueva, entonces el
      periodo nuevo empieza el día siguiente al fin del actual.
- [ ] Dado que el cliente vuelve de la pasarela antes de que llegue el
      webhook, entonces ve "Estamos confirmando tu pago" y la pantalla se
      actualiza sola cuando el pago cambia.
- [ ] Dada una membresía que termina en alguno de los días de aviso, cuando
      corre el proceso nocturno, entonces se registra un aviso de
      renovación una sola vez; si ya hay otro periodo pagado después, no.
- [ ] Dado el sandbox de la pasarela elegida, cuando se paga con una
      tarjeta de prueba, entonces el webhook real activa la membresía
      (prueba manual documentada en el PR, con la cuenta de pruebas del
      cliente).

## Fixtures

`tests/Fixtures/membresias-escenarios.json` (nuevo, en inglés): planes
iniciales, días de aviso de renovación (supuesto), cálculo de periodos y
casos de notificación de la pasarela con su resultado esperado.

Los vectores de firma de cada pasarela (cuerpo, secreto de prueba y firma
esperada) van con el adaptador, en el PR de código, tomados de la
documentación oficial de la pasarela.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **`planes_membresia`**: `id`, `nombre`, `nivel`, `duracion_meses`,
  `precio_cop` (entero), `activo`, timestamps. Cambiar el precio afecta
  solo a los pagos que se inicien después: cada pago guarda su copia
  (abajo).
- **`pagos_membresia`**:
  - columnas: `id`, `cliente_id`, `plan_id`, `referencia` (única, la pone
    el servidor), `pasarela`, `id_externo` (id de la transacción en la
    pasarela, nullable), `estado` (`pendiente` | `aprobado` | `rechazado`
    | `anulado`), `aprobado_at`, timestamps;
  - copia del plan al iniciar el pago: `nivel`, `duracion_meses` y
    `monto_cop`;
  - índice `(pasarela, id_externo)`, no único: un pago pendiente no tiene
    id externo y SQL Server admite un solo NULL por índice único (regla
    dura 3.13). Al aplicar una notificación se revisa que ese id no sea de
    otro pago; si lo es, va a revisión.
- **`membresias`** (un periodo por pago aprobado, append-only): `id`,
  `cliente_id`, `plan_id`, `pago_id` (único), `nivel`, `vigente_desde`,
  `vigente_hasta`, `created_at`. CLAUDE.md §4 lista una columna `estado`;
  aquí no hace falta, porque la vigencia sale de las fechas y un periodo
  nunca se edita. `clientes.membresia_vigente_hasta` es el fin más lejano
  de los periodos del cliente, materializado en la misma transacción.
- **`notificaciones_pago`** (append-only): `id`, `pasarela`, `referencia`
  (nullable), `id_externo` (nullable), `estado_informado`, `firma_valida`,
  `resultado` (`aplicado` | `repetido` | `rechazado` | `firma_invalida` |
  `referencia_desconocida` | `monto_distinto` | `transicion_invalida`),
  `payload` (json), `created_at`. Es el registro de toda notificación,
  válida o no.
- **`avisos_renovacion`**: `id`, `cliente_id`, `vigente_hasta`,
  `dias_antes`, `enviado_at` (nullable, lo llena E7-01), `created_at`.
  Restricción única `(cliente_id, vigente_hasta, dias_antes)`, como
  `avisos_descenso` (E3-05).
- **Contrato `App\Pagos\PasarelaPagos`**:
  - `checkout(PagoMembresia): string` devuelve la URL a la que se
    redirige al cliente;
  - `notificacion(Request): NotificacionPago` verifica la firma y la
    traduce a un objeto propio con referencia, id externo, estado, monto
    en pesos y moneda. Si la firma no es válida, lanza una excepción.

  El adaptador se elige con `config/pagos.php` y las credenciales van en
  `.env` (llaves de prueba en `.env.example`, vacías).

- **Primer adaptador**: Wompi, la sugerencia del issue. Si el cliente
  elige Mercado Pago, cambia solo el adaptador (y su verificación, que
  además consulta el pago en su API antes de aceptarlo). Mientras no
  decida, el código lleva `// TODO(cliente):` donde se elige.
- **Actions** (`app/Actions/Membresias/`, invocables):
  - `StartPagoMembresia`: crea el pago pendiente y devuelve la URL de
    checkout.
  - `ApplyNotificacionPago`: aplica una notificación ya verificada
    (reglas 4 a 9) y escribe `notificaciones_pago`.
  - `GrantMembresia`: crea el periodo, actualiza las columnas del cliente
    y llama a `RaiseNivelEfectivo`.
- **Webhook**: `POST /webhooks/pagos/{pasarela}`, sin sesión ni CSRF, con
  límite de peticiones. El controlador solo delega a las Actions. Responde
  200 en todo lo que no sea firma inválida, para que la pasarela no
  reintente algo que no va a cambiar.
- **Proceso nocturno**: un job `RegisterAvisosRenovacion` en la cola
  `nocturno`, después de los de E3-05.
- **PWA**:
  - `/membresia`, pantalla 1s: planes activos, aviso de que los puntos
    siguen contando, "Continuar al pago · $49.900";
  - `/membresia/pago/{referencia}`: estado del pago del propio cliente,
    con `wire:poll` mientras está pendiente.

  La pantalla de niveles (1r) enlaza a `/membresia`.

- **Panel** (grupo «Membresías», solo el administrador del grupo):
  - planes: crear, editar y desactivar, sin borrar;
  - pagos y notificaciones: solo lectura, con filtros por estado y
    resultado;
  - en la ficha del cliente, sus periodos de membresía.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-11-03); el revisor las aprueba con
esta spec.

1. Un periodo por pago aprobado, sin columna `estado` en `membresias`
   (CLAUDE.md §4 la lista).
2. Renovar antes de vencer apila el periodo nuevo al final del actual.
3. Dos pagos aprobados se apilan en lugar de rechazar el segundo.
4. Un rechazo o anulación después de aprobado solo se registra. Reversar
   un periodo queda manual.
5. Wompi como primer adaptador mientras el cliente decide (`TODO(cliente)`).
6. Días de aviso de renovación: supuesto del fixture, para confirmar con
   el cliente.
7. Los precios de la decisión 8 del issue #1 están confirmados, así que
   CLAUDE.md §9 marca ese pendiente como resuelto en este PR.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los puntos son estatus, no moneda
- 3.5 Tres fuentes de nivel separadas
- 3.6 Ascenso síncrono, descenso nocturno
- 3.7 Idempotencia por restricción de base de datos
- 3.9 Pesos enteros y `America/Bogota`
- 3.13 Código agnóstico del motor

## Notas de interfaz

- Pantalla 1s de `docs/diseno/` (COMPONENTS.md, "Subscription (1s)"):
  tarjeta Diamante, planes con radio y CTA con el precio del plan
  elegido.
- El texto "Tarjeta de crédito, débito o PSE" depende de los medios que
  ofrezca la pasarela elegida (`docs/diseno/NOTAS.md`).
- El estado del pago: pendiente ("Estamos confirmando tu pago"), aprobado
  (nivel nuevo y vigencia) y rechazado (con "Intentar de nuevo").
- Prueba E2E del flujo principal con la pasarela simulada.

## Depende de

E3-03 (`RaiseNivelEfectivo` y lectura de `membresia_vigente_hasta`), E3-05
(cola `nocturno`), E8-01 (base de la PWA), E7-01 (envío del aviso de
renovación). Cuenta de la pasarela a nombre del cliente, solo para la
prueba con el sandbox.

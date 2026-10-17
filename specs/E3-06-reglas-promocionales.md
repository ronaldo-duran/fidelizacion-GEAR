# E3-06 · Reglas promocionales: bono de registro y misiones

**Épica:** E3 · **Horas estimadas:** por estimar · **Riesgo:** alto

## Objetivo

Otorgar puntos fijos por hechos que no son una compra (inscribirse en el
programa durante una promoción o cumplir una misión validada por un
comercio), configurados desde el panel, con control central del grupo
sobre cuánto puede dar cada comercio y aprobación delegada al comercio
dueño de la misión.

## Fuera de alcance

- Integración automática con Instagram, WhatsApp u otras redes, y lectura
  automática de la imagen del soporte (OCR): por contrato (CLAUDE.md §8).
  La validación siempre la hace una persona.
- Analítica de campañas y segmentación de clientes.
- Notificar al cliente la aprobación o el rechazo (E7-01).
- El registro del cliente en sí (E8-02): aquí solo se define la Action que
  el registro llama para abonar el bono.

## Reglas de negocio

1. **Dos tipos de regla nuevos** en el motor de E3-01, con esquema cerrado
   (regla dura 3.8):
   - `bono_registro`: puntos fijos al inscribirse en el programa mientras
     la regla está vigente y activa. Automático. Es del programa, no de un
     comercio, y solo puede haber uno vigente a la vez.
   - `mision`: puntos fijos al cumplir una tarea que valida a mano un
     usuario del comercio dueño de la misión.
2. **Versionado** (regla dura 3.2): activar, desactivar o cambiar una
   promoción crea una versión nueva; nunca se edita la anterior.
3. **Los puntos son reales** (regla dura 3.1): entran al saldo como un
   movimiento `promocion`, cuentan para el nivel por puntos y vencen como
   cualquier lote (E3-05). No son una moneda aparte.
4. **Abono por el libro** (reglas duras 3.3 y 3.4): todo abono entra por
   una Action del libro de E3-02, dentro de una transacción, con
   `regla_id` y `regla_version` de la promoción.
5. **Una vez por cliente, garantizado por la base de datos** (regla dura
   3.7): el bono de registro se abona una sola vez por cliente. Cada misión
   define un máximo por cliente (por defecto 1; p. ej. "matricular un
   alumno" puede aceptar uno por hijo). Una restricción única lo garantiza;
   nunca una validación en código.
6. **Control central del grupo**: el administrador del grupo define, por
   comercio, si puede crear misiones y cuántos puntos como máximo puede dar
   una misión. Un comercio no puede crear ni versionar una misión que se
   salga de lo habilitado.
7. **Quién crea qué**: el bono de registro lo crea solo el administrador
   del grupo. Una misión la crea el administrador del grupo para cualquier
   comercio, o el administrador de comercio para el suyo, dentro de sus
   límites.
8. **Aprobación delegada**: aprueba o rechaza un usuario activo del
   comercio dueño de la misión que tenga el permiso `aprueba_misiones` (no
   es un rol: puede ser un cajero). El administrador del grupo no aprueba
   cumplimientos (comentario de Ronaldo en el issue #44).
9. **Flujo de una misión**: el cliente envía el cumplimiento con su soporte
   (pantallazo) desde la PWA → queda pendiente → un usuario autorizado lo
   aprueba o lo rechaza. Solo al aprobar se abonan los puntos; un rechazo
   queda registrado con su motivo y no abona nada.
10. Un cumplimiento se abona con la versión de la misión vigente cuando el
    cliente lo envió (snapshot, regla dura 3.3), aunque la misión cambie o
    se desactive antes de la aprobación.

## Criterios de aceptación

- [ ] El esquema acepta los tipos `bono_registro` y `mision` con lista
      blanca cerrada; rechaza claves o valores fuera de la lista.
- [ ] Una promoción se activa y desactiva creando versiones, sin editar la
      anterior y sin solapes; no puede haber dos `bono_registro` vigentes a
      la vez.
- [ ] Dado un cliente que se inscribe con un bono vigente, cuando se abona,
      entonces existe un movimiento `promocion` con el snapshot de la regla,
      dentro de la misma transacción, y el saldo subió esos puntos.
- [ ] Dado un cliente que se inscribe sin bono vigente, entonces no se abona
      nada.
- [ ] Dados dos abonos simultáneos del mismo bono al mismo cliente, cuando
      ambos terminan, entonces existe un solo movimiento: lo impide la
      restricción única de la base de datos (prueba de concurrencia).
- [ ] Dada una misión con máximo 2 por cliente, cuando se aprueban dos
      cumplimientos, entonces se abonan ambos; un tercero se rechaza.
- [ ] Dado un comercio sin misiones habilitadas, o una misión que pide más
      puntos que su máximo, cuando su administrador la guarda, entonces se
      rechaza.
- [ ] Dado un administrador de comercio, cuando intenta crear un
      `bono_registro` o una misión de otro comercio, entonces recibe 403.
- [ ] Dado un cumplimiento pendiente, cuando lo aprueba un usuario del
      comercio dueño con `aprueba_misiones`, entonces se abona; cuando lo
      intenta un usuario sin el permiso, de otro comercio o el
      administrador del grupo, entonces recibe 403 y no se abona nada.
- [ ] Dado un cumplimiento rechazado, entonces no hay movimiento y queda el
      motivo y quién lo rechazó.
- [ ] Dado un cumplimiento enviado antes de desactivar la misión, cuando se
      aprueba después, entonces se abona con la versión vigente al enviarlo.
- [ ] Dado un cliente con un cumplimiento pendiente de una misión, cuando
      envía otro de la misma misión, entonces se rechaza.
- [ ] Cobertura obligatoria (riesgo alto): motor de reglas e idempotencia.

## Fixtures

`tests/Fixtures/promociones-escenarios.json` (nuevo, en inglés):
definiciones válidas e inválidas de los dos tipos nuevos, límites por
comercio y escenarios de abono, aprobación e idempotencia. Los ejemplos
usan los valores que dio el cliente (400 puntos por registro en el
lanzamiento; 10 puntos por seguir en Instagram; 100 por matricular un
alumno).

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **Esquema**: se agregan `defBonoRegistro` (`puntos`, `titulo`) y
  `defMision` (`comercio_id`, `titulo`, `descripcion`, `puntos`,
  `max_por_cliente`, `requiere_soporte`) a `schema-regla.json` y a su
  copia en `resources/schemas/regla.json`, en el PR del código. Los textos
  se muestran al cliente; nunca se evalúan.
- **`comercio_promocion_limites`** (tabla nueva, la administra el grupo):
  `comercio_id` (único), `permite_misiones`, `puntos_max_por_mision`,
  timestamps. Sin fila = sin misiones. Sus cambios quedan en la auditoría
  de E2-01.
- **`users.aprueba_misiones`** (booleana, por defecto falsa): igual que
  `edita_reglas_json`, no es asignable en masa. El administrador del grupo
  la puede dar a cualquier usuario; el administrador de comercio, solo a
  usuarios de su comercio.
- **`cumplimientos_mision`** (tabla nueva): `id`, `cliente_id`,
  `regla_id`, `regla_version`, `comercio_id`, `soporte_path` (disco
  privado, nullable si la misión no lo pide), `estado` (`pendiente` |
  `aprobado` | `rechazado`), `motivo_rechazo`, `revisado_por`,
  `revisado_at`, timestamps. El estado solo pasa una vez de `pendiente` a
  `aprobado` o `rechazado`; el modelo rechaza cualquier otro cambio.
- **`promociones_otorgadas`** (tabla nueva, la que garantiza la
  idempotencia): `cliente_id`, `codigo` (de la regla, para que valga a
  través de sus versiones), `ocurrencia` (1, 2, …), `movimiento_id`,
  `created_at`. **Restricción única `(cliente_id, codigo, ocurrencia)`**.
  Se inserta en la misma transacción que el movimiento; un abono repetido o
  simultáneo choca en la base de datos y no se duplica.
- **Movimiento `promocion`** (ajuste a la spec E3-02, PR #57): positivo,
  con snapshot de regla, `comercio_id` de la misión (nulo en el bono de
  registro), sin transacción de compra y con `vigencia_hasta` según la
  regla de acumulación vigente.
- **Actions**:
  - `GrantBonoRegistro`: la llama el registro del cliente (E8-02) en su
    misma transacción. Si hay un `bono_registro` vigente y activo, abona.
  - `SubmitCumplimientoMision`: el cliente envía el soporte.
  - `ApproveCumplimientoMision` y `RejectCumplimientoMision`.
  - El abono pasa por una Action del libro (`RecordPromocion`, E3-02) y
    luego por `RaiseNivelEfectivo` (E3-03), porque los puntos de una
    promoción pueden subir de nivel al cliente.
- **Panel**:
  - "Reglas" (E3-01) gestiona también el `bono_registro`.
  - Recurso nuevo "Misiones": el grupo ve todas y el comercio solo las
    suyas; crear y guardar versiones pasa por las Actions de E3-01 con la
    validación de límites.
  - Recurso nuevo "Cumplimientos": cola de pendientes del comercio para
    quien tiene `aprueba_misiones`, con el soporte y los botones aprobar y
    rechazar.
  - En "Comercios", el grupo edita los límites de promociones.
- **PWA**: pantalla de misiones disponibles y envío del soporte, con su
  prueba E2E (CLAUDE.md §7). Depende de E8-01 y E8-02.
- **Sugerencia de PRs del código**: (1) esquema, límites, permisos y bono
  de registro; (2) misiones y cumplimientos en el panel; (3) pantalla de la
  PWA.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-14); el revisor las aprueba con
esta spec.

1. El permiso `aprueba_misiones` es una columna booleana en `users`, como
   `edita_reglas_json`, sin paquete de permisos.
2. El administrador de comercio crea las misiones de su comercio dentro de
   los límites del grupo. Esto abre el motor de reglas al administrador de
   comercio solo para el tipo `mision` y solo para su comercio.
3. Cada misión tiene un máximo por cliente configurable (por defecto 1);
   el bono de registro siempre es uno.
4. Los puntos de una promoción entran como un tipo de movimiento nuevo,
   `promocion`, que se agrega a la spec E3-02.
5. "Inscribirse en el programa" es completar el registro del cliente
   (E8-02), sin importar el canal. Un cliente creado antes de que exista el
   bono no lo recibe después.
6. Solo puede haber un `bono_registro` vigente a la vez.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los puntos son estatus, no moneda
- 3.2 Versionado de reglas
- 3.3 Snapshot de regla en cada movimiento
- 3.4 Libro inmutable
- 3.7 Idempotencia por restricción de base de datos
- 3.8 Esquema JSON cerrado
- 3.13 Código agnóstico del motor

## Depende de

E3-01 (#7, motor de reglas), E3-02 (#8, libro de puntos), E3-03 (#9,
nivel efectivo), E3-05 (#16, vencimiento de lotes), E4-01 (#14), E8-02
(#18, registro del cliente), E2-01 (roles y alcance por comercio).

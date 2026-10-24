# E8-02 · Registro y acceso del cliente

**Épica:** E8 · **Horas estimadas:** 8 (más E8-06) · **Riesgo:** medio

## Objetivo

El cliente se inscribe en el programa desde su celular, verifica su número
por SMS y entra a la PWA con su tipo y número de documento y un PIN.

## Fuera de alcance

- El envío del código por SMS, su generación, expiración e intentos
  (E8-06, #46). Aquí se define en qué momento del registro se pide y qué
  pasa cuando se verifica.
- El texto legal de la autorización y la política de datos, y la consulta
  y supresión de datos (E10-03). Aquí se captura la autorización.
- El bono de registro (E3-06). Aquí solo se llama a su Action cuando
  exista.
- La pantalla del cajero (E4-02). Aquí se define el enlace de registro que
  el cajero muestra como QR cuando el documento no existe.
- El registro asistido sin celular ("No tiene celular: lo registro yo",
  pantalla 1i): queda como decisión a confirmar (ver abajo).
- Inicio de sesión de cajeros y administradores (E4-02, E10-01).

## Reglas de negocio

1. **El cliente es el par tipo + número de documento** (CLAUDE.md §3.10).
   Toda pantalla pide el tipo, con `CC` preseleccionado; el número se
   guarda y se busca en mayúsculas y sin espacios.
2. **Datos del registro** (pantalla 1m, ajustada): tipo y número de
   documento, nombre completo, fecha de nacimiento, celular, PIN (dos
   veces) y la autorización de tratamiento de datos. El correo es
   opcional. "Crear mi cuenta" está deshabilitado hasta marcar la
   autorización, con la ayuda "Marca la autorización para continuar".
3. **Validaciones**: las reglas de cada tipo de documento, del celular, del
   PIN y la edad mínima viven en el fixture (no en esta prosa). El PIN
   rechaza combinaciones triviales (todos iguales o consecutivos).
4. **La cuenta existe solo cuando el celular queda verificado** (E8-06).
   Mientras tanto, los datos esperan en un registro pendiente que vence;
   un registro sin verificar no crea cliente, no acredita compras y no da
   acceso.
5. **Al verificar**, en una sola transacción: se crea el cliente, se guarda
   la autorización (versión del texto, fecha, IP y canal), se acreditan
   sus compras pendientes por tipo + número (`CreditPendingCompras`,
   E4-01) y, cuando exista E3-06, se abona el bono de registro
   (`GrantBonoRegistro`). Si algo falla, no queda nada. El cliente queda
   con la sesión iniciada.
6. **Documento ya registrado**: el mensaje no revela datos del titular:
   "Ya hay una cuenta con ese documento. Ingresa o recupera tu PIN."
7. **Registro desde la caja** (pantalla 1i): cuando el cajero no encuentra
   el documento, muestra un QR con un enlace firmado y de vida corta que
   abre el registro con el tipo y número ya puestos. El cliente lo
   completa en su celular y el cajero atiende al siguiente. La compra que
   quedó pendiente se acredita al verificar, por la regla 5.
8. **Ingreso**: tipo + número de documento + PIN. Tras varios intentos
   fallidos seguidos, el ingreso de ese documento se bloquea un tiempo
   (números en el fixture). El mensaje de error no dice si el documento
   existe. La sesión se mantiene en el dispositivo hasta cerrar sesión.
9. **Olvidé mi PIN**: se verifica el celular registrado con un código por
   SMS (E8-06) y se define un PIN nuevo. Cambiar el PIN cierra las otras
   sesiones del cliente.
10. **Sesión del cliente separada** de la de los usuarios del panel: un
    guard propio, y un cliente nunca entra al panel ni un usuario del
    panel a la PWA como cliente.

## Criterios de aceptación

- [ ] Dado el formulario sin la autorización marcada, entonces "Crear mi
      cuenta" no se puede usar y no se crea nada.
- [ ] Dado un documento, celular, PIN o fecha de nacimiento fuera de las
      reglas del fixture, cuando se envía, entonces se muestra el error del
      campo y no se envía SMS.
- [ ] Dado un registro sin verificar, entonces no existe el cliente, sus
      compras siguen pendientes y no puede ingresar.
- [ ] Dado un registro verificado, entonces existe el cliente con su
      autorización (versión, fecha, IP y canal), sus compras pendientes de
      ese tipo + número quedaron acreditadas una sola vez y tiene sesión.
- [ ] Dada una compra pendiente con CE, cuando se registra alguien con el
      mismo número y CC, entonces no se le acredita.
- [ ] Dado un documento ya registrado, cuando se intenta registrar de
      nuevo, entonces se muestra el mensaje genérico y no se revela nada
      del titular.
- [ ] Dado el enlace del QR de la caja, cuando se abre, entonces el
      registro trae el tipo y el número puestos; un enlace vencido o
      alterado se rechaza.
- [ ] Dado el PIN correcto, entonces entra; dado uno incorrecto, entonces
      un mensaje que no dice si el documento existe; tras el máximo de
      intentos, entonces ese documento queda bloqueado el tiempo del
      fixture, aunque después llegue el PIN correcto.
- [ ] Dado "Olvidé mi PIN" con el código correcto, entonces el PIN cambia y
      se cierran las otras sesiones.
- [ ] Dado un cliente con sesión, cuando abre `/admin`, entonces no entra;
      y un usuario del panel no tiene sesión de cliente.
- [ ] Cada pantalla (registro, verificación, ingreso, recuperar PIN) tiene
      su prueba E2E a 360 px.

## Fixtures

`tests/Fixtures/registro-escenarios.json` (nuevo, en inglés): reglas de
validación por tipo de documento, celular, PIN y edad, intentos y tiempos
de bloqueo, vida del registro pendiente y del enlace de la caja, y
escenarios de registro, duplicados, compras pendientes, ingreso y
recuperación del PIN. Los valores que el cliente no ha confirmado van con
`"_assumption": true`.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **`clientes`** suma `nombre_completo`, `pin_hash` (hash de Laravel,
  nunca el PIN), `celular_verificado_at` y `remember_token`. `celular` se
  guarda normalizado (10 dígitos). El modelo `Cliente` pasa a ser
  autenticable (guard `cliente`, proveedor propio).
- **`registros_pendientes`** (tabla nueva): `id`, `tipo_documento`,
  `documento`, `nombre_completo`, `fecha_nacimiento`, `celular`, `email`,
  `pin_hash`, `autorizacion_version`, `ip`, `canal` (`pwa` | `caja_qr`),
  `vence_at`, timestamps. Un intento nuevo con el mismo documento
  reemplaza al anterior; los vencidos se purgan. No reserva el documento:
  el cliente se crea solo al verificar.
- **`autorizaciones_datos`** (append-only): `id`, `cliente_id`,
  `version_texto`, `aceptada_at`, `ip`, `canal`, `created_at`. La versión
  del texto la define E10-03; mientras tanto, una versión borrador
  evidente con `TODO(cliente)`.
- **Bloqueo del ingreso**: `RateLimiter` de Laravel por tipo + número
  normalizados (y por IP), con los números del fixture.
- **Enlace de la caja**: URL firmada temporal de Laravel
  (`temporarySignedRoute`) con el tipo y el número; nunca datos de la
  compra.
- **Actions** (`app/Actions/Clientes/`, invocables):
  - `StartRegistro`: valida (Form Request en la pantalla), guarda el
    registro pendiente y pide el código a E8-06.
  - `CompleteRegistro`: verifica el código (E8-06) y hace la regla 5 en una
    transacción.
  - `LoginCliente`: tipo + número + PIN con el bloqueo.
  - `ResetPin`: verifica el código (E8-06), cambia el PIN y cierra las
    otras sesiones.
- **Pantallas** (Livewire, delgadas, toda la lógica en las Actions):
  registro (1m ajustada), verificar código, ingreso y recuperar PIN. El
  diseño no tiene pantalla de ingreso del cliente (`docs/diseno/NOTAS.md`):
  se arma con los componentes de E8-01 y la estructura de la pantalla 1y.
- **Orden con E8-06**: el código de E8-02 se apoya en la interfaz de
  envío de E8-06; se construye E8-06 primero, o los dos en la misma pila.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-22); el revisor las aprueba con
esta spec.

1. La cuenta se crea solo al verificar el celular; antes, un registro
   pendiente que vence y no reserva el documento.
2. La fecha de nacimiento es obligatoria en el registro, con una edad
   mínima (supuesto en el fixture: 18 años, por la Ley 1581 sobre datos de
   menores; confirmar con el área legal).
3. PIN de 4 dígitos que rechaza combinaciones triviales (supuesto en el
   fixture).
4. Bloqueo del ingreso tras 5 intentos fallidos durante 15 minutos
   (supuesto en el fixture).
5. "Olvidé mi PIN" con código por SMS al celular registrado (E8-06).
6. El correo es opcional.
7. El registro asistido sin celular (1i) **no** entra en E8-02: sin
   celular no hay verificación ni PIN, y la autorización necesita prueba.
   Mientras tanto, la compra queda pendiente sin vencer (E4-01) hasta que
   el cliente se registre. Si se quiere, va en un issue aparte con su
   forma de capturar la autorización.

## Reglas duras de CLAUDE.md que aplican

- 3.10 Cliente por tipo + número, normalizado
- 3.3 y 3.4 Las compras pendientes se acreditan por las Actions del libro
  (E4-01)
- 3.13 Código agnóstico del motor (normalizar antes de guardar y buscar)

## Notas de interfaz

- Skill `club-ar-ui`: tokens, tuteo, zonas táctiles de 44 px y prueba a
  360 px.
- Textos de la pantalla 1m: "Únete al Club", "Tus compras en los 15
  comercios del grupo suman puntos para subir de nivel.", "Crear mi
  cuenta", "Marca la autorización para continuar".

## Depende de

E8-01 (base de la PWA), E4-01 (compras pendientes, en `main`), E8-06
(#46, verificación por SMS). Se enlaza con E3-06 (bono de registro) y
E4-02 (QR desde la caja).

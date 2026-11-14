# E9-01 · API pública para comercios

**Épica:** E9 · **Horas estimadas:** 36 · **Riesgo:** alto

## Objetivo

Que el punto de venta de un comercio registre compras y entregue
beneficios sin pasar por la caja del programa. Lo hace con credenciales
propias, siete operaciones documentadas y reintentos que nunca duplican
puntos ni redenciones.

## Fuera de alcance

- La integración a la medida con un POS específico (§8). Aquí se publica
  la API y su documentación; conectar cada POS lo hace el comercio o su
  proveedor.
- La carga masiva por archivo (E4-03). Usa la misma Action de compra, pero
  por otra puerta.
- Webhooks salientes hacia el comercio (avisar al POS de un cambio). No los
  pide el issue.
- Registrar clientes, eventos calificados o membresías desde la API. El
  cliente se registra en la PWA (E8-02); una compra de un documento sin
  cliente queda pendiente (E4-01, regla 5).
- Ajustes manuales de puntos. Siguen siendo solo del panel (E10-06).
- Un portal de desarrolladores. La documentación es un archivo OpenAPI y
  una guía en el repositorio, que se entregan al comercio.

## Las siete operaciones

Todas cuelgan de `/api/v1` y responden JSON. Cada una exige un alcance
(regla 3). Los nombres de los campos son los mismos de las columnas de la
base (dominio en español).

| #   | Método y ruta                                         | Alcance              | Qué hace                                                                                                                                                     | Action que reutiliza                            |
| --- | ----------------------------------------------------- | -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------ | ----------------------------------------------- |
| 1   | `GET /clientes?tipo_documento=&documento=`            | `clientes:leer`      | Dice si el documento es cliente del programa y su nivel efectivo.                                                                                            | `FindCliente` (E4-02)                           |
| 2   | `GET /clientes/beneficios?tipo_documento=&documento=` | `clientes:leer`      | Los beneficios del comercio para ese cliente, con su estado y su valor para el nivel.                                                                        | `FindCliente` + `BeneficiosParaCliente` (E5-01) |
| 3   | `POST /compras`                                       | `compras:registrar`  | Registra una compra y otorga los puntos, con el ascenso en la misma petición.                                                                                | `RegisterCompra` (E4-01)                        |
| 4   | `GET /compras/{referencia_externa}`                   | `compras:registrar`  | Consulta una compra del comercio: estado y puntos otorgados.                                                                                                 | lectura de `transacciones`                      |
| 5   | `POST /compras/{referencia_externa}/anulacion`        | `compras:anular`     | Anula una compra con su reversa, dentro del plazo.                                                                                                           | `AnnulCompra` (E4-01)                           |
| 6   | `GET /codigos/{codigo}`                               | `beneficios:redimir` | Valida el código de un beneficio sin gastarlo: válido, ya usado o no válido.                                                                                 | `CheckBeneficioCredencial` (E5-02)              |
| 7   | `POST /redenciones`                                   | `beneficios:redimir` | Confirma la entrega de un beneficio, por código o por documento, con su valor otorgado. Por documento exige además `beneficios:redimir_documento` (regla 3). | `RedeemBeneficio` (E5-02)                       |

Los cuerpos y las respuestas exactas quedan en `docs/api/openapi.yaml`
(regla 13); los casos con números, en el fixture.

## Reglas de negocio

1. **Una credencial es de una sede.** Cada credencial pertenece a un
   comercio y a una de sus sedes (decisión 1). Todo lo que hace queda
   registrado en esa sede: la compra, la anulación y la redención. Un
   comercio con varias cajas o sedes crea una credencial por cada una.
2. **Un token de un comercio nunca opera sobre otro.** El comercio y la
   sede salen siempre de la credencial, nunca del cuerpo de la petición.
   - Una compra de otro comercio no existe para la credencial: responde
     404, igual que una referencia que no existe, para no revelar nada.
   - Un código de beneficio de otro comercio responde "no válido", como en
     la caja.
   - Solo se ven y se entregan los beneficios del comercio.
3. **Alcances.** Cada credencial lleva uno o más alcances: `clientes:leer`,
   `compras:registrar`, `compras:anular`, `beneficios:redimir` y
   `beneficios:redimir_documento`. Sin el alcance de la operación responde 403. Una credencial nueva no trae ninguno marcado: el administrador elige.
   - **Entrega por documento** (decisión 10): además de
     `beneficios:redimir`, exige `beneficios:redimir_documento`, para que el
     comercio la active a propósito. Sin él responde 403 y no escribe nada;
     la entrega por código sigue igual.
   - La vía por documento es para que el cajero **lea** el número del QR o
     de la cédula digital que el cliente presenta en ese momento, nunca un
     número dictado. La presencia del cliente sigue siendo el control (regla
     dura 3.12) y verificarla es responsabilidad del comercio. La guía del
     POS y el OpenAPI lo dicen igual.
4. **Credenciales desde el panel.** El administrador del grupo, en
   cualquier comercio, y el administrador de comercio, en el suyo:
   - **Crear:** nombre (por ejemplo "POS caja 1"), sede y alcances. El
     token se muestra **una sola vez**; la base guarda solo su huella.
   - **Rotar:** emite un token nuevo y el anterior sigue sirviendo durante
     una ventana de gracia (horas en el fixture), para que el POS cambie
     sin cortar ventas. Después deja de servir.
   - **Revocar:** el token deja de servir en la siguiente petición.
   - El panel muestra cuándo se usó por última vez. Crear, rotar y revocar
     quedan en la auditoría.
5. **Una credencial deja de servir** si se revoca, si vence su ventana de
   gracia, o si su comercio o su sede se desactivan. Responde 401 en los
   dos primeros casos y 403 en el último, siempre sin decir nada del
   token.
6. **Idempotencia** (regla dura 3.7). Todo POST exige el header
   `Idempotency-Key` con un UUID (decisión 3). Sin él, o mal formado,
   responde **400** antes de tocar la base. Lo que protege de verdad es una
   restricción única en la base, nunca una validación en código:
   - **Compra:** la referencia del POS (`referencia_externa`) con la
     restricción `UNIQUE (comercio_id, referencia_externa)` de E4-01. La
     llave se guarda en `transacciones.idempotency_key` para soporte.
   - **Redención:** la llave es la `referencia` de la redención, que ya es
     única (E5-02).
   - **Anulación:** la compra solo pasa una vez a `anulada` y la reversa se
     inserta una sola vez (E3-02).
7. **Un reintento devuelve la respuesta original.** Ese reintento puede
   ser la misma compra con los mismos datos, la misma llave de redención o
   la anulación de una compra ya anulada. Responde **200** (no 201) con el
   mismo recurso y el header `Idempotent-Replayed: true`, y no otorga ni
   descuenta nada. El cuerpo es el mismo, salvo el nivel efectivo, que es
   el de ese momento.
8. **Un reintento con otros datos es un conflicto.** La misma referencia
   con otro cliente o con otro monto responde **409**
   (`compra_en_conflicto`), como en E4-01. Lo mismo pasa con la misma llave
   de redención y otro beneficio, otro cliente u otro código. Nada se
   escribe.
9. **Compra.**
   - **Cuerpo:** `tipo_documento`, `documento`, `monto` y
     `referencia_externa`.
   - **Monto:** un entero JSON en pesos, mayor que cero (regla dura 3.9). No
     se acepta un texto (`"85000"`) ni un número con decimales (`85000.0`).
     El tope sale del fixture.
   - **Documento:** se normaliza como en todo el sistema (regla dura 3.10).
     Un documento sin cliente deja la compra pendiente y responde 201 con
     `estado: pendiente`, igual que en la caja.
   - **Respuesta:** el estado, los puntos otorgados (decisión 6) y el nivel
     efectivo después de la compra.
   - **Canal:** la compra queda con `canal = api`.
10. **Anulación.**
    - Pide un `motivo` y solo alcanza compras del mismo comercio
      registradas por la API.
    - El plazo es el de la reversa del libro (E3-02, `reversal_window_days`)
      (decisión 4).
    - Fuera de plazo responde 422 y la compra no cambia.
    - Una compra pendiente se anula sin movimientos.
11. **Consulta de cliente.** Responde solo lo que el POS necesita para
    atender:
    - el primer nombre;
    - el nivel efectivo y desde cuándo lo tiene.

    Nunca devuelve el celular, el correo, la fecha de nacimiento, el saldo
    ni los movimientos (Ley 1581). Un documento sin cliente responde 404
    (`cliente_no_registrado`). El mismo número con otro tipo de documento
    es otro cliente.

12. **Topes por credencial.** Responden **429** con `Retry-After` (los
    números, en el fixture):
    - un tope general de peticiones por minuto;
    - uno más bajo para las consultas por documento (operaciones 1 y 2),
      para que nadie recorra documentos.

    Las peticiones rechazadas por el tope no tocan la base.

13. **Versión y documentación.**
    - La ruta lleva la versión (`/api/v1`). En `v1` solo se agregan campos
      o rutas; quitar o cambiar uno es `v2`.
    - `docs/api/openapi.yaml` (OpenAPI 3.1) describe las siete operaciones,
      sus errores y ejemplos.
    - `docs/api/README.md` es la guía en español para el proveedor del POS:
      credencial, idempotencia, reintentos y errores.
14. **Errores en un solo formato.** Todo error de `/api` (incluidos 404 de
    ruta, 405, 429 y 500) responde JSON con esta forma:

    ```json
    {
      "error": {
        "codigo": "compra_en_conflicto",
        "mensaje": "Ya existe otra compra con esa referencia.",
        "campos": {}
      }
    }
    ```

    - `codigo` es estable y es lo que el POS debe leer.
    - `mensaje` está en español de Colombia.
    - `campos` lleva los errores de validación por campo (422).

    Un 500 nunca muestra la traza ni el SQL.

15. **Fechas y montos.** Las fechas salen en ISO 8601 con la zona de Bogotá
    (`2026-11-06T15:00:00-05:00`). La hora la pone la aplicación (regla dura
    3.13). Los montos y los valores otorgados salen como enteros en pesos.

## Criterios de aceptación

- [ ] 10 peticiones simultáneas de la misma compra (misma referencia, mismos
      datos) dan 1 transacción y los movimientos de una sola acreditación.
      Una responde 201 y las demás 200 con `Idempotent-Replayed: true`.
      Corre con concurrencia real en PostgreSQL, y también en SQL Server en
      el CI.
- [ ] 10 entregas simultáneas con la misma llave dan 1 redención.
- [ ] Un POST sin `Idempotency-Key`, o con una que no es UUID, responde 400
      y no escribe nada.
- [ ] La misma referencia con otro monto o con otro documento responde 409
      y no escribe nada. La misma referencia en otro comercio se registra.
- [ ] Una credencial del comercio A responde lo siguiente sobre el
      comercio B:
  - 404 al consultar o anular una compra de B;
  - "no válido" ante un código de B;
  - nunca ve ni entrega un beneficio de B.
- [ ] Sin token, con un token revocado o con un token rotado después de su
      ventana de gracia responde 401. Durante la ventana sirven el token
      viejo y el nuevo.
- [ ] Sin el alcance de la operación responde 403. Con el comercio o la
      sede de la credencial inactivos, también 403.
- [ ] Un monto de cero, negativo, en texto o con decimales responde 422.
- [ ] Una compra de un cliente que cruza un umbral responde con el nivel
      nuevo. Una de un documento sin cliente queda pendiente.
- [ ] Anular dentro del plazo inserta la reversa y deja la compra anulada.
      Repetirlo responde 200 sin otra reversa. Fuera de plazo responde 422.
- [ ] La consulta de cliente no devuelve celular, correo, fecha de
      nacimiento ni saldo. Pasado el tope por minuto, responde 429.
- [ ] Una redención de porcentaje guarda el valor otorgado del monto de la
      cuenta (regla dura 3.11), con el redondeo de E5-02. No cambia el saldo
      ni el nivel (regla dura 3.1).
- [ ] Todo error de `/api` tiene la forma de la regla 14, incluido un 404
      de ruta inexistente y un 500.
- [ ] Cada ruta de `/api/v1` aparece en `docs/api/openapi.yaml` (una prueba
      lo compara).
- [ ] Panel:
  - crear una credencial muestra el token una vez;
  - rotarla y revocarla funcionan;
  - un administrador de comercio solo ve y crea credenciales de su comercio.
- [ ] Cobertura obligatoria de la idempotencia (CLAUDE.md §7).

## Fixtures

`tests/Fixtures/api-escenarios.json` (nuevo, en inglés). Trae:

- topes, ventana de gracia y monto máximo;
- los alcances de cada operación;
- casos de autenticación, idempotencia, aislamiento entre comercios,
  validación, compra, anulación, consulta de cliente, beneficios y
  redención.

Las reglas y los comercios son los de `tests/Fixtures/compras-escenarios.json`.
El valor otorgado y los estados de un beneficio son los de
`tests/Fixtures/redencion-escenarios.json` y no se repiten aquí.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

### Credenciales: Laravel Sanctum sobre un usuario de integración

- **Dependencia nueva:** `laravel/sanctum` (MIT, oficial de Laravel), con
  `php artisan install:api`. La propone el issue y se aprueba con esta spec
  (CLAUDE.md §2: se pregunta antes de agregar dependencias). Solo tokens,
  sin el modo de cookies para SPA.
- **Usuario de integración:**
  - Cada credencial es un `User` con el rol nuevo `integracion`, su
    `comercio_id` y su `sede_id`. Su nombre es el de la credencial, el
    correo es interno y único, y la contraseña es aleatoria y nunca se
    muestra.
  - Ese usuario no entra al panel ni a la caja: `puedeAccederAlPanel()` y
    el inicio de sesión del cajero lo rechazan.
  - No aparece en la lista de usuarios del panel; vive en su propio
    recurso.
  - Así, las Actions de compra, reversa y redención, que piden un
    `User`, no cambian de firma. `registrada_por`, `usuario_id` de la
    reversa y `validado_por` de la redención quedan con el usuario de la
    credencial, y cada operación se puede rastrear hasta el POS que la
    hizo.
- **Tokens:** `personal_access_tokens` de Sanctum guarda la huella SHA-256,
  los alcances (`abilities`), `last_used_at` y `expires_at`.
  - **Rotar:** pone `expires_at` al token viejo (ahora + la ventana de
    gracia) y emite uno nuevo.
  - **Revocar:** borra los tokens y desactiva al usuario.

  Esa tabla es de Sanctum, no de dominio: no es append-only.

- **Recurso del panel:** "Credenciales API", en el grupo de Comercios.
  - Lista: nombre, comercio, sede, alcances, último uso y estado.
  - Acciones: Crear (modal que muestra el token una vez, con botón de
    copiar), Rotar y Revocar, con confirmación.
  - Alcance del administrador como en el resto del panel (`AlcancePanel`).
  - El modelo del usuario de integración lleva `AuditoriaObserver`.

### Autorización

- Middleware de la API, en orden:
  1. `auth:sanctum`;
  2. el usuario es `integracion` y está activo, con comercio y sede
     activos;
  3. el alcance del token (`abilities:`);
  4. los topes.
- `TransaccionPolicy::register`: la integración registra solo en su sede.
- `TransaccionPolicy::annul` y `MovimientoPuntosPolicy::reverse`: la
  integración anula solo las compras de su comercio con `canal = api`,
  dentro del plazo de la reversa.
- `RedeemBeneficio` y `CheckBeneficioCredencial` hoy piden
  `puedeEntrarALaCaja()`. Pasan a pedir un método nuevo,
  `puedeEntregarBeneficios()`: cajero activo con sede, o integración
  activa con sede. La caja no cambia de comportamiento.
- `FindCliente` lee su tope de `cajero.search_max_per_minute`. Pasa a
  recibirlo según el rol, y la API usa el suyo (regla 12).

### Idempotencia

- Un middleware `RequireIdempotencyKey` en los POST valida el header
  (UUID) y lo pasa al Form Request. Es solo la puerta del 400: no
  protege de duplicados.
- **Compra:** `RegisterCompra` ya atrapa la violación de la restricción
  única y devuelve la compra existente, o `CompraEnConflicto`. La API lo
  traduce a 200 con el header de repetición, o a 409.
  - Para que el reintento responda lo mismo que la primera vez, los puntos
    otorgados se leen de los movimientos `acumulacion` de esa transacción.
    No se guarda una copia de la respuesta (decisión 5).
- **Redención:** la llave del header es la `referencia` de
  `EntregaBeneficio`. `RedeemBeneficio` ya devuelve la redención repetida.
  - La API compara beneficio, cliente y código con los del cuerpo. Si
    difieren, responde 409.
  - Si la llave existe en otro comercio, `RedeemBeneficio` lanza
    `AuthorizationException`; la API responde 409 sin decir de quién es.
- **Anulación:** si la compra ya está anulada, la API responde 200 con la
  compra y el header de repetición, en lugar del error de `AnnulCompra`.

### Controladores, Form Requests y respuestas

- Controladores delgados en `app/Http/Controllers/Api/V1/`. Cada uno valida
  con su Form Request, llama a una Action y devuelve un `JsonResource`
  (`app/Http/Resources/Api/V1/`).
- Ninguna regla de negocio vive en el controlador.
- `bootstrap/app.php` registra la ruta `api` con el prefijo `v1` y un
  manejador de excepciones para `/api/*` con la forma de la regla 14:
  - `ValidationException` → 422;
  - `AuthenticationException` → 401;
  - `AuthorizationException` → 403;
  - `ModelNotFoundException` y ruta inexistente → 404;
  - `CompraEnConflicto` → 409;
  - `EntregaRechazada` → 422 con su código;
  - `ThrottleRequestsException` → 429;
  - el resto → 500 con mensaje genérico.
- Los topes son dos limitadores con nombre en `AppServiceProvider`, por id
  de token: `api` y `api-consultas`.

### Datos

- Migración de Sanctum (`personal_access_tokens`), con los tipos de
  Laravel (regla dura 3.13).
- No hay tablas de dominio nuevas, y en `transacciones` y `redenciones` no
  cambia nada: la idempotencia ya vive en sus restricciones únicas.
- `RolUsuario::Integracion` nuevo. La columna `rol` es texto, así que no
  hay migración.

### Prueba de concurrencia

Diez procesos PHP (`Process::pool`) llaman a `RegisterCompra` con la misma
compra contra la base de prueba, sin la transacción envolvente de las
pruebas. Usa el truncado de `BloqueoTestData`, que conserva los catálogos
sembrados. La prueba verifica:

- una sola fila en `transacciones`;
- los movimientos de una sola acreditación;
- el saldo esperado;
- que cada proceso recibió la compra.

Lo mismo se hace con `RedeemBeneficio` y una sola llave. Corre en
PostgreSQL y SQL Server en el CI. Las secuencias del fixture se prueban
además en orden, por HTTP.

## Decisiones a confirmar en la revisión

Propuestas por el implementador el 2026-11-06.

1. **Una credencial por sede.** Así cada redención y cada compra tienen
   una sede cierta, como en la caja, y la API no confía en una sede que
   manda el POS. Un sistema central con varias sedes usa varias
   credenciales. Con 15 comercios no pesa.
2. **El usuario de integración** en vez de que el token pertenezca al
   comercio directamente. Las Actions de E4-01, E3-02 y E5-02 piden un
   `User` y las columnas `validado_por` y `usuario_id` lo exigen. Así no
   se toca su firma, que es código de riesgo alto ya revisado.
3. **`Idempotency-Key` es un UUID** en todos los POST. Es el formato que
   ya usa `redenciones.referencia`, y una regla sola es más fácil de
   documentar para el proveedor del POS.
4. **La anulación por API** solo alcanza compras registradas por la API,
   con el plazo de la reversa (30 días hoy). El plazo de 72 horas es el
   de la caja. Una devolución en el POS puede llegar días después de la
   venta; las compras de la caja se siguen anulando en la caja o en el
   panel.
5. **No se guarda una copia de cada respuesta** (una tabla de llaves al
   estilo de Stripe). El reintento se responde con el recurso que ya está
   en la base y la protección es la restricción única de cada tabla. Lo
   que se pierde: la misma llave con otra referencia de compra cuenta
   como otra compra. Manda la referencia, como dice la regla 3.7.
6. **La respuesta de la compra incluye los puntos otorgados**, para que el
   POS pueda imprimirlos en el recibo. La caja no los muestra por diseño
   (E4-02), pero eso es una decisión de pantalla. Los puntos siguen siendo
   estatus, no saldo (regla dura 3.1), y la respuesta no trae el saldo.
7. **Los topes** (supuestos, en el fixture): 120 peticiones por minuto por
   credencial y 60 consultas por documento por minuto. Una caja atiende
   como mucho una venta cada pocos segundos.
8. **El documento viaja en la consulta de la URL** (operaciones 1 y 2),
   porque una lectura no lleva cuerpo y un POST exigiría llave de
   idempotencia. La aplicación no registra la consulta de las URL de
   `/api`. El registro del proxy (Coolify) se revisa en la salida a
   producción (E1-02).
9. **Los campos y los códigos de error van en español**, como las columnas
   de la base y el dominio. El proveedor del POS es colombiano y el
   contrato coincide con la documentación.
10. **La entrega por documento se queda, con su propio alcance**
    (decidido por Ronaldo en la revisión del #131, 2026-11-11). Es la misma
    vía que la caja tiene desde E5-02 ("Buscar por documento"). Como no hay
    un código que pruebe que el cliente está presente, va en
    `beneficios:redimir_documento`, aparte de `beneficios:redimir`, y la
    documentación deja claro que el documento se lee del cliente presente.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Una redención por API no toca puntos; la respuesta no trae saldo.
- 3.3 y 3.4 Las compras y las reversas pasan por el libro (E3-02); nada
  escribe en `movimientos_puntos` fuera de sus Actions.
- 3.6 Ascenso en la misma petición de la compra.
- 3.7 `Idempotency-Key` obligatorio; la protección es la restricción única
  en la base, nunca una validación en código.
- 3.9 Montos enteros en pesos; fechas en `America/Bogota`.
- 3.10 Cliente por tipo y número de documento, normalizado.
- 3.11 Toda redención guarda su valor otorgado.
- 3.12 Solo se aceptan códigos firmados y vigentes (E4-04).
- 3.13 Query builder y Eloquent; tipos de Laravel en la migración; hora de
  la aplicación.

## Notas de interfaz

Solo el panel tiene pantalla (recurso "Credenciales API"). Usar la skill
`club-ar-ui`; zonas táctiles de 44 px.

- El token se muestra en un campo de solo lectura en mono, con "Copiar" y
  el aviso: "Guárdalo ahora. No lo vas a poder ver otra vez."
- Rotar pide confirmación: "El token actual sigue sirviendo N horas. Después
  solo sirve el nuevo." Revocar pide confirmación: "El POS que use este
  token deja de registrar compras de inmediato."

## Depende de

E4-01 (compras), E5-02 (redención), E4-04 (código firmado), E3-02 (reversa)
y E2-01 (roles), todos en `main`.

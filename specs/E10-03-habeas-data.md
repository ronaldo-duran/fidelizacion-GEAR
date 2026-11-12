# E10-03 · Seguridad y habeas data (Ley 1581 de 2012)

**Épica:** E10 · **Horas estimadas:** 12 · **Riesgo:** medio · **Revisa:**
Ronaldo (y los textos legales, el equipo legal del cliente)

## Objetivo

Los datos personales de los clientes viajan siempre cifrados, el celular y
el correo se guardan cifrados, las sesiones se pueden cerrar y no aguantan
fuerza bruta, cada cliente acepta una versión identificable de la
autorización, y puede descargar sus datos o pedir que se borren sin romper
el libro de puntos.

## Fuera de alcance

- **Brevo** (E7-03, bloqueado). Aquí se define el contrato que E7-03 debe
  cumplir: solo sincroniza clientes con autorización vigente y borra el
  contacto cuando el cliente se suprime (reglas 6 y 14). Las pruebas
  contra Brevo van en E7-03.
- **Respaldos.** La supresión no reescribe respaldos viejos: vencen según
  la retención de E1-03, que la política de datos menciona.
- **El texto legal definitivo.** Aquí van los **borradores** de la
  cláusula 10 (política de tratamiento y texto de autorización). Los
  revisa y aprueba el equipo legal del cliente (decisión 13 del issue #1).
- Verificar el documento contra una fuente oficial (E8-02, riesgo ya
  aceptado).
- Doble factor para el panel.
- Los datos de los usuarios del panel y de la caja (empleados del grupo).
  Esta spec cubre a los clientes del programa, que son los titulares.
- Corregir el documento (E10-06) o el correo y la fecha de nacimiento
  (E8-05). Aquí solo se enlazan.

## Reglas de negocio

### Textos legales versionados

1. **Dos textos, cada uno versionado:** la política de tratamiento de
   datos y el texto de autorización que se acepta al registrarse (E8-02).
   - Cada versión es un archivo Markdown en `resources/legal/politica/` o
     `resources/legal/autorizacion/`, con la versión como nombre
     (`borrador-2026-11.md`).
   - `config/legal.php` dice cuál es la versión actual de cada uno y guarda
     la huella SHA-256 de cada versión publicada. Una prueba recalcula las
     huellas: **una versión publicada nunca se edita**, como las reglas
     (regla dura 3.2). Cambiar el texto es publicar una versión nueva.
2. **Páginas públicas** sin sesión: `/politica-de-datos` (la versión actual)
   y `/politica-de-datos/{version}`. Igual con `/autorizacion-de-datos`.
   El enlace del registro (E8-02, hoy `#`) y el del perfil (E8-05,
   `config/documentos.php`) apuntan a la primera.
3. **Borradores visibles.** Mientras la versión empiece por `borrador-`,
   la página muestra arriba "Borrador pendiente de revisión legal". Los
   datos del responsable (razón social, NIT, dirección, correo y teléfono
   de contacto) son marcadores evidentes con `TODO(cliente)`.
4. **Contenido mínimo del borrador de la política** (Ley 1581 y Decreto
   1074 de 2015): responsable y contacto; datos que se recogen y para qué
   (el programa, los niveles, los beneficios, los avisos, los reportes
   agregados por comercio); que los 15 comercios del grupo comparten los
   datos para operar el programa; derechos del titular (conocer,
   actualizar, rectificar, suprimir, revocar, pedir prueba de la
   autorización y quejarse ante la SIC); canales y plazos (consultas 10
   días hábiles, reclamos 15); tratamiento de los datos de quien ya no es
   cliente; retención de respaldos; vigencia. El programa no recoge datos
   sensibles.
5. **El registro guarda la versión actual de la autorización.**
   `autorizaciones_datos.version_texto` pasa a salir de `config/legal.php`
   (hoy `config/registro.php`, `borrador-2026-10`). Las filas que ya existen
   no se tocan: la tabla es append-only.
6. **Autorización vigente.** Un cliente tiene autorización vigente si la
   última versión que aceptó está en la lista
   `legal.autorizacion.versiones_vigentes`. Corregir una tilde no obliga a
   nadie a aceptar otra vez; cambiar una finalidad sí, y la versión vieja
   sale de la lista.
7. **Aceptar una versión nueva.** Si el cliente entra a la PWA sin
   autorización vigente, ve el texto nuevo con "Acepto" antes de cualquier
   otra pantalla.
   - Aceptar inserta una fila nueva en `autorizaciones_datos` con versión,
     fecha, IP y canal `pwa`.
   - Sin aceptar, solo puede cerrar sesión, descargar sus datos o pedir la
     supresión.
   - Sus compras en la caja siguen sumando puntos: la autorización anterior
     cubre la operación del programa hasta que acepte o se suprima.

### Seguridad

8. **HTTPS obligatorio en producción**:
   - las URL se generan con `https`;
   - una petición `http` se redirige a `https`;
   - el encabezado `Strict-Transport-Security` vale un año
     (`settings.hsts_max_age`);
   - las cookies de sesión son `Secure`, `HttpOnly` y `SameSite=Lax`.

   En local y en pruebas no aplica (`APP_ENV`). La terminación TLS la hace
   Coolify; la aplicación ya confía en el proxy (`trustProxies`).

9. **Cifrado en reposo** del celular y del correo del cliente, con el
   cifrado de Laravel (cast `encrypted`, llave `APP_KEY`):
   - `clientes.celular`, `clientes.email`;
   - `registros_pendientes.celular`, `registros_pendientes.email`;
   - `codigos_verificacion.celular`;
   - `envios_notificacion.destinatario`.

   Para buscar sin descifrar, cada columna que se busca tiene una columna
   gemela `*_hash` con un HMAC-SHA256 del valor normalizado (índice ciego):
   `clientes.celular_hash`, `clientes.email_hash`,
   `codigos_verificacion.celular_hash` y `supresiones_correo.email_hash`.
   - La búsqueda de clientes del panel por celular pasa a ser por el número
     completo (diez dígitos), no por una parte.
   - El documento, el nombre y la fecha de nacimiento **no** se cifran
     (decisión 3).

10. **Sesiones.**
    - **Cliente (PWA):** la sesión dura `settings.cliente_session_days`
      días con "recordarme", que la PWA siempre usa (E8-02).
    - **Panel:** expira tras `SESSION_LIFETIME` minutos sin uso (hoy
      120). "Mantener la sesión" dura `settings.panel_remember_days` días.
    - **Caja:** como el panel, sin "recordarme" de más de un turno
      (`settings.cajero_remember_hours`).
11. **Cerrar sesión en los demás dispositivos.**
    - En el perfil del cliente, con su PIN: cierra todas sus sesiones
      menos la actual (`Auth::logoutOtherDevices` con el middleware
      `AuthenticateSession` en el guard `cliente`).
    - Cambiar o recuperar el PIN (E8-02) y la supresión cierran todas las
      sesiones del cliente.
    - Restablecer la contraseña del panel cierra las demás sesiones de ese
      usuario. El panel ya usa `AuthenticateSession`.
12. **Fuerza bruta.** Cada entrada que recibe un secreto tiene tope. Los
    números viven en el fixture (`throttles`):
    - ya existen: ingreso del cliente y de la caja (por documento o correo,
      y por IP), código SMS (E8-06), códigos cortos de beneficio (E4-04) y
      la API (E9-01);
    - nuevos:
      - ingreso al panel, con el mismo bloqueo por correo y por IP que la
        caja, además del tope por minuto que ya trae Filament;
      - el PIN que confirma "cerrar las demás sesiones", la descarga de
        datos y la supresión.

    Una prueba recorre la tabla del fixture y verifica que cada tope existe
    y bloquea.

### Derechos del titular

13. **Consulta: "Descargar mis datos".**
    - **Desde la PWA:** en el perfil, "Mis datos personales" lleva a una
      pantalla que dice qué datos tiene el programa y para qué, con
      "Descargar mis datos". El archivo es un `.xlsx`, con una hoja por
      tema:
      - Mis datos;
      - Autorizaciones;
      - Compras;
      - Puntos (el libro);
      - Beneficios usados;
      - Membresías;
      - Avisos enviados.

      Se genera al pedirlo, en _stream_, y no se guarda. Cada descarga deja
      una fila en `solicitudes_datos` (regla 16).

    - **Desde el panel:** el administrador del grupo descarga el mismo
      archivo para un cliente que lo pidió por otro canal (correo, oficina),
      con el motivo.
14. **Supresión.**
    - **Desde la PWA:** "Eliminar mis datos" explica qué pasa (pierde sus
      puntos, su nivel y su membresía vigente, y no se puede deshacer), pide
      el PIN y suprime al instante.
    - **Desde el panel:** el administrador del grupo suprime a un cliente
      que lo pidió por otro canal, con el motivo. El administrador de
      comercio no puede.
    - **Cómo se suprime:** se anonimiza, no se borra la fila. El
      `cliente_id` se queda para que el libro y los reportes cuadren (regla
      dura 3.4). Todo en una transacción, con la fila del cliente bloqueada:

      | Dónde                                                                   | Qué pasa                                                                                                                                                                                                                             |
      | ----------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
      | `clientes`                                                              | `nombre_completo` = "Cliente suprimido"; `documento` = un identificador aleatorio sin significado (`SUP-` + 12 caracteres); celular, correo, sus `*_hash` y la fecha de nacimiento en nulo; PIN inutilizable; `suprimido_at` = ahora |
      | `clientes` (nivel y saldo)                                              | Se quedan como están: son cifras del libro, no datos personales (regla dura 3.5)                                                                                                                                                     |
      | `movimientos_puntos`, `cambios_nivel`, `redenciones`                    | Nada: no tienen datos personales y son append-only                                                                                                                                                                                   |
      | `transacciones` del cliente y pendientes de su documento                | `documento_cliente` = el mismo identificador aleatorio. Es el único cambio que el modelo permite fuera del `estado`                                                                                                                  |
      | `registros_pendientes`, `codigos_verificacion`                          | Se borran las filas de su documento o su celular                                                                                                                                                                                     |
      | `envios_notificacion`                                                   | `destinatario` en nulo y `cuerpo` = "[suprimido]"; el evento y las fechas se quedan                                                                                                                                                  |
      | `supresiones_correo`                                                    | Se borra la fila de su correo                                                                                                                                                                                                        |
      | `notificaciones_pago` de sus pagos                                      | Del `payload` se quitan nombre, correo, teléfono y documento del pagador; se quedan montos, referencias y estados                                                                                                                    |
      | `eventos_calificados.soporte_path`, `cumplimientos_mision.soporte_path` | Se borran los archivos y la ruta queda en nulo                                                                                                                                                                                       |
      | `autorizaciones_datos`                                                  | Se queda: es la prueba de que hubo autorización (Ley 1581, art. 9). Ya no apunta a nadie identificable                                                                                                                               |
      | `auditorias` del evento calificado                                      | `antes` y `despues` pierden `soporte_path`                                                                                                                                                                                           |
      | Sesiones                                                                | Se cierran todas                                                                                                                                                                                                                     |
      | Brevo                                                                   | Se emite `ClienteSuprimido` después del commit; E7-03 borra el contacto                                                                                                                                                              |

    - **Después:**
      - el cliente ya no entra ni se encuentra en la caja ni en el panel;
      - su documento queda libre: si vuelve, se registra como cliente nuevo,
        sin los puntos de antes;
      - los procesos nocturnos siguen con su libro, pero no le mandan avisos.

15. **Revocar la autorización es pedir la supresión.** El programa no puede
    operar sin tratar los datos, así que la PWA ofrece una sola acción y
    la política lo explica. Dejar de recibir correos es otra cosa: es la
    preferencia de avisos de E8-05.
16. **Registro de solicitudes** (`solicitudes_datos`, append-only): `id`,
    `cliente_id`, `tipo` (`consulta` | `supresion` | `aceptacion`), `canal`
    (`pwa` | `panel`), `motivo` (panel), `atendida_por` (panel),
    `created_at`. No guarda datos personales. Sirve para responderle a la
    SIC cuándo se atendió cada solicitud.
17. **Sin datos personales en los registros de la aplicación.** Ningún
    `Log::` lleva celular, correo, documento, nombre ni PIN. El canal
    `daily` guarda `settings.log_days` días.

## Criterios de aceptación

- [ ] Dada una versión publicada de un texto legal, cuando su archivo
      cambia, entonces la prueba de huellas falla.
- [ ] Dado un registro nuevo, entonces su autorización guarda la versión
      actual de `config/legal.php`, con fecha, IP y canal.
- [ ] Dado un cliente cuya última versión aceptada salió de las vigentes,
      cuando entra a la PWA, entonces ve el texto nuevo; al aceptar queda
      una fila nueva y sigue a su inicio; sin aceptar, solo llega al
      perfil, la descarga, la supresión y cerrar sesión.
- [ ] Dado `APP_ENV=production`, entonces una petición `http` se redirige a
      `https`, la respuesta trae `Strict-Transport-Security` y la cookie de
      sesión es `Secure`.
- [ ] Dado un cliente, entonces en la base su celular y su correo no
      aparecen en claro (consulta directa a la tabla) y se leen bien por el
      modelo; la búsqueda por celular completo lo encuentra.
- [ ] Dado un registro, un ingreso, un código SMS y una recuperación de
      PIN, entonces todo sigue funcionando con las columnas cifradas.
- [ ] Dadas dos sesiones del mismo cliente, cuando cierra las demás con su
      PIN, entonces la otra queda fuera en su siguiente petición; con un PIN
      equivocado no cierra nada y cuenta para el tope.
- [ ] Dada cada entrada de `throttles` del fixture, cuando se pasa del
      tope, entonces se bloquea con el mensaje y el tiempo esperados.
- [ ] Dado un cliente con compras, movimientos, redenciones, membresía y
      avisos, cuando descarga sus datos, entonces el archivo trae cada hoja
      con sus filas y ningún dato de otro cliente.
- [ ] Dado ese cliente, cuando se suprime, entonces **ninguna tabla**
      guarda su nombre, documento, celular, correo ni fecha de nacimiento.
      La prueba busca cada valor original en todas las columnas de texto
      de la base. Sus archivos de soporte ya no existen.
- [ ] Dado ese cliente suprimido, entonces los reportes de E10-02 y la
      suma del libro dan lo mismo que antes de suprimirlo.
- [ ] Dado un cliente suprimido, cuando alguien se registra con su
      documento, entonces es un cliente nuevo sin sus puntos.
- [ ] Dada una supresión, entonces se emite `ClienteSuprimido` una vez y
      solo después del commit, y queda la solicitud registrada.
- [ ] Dado un administrador de comercio, entonces no ve las acciones de
      descarga ni de supresión del panel.
- [ ] `Cliente::conAutorizacionVigente()` excluye a los suprimidos y a
      quien no tiene una versión vigente (contrato de E7-03).
- [ ] E2E (celular): el cliente sembrado abre "Mis datos personales",
      descarga sus datos y cierra las demás sesiones; otro cliente
      sembrado se suprime y ya no puede entrar.

## Fixtures

`tests/Fixtures/habeas-data-escenarios.json` (nuevo, en inglés):

- `settings`: HSTS, duración de las sesiones, días de registros, prefijo y
  largo del identificador de supresión.
- `throttles`: cada entrada con su clave, tope, ventana y mensaje.
- `legal`: versiones vigentes y la que se acepta en el registro.
- `customer`: un cliente con datos en cada tabla de la regla 14.
- `after_suppression`: el valor esperado de cada columna después.
- `export`: las hojas y sus columnas.

Los valores que el cliente no ha confirmado van con
`"_assumption": true`.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **Migraciones:**
  - `clientes`: `celular_hash`, `email_hash` (con índice), `suprimido_at`;
    `celular` y `email` pasan a `text`.
  - `registros_pendientes`, `codigos_verificacion` y `envios_notificacion`:
    las columnas cifradas pasan a `text` y se agregan sus `*_hash`.
  - `supresiones_correo`: `email_hash` con índice único.
  - `eventos_calificados.soporte_path` y `cumplimientos_mision.soporte_path`
    pasan a nulos.
  - Tabla nueva `solicitudes_datos`.
  - Una migración de datos cifra los valores que ya existen y llena los
    `*_hash`. Ningún entorno de producción tiene clientes todavía; el
    entorno de demostración sí.
- **Índice ciego:** `App\Seguridad\IndiceCiego::hash(string $valor)`, con
  HMAC-SHA256 y una llave derivada de `APP_KEY` para ese uso
  (`hash_hmac` con la llave `hkdf` de `APP_KEY` y la etiqueta
  `indice-ciego`). El valor se normaliza antes: celular con
  `CodigoHasher::normalizeCelular`, correo en minúsculas y sin espacios.
- **`APP_KEY` no se rota sin `APP_PREVIOUS_KEYS`**: los datos cifrados
  dependen de ella. Su respaldo va en la bóveda del cliente (E1-03).
- **Actions** (`app/Actions/Datos/`):
  - `AcceptAutorizacion`;
  - `ExportDatosCliente` (devuelve las filas por hoja; la descarga escribe
    con `openspout`, como E10-02);
  - `SuppressCliente` (la regla 14 completa);
  - `LogoutOtherSessions`.

  Livewire y Filament solo llaman a estas Actions.

- **Evento** `App\Events\ClienteSuprimido` con el `cliente_id` y el hash
  del correo que tenía, para que E7-03 encuentre el contacto en Brevo sin
  guardar el correo.
- **`Transaccion`**: el modelo permite cambiar `documento_cliente` solo
  desde `SuppressCliente` (un método explícito del modelo), y nada más.
- **Pantallas:** en la PWA, "Mis datos personales" (`/perfil/datos`) y la
  aceptación de una versión nueva (`/autorizacion`). En el panel, las
  acciones "Descargar datos" y "Suprimir datos" en la ficha del cliente
  (E10-06), solo para el administrador del grupo.
- **Partición en PRs:**
  1. textos legales y aceptación;
  2. HTTPS, sesiones y topes;
  3. cifrado e índice ciego;
  4. consulta y supresión.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-11-11); el revisor las aprueba con
esta spec.

1. **Supresión = anonimización** (la propuesta del issue). La fila del
   cliente se queda con un identificador aleatorio y sin datos personales;
   el libro no se toca.
2. **La supresión desde la PWA es inmediata**, confirmada con el PIN, sin
   periodo de espera. Si se prefiere una espera (por ejemplo 7 días para
   arrepentirse), se agrega un estado `programada` y el job nocturno la
   ejecuta.
3. **Solo se cifran el celular y el correo**, como dice el issue. El
   documento es la llave de búsqueda de la caja, la API y el registro, y el
   nombre se busca por partes en el panel; cifrarlos obligaría a quitar
   esas búsquedas. Los protege el control de acceso, y el disco del
   servidor se cifra en E1-03.
4. **La búsqueda por celular en el panel pasa a ser exacta**, por el
   número completo.
5. **Las autorizaciones aceptadas se conservan** después de la supresión,
   como prueba ante la SIC, sin nada que identifique al cliente.
6. **Revocar = suprimir** (regla 15).
7. **Una membresía vigente se pierde** al suprimir, sin reembolso
   automático. `TODO(cliente)`: confirmar si hay reembolso proporcional y
   cómo se pide.
8. **Sesión del cliente de 90 días** (supuesto), porque es una PWA en su
   propio celular; la del panel sigue en 120 minutos sin uso, con
   "Mantener la sesión" de 14 días (supuesto).
9. **El programa es para mayores de edad**: el texto de autorización lo
   dice y la fecha de nacimiento, que es opcional, no se valida.
   `TODO(cliente)`: confirmar, porque uno de los comercios es un colegio.
10. **Textos legales en archivos del repositorio**, no editables desde el
    panel: cambian pocas veces, pasan por revisión legal y así quedan en el
    historial de git. Si el cliente quiere editarlos él mismo, es un
    recurso del panel con versiones, como las reglas.

## Reglas duras de CLAUDE.md que aplican

- 3.2 Las versiones publicadas de los textos legales no se editan
- 3.4 La supresión no toca `movimientos_puntos`
- 3.5 El nivel y el saldo del cliente suprimido se quedan como están
- 3.10 El documento se libera para un registro nuevo; el par (tipo,
  número) sigue único con el identificador aleatorio
- 3.13 Migraciones y búsquedas que funcionan en PostgreSQL y SQL Server

## Notas de interfaz

- Skill `club-ar-ui`. Pantallas sin diseño propio en `docs/diseno/`: se
  arman con los componentes del perfil (E8-05).
- Textos:
  - "Mis datos personales", "Descargar mis datos", "Eliminar mis datos",
    "Cerrar sesión en los demás dispositivos", "Acepto";
  - en la supresión: "Vas a perder tus puntos, tu nivel y tu membresía.
    Esto no se puede deshacer.";
  - aviso de borrador: "Borrador pendiente de revisión legal".
- Zonas táctiles de 44 px. "Eliminar mis datos" en `danger`.

## Depende de

E8-02 (registro y autorización), E8-05 (perfil y enlaces), E8-06 (PIN y
código SMS), E10-06 (ficha del cliente en el panel), E10-02 (reportes que
deben seguir cuadrando), E7-03 (Brevo, contrato de las reglas 6 y 14) y
E1-03 (respaldos y cifrado del disco).

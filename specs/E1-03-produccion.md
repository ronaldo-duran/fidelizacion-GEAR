# E1-03 · Producción: respaldos verificados, monitoreo y salida en vivo

**Épica:** E1 · **Horas estimadas:** 6 · **Riesgo:** alto · **Revisa:**
Ronaldo (entrega las credenciales del cliente y autoriza la salida en vivo)

## Objetivo

La plataforma sale en vivo en el hosting del cliente con un respaldo
diario fuera del servidor, una restauración semanal que demuestra que el
respaldo sirve, y alertas cuando el sitio se cae, un proceso nocturno no
corre, la cola se atasca o la aplicación falla. Es condición del **hito 4**.

## Fuera de alcance

- **La fase VPS** (Hetzner, Coolify, Redis y Horizon). Hoy se despliega en
  el hosting compartido de **Hostinger** con MySQL, cola en base de datos y
  cron (decisión del cliente del 2026-10-10, #43). Lo de Horizon del issue
  se aplica cuando se pase al VPS. La regla 9 deja anotado qué cambia.
- El despliegue por tag, que ya existe (E1-02, #43, `docs/despliegue.md`).
- El DNS y la entregabilidad del correo (E7-02) y Brevo (E7-03). Las
  alertas salen por el correo que esté configurado (`MAIL_*`).
- La pasarela de pagos en modo real (E6-01, bloqueado por la cuenta de
  Wompi). Aparece en la lista de salida, pero se configura en E6-01.
- Contratar servicios de pago. Todo lo de esta spec cabe en planes
  gratuitos o en lo que el cliente ya paga.

## Reglas de negocio

### Cola y tareas programadas en el hosting compartido

1. **La cola se procesa por cron.** Hoy el cron solo corre `schedule:run`
   y nada procesa la tabla `jobs`. Así, los procesos nocturnos (que se
   encolan, E3-05) y los correos (E7-01) se quedarían sin correr.
   - Con `QUEUE_CONNECTION=database`, el scheduler corre cada minuto
     `queue:work --stop-when-empty --max-time` (los segundos del fixture,
     menos de un minuto) sobre las colas `nocturno`, `notificaciones` y
     `default`, en ese orden y sin solaparse.
   - Con Redis (VPS) no se programa: lo hace Horizon.

### Respaldos

2. **Respaldo diario fuera del servidor.** Un comando `respaldo:crear`
   corre todos los días a la hora del fixture (`backup.daily_at`), en
   `America/Bogota`, después de los procesos nocturnos.
   - **Base de datos:** `mysqldump` con `--single-transaction --quick
--no-tablespaces`, para no bloquear tablas y porque el hosting
     compartido no da permiso de `PROCESS`.
   - **Archivos subidos** (`storage/app`: soportes de eventos y de
     misiones): un `.tar`.
   - **Empaque:** los dos se comprimen y se **cifran** (AES-256, con
     `RESPALDO_LLAVE`, distinta de `APP_KEY`) antes de salir del servidor,
     porque llevan datos personales (E10-03).
   - **Destino:** el almacenamiento S3 compatible **a nombre del cliente**
     (por ejemplo Backblaze B2), con credenciales de solo escritura y
     lectura sobre su carpeta. Nunca el mismo servidor.
   - **Nombre:** `club-AAAA-MM-DD-HHMM.tar.gz.enc` y un `.sha256` al lado.
3. **Retención** (fixture `backup.retention`):
   - se conservan los respaldos de los últimos N días;
   - además, el último de cada una de las últimas N semanas y el último de
     cada uno de los últimos N meses;
   - el resto se borra en la misma corrida, y solo después de que el
     respaldo nuevo subió completo.

   Los casos con fechas están en el fixture.

4. **Un respaldo que falla avisa.** Si el volcado, el cifrado o la subida
   fallan, o el archivo pesa menos que el mínimo del fixture, el comando
   termina con error, manda la alerta y no borra nada de la retención.

### Verificación

5. **Restauración semanal** (`respaldo:verificar`, día y hora en el
   fixture):
   - descarga el último respaldo, revisa su `.sha256`, lo descifra y lo
     importa en una **segunda base de datos** del mismo hosting, creada una
     vez en el panel de Hostinger y usada solo para esto
     (`DB_VERIFICACION_*`);
   - corre las comprobaciones de la regla 6 y vacía esa base al terminar.
6. **Comprobaciones** de la base restaurada:
   - la tabla `migrations` tiene todas las migraciones del código
     desplegado;
   - `clientes`, `transacciones` y `movimientos_puntos` tienen filas, y
     ninguna tiene menos que en la verificación anterior (el libro es
     append-only, regla dura 3.4);
   - el libro cuadra: la suma de `movimientos_puntos.puntos` de cada
     cliente es igual a su `saldo_puntos` (la misma comprobación del
     proceso nocturno de E3-05);
   - el `.tar` de archivos se abre.
7. **Queda constancia.** Cada verificación inserta una fila en
   `verificaciones_respaldo`: archivo, inicio, fin, resultado (`ok` |
   `fallo`), detalle de cada comprobación y conteos. El panel muestra la
   última, solo al administrador del grupo, en el tablero o en una página
   "Respaldos". Si falla, alerta.

### Monitoreo y alertas

8. **Qué se vigila y cómo avisa.** Las alertas van por correo a los
   destinatarios de `ALERTAS_CORREOS` (fixture `alerts.recipients`). Las
   cuentas externas van a nombre del cliente, en plan gratuito:

   | Qué                      | Cómo                                                                                                                                                              |
   | ------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------- |
   | Disponibilidad           | Servicio externo (UptimeRobot) que pide `/up` cada pocos minutos; avisa si falla dos veces seguidas                                                               |
   | Procesos nocturnos       | El último job de la cadena de E3-05 hace ping a un _heartbeat_ (Healthchecks.io). Si el ping no llega en la ventana del fixture, Healthchecks avisa               |
   | Respaldo diario          | Ping al heartbeat del respaldo al terminar bien; si no llega, aviso                                                                                               |
   | Verificación semanal     | Ping al heartbeat de la verificación al terminar bien; si no llega, aviso                                                                                         |
   | Cola atascada            | Un chequeo cada 10 minutos: si el job más viejo de `jobs` lleva más de N minutos, o `failed_jobs` creció desde el último chequeo, alerta                          |
   | Cron caído               | Heartbeat del propio `schedule:run`: un ping cada pocos minutos. Si el cron de Hostinger deja de correr, nada de lo anterior corre y esto es lo primero que avisa |
   | Errores de la aplicación | Un canal de log que manda por correo los `error` y peores. El mismo error (por su huella) se manda una vez por ventana del fixture, para no inundar el correo     |
   - Las URL de los heartbeats van en variables de entorno
     (`HEARTBEAT_*`). Sin la variable, no se hace ping: local y pruebas
     no llaman a nadie.
   - Las alertas de la aplicación (cola, respaldo, verificación, errores)
     no llevan datos personales: solo el qué, el cuándo y un identificador.

9. **Al pasar al VPS:** los heartbeats y las alertas se quedan; la cola la
   procesa Horizon (sin la regla 1); `LongWaitDetected` de Horizon
   reemplaza el chequeo de cola atascada; `mysqldump` cambia a `pg_dump`
   si el motor cambia. Queda escrito en `docs/despliegue.md`.

### Salida en vivo

10. **Lista de salida** en `docs/salida-en-vivo.md`, con responsable y
    casilla por cada punto. No se sale en vivo con una casilla vacía, salvo
    las que Ronaldo marque como aplazadas por escrito:
    - `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` propia y respaldada
      en la bóveda del cliente (E10-03);
    - `LOG_LEVEL=warning` y el canal de errores por correo activo;
    - HTTPS válido en `club.grupoaponterivera.com` (DNS del cliente);
    - correo transaccional real (Brevo, E7-02 y E7-03) o, mientras tanto,
      el SMTP de Hostinger;
    - pasarela en modo real (E6-01) y proveedor de SMS en modo real
      (E8-06);
    - base limpia: `migrate --force` sin datos de muestra
      (`SEMBRAR_DATOS_DE_MUESTRA` apagada) y el usuario administrador del
      grupo creado con un comando (`usuarios:crear-administrador`), sin
      contraseñas en el repositorio;
    - reglas iniciales cargadas desde el panel con los valores confirmados
      por el cliente (sección 9 de CLAUDE.md);
    - cron de Hostinger configurado y el heartbeat del cron en verde;
    - un respaldo creado y **una verificación en `ok`** antes de abrir;
    - cada alerta de los criterios probada una vez;
    - credenciales entregadas (regla 11) y autorización escrita de Ronaldo.
11. **Entrega de credenciales** (cláusula 5). `docs/entrega-credenciales.md`
    lista cada cuenta, quién es el titular (el cliente), quién tiene acceso
    y dónde está la credencial. **Nunca la credencial misma**. Las cuentas
    son:
    - Hostinger;
    - los dos repositorios de GitHub;
    - el DNS;
    - el almacenamiento de respaldos;
    - Healthchecks y UptimeRobot;
    - Brevo, la pasarela y el proveedor de SMS;
    - la bóveda de llaves con `APP_KEY`, `RESPALDO_LLAVE` y las contraseñas
      de las bases.

## Criterios de aceptación

- [ ] Dado `QUEUE_CONNECTION=database`, cuando corre `schedule:run`,
      entonces está programado `queue:work --stop-when-empty` con las colas
      y el tiempo del fixture; con `redis`, no.
- [ ] Dado el respaldo diario, entonces sube un archivo cifrado y su
      `.sha256` al almacenamiento, y sin `RESPALDO_LLAVE` el archivo no se
      puede abrir.
- [ ] Dados los casos de retención del fixture, entonces se conservan y se
      borran exactamente los respaldos esperados.
- [ ] Dado un destino inaccesible (credenciales malas), entonces el comando
      falla, llega la alerta y no se borra ningún respaldo.
- [ ] Dado un respaldo bueno, cuando se verifica, entonces queda una fila
      `ok` con sus conteos; dado un respaldo dañado o un libro que no
      cuadra, queda `fallo` y llega la alerta.
- [ ] **En el servidor real** (manual, con evidencia en el issue):
  - una restauración funciona y queda la fecha y el resultado;
  - tumbar el sitio (modo mantenimiento) dispara el aviso de
    disponibilidad;
  - no correr la cadena nocturna una noche dispara el aviso del heartbeat;
  - un job atascado y uno fallido disparan la alerta de cola;
  - un error forzado llega por correo una sola vez dentro de la ventana.
- [ ] La lista de salida en vivo está completa y Ronaldo autoriza la salida.

Las pruebas automáticas usan un disco falso (`Storage::fake`), un
`mysqldump` simulado y `Http::fake()` para los heartbeats. La restauración
real se prueba en el CI contra el servicio MySQL que ya existe.

## Fixtures

`tests/Fixtures/produccion-escenarios.json` (nuevo, en inglés):

- `queue`: colas, en orden, y segundos de `--max-time`.
- `backup`: hora, tamaño mínimo y nombre del archivo.
- `retention`: días, semanas y meses, con casos de fechas existentes y las
  que deben quedar.
- `verification`: día, hora y comprobaciones.
- `alerts`: umbral de la cola atascada, ventana de errores repetidos,
  ventanas de cada heartbeat y destinatarios.

Los valores que el cliente no ha confirmado van con
`"_assumption": true`.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **Comandos** en `app/Console/Commands/Produccion/`, delgados, que llaman
  a clases de `app/Produccion/`:
  - `respaldo:crear`;
  - `respaldo:verificar`;
  - `cola:revisar`;
  - `usuarios:crear-administrador`.
- **Volcado:** `mysqldump` por `Process` de Laravel, con la contraseña en
  un archivo de opciones temporal (`--defaults-extra-file`), nunca en la
  línea de comandos.
- **Cifrado:** `openssl` por `Process`, o `sodium_crypto_secretstream`
  de PHP si el hosting no permite `openssl` en la línea de comandos.
- **Subida:** el disco `s3` de Laravel con el endpoint del proveedor.
- **Tabla nueva** `verificaciones_respaldo`: `id`, `archivo`,
  `iniciada_at`, `terminada_at`, `resultado`, `detalle` (JSON), `created_at`.
- **Canal de log** `alertas`: un _handler_ de Monolog propio que manda un
  correo por error, con la huella del mensaje y su clase guardada en caché
  durante la ventana.
- **Heartbeats:** `->pingOnSuccess(env)` del scheduler y un ping explícito
  al final de la cadena nocturna.
- **Hostinger:**
  - Validar en el servidor, antes del código, que `exec`/`proc_open`,
    `mysqldump` y `mysql` están disponibles por SSH y desde PHP.
  - Si no lo están, el volcado se hace con PHP puro, tabla por tabla, y la
    restauración se verifica en el CI.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-11-11); el revisor las aprueba con
esta spec.

1. **La spec se escribe para Hostinger con MySQL**, que es donde se
   despliega hoy (#43). El texto del issue habla de PostgreSQL y Horizon,
   que son de la fase VPS.
2. **Paquete nuevo `league/flysystem-aws-s3-v3`** (MIT, el adaptador
   oficial que Laravel sugiere para el disco `s3`), para subir al
   almacenamiento del cliente. CLAUDE.md §2 pide preguntar antes. La
   alternativa sin paquete es firmar las peticiones S3 a mano; no vale la
   pena.
3. **Respaldos cifrados con una llave propia** (`RESPALDO_LLAVE`), que
   vive en la bóveda del cliente junto a `APP_KEY`. Sin ella el respaldo
   no se puede abrir; perderla es perder los respaldos.
4. **Verificación en una segunda base del mismo hosting**, para no sacar
   la llave del servidor. Si Hostinger no da una segunda base o no permite
   importar desde PHP, la verificación pasa a un workflow semanal de
   GitHub Actions con la llave como secreto.
5. **Retención 7 + 4 + 3** (la sugerida en el issue), a confirmar con el
   cliente.
6. **Respaldo a las 03:00 y verificación los domingos a las 04:00**
   (supuestos), después de los procesos nocturnos de la 01:30.
7. **UptimeRobot y Healthchecks.io en plan gratuito** a nombre del
   cliente; **sin Sentry**. Los errores llegan por correo.
8. **Destinatarios de las alertas:** Neyder, Ronaldo y un contacto técnico
   del cliente (`TODO(cliente)`). Las alertas salen por el correo
   configurado, aunque Brevo no esté listo.
9. **Programar `queue:work` en el scheduler** (regla 1). Sin esto, la cola
   no se procesa en Hostinger. Puede ir en un PR propio y pequeño antes del
   resto, porque ya afecta a la demostración.

## Reglas duras de CLAUDE.md que aplican

- 3.4 El libro es append-only: la verificación lo usa para detectar un
  respaldo incompleto (ningún conteo baja)
- 3.6 y 3.9 Los procesos nocturnos (descenso, caducidad) corren y se
  vigilan en hora de `America/Bogota`
- 3.13 La hora de cada respaldo y verificación la pone la aplicación

## Notas de interfaz

- Panel: la última verificación ("Último respaldo verificado: 9 nov,
  04:12 · Correcto"), solo para el administrador del grupo, con los tokens
  del tema (`success` / `danger`).
- Correos de alerta en español, con asunto que empieza por "[Club Aponte
  Rivera] Alerta:" y el qué en una línea.

## Depende de

E1-02 y #43 (despliegue por tag en Hostinger), E3-05 (cadena nocturna),
E7-01 (correo), E10-03 (llaves y datos personales en los respaldos) y el
issue #1 (cuentas a nombre del cliente: Hostinger, almacenamiento, DNS).

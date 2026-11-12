# Auditoría de seguridad y de lógica — noviembre de 2026

Revisión completa del código de `main` (commit `293016e`, versión 0.36.0)
hecha el 2026-11-12. Busca fallos de seguridad y problemas de lógica que
las revisiones por PR no ven porque atraviesan varias features.

Este documento dice qué se encontró, cómo se comprobó, cómo se arregló y
qué regla queda para que no vuelva a pasar. Las reglas están resumidas en
`CLAUDE.md` §7 (Seguridad) y las revisa Claude en cada PR
(`.github/claude/revision.md`).

## Resumen

| #   | Hallazgo                                                                    | Gravedad | Estado    |
| --- | --------------------------------------------------------------------------- | -------- | --------- |
| 1   | El administrador de comercio podía salirse de su comercio en el panel       | Alta     | Corregido |
| 2   | Los usuarios sembrados quedaban con la contraseña `password` fuera de local | Alta     | Corregido |
| 3   | Se confiaba en cualquier proxy: la IP del visitante se podía inventar       | Media    | Corregido |
| 4   | Ninguna respuesta llevaba encabezados de seguridad                          | Media    | Corregido |
| 5   | Las contraseñas del panel y de la caja no tenían mínimo                     | Media    | Corregido |
| 6   | `APP_DEBUG=true` en producción mostraría la página de depuración            | Baja     | Corregido |
| 7   | La búsqueda de clientes del panel aceptaba comodines de `LIKE`              | Baja     | Corregido |
| 8   | En el hosting compartido nada procesaba la cola                             | Alta     | PR #143   |

Además hay cuatro observaciones sin arreglo en este PR (al final).

## Qué se revisó

- **Entradas:** rutas web y de webhooks, `bootstrap/app.php`, controladores
  y middleware.
- **Autorización:** los 16 recursos de Filament y los componentes Livewire
  de la PWA y de la caja (propiedades públicas, acciones y `#[Locked]`).
  Los recursos de comercios y de sedes se probaron con peticiones reales
  de un administrador de comercio. De los demás se leyeron las políticas,
  los formularios y las Actions que autorizan cada escritura.
- **Autenticación:** ingreso del cliente, de la caja y del panel;
  recuperación del PIN; códigos por SMS; regeneración de la sesión.
- **Datos:** asignación masiva, SQL crudo, salida sin escapar en Blade y
  en Filament, subida de archivos, tipos de las columnas de dinero y de
  puntos, llaves foráneas en cascada.
- **Pagos:** firma del webhook de Wompi, pasarela simulada, redirecciones.
- **Configuración:** sesiones y cookies, `.env.example`, seeders, registros
  (`Log::`), Horizon, service worker de la PWA.
- **Dependencias:** `composer audit` y `npm audit --omit=dev`, sin
  vulnerabilidades conocidas.

**No se revisó:** el código que todavía está en PRs abiertos (la API
pública de E9-01, el uso del beneficio de E8-04, el historial y el perfil
de E8-05). Cada uno pasa por su propia revisión.

## Hallazgos

### 1. El administrador de comercio podía salirse de su comercio (alta)

**Qué pasaba.** Los recursos de comercios y de sedes (E2-02) no tenían
política. Solo filtraban lo que se **veía** en la lista. Un administrador
de comercio podía:

- crear comercios nuevos;
- cambiar la categoría, el NIT y el estado activo de su propio comercio.
  De la categoría sale la tasa de puntos, así que podía darse la tasa de
  otra categoría;
- crear una sede en el comercio de otro, porque el selector de comercio
  ofrecía los 15;
- mover una sede suya a otro comercio. Las compras y redenciones de esa
  sede habrían quedado con un comercio y una sede que ya no coinciden.

**Cómo se comprobó.** Con cuatro pruebas que hacían cada cosa con un
administrador de comercio. Las cuatro lograban el cambio.

**Arreglo.**

- `ComercioPolicy` y `SedePolicy`. Crear comercios es solo del grupo. La
  categoría, el NIT y el estado activo son solo del grupo: el formulario
  los bloquea y `EditComercio` los descarta en el servidor.
- El selector de comercio de una sede solo ofrece los del alcance, y
  `CreateSede` lo autoriza otra vez en el servidor.
- **Una sede no cambia de comercio**, tampoco para el grupo: el campo va
  bloqueado al editar y `EditSede` lo descarta.
- Políticas para los cinco recursos que solo usaban `canAccess()`
  (categorías, usuarios, planes, pagos y notificaciones de pago). Nadie
  borra comercios, sedes, categorías, usuarios ni planes: se desactivan.
- Pruebas: `tests/Feature/SeguridadPanelTest.php`.

**Regla.** Todo modelo que administra un recurso del panel tiene política
(lo exige una prueba). El alcance se exige al **escribir**, en el
servidor, no solo al listar. Un selector de comercio o de sede siempre va
filtrado por el alcance del usuario.

### 2. Usuarios sembrados con contraseña conocida (alta)

**Qué pasaba.** `DatabaseSeeder` crea tres usuarios (administrador del
grupo, administrador de comercio y cajero) con la contraseña `password` de
la fábrica. El seeder no corre en producción, pero sí en staging y en el
servidor de demostración, que son alcanzables desde internet. Cualquiera
que conociera los correos sembrados, que están en el repositorio, entraba
como administrador del grupo.

**Arreglo.** La contraseña sale de `SEMILLA_CLAVE`. `password` solo se usa
en local y en las pruebas. En cualquier otro entorno, sin una
`SEMILLA_CLAVE` de al menos 10 caracteres el seeder falla antes de crear
los usuarios.

**Acción manual pendiente.** En el servidor de demostración de Hostinger
hay que **cambiar ya** la contraseña de los tres usuarios sembrados (desde
el panel, Configuración › Usuarios), porque se crearon con `password`.

**Regla.** Ninguna credencial conocida fuera de local y de las pruebas.
Lo que el repositorio siembra en un entorno público toma sus secretos del
entorno.

### 3. Se confiaba en cualquier proxy (media)

**Qué pasaba.** `trustProxies(at: '*')` aceptaba el encabezado
`X-Forwarded-For` de cualquiera. Si el servidor recibe conexiones directas
(el hosting compartido), un atacante se inventa una IP distinta en cada
petición y se salta todos los topes por IP:

- los intentos fallidos de ingreso del cliente y de la caja;
- los SMS por IP. Con un celular distinto en cada petición, podía hacer
  que el programa enviara SMS sin límite, que el cliente paga;
- además, la IP que se guarda como prueba de la autorización de datos
  (Ley 1581) sería falsa.

**Arreglo.**

- Los proxies de confianza salen de `TRUSTED_PROXIES`. Por defecto,
  ninguno: la IP es la de la conexión.
- Tope nuevo de SMS por día para todo el programa
  (`verificacion.max_per_day_global`, supuesto de 1.000,
  `TODO(cliente)`), que limita el costo aunque alguien reparta las
  peticiones entre muchas IP reales.

**Acción manual pendiente.** Al desplegar, revisar qué IP ve la aplicación.
Si el servidor está detrás de un proxy o un CDN (Coolify en el VPS, o el
CDN de Hostinger), hay que poner su IP en `TRUSTED_PROXIES`. Si no se
pone, todos los visitantes comparten la IP del proxy, los topes por IP se
llenan entre todos y los enlaces pueden salir con `http`.

**Regla.** Nunca `*` como proxy de confianza por defecto. Todo tope que
protege dinero (SMS, correos) tiene además un tope global que no depende
de la IP.

### 4. Sin encabezados de seguridad (media)

**Qué pasaba.** Ninguna respuesta decía que no se podía enmarcar. Otro
sitio podía cargar la caja o el panel en un marco invisible y hacer que un
cajero o un administrador con la sesión abierta hiciera clic donde el
atacante quisiera (clickjacking). Tampoco había política de referencia: el
enlace firmado del registro lleva el documento en la URL y podía llegar a
otros sitios.

**Arreglo.** El middleware `SecurityHeaders` agrega a toda respuesta
`X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`,
`Referrer-Policy: strict-origin-when-cross-origin` y una
`Permissions-Policy` que solo deja la cámara (el escáner de QR). En
producción y por HTTPS agrega `Strict-Transport-Security` de un año.

**Lo que queda fuera.** No hay `Content-Security-Policy`: Livewire, Alpine
y Filament usan scripts en línea y habría que poner un nonce en cada uno.
Queda como observación.

**Regla.** Toda respuesta pasa por `SecurityHeaders`. Una prueba lo
verifica en la PWA, la caja, el panel y `/up`.

### 5. Contraseñas del panel sin mínimo (media)

**Qué pasaba.** Al crear un usuario del panel o de la caja se aceptaba
cualquier contraseña, también de un carácter.

**Arreglo.** `Password::defaults()`: mínimo 10 caracteres, con letras y
números. La usan el formulario de usuarios y el restablecimiento de
contraseña de Filament. No se consulta ninguna lista externa de
contraseñas filtradas, para no depender de otro servicio.

**Regla.** Todo campo de contraseña valida con `Password::defaults()`.

### 6. Depuración en producción (baja)

**Qué pasaba.** `.env.example` trae `APP_DEBUG=true`. Si alguien copia ese
archivo en producción y olvida cambiarlo, cada error muestra variables de
entorno, consultas y rutas del servidor.

**Arreglo.** En producción la aplicación apaga la depuración al arrancar,
diga lo que diga `APP_DEBUG`.

### 7. Comodines en la búsqueda de clientes (baja)

**Qué pasaba.** La búsqueda del panel pasaba `%` y `_` tal cual a `LIKE`.
No daba acceso a nada fuera del alcance del usuario, pero `%` devolvía
toda su lista como si fuera una búsqueda.

**Arreglo.** Se quitan `%` y `_` del término. No se usa `ESCAPE`, porque
su sintaxis cambia entre motores (regla dura 3.13).

### 8. En el hosting compartido nada procesaba la cola (alta, lógica)

Se encontró al escribir la spec de E1-03 y está corregido en el PR #143:
el cron solo corría `schedule:run` y ninguna tarea corría `queue:work`, así
que los procesos nocturnos (caducidad y descenso de nivel) y los correos no
habrían corrido en Hostinger.

**Regla.** Todo lo que se encola tiene quién lo procese en **cada**
entorno donde se despliega, y una alerta si deja de procesarse (E1-03).

## Lo que se revisó y está bien

- **Ingreso del cliente y de la caja:** el intento se cuenta antes de
  comparar, el error no dice si la cuenta existe, se compara contra un
  hash aunque no exista y la sesión se regenera al entrar.
- **Códigos por SMS:** solo se guarda la huella, sirven una vez, vencen y
  se invalidan al quinto intento con un UPDATE condicionado.
- **Webhook de Wompi:** firma comparada en tiempo constante, exige que la
  firma cubra el id, el estado y el monto, y no guarda datos del pagador.
- **Pasarela simulada:** apagada en producción en dos sitios; la
  redirección de regreso solo acepta direcciones de la propia aplicación.
- **Livewire:** todo el estado sensible de la caja va con `#[Locked]` y
  las acciones delegan en Actions que autorizan.
- **Plantillas de avisos:** sustitución de texto en una pasada, todo
  escapado antes de convertir los enlaces; nunca se compila como Blade.
- **Archivos subidos:** disco privado, tipos y tamaño validados, nombres
  generados por el servidor.
- **Base de datos:** sin asignación masiva abierta, sin SQL con
  interpolación, montos en `bigInteger`.
- **Service worker:** no guarda respuestas con datos del cliente.
- **Horizon:** solo el administrador del grupo activo.

## Riesgos aceptados

- **Bloqueo de un cliente por intentos ajenos.** Quien conozca el
  documento de un cliente puede fallar el PIN cinco veces y dejarlo
  bloqueado 15 minutos. Es el costo de frenar la fuerza bruta sobre un PIN
  de cuatro dígitos (E8-02).
- **El documento no se verifica** contra una fuente oficial (E8-02).
- **El tope diario de SMS** puede dejar sin código a clientes reales si un
  ataque lo agota. Se prefiere eso a un costo sin techo.

## Observaciones sin arreglo en este PR

1. **`Content-Security-Policy`.** Sería la defensa de fondo contra XSS.
   Exige nonces en Livewire, Alpine y Filament. Proponer como tarea propia
   después de la salida en vivo.
2. **Cifrado del celular y del correo, sesiones y supresión de datos:**
   están en la spec de E10-03 (#141).
3. **Respaldos, monitoreo y alertas:** están en la spec de E1-03 (#142).
4. **La documentación no coincide con el CI sobre el segundo motor.**
   `CLAUDE.md` (§2, §3.13 y §6) y `.github/claude/revision.md` dicen que las
   pruebas corren en PostgreSQL y **SQL Server**. El CI corre PostgreSQL y
   **MySQL**, y producción en Hostinger usa MySQL (#43). Hay que decidir
   cuál es la verdad y corregir el otro.

## Cómo repetir esta auditoría

1. Recorrer la lista de "Qué se revisó" sobre el `main` del momento.
2. Para cada recurso nuevo del panel, intentar con un administrador de
   comercio crear, editar y mover registros hacia otro comercio.
3. `composer audit` y `npm audit --omit=dev`.
4. Buscar: `{!!`, `Raw(`, `DB::statement`, `guarded`, `trustProxies`,
   `password` en seeders y fábricas, `Log::` con datos personales.
5. Anotar lo encontrado en un archivo nuevo de `docs/seguridad/`.

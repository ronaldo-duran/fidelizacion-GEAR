# E4-04 · Código QR firmado y rotativo

**Épica:** E4 · **Horas estimadas:** 10 · **Riesgo:** medio

## Objetivo

La app del cliente muestra un código que cambia cada pocos segundos, para
que una captura de pantalla no pueda reutilizarse, y el cajero lo valida
escaneándolo o digitando su código corto.

## Fuera de alcance

- Las pantallas que lo muestran (uso del beneficio E8-04, credencial del
  cliente) y la hora en vivo con segundos que acompaña al código. Aquí se
  define el servicio que las alimenta.
- El escáner de la cámara del cajero (E4-02, E5-02): leer la imagen es del
  lado del navegador. Aquí se valida lo que el escáner entrega.
- Registrar la redención del beneficio y su valor otorgado (E5-02, regla
  dura 3.11). Aquí solo se garantiza que un código de beneficio se usa una
  vez.

## Reglas de negocio

1. **Nunca un QR estático** (regla dura 3.12): el código se firma en el
   servidor y vence en pocos segundos. La app pide uno nuevo antes de que
   venza; el mismo cliente nunca ve el mismo código en dos ventanas.
2. **Dos propósitos**: `identificar` (el cajero reconoce al cliente para
   registrar una compra, E4-02) y `beneficio` (el cliente usa un beneficio
   concreto, E5-02/E8-04). Un código de un propósito no sirve para el
   otro.
3. **El contenido firmado**: cliente, propósito, beneficio (si aplica),
   emisión, vencimiento y un valor aleatorio único. La firma es un HMAC con
   la clave de la aplicación; nunca lleva datos personales legibles.
4. **Código corto equivalente** para "Digitar código" en el cajero (p. ej.
   `BBC-4821`): mismo propósito, misma vida y mismo control de uso que el
   QR.
5. **Validación en el cajero**: firma (o código corto existente), que no
   esté vencido (con una tolerancia mínima de reloj), propósito esperado,
   y para un beneficio, que sea del comercio del cajero.
6. **Uso único del beneficio**: un código de beneficio se acepta una sola
   vez, garantizado por la base de datos (un UPDATE condicionado), nunca
   por una validación en código. El de `identificar` puede validarse más de
   una vez dentro de su ventana.
7. **Respuestas del cajero**: válido; vencido ("El código venció. Pídele al
   cliente que lo actualice."); ya usado; o inválido. Nunca muestran datos
   del cliente si el código no es válido.
8. **Anti-fuerza bruta del código corto**: intentos fallidos limitados por
   cajero y por sede en una ventana de tiempo.
9. Tiempos, largo del código corto y límites viven en el fixture.

## Criterios de aceptación

- [ ] Dado un código con la firma alterada, o con otro cliente, propósito o
      beneficio en el contenido, cuando se valida, entonces se rechaza como
      inválido.
- [ ] Dado un código vencido (fuera de la tolerancia), cuando se valida,
      entonces se rechaza como vencido.
- [ ] Dado un código de beneficio ya usado, cuando se valida otra vez,
      entonces se rechaza como ya usado; dos validaciones simultáneas solo
      aceptan una (lo garantiza la base de datos).
- [ ] Dado un código de `identificar`, cuando se usa para un beneficio (o
      al revés), entonces se rechaza.
- [ ] Dado un código de beneficio de otro comercio, cuando lo valida un
      cajero, entonces se rechaza.
- [ ] Dadas dos emisiones seguidas para el mismo cliente, entonces los
      códigos (QR y corto) son distintos.
- [ ] Dado el código corto, cuando se digita, entonces vale lo mismo que el
      QR; tras el máximo de intentos fallidos, el cajero queda bloqueado el
      tiempo del fixture.
- [ ] Dado un código inválido, entonces la respuesta no trae ningún dato
      del cliente.
- [ ] El QR se genera como imagen real con la librería (no imágenes de
      ejemplo).

## Fixtures

`tests/Fixtures/qr-escenarios.json` (nuevo, en inglés): vida del código,
cada cuánto se renueva, tolerancia de reloj, formato del código corto,
límites de intentos y escenarios de emisión, validación, vencimiento, uso
único y alteración.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **`credenciales_qr`** (tabla nueva): `id`, `cliente_id`, `proposito`,
  `beneficio_id` (nullable; la FK llega con E5-01), `comercio_id`
  (nullable; el del beneficio), `nonce` (único), `codigo_corto`,
  `emitido_at`, `vence_at`, `usado_at`, `usado_por`, `created_at`. El
  código corto se busca junto con `vence_at` vigente; al emitir se
  reintenta si choca con uno vigente. Las filas vencidas se purgan en el
  proceso nocturno (E3-05).
- **Token del QR**: `base64url(contenido) . base64url(HMAC-SHA256)`, con el
  `nonce` de la fila. Al validar se comprueba la firma y luego la fila.
- **Código corto**: prefijo de 3 letras (el del comercio del beneficio, o
  `AR` para identificar) más 4 dígitos, sin caracteres ambiguos.
- **Uso único**: `UPDATE credenciales_qr SET usado_at = … WHERE id = ? AND
usado_at IS NULL`; si afecta cero filas, ya se usó. E5-02 lo llama dentro
  de la transacción de la redención.
- **Actions** (`app/Actions/Credenciales/`, invocables):
  - `IssueCredencial`: emite el token y el código corto para un cliente y
    propósito.
  - `ValidateCredencial`: valida token o código corto para un cajero y
    propósito esperado; para un beneficio, lo marca usado.
- **Librería de QR**: `chillerlan/php-qrcode` (MIT/Apache-2.0), que ya
  está instalada como dependencia de Filament 5. Se declara directo en
  `composer.json` para no depender de que Filament la siga trayendo. Genera
  SVG en el servidor.
- **Rotación en la PWA**: el componente Livewire pide una credencial nueva
  antes del vencimiento (polling con el intervalo del fixture).

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-22); el revisor las aprueba con
esta spec.

1. El código vive **30 segundos** y la app lo renueva cada **20**, con
   **5 segundos** de tolerancia de reloj (supuesto; el diseño dice "cambia
   solo cada 20 s").
2. Código corto con prefijo de 3 letras + 4 dígitos (como `BBC-4821` del
   diseño), con un máximo de **5 intentos fallidos por minuto** por cajero
   (supuesto).
3. Un código de `identificar` puede validarse varias veces en su ventana;
   solo el de beneficio es de un uso.
4. Usar `chillerlan/php-qrcode`, ya presente por Filament, declarándolo en
   `composer.json` (no es un paquete nuevo en el proyecto, pero sí una
   dependencia directa nueva).
5. Las filas vencidas se purgan en el proceso nocturno de E3-05.

## Reglas duras de CLAUDE.md que aplican

- 3.12 QR firmado con ventana corta
- 3.13 Código agnóstico del motor; la hora la pone la aplicación
- 3.10 El cliente se identifica por su id interno en el código, nunca por
  el documento

## Depende de

Nada para construirse. Lo usan E4-02 (escanear QR para registrar compra),
E5-02 (validar beneficio) y E8-04 (uso del beneficio).

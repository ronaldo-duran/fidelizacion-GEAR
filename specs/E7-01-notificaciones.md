# E7-01 · Motor de notificaciones

**Épica:** E7 · **Horas estimadas:** 18 · **Riesgo:** medio

## Objetivo

Avisarle al cliente por correo y dentro de la PWA lo que le pasa en el
programa (bienvenida, subida de nivel, puntos que lo van a bajar,
membresía, beneficio usado), con textos que el administrador edita en el
panel sin desplegar.

## Fuera de alcance

- Autenticar el dominio (SPF, DKIM, DMARC) y recibir los webhooks de
  rebotes y quejas del proveedor (E7-02). Aquí solo se lee la lista de
  supresión que E7-02 llena.
- Campañas de mercadeo y sincronización de contactos con Brevo (E7-03).
- WhatsApp y SMS (CLAUDE.md §8; issue #1, decisión 11). El SMS del registro
  es de E8-06 y no pasa por aquí.
- Decidir a quién avisar de un descenso o de una renovación: ya lo deciden
  E3-05 (`avisos_descenso`) y E6-01 (`avisos_renovacion`). Aquí solo se
  envían.
- Preferencias del cliente en el perfil, como los "Avisos por correo" que
  reemplazan los de WhatsApp del diseño (E8-05).

## Reglas de negocio

1. **Eventos.** Los eventos, su categoría, sus variables y sus textos
   iniciales están en `tests/Fixtures/notificaciones-escenarios.json`:
   bienvenida, subida de nivel, aviso de descenso, membresía confirmada,
   membresía por vencer y beneficio usado.
2. **Plantillas editables.** Cada evento tiene una plantilla con asunto y
   cuerpo que el administrador del grupo edita en el panel. El siguiente
   envío usa el texto nuevo, sin desplegar.
3. **Variables de lista blanca** (regla dura 3.8). Una plantilla solo
   admite marcadores `{variable}` de la lista de su evento. Al guardar se
   rechaza cualquier marcador fuera de la lista, una llave sin cerrar o un
   asunto o cuerpo vacío. No hay expresiones, filtros ni Blade: la
   sustitución es texto por texto.
4. **Valores escapados.** Los valores (nombre del cliente, nombre del
   beneficio) se escapan al armar el HTML del correo, y un valor que
   parezca un marcador no se vuelve a sustituir.
5. **Canales.** Todo evento crea un aviso dentro de la PWA. El correo se
   envía solo si el cliente tiene correo, porque en el registro es opcional
   (E8-02) y buena parte del público lo usa poco (issue #1).
6. **Supresión** (lista de E7-02):
   - un correo que rebotó o se quejó no recibe ningún correo, ni siquiera
     transaccional;
   - una baja de mercadeo no recibe correos de mercadeo, pero sí los
     transaccionales;
   - la supresión nunca bloquea el aviso dentro de la PWA.

   Los eventos de esta lista son todos transaccionales: avisan algo de la
   cuenta del cliente. La categoría existe para que la regla quede probada
   y para eventos futuros; las campañas viven en Brevo (E7-03).

7. **Un envío por evento y canal.** Cada envío nace de una fila de origen
   (el cliente, el cambio de nivel, el aviso de descenso, el pago, el aviso
   de renovación o la redención). Una restricción única en la base de datos
   sobre `(evento, origen_id, canal)` impide que un reintento o un
   disparo repetido mande dos veces lo mismo.
8. **Después de la transacción.** El envío se dispara cuando la
   transacción que creó el origen ya se confirmó (`afterCommit`). Una
   compra o un pago nunca esperan al correo, y un correo nunca sale por
   algo que se deshizo.
9. **En cola.** El correo sale en la cola `notificaciones` de Horizon, con
   los reintentos del fixture. Si se agotan, el envío queda `fallido` con
   el error, y el aviso en la PWA no se pierde.
10. **Hora de los avisos nocturnos.** Los avisos de descenso y de
    renovación se registran de noche (E3-05, E6-01), pero se envían a la
    hora del fixture (supuesto), en `America/Bogota`, no de madrugada. Al
    enviarlos se llena su `enviado_at`.
11. **Subida de nivel por membresía.** Cuando el ascenso viene de pagar una
    membresía (`ascenso_membresia`), solo se envía "membresía confirmada",
    que ya dice el nivel, para no mandar dos correos por el mismo hecho.
12. **"Puntos por vencer"** del issue se cubre con el aviso de descenso. El
    cliente decidió que solo se avisa cuando perder los puntos lo baja de
    nivel (issue #1, decisión 4; CLAUDE.md §3.6), así que no hay un aviso
    aparte de puntos que vencen sin consecuencia.
13. **Registro para soporte.** Cada envío queda guardado con el
    destinatario, el asunto y el cuerpo ya armados, su estado y su error.
    El panel lo muestra en solo lectura.
14. **Los puntos son estatus** (regla dura 3.1). Ningún texto inicial
    habla de canjear o gastar puntos, y la vista previa del panel lo
    recuerda.

## Criterios de aceptación

- [ ] Dado que el administrador cambia el asunto de "subida de nivel",
      cuando el siguiente cliente sube de nivel, entonces el correo sale
      con el asunto nuevo.
- [ ] Dada una plantilla con una variable fuera de la lista de su evento,
      una llave sin cerrar o algo que parezca código, cuando se guarda,
      entonces no se guarda y el panel dice qué marcador sobra.
- [ ] Dado un nombre de cliente con HTML, cuando se envía el correo,
      entonces se ve como texto.
- [ ] Dado un cliente sin correo, cuando sube de nivel, entonces recibe el
      aviso en la PWA y el envío por correo queda `omitido` con motivo.
- [ ] Dado un correo que rebotó, cuando hay un evento transaccional,
      entonces no se le envía correo y sí se crea el aviso en la PWA.
- [ ] Dada una baja de mercadeo, entonces recibe los transaccionales y no
      los de mercadeo.
- [ ] Dado el mismo origen disparado dos veces, entonces hay un solo envío
      por canal.
- [ ] Dada una compra que sube de nivel y cuya transacción falla, entonces
      no sale ningún correo.
- [ ] Dado un aviso de descenso registrado de noche, cuando llega la hora
      del fixture, entonces sale el correo y el aviso queda con
      `enviado_at`.
- [ ] Dado un pago de membresía que sube al cliente a Diamante, entonces
      recibe "membresía confirmada" y no "subida de nivel".
- [ ] Dado un proveedor de correo caído, entonces el envío se reintenta,
      queda `fallido` al agotar los intentos y la compra o el pago que lo
      originaron no se ven afectados.

## Fixtures

`tests/Fixtures/notificaciones-escenarios.json` (nuevo, en inglés):

- eventos con su categoría, origen, variables y textos iniciales;
- casos de validación de plantillas;
- casos de escape al armar el correo;
- casos de destinatario (sin correo, rebote, queja, baja de mercadeo);
- hora de envío de los avisos nocturnos, intentos y tamaño de la lista en
  la PWA (supuestos).

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **`plantillas_notificacion`**: `id`, `evento` (único), `asunto`,
  `cuerpo`, `activa`, timestamps. Se siembran desde el fixture. Se editan
  en sitio y el `AuditoriaObserver` (E2-01) guarda el antes y el después.
  No son reglas de negocio con números, así que no se versionan como las
  reglas (3.2); el envío guarda el texto ya armado.
- **`envios_notificacion`**:
  - columnas: `id`, `cliente_id`, `evento`, `canal` (`correo` | `app`),
    `origen_id`, `destinatario` (nullable), `asunto`, `cuerpo`, `estado`
    (`pendiente` | `enviado` | `omitido` | `fallido`), `motivo` (nullable:
    `sin_correo`, `suprimido`, `plantilla_inactiva`), `intentos`, `error`
    (nullable), `enviado_at`, `leido_at` (solo `app`), `created_at`;
  - restricción única `(evento, origen_id, canal)`: el evento ya dice de
    qué tabla viene el origen.
- **`supresiones_correo`**: `id`, `email` (único, en minúsculas), `motivo`
  (`rebote` | `queja` | `baja_mercadeo`), `created_at`. Se crea aquí para
  que la regla 6 quede probada desde ya. E7-02 la llena con sus webhooks y
  con el enlace de baja.
- **Plantillas** (`app/Notificaciones/`):
  - `CatalogoEventos` lee de `config/notificaciones.php`, espejo del fixture,
    los eventos, sus variables y su categoría (una prueba verifica que
    coinciden con el fixture);
  - `ValidadorPlantilla` busca todo `{…}` y lo compara con la lista
    blanca;
  - `RenderPlantilla` sustituye en una sola pasada (`strtr`) y arma el
    correo con una vista Blade fija del sistema, con los tokens del diseño.
    El texto del administrador nunca se compila como Blade.
- **Actions** (`app/Actions/Notificaciones/`, invocables):
  - `Notify`: recibe el evento, el cliente, el origen y los valores; crea
    los envíos de los dos canales (o los omite con motivo) y despacha el
    trabajo de correo después del commit;
  - `SendEnvioCorreo`: el trabajo en cola que manda el correo y deja el
    estado.
- **Disparadores**, cada uno con la fila de origen ya confirmada:
  - bienvenida: `CompleteRegistro` (E8-02);
  - subida de nivel: el `cambios_nivel` de ascenso (E3-03), salvo
    `ascenso_membresia`;
  - aviso de descenso: un job `SendAvisosNocturnos` a la hora del fixture
    toma los `avisos_descenso` sin `enviado_at`;
  - membresía confirmada: `GrantMembresia` (E6-01);
  - membresía por vencer: el mismo job, con los `avisos_renovacion` (E6-01,
    cuando exista);
  - beneficio usado: la redención (E5-02).
- **Correo**: Laravel Mail con el transporte de `MAIL_MAILER` (en local,
  `log`; en producción, el que deje E7-02). Remitente desde `.env`.
- **PWA**: `/avisos`, con los últimos avisos del cliente (tamaño del
  fixture), los no leídos marcados, y un enlace con el número de no leídos
  en el encabezado. Abrir la lista los marca como leídos.
- **Panel** (grupo «Configuración», solo el administrador del grupo):
  - plantillas: editar asunto y cuerpo, activar o desactivar, ver las
    variables de su evento y una vista previa con datos de ejemplo; sin
    crear ni borrar, porque los eventos los define el código;
  - envíos: solo lectura, con filtros por evento, canal, estado y cliente.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-11-03); el revisor las aprueba con
esta spec.

1. El aviso dentro de la PWA entra ya, no "si da el tiempo": el correo es
   opcional y muchos clientes no lo tienen (issue #1, decisión 11).
2. "Puntos por vencer" se cubre con el aviso de descenso (regla 12).
3. Con `ascenso_membresia` solo sale "membresía confirmada" (regla 11).
4. La tabla `supresiones_correo` se crea en E7-01 y E7-02 la llena.
5. Las plantillas se editan en sitio con auditoría, sin versiones.
6. Los avisos nocturnos salen a las 8:00 (supuesto del fixture).
7. Rebote y queja bloquean todo correo; la baja de mercadeo, solo el de
   mercadeo.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los puntos son estatus (los textos no hablan de canjear)
- 3.6 El aviso de descenso solo cuando el cliente va a bajar
- 3.8 Lista blanca, sin evaluación dinámica de lo que llega del panel
- 3.9 `America/Bogota` para la hora de envío
- 3.13 Código agnóstico del motor (correos normalizados en minúsculas)

## Notas de interfaz

- Correo: plantilla HTML sencilla con el logo provisional, el color de
  marca y un pie con el nombre del programa. Debe leerse bien sin
  imágenes.
- `/avisos` sigue la skill `club-ar-ui`: lista con fecha y texto, estado
  vacío "No tienes avisos", zonas táctiles de 44 px y prueba E2E del flujo
  principal.

## Depende de

E3-03 (`cambios_nivel`), E3-05 (`avisos_descenso`), E5-02 (redenciones),
E8-02 (registro). E6-01 y E7-02 se conectan cuando existan: este motor no
los espera.

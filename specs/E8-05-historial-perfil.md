# E8-05 · PWA: historial y perfil

**Épica:** E8 · **Horas estimadas:** 8 · **Riesgo:** bajo

## Objetivo

El cliente revisa de dónde salen sus puntos y cuándo dejan de contar, y
administra sus datos y sus avisos por correo.

## Fuera de alcance

- Escribir en el libro de puntos. El historial solo **lee**
  `movimientos_puntos` (E3-02).
- Avisos por WhatsApp. El diseño los muestra, pero están fuera de alcance
  (§8 y decisión 11 del issue #1). Aquí van **avisos por correo**.
- Cambiar el documento o el celular. El documento lo corrige el panel
  (E10-06). El celular está verificado por SMS (E8-06): cambiarlo pide otro
  código, y eso queda para cuando el cliente lo pida.
- Consultar, exportar o eliminar los datos personales: E10-03. Aquí solo
  va el enlace cuando esa pantalla exista.
- El texto del reglamento y de la política de datos. Los entrega el
  cliente (decisión 13 del issue #1).

## Reglas de negocio

### Historial (`/historial`)

1. **Los puntos son estatus** (regla dura 3.1). El historial explica de
   dónde salen los puntos y cuándo dejan de contar. Nunca habla de saldo
   para gastar, canjear ni redimir.
2. **La cifra de arriba** es `clientes.saldo_puntos`: los puntos que hoy
   cuentan para el nivel. El título usa la vigencia de la regla de
   acumulación vigente (`vigencia_puntos_meses`): "Puntos de los últimos 12
   meses". Ningún número va escrito en la vista.
3. **Aviso de puntos por vencer.** Si algún lote vence dentro de los
   próximos días del fixture (`history.expiry_notice_days`), arriba de la
   lista aparece un aviso con el lote más próximo: "180 puntos dejan de
   contar el 31 de octubre. Son de compras de octubre de 2025." Se suman los
   puntos que **quedan** de los lotes que vencen ese día, con
   `LoteCalculator`. Si no vence nada en esa ventana, no hay aviso.
4. **Los movimientos** son los del cliente creados dentro de la vigencia
   (los últimos 12 meses hoy), del más nuevo al más viejo, agrupados por mes
   en `America/Bogota` (regla dura 3.9). Cada fila tiene un título, una línea
   con la fecha y el tipo, y los puntos con signo:

   | Tipo            | Título                              | Línea                       | Puntos             |
   | --------------- | ----------------------------------- | --------------------------- | ------------------ |
   | `acumulacion`   | el comercio                         | "27 sep · Compra $85.000"   | `+85` en verde     |
   | `reversa`       | el comercio                         | "24 sep · Devolución"       | `−32` en `danger`  |
   | `caducidad`     | "Puntos vencidos"                   | "1 oct · Dejaron de contar" | `−180` en `danger` |
   | `ajuste_manual` | "Ajuste del Club"                   | "3 oct · Ajuste"            | con su signo       |
   | `promocion`     | el comercio, o "Club Aponte Rivera" | "5 oct · Bono"              | `+50` en verde     |
   - El signo menos es el carácter "−" (U+2212), como en el diseño.
   - El motivo de un ajuste manual no se muestra: lo escribe el
     administrador para el registro interno.
   - Una compra anulada se ve con su compra y su devolución, cada una en su
     fecha, como en el diseño.

5. **Saldo y libro coinciden.** La cifra de arriba es la suma de lo que
   queda en los lotes vigentes del libro. Una prueba lo verifica con
   `SaldoCheck` sobre el cliente del fixture.
6. **Sin N+1.** El historial hace un número fijo de consultas sin importar
   cuántos movimientos tenga el cliente: los comercios se cargan en una sola
   consulta.

### Perfil (`/perfil`)

7. **Encabezado.** Avatar de 56 px con las iniciales del nombre (las dos
   primeras palabras) en los colores del nivel efectivo, el nombre completo y
   "{Nivel} desde {mes} {año}" con `nivel_efectivo_desde`. Sin nombre, el
   avatar muestra el ícono de usuario.
8. **Mis datos.**
   - **Documento:** el tipo y el número enmascarado, que solo muestra los
     cuatro últimos caracteres: "CC •••• 6789". Un número de cuatro o menos
     caracteres se muestra completo enmascarado: "••••".
   - **Celular:** con el formato `300 123 4567`. Solo lectura (fuera de
     alcance).
   - **Correo:** si no hay, "Agregar". El cliente lo agrega o lo cambia. Se
     guarda en minúsculas y sin espacios (regla dura 3.13), con validación de
     correo.
   - **Fecha de nacimiento:** si no hay, "Agregar", para los beneficios de
     cumpleaños (E5-01, E8-04 decisión 6). Una vez guardada, el cliente ya no
     la cambia: la corrige el panel (decisión 3).
9. **Avisos por correo.** Un interruptor por cada tipo de aviso del
   fixture (`profile.email_notices`):
   - "Cuando subo de nivel" (`subida_nivel`);
   - "Cuando voy a bajar de nivel" (`aviso_descenso`);
   - "Cuando mi suscripción va a vencer" (`membresia_por_vencer`);
   - "Cuando uso un beneficio" (`beneficio_usado`).

   Todos empiezan encendidos. Apagar uno solo deja de mandar **ese correo**:
   el aviso dentro de la PWA siempre se crea (E7-01, regla 5). La bienvenida
   y la confirmación de la suscripción no tienen interruptor: son la
   constancia de algo que el cliente acaba de hacer. Sin correo, los
   interruptores se ven apagados y deshabilitados, con "Agrega tu correo para
   recibir avisos".

10. **El envío respeta la preferencia.** Cuando E7-01 va a mandar el correo
    de un evento que el cliente apagó, el envío queda `omitido` con el motivo
    `preferencia_cliente`, igual que un cliente sin correo. Nunca se borra ni
    se salta el registro del envío.
11. **Documentos.** "Reglamento del programa" y "Política de tratamiento de
    datos" enlazan a la dirección configurada para cada uno
    (`config/documentos.php`). Mientras el cliente no entregue el texto, la
    dirección está vacía con `TODO(cliente)` y la fila dice "Disponible
    pronto", sin enlace. El mismo enlace de la política se usa en el registro
    (E8-02), que hoy apunta a `#`.
12. **Mis datos personales.** "Consultar o eliminar mis datos" enlaza a la
    pantalla de E10-03 solo si su ruta existe (`Route::has()`), como las
    pestañas de E8-03.
13. **Cerrar sesión** pasa del inicio al final del perfil, en `danger`, y se
    resuelve el `TODO(E8-05)` del inicio.
14. **Entrada al perfil.** El botón de perfil del encabezado
    (`user-circle`) pasa a ser enlace a `/perfil` en todas las pantallas del
    cliente.

### Comunes

15. **Ningún texto menciona WhatsApp** en el historial, el perfil ni sus
    componentes.
16. **Estados.**
    - **Vacío:** un cliente sin movimientos ve "Todavía no tienes
      movimientos" con "Tus compras en los comercios del Club suman puntos."
    - **Cargando:** las dos pantallas se pintan en la primera respuesta.
      Guardar un dato o un interruptor muestra el botón deshabilitado mientras
      responde.
    - **Error al guardar:** el mensaje del campo bajo el campo; un
      interruptor que no se pudo guardar vuelve a su estado con "No pudimos
      guardar el cambio. Intenta de nuevo."
    - **Sin conexión:** la página "Sin conexión" del service worker (E8-01).

## Criterios de aceptación

- [ ] Dado el cliente del fixture, cuando abre `/historial`, entonces ve la
      cifra de arriba igual a su saldo y las filas del fixture, agrupadas por
      mes, con sus títulos, líneas y signos.
- [ ] La suma de lo que queda en los lotes vigentes del cliente es igual a
      `saldo_puntos` (`SaldoCheck`), y es la cifra de arriba.
- [ ] Dado un lote que vence dentro de la ventana del fixture, cuando el
      cliente abre el historial, entonces ve el aviso con los puntos que
      quedan y la fecha. Fuera de la ventana no hay aviso.
- [ ] Una devolución y unos puntos vencidos se ven en `danger` con "−".
- [ ] Un movimiento más viejo que la vigencia no aparece.
- [ ] El historial hace el mismo número de consultas con 3 que con 30
      movimientos.
- [ ] Dado un cliente sin movimientos, entonces ve el estado vacío.
- [ ] Dado el cliente del fixture, cuando abre `/perfil`, entonces ve sus
      iniciales, "Plata desde mayo 2026", el documento enmascarado y el
      celular con formato.
- [ ] El cliente agrega su correo y queda en minúsculas; un correo inválido
      muestra el error y no se guarda.
- [ ] El cliente agrega su fecha de nacimiento; una vez guardada no la puede
      cambiar desde el perfil.
- [ ] Dado un cliente que apagó "Cuando subo de nivel", cuando sube de
      nivel, entonces recibe el aviso en la PWA y el envío por correo queda
      `omitido` con `preferencia_cliente`. Con el interruptor encendido, el
      correo sale.
- [ ] Sin correo, los interruptores están deshabilitados.
- [ ] Ninguna de las dos pantallas contiene "WhatsApp".
- [ ] Con la dirección de un documento vacía, la fila dice "Disponible
      pronto" sin enlace; con dirección, enlaza.
- [ ] Un cliente sin sesión que pide `/historial` o `/perfil` va a
      `/ingresar`.
- [ ] E2E en celular a 360 px: inicio → Historial → perfil (desde el ícono)
      → agregar correo → apagar un aviso → cerrar sesión. Se compara a mano
      contra `docs/diseno/reference/` (1q y 1t).

## Fixtures

`tests/Fixtures/historial-perfil-escenarios.json`: el día, el cliente con
sus datos y sus movimientos (compras, una devolución, un vencimiento, un
ajuste, un bono y uno fuera de la vigencia), las filas y los meses
esperados, el aviso de puntos por vencer y su ventana, el encabezado del
perfil, el enmascarado del documento, el formato del celular y los avisos
con interruptor.

## Propuesta técnica (pendiente de revisión)

- **Rutas** detrás de `auth:cliente`: `cliente.historial` (`/historial`) y
  `cliente.perfil` (`/perfil`). La pestaña Historial empieza a enlazar sola
  (`Route::has()`, E8-03).
- **Livewire:** `App\Livewire\Cliente\Historial` y
  `App\Livewire\Cliente\Perfil`, delgados.
- **Lectura:** `App\Puntos\HistorialCliente` arma la cifra, el aviso por
  vencer y las filas agrupadas por mes, con una consulta de movimientos (con
  sus comercios) y `LoteCalculator` para lo que queda en cada lote.
- **Escrituras** (Actions en `app/Actions/Clientes/`):
  - `UpdateCorreoCliente`: valida y guarda el correo normalizado.
  - `SetFechaNacimiento`: la guarda solo si estaba vacía.
  - `SetPreferenciaAviso`: enciende o apaga un aviso por correo.
- **Preferencias:** tabla nueva `preferencias_avisos`: `id`, `cliente_id`,
  `evento`, `correo` (booleano), `updated_at`, con
  `UNIQUE (cliente_id, evento)`. Sin fila, el aviso está encendido. No es
  una tabla de dominio append-only: guarda la preferencia actual.
- **Envío:** `Notify` (E7-01) consulta la preferencia antes de encolar el
  correo y, si está apagada, registra el envío `omitido` con
  `preferencia_cliente`. El aviso de la PWA no cambia.
- **Configuración:** `config/documentos.php` con `reglamento_url` y
  `politica_datos_url`, vacías con `TODO(cliente)`. `config/historial.php`
  con la ventana del aviso por vencer.
- **Componentes:** se reutilizan `client-header`, `tab-nav`, `notice`,
  `toggle`, `field`, `button` y `empty-state`. `client-header` enlaza el
  ícono de perfil. Nada de tokens nuevos.

## Decisiones a confirmar en la revisión

1. **Avisos por correo en vez de WhatsApp** (decisión 11 del issue #1), con
   cuatro interruptores. El diseño trae "Beneficios nuevos", pero no hay un
   evento de beneficio nuevo: eso es una campaña de mercadeo de Brevo
   (E7-03). Se quita del perfil.
2. **"Puntos por vencer" del diseño pasa a "Cuando voy a bajar de nivel"**:
   el cliente decidió que solo se avisa cuando perder los puntos lo baja de
   nivel (E3-05), así que ese es el correo que existe.
3. **La fecha de nacimiento se agrega una sola vez** desde el perfil. Si el
   cliente pudiera cambiarla, podría mover su cumpleaños para repetir un
   beneficio. Una corrección la hace el panel.
4. **El celular no se cambia desde el perfil.** Exige verificar el número
   nuevo con un SMS, y eso no está en el alcance de este issue.
5. **La ventana del aviso por vencer es de 30 días** (supuesto del
   fixture), igual al primer aviso de descenso de la regla `niveles`.
6. **Los movimientos que se muestran son los de la vigencia** (12 meses
   hoy). Lo anterior ya no cuenta para el nivel y se consulta en el panel.
7. **Una compra anulada se ve como dos filas** (compra y devolución), como
   en el diseño, y no como una sola fila tachada.
8. **Los documentos sin texto dicen "Disponible pronto"**, y el registro
   usa la misma dirección de la política en lugar de `#`.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los puntos son estatus: nada de saldo para gastar ni "canjear".
- 3.4 El libro es de solo lectura aquí: ninguna escritura en
  `movimientos_puntos`.
- 3.9 Fechas y meses en `America/Bogota`, formato colombiano.
- 3.10 El documento no se cambia desde la PWA; se muestra enmascarado.
- 3.13 El correo se normaliza a minúsculas antes de guardarlo.
- §8 WhatsApp fuera de alcance: no se construye nada contra él.
- Sección 7: lógica en clases de lectura y Actions, componentes delgados,
  zonas táctiles de 44 px, tuteo, una prueba E2E por pantalla nueva.

## Notas de interfaz

- Diseño: pantallas 5 (1q) y 7 (1t) en `docs/diseno/reference/`.
  Componentes HistoryList y Profile de `docs/diseno/COMPONENTS.md`.
- Historial: cifra en display 700 a 40 px con `tabular-nums`; el aviso por
  vencer en el componente `notice`; los meses como overline (12 px, 700,
  mayúsculas, `ink-2`); filas de 56 px con el comercio a 15 px 700, la línea
  a 13 px `ink-2` y los puntos a 17 px 700 tabulares, en `success-ink` si
  suman y en `danger` si restan.
- Perfil: grupos con overline (Mis datos, Avisos por correo, Documentos).
  Los interruptores son el `toggle` de 48×28 en una fila de 44 px como
  mínimo. "Cerrar sesión" en `danger`.
- Skill `club-ar-ui`. Probar a 360 px.

## Depende de

E8-01 (componentes y PWA), E8-02 (sesión y registro), E8-03 (pestañas e
inicio), E3-02 (libro de puntos), E3-05 (vencimientos) y E7-01 (envíos y
eventos).

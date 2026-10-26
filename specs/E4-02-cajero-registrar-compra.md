# E4-02 · Pantalla del cajero: registrar compra

**Épica:** E4 · **Horas estimadas:** 10 · **Riesgo:** medio

## Objetivo

El cajero registra una compra desde el celular del comercio, con una mano y
de pie: digita el monto, identifica al cliente por documento o por su QR y
confirma. Si el cliente no está registrado, el monto nunca se pierde.

## Fuera de alcance

- Validar beneficios ("Validar beneficio", 1j, 1k, 1l): E5-02. Aquí el
  selector de modo lo muestra, pero esa opción llega con E5-02.
- Calcular puntos, idempotencia, compras pendientes y anulación con la
  reversa del libro: E4-01 (`RegisterCompra`, `AnnulCompra`), ya en `main`.
- Firmar y validar el QR del cliente: E4-04 (`ValidateCredencial`). Aquí se
  lee la cámara y se pasa lo leído.
- El registro del cliente: E8-02. Aquí solo se muestra el QR con el enlace
  firmado de `MakeEnlaceRegistro`.
- "No tiene celular: lo registro yo" (1i): no entra (decisión 7 de E8-02).
- La carga masiva (E4-03) y la API (E9-01).

## Reglas de negocio

1. **Ingreso del cajero**: ruta propia, fuera del panel, con el diseño de
   1y y el layout del cajero. Usa la cuenta de usuario del sistema (E2-01):
   correo y contraseña, y "Mantener la sesión en este celular". Solo entran
   usuarios activos con rol cajero y sede asignada; el error no dice si la
   cuenta existe. Si la cuenta se desactiva, la sesión se cierra en la
   siguiente petición. El cajero sigue sin entrar al panel.
2. **Monto primero** (1c): el monto se escribe con el teclado (con "000"),
   con un máximo de dígitos (fixture). **No se muestran los puntos que
   suma** en esta pantalla (decisión del cliente).
3. **Identificar al cliente**: "Cédula" (por defecto) abre el tipo de
   documento (`CC` preseleccionado, CLAUDE.md 3.10) y el número; "Escanear
   QR" abre la cámara y valida la credencial de propósito `identificar`
   (E4-04). Un QR vencido, alterado o de otro propósito se rechaza sin
   mostrar datos del cliente.
4. **Confirmar**: antes de registrar se ve el nombre del cliente y el monto,
   con un botón "Registrar compra". La compra se registra en el comercio y
   la sede del cajero, canal `cajero`, con `RegisterCompra` (E4-01).
5. **Doble toque no duplica** (regla dura 3.7): cada intento de registro
   lleva una referencia generada al confirmar; repetirla devuelve la misma
   compra (E4-01, regla 1). El botón se bloquea mientras registra.
6. **Compra registrada** (1g): pantalla completa en `success` para girar el
   celular hacia el cliente: "Compra registrada", los puntos que sumó, el
   nombre, la pastilla del nivel y cuánto le falta para el siguiente.
   Vuelve sola al inicio a los segundos del fixture, o con "Nueva compra".
7. **Documento no registrado** (1h): el monto queda guardado y el teclado se
   reemplaza por tres salidas:
   - **Guardar pendiente y seguir**: registra la compra como pendiente
     (E4-01) y vuelve al inicio.
   - **Registrar cliente ahora** (1i): registra la compra pendiente y
     muestra el QR con el enlace de registro (E8-02); "Atender al siguiente"
     vuelve al inicio. Al verificar su celular, la compra se acredita sola.
   - **Corregir cédula**: vuelve al número con el monto intacto.
8. **Anular desde la caja**: "Anular esta compra" en 1g, y "Anular la última
   compra" en el inicio mientras siga en plazo. El cajero solo anula una
   compra que registró él, en su sede, dentro del plazo del fixture;
   la anulación va por `AnnulCompra` (reversa del libro, E3-02) con un
   motivo fijo. Fuera de ese plazo, la anula un administrador.
9. **Zonas táctiles de 64 px** en todo el flujo, fondo blanco, una acción
   por pantalla y resultados sin animación larga (máximo 120 ms).
10. **Componentes delgados**: toda la lógica en las Actions de E4-01, E4-04
    y E8-02; el componente solo maneja el paso de la pantalla.

## Criterios de aceptación

- [ ] Dado un cajero activo con sede, cuando ingresa, entonces llega a la
      caja; un usuario inactivo, sin sede o de otro rol no entra y el
      mensaje no revela si la cuenta existe.
- [ ] Dada una cuenta desactivada con la sesión abierta, cuando hace otra
      petición, entonces la sesión se cierra.
- [ ] Dado el teclado, entonces "000" agrega tres ceros, borrar quita el
      último dígito y no se pasa del máximo de dígitos; la pantalla del
      monto no muestra puntos.
- [ ] Dado un documento registrado, cuando se confirma, entonces la compra
      queda en el comercio y la sede del cajero y aparece 1g con los puntos
      que sumó; vuelve sola al inicio a los segundos del fixture.
- [ ] Dado un doble toque en "Registrar compra", entonces queda una sola
      compra.
- [ ] Dado un QR de identificación válido, entonces identifica al cliente;
      uno vencido o de beneficio se rechaza sin datos del cliente.
- [ ] Dado un documento no registrado, entonces el monto sigue guardado;
      "Guardar pendiente y seguir" deja la compra pendiente; "Registrar
      cliente ahora" la deja pendiente y muestra el QR de registro; "Corregir
      cédula" vuelve al número sin perder el monto.
- [ ] Dada una compra que registró el cajero, cuando la anula dentro del
      plazo, entonces queda anulada con su reversa; fuera del plazo, o una
      compra de otro cajero, se rechaza.
- [ ] Dado un cajero de una sede, entonces nunca registra en otra.
- [ ] El flujo completo tiene su prueba E2E a 360 px.

## Fixtures

`tests/Fixtures/cajero-escenarios.json` (nuevo, en inglés): máximo de
dígitos del monto, segundos de la vuelta automática, plazo de anulación del
cajero (horas), motivo fijo de la anulación y escenarios de teclado,
cliente registrado, pendiente, QR, doble toque, anulación y sedes. Los
valores que el cliente no ha confirmado van con `"_assumption": true`.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **Rutas** (`/caja`): ingreso (`/caja/ingresar`) y la caja, con el guard
  `web` de los usuarios del sistema y un middleware que exige rol cajero,
  cuenta activa y sede. Sin tablas nuevas.
- **Pantalla**: un componente Livewire con los pasos `monto` →
  `identificar` → `confirmar` → `registrada` | `no_registrado` →
  `registro_qr`, con el layout `x-layouts.cajero` y componentes del cajero
  de `docs/diseno/COMPONENTS.md` (CashierHeader, ModeSwitch, AmountDisplay,
  Keypad, IdentifyActions, ResultScreen). Teclado en Alpine para que cada
  tecla no espere al servidor.
- **Cámara**: la API `BarcodeDetector` del navegador donde exista (Chrome en
  Android). En iPhone (Safari), que no la tiene, se usa `jsqr` (MIT, npm) para
  leer el QR con la cámara; "Digitar código" con el código corto de E4-04
  (`AR-1234`) queda como alternativa, y valida lo mismo. (Dependencia nueva
  aprobada por el cliente el 2026-10-26.)
- **Anulación**: `TransaccionPolicy::annul` suma al cajero para sus propias
  compras de su sede dentro del plazo (riesgo alto: toca el libro; pasa por
  `AnnulCompra` sin cambiarla).
- **Encabezado**: comercio y sede sobre el nombre del cajero. El diseño dice
  "Caja 2", pero el sistema no tiene cajas numeradas: se muestra la sede.

## Decisiones de la revisión (resueltas)

Propuestas por el implementador (2026-10-25) y resueltas por el revisor el
2026-10-26.

1. El cajero ingresa por `/caja` con su correo y contraseña (la misma cuenta
   del sistema), no por el panel. Solo el rol cajero; los administradores
   registran desde el panel. **Aprobada tal cual.**
2. Paso de confirmación con el nombre del cliente antes de registrar (el
   diseño pasa directo de identificar a 1g): evita registrar a otra persona
   por un dígito mal puesto. **Aprobada: se mantiene el paso de confirmación
   con el mensaje que muestra el nombre del cliente** (regla 4).
3. El cajero puede anular solo sus propias compras, con el motivo "Anulada
   desde la caja". **Resuelta: el plazo es de 3 días (72 h en el fixture), no
   10 minutos.** Fuera de ese plazo, la anula un administrador.
4. En la compra pendiente (1h, 1i) **no** se muestran puntos ("Los 85 pts se
   le suman…" del diseño): todavía no existen, se calculan al registrarse con
   las reglas de la fecha de la compra y con sus topes. Se dice "Los puntos
   se le suman cuando se registre". **Aprobada tal cual.**
5. Cámara con `BarcodeDetector` en Android. **Resuelta: también cámara en
   iPhone con `jsqr` (MIT, npm), dependencia nueva aprobada;** "Digitar
   código" queda como alternativa.
6. Vuelta automática a los 5 s (diseño) y máximo de 9 dígitos en el monto
   ($999.999.999). **Aprobada tal cual** (confirmada en el fixture).

## Reglas duras de CLAUDE.md que aplican

- 3.7 Idempotencia: la referencia de cada intento y la restricción única de
  E4-01.
- 3.10 Documento con su tipo, normalizado.
- 3.12 QR firmado con ventana corta (E4-04).
- 3.4 La anulación es una reversa, nunca un cambio sobre la compra o sus
  movimientos.

## Notas de interfaz

- Skill `club-ar-ui`: fondo blanco, tinta `cashier-ink`, 64 px, resultados
  a pantalla completa, sin decorativos.
- Textos del diseño: "Monto de la compra", "¿Quién es el cliente?",
  "Cédula", "Escanear QR", "Compra registrada", "Nueva compra", "Vuelve sola
  en 5 s", "Anular esta compra", "No está registrada", "Guardado", "Guardar
  pendiente y seguir", "Registrar cliente ahora", "Él escanea un código con
  su celular", "Corregir cédula", "Que el cliente escanee este código",
  "Atender al siguiente".

## Depende de

E4-01, E8-01, E2-01 y E4-04 (en `main`); E8-02 (#84, enlace de registro).

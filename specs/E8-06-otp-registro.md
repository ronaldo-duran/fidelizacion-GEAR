# E8-06 · Verificación del celular por SMS

**Épica:** E8 · **Horas estimadas:** por estimar · **Riesgo:** medio

## Objetivo

Verificar que el celular de un cliente es suyo con un código de un solo
uso enviado por SMS, para el registro (E8-02) y para recuperar el PIN.

## Cambio de alcance

El código por SMS está **excluido por contrato** (CLAUDE.md §8). El
cliente lo pidió el 2026-10-10 (issue #1) y se asumió como **alcance
adicional**, sin mover las fechas de los otros hitos. El costo de cada SMS
lo paga el cliente.

## Fuera de alcance

- El formulario de registro, el ingreso con PIN y la recuperación del PIN
  en sí (E8-02). Aquí solo se envía y se verifica el código.
- WhatsApp, Instagram o cualquier verificación social automática (§8).
  El envío queda detrás de una interfaz para poder cambiar de canal con
  configuración más adelante.
- Elegir el proveedor y contratar la cuenta: lo hace el cliente.

## Reglas de negocio

1. **Dos propósitos**: `registro` (verificar el celular antes de crear la
   cuenta) y `recuperar_pin`.
2. **El código**: numérico, corto y de vida corta (largo y minutos en el
   fixture). Un solo código vigente por celular y propósito: pedir uno
   nuevo invalida el anterior.
3. **Nunca en claro**: el código se guarda con un hash y se compara en
   tiempo constante. **Nunca aparece en logs**, ni en la base, ni en
   excepciones, ni en respuestas.
4. **Intentos**: tras el máximo de intentos fallidos, el código se
   invalida y hay que pedir otro.
5. **Reenvíos**: espera mínima entre envíos al mismo celular y tope de
   envíos por celular y por IP en una ventana de tiempo (anti-abuso y
   control del costo). Números en el fixture.
6. **El mensaje** dice qué es, el código, cuánto dura y que no se
   comparte: "Tu código del Club Aponte Rivera es 482613. Vence en 5
   minutos. No lo compartas." (el código y los minutos salen del envío y
   del fixture).
7. **El celular** se normaliza antes de guardar y enviar (10 dígitos, sin
   espacios ni indicativo).
8. **Respuestas que no revelan nada**: pedir un código para recuperar el
   PIN responde igual exista o no un cliente con ese documento y celular.

## Criterios de aceptación

- [ ] El envío pasa por una interfaz con al menos dos implementaciones:
      una de prueba (`array`, guarda los mensajes en memoria para las
      pruebas) y la del proveedor real; se elige por configuración.
- [ ] Dado un código pedido, entonces en la base solo queda su hash, y
      ningún log, excepción o respuesta contiene el código (prueba
      explícita sobre el log).
- [ ] Dado un código vencido, cuando se verifica, entonces se rechaza.
- [ ] Dados dos pedidos seguidos, entonces solo el último código sirve.
- [ ] Dado el máximo de intentos fallidos, entonces el código se invalida
      aunque después llegue el correcto.
- [ ] Dado un pedido antes de la espera mínima, o por encima del tope por
      celular o por IP, entonces se rechaza sin enviar SMS.
- [ ] Dado un código verificado, cuando se usa otra vez, entonces se
      rechaza (un solo uso).
- [ ] Dado un código de un propósito, cuando se usa para el otro,
      entonces se rechaza.
- [ ] Las credenciales del proveedor van solo por `.env`; `.env.example`
      tiene un placeholder evidente.

## Fixtures

`tests/Fixtures/otp-escenarios.json` (nuevo, en inglés): largo y vida del
código, intentos, espera entre envíos, topes por celular y por IP, y
escenarios de envío, verificación, vencimiento, reenvío y abuso. Los
valores no confirmados van con `"_assumption": true`.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **`codigos_verificacion`** (tabla nueva): `id`, `proposito`, `celular`
  (normalizado), `codigo_hash`, `intentos`, `vence_at`, `usado_at`,
  `invalidado_at`, `ip`, `created_at`. Índice `(celular, proposito)`. El
  hash es un HMAC-SHA256 con la clave de la aplicación; el código nunca se
  guarda.
- **Envío** (`app/Sms/`): interfaz `SmsSender` con `send(celular,
mensaje)`. Implementaciones:
  - `array`: para pruebas; guarda los mensajes en memoria.
  - `log`: solo en el entorno `local`, para probar el flujo a mano; se
    niega a funcionar en cualquier otro entorno. En `local` el mensaje
    completo va al log de desarrollo (ver Decisiones).
  - La del proveedor elegido por el cliente (Inalambria, smsmasivo o
    Háblame): cliente HTTP con sus credenciales por `.env`. Mientras el
    cliente no elija, queda un placeholder con `TODO(cliente)`.
  - Configuración en `config/sms.php` (`driver`, credenciales y remitente).
- **Actions** (`app/Actions/Verificacion/`, invocables):
  - `SendVerificationCode`: aplica la espera y los topes (`RateLimiter` de
    Laravel), invalida el código anterior, guarda el hash y envía.
  - `VerifyCode`: compara en tiempo constante, cuenta intentos, marca el
    uso. E8-02 la llama dentro de su transacción.
- **Hora**: la pone la aplicación (`now()`, `America/Bogota`), regla 3.13.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-22); el revisor las aprueba con
esta spec.

1. Código de **6 dígitos** que vence a los **5 minutos** (supuesto).
2. **5 intentos** por código (supuesto).
3. **60 segundos** entre envíos al mismo celular, máximo **3 envíos por
   hora por celular** y **10 por hora por IP** (supuesto; controlan el
   costo de los SMS).
4. El driver `log` existe solo para el entorno `local` y ahí sí escribe el
   mensaje con el código, para poder probar el registro a mano. En
   cualquier otro entorno no se puede seleccionar. En pruebas se usa
   `array`.
5. El proveedor real se implementa cuando el cliente elija y entregue las
   credenciales; hasta entonces el criterio "dos implementaciones" se
   cumple con `array` y un esqueleto del proveedor con `TODO(cliente)`.

## Reglas duras de CLAUDE.md que aplican

- §8 Fuera de alcance: este feature es un cambio de alcance aceptado (#1)
- 3.13 Código agnóstico del motor; la hora la pone la aplicación
- 3.9 `America/Bogota` para la vida del código

## Depende de

Nada para construirse. Lo usa E8-02 (#18, registro y recuperar PIN). Para
enviar SMS reales: proveedor y credenciales del cliente.

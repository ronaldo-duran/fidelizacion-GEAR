# API pública del Club Aponte Rivera

Guía para el proveedor del punto de venta (POS) de un comercio del grupo. La
referencia completa, con cada campo, error y ejemplo, está en
[`openapi.yaml`](openapi.yaml) (OpenAPI 3.1).

## Qué se puede hacer

| Operación                                             | Para qué                                          | Permiso                                                               |
| ----------------------------------------------------- | ------------------------------------------------- | --------------------------------------------------------------------- |
| `GET /clientes?tipo_documento=&documento=`            | Saber si alguien es cliente y su nivel            | `clientes:leer`                                                       |
| `GET /clientes/beneficios?tipo_documento=&documento=` | Los beneficios del comercio para ese cliente      | `clientes:leer`                                                       |
| `POST /compras`                                       | Registrar una compra; el cliente suma sus puntos  | `compras:registrar`                                                   |
| `GET /compras/{referencia_externa}`                   | Consultar una compra                              | `compras:registrar`                                                   |
| `POST /compras/{referencia_externa}/anulacion`        | Anular una compra (devolución)                    | `compras:anular`                                                      |
| `GET /codigos/{codigo}`                               | Validar el código de un beneficio sin gastarlo    | `beneficios:redimir`                                                  |
| `POST /redenciones`                                   | Entregar un beneficio, por código o por documento | `beneficios:redimir` y, por documento, `beneficios:redimir_documento` |

Todas las rutas cuelgan de `https://{dominio}/api/v1` y responden JSON.

Los puntos solo sirven para subir de nivel: no se canjean ni se gastan.
Entregar un beneficio no descuenta puntos.

## La credencial

El administrador del comercio la crea en el panel, en **Comercios ›
Credenciales API**:

- Cada credencial es de **una sede**. Todo lo que hace queda en esa sede. Si
  el comercio tiene varias cajas o sedes, se crea una credencial por cada una.
- Al crearla se eligen sus permisos (la tabla de arriba).
- El token se muestra **una sola vez**. Guárdalo en un lugar seguro del POS;
  nadie lo puede volver a ver.

Cada petición lleva el token en el header:

```
Authorization: Bearer 12|clubar_…
Accept: application/json
```

- **Rotar** el token (en el panel) emite uno nuevo. El viejo sigue sirviendo
  24 horas para que el POS cambie sin cortar ventas.
- **Revocar** corta el token de inmediato.

## Reintentos sin duplicar: `Idempotency-Key`

Todo `POST` exige el header `Idempotency-Key` con un UUID nuevo por
operación:

```
Idempotency-Key: 3f1c2b9e-6a1d-4f7e-9c2a-1b8d7e6f5a40
```

Si la red falla y no sabes si la operación llegó, **repítela igual, con el
mismo UUID y los mismos datos**. El programa nunca la cuenta dos veces:

- Si ya se había hecho, responde `200` (en vez de `201`) con el header
  `Idempotent-Replayed: true` y el mismo recurso. No otorga puntos de nuevo
  ni entrega otra vez el beneficio.
- En las compras lo que manda es la `referencia_externa` (el número de
  factura del POS): la misma referencia con otro documento u otro monto
  responde `409 compra_en_conflicto`.
- En las entregas la llave es la referencia de la redención: la misma llave
  con otro beneficio, otro cliente, otro código, otro monto de la cuenta u otro
  soporte responde
  `409 redencion_en_conflicto`.

Sin el header, o con algo que no es un UUID, la API responde `400` y no hace
nada.

## Montos, documentos y fechas

- Los montos son **pesos enteros**, como número JSON: `45000`. No se acepta
  `"45000"` (texto) ni `45000.0` (con decimales).
- El cliente se identifica por **tipo y número de documento**: `CC`, `CE`,
  `PPT`, `PA` o `DEX`. El mismo número con otro tipo es otra persona. El
  número se compara en mayúsculas y sin espacios.
- Una compra de un documento que todavía no es cliente queda `pendiente`; los
  puntos se le acreditan cuando se registre en el programa.
- Las fechas salen en ISO 8601 con la zona de Bogotá:
  `2026-11-06T15:00:00-05:00`.

## Entregar un beneficio

1. El cliente muestra el código en su celular. El POS lee el QR o el cajero
   digita el código corto (`BBC-4821`).
2. `GET /codigos/{codigo}` dice si es `valido`, `ya_usado` o `no_valido`, sin
   gastarlo.
3. `POST /redenciones` con `{"codigo": "…"}` lo entrega. Si el beneficio es un
   porcentaje, se envía también `monto_cuenta_cop` (el total de la cuenta) y
   el descuento es ese porcentaje redondeado hacia abajo. Si el beneficio pide
   soporte, se envía `soporte` (número de contrato o de factura).

Sin código, se puede entregar por documento con `tipo_documento`, `documento`
y `beneficio_id` (de `GET /clientes/beneficios`). Esta vía:

- Necesita además el permiso `beneficios:redimir_documento`. El comercio lo
  activa a propósito en la credencial; sin él, la respuesta es 403
  `sin_permiso`.
- Es para que el cajero **lea** el número del QR o de la cédula digital que
  el cliente presenta en ese momento. **Nunca un número dictado.** Sin código,
  la presencia del cliente es el único control, y verificarla es
  responsabilidad del comercio.

## Errores

Todo error responde JSON con la misma forma:

```json
{
  "error": {
    "codigo": "compra_en_conflicto",
    "mensaje": "Ya existe otra compra con esa referencia.",
    "campos": {}
  }
}
```

El POS debe leer `codigo`, que no cambia. `mensaje` está en español para
mostrar o registrar. En un `422` de datos, `campos` dice qué campo falló.

| Estado | Códigos                                                               | Qué hacer                                        |
| ------ | --------------------------------------------------------------------- | ------------------------------------------------ |
| 400    | `idempotency_key_requerida`, `idempotency_key_invalida`               | Enviar un UUID en `Idempotency-Key`              |
| 401    | `no_autenticado`                                                      | Revisar el token; pedir uno nuevo si se revocó   |
| 403    | `sin_permiso`, `comercio_inactivo`, `sede_inactiva`                   | Pedir al administrador el permiso o la sede      |
| 404    | `cliente_no_registrado`, `compra_no_encontrada`, `ruta_no_encontrada` | Revisar el documento, la referencia o la ruta    |
| 409    | `compra_en_conflicto`, `redencion_en_conflicto`                       | No reintentar: es otra operación con la misma ID |
| 422    | `datos_invalidos`, `fuera_de_plazo`, `ya_usado`, `sin_cupos`, …       | Corregir los datos o avisar al cajero            |
| 429    | `demasiadas_peticiones`                                               | Esperar los segundos de `Retry-After`            |
| 500    | `error_interno`                                                       | Reintentar con la misma `Idempotency-Key`        |

## Topes

Cada credencial puede hacer hasta 120 peticiones por minuto, y hasta 60
consultas por documento por minuto (`GET /clientes` y
`GET /clientes/beneficios`). Pasado el tope, la API responde `429` con
`Retry-After`.

## Anular una compra

`POST /compras/{referencia_externa}/anulacion` con `{"motivo": "…"}`:

- Solo anula compras registradas por la API en el mismo comercio. Las de la
  caja se anulan en la caja o en el panel.
- El plazo es de 30 días desde la compra. Después responde
  `422 fuera_de_plazo`.
- Anular dos veces responde `200` con `Idempotent-Replayed: true`, sin
  descontar dos veces.

## Versiones

La versión va en la ruta (`/api/v1`). En `v1` solo se agregan campos o
rutas; si algo tiene que cambiar o desaparecer, sale como `v2` y `v1` sigue
funcionando mientras los POS migran.

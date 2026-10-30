# E10-06 · Panel: clientes, libro de puntos y transacciones

**Épica:** E10 · **Horas estimadas:** 10 · **Riesgo:** alto

## Objetivo

El administrador consulta un cliente con su saldo, su nivel y cada
movimiento de puntos con la regla que lo generó, revisa las compras de los
comercios y corrige puntos con un ajuste manual o anula una compra, sin
depender de un programador.

## Fuera de alcance

- Crear o editar clientes desde el panel: el cliente se registra en la PWA
  (E8-02). Corregir un documento o un celular es una decisión aparte.
- Registrar compras desde el panel: caja (E4-02), carga masiva (E4-03) y
  API (E9-01).
- Reportes exportables (E10-02) y el historial del cliente en la PWA
  (E8-05).
- Redenciones de beneficios (E5-02). Cuando existan, se agregan como otra
  pestaña del cliente en ese issue.
- Bajar el nivel por un ajuste negativo: eso solo lo hace el proceso
  nocturno (E3-05, regla dura 3.6).

## Reglas de negocio

1. **Clientes, solo lectura.** El recurso no crea, no edita y no borra
   clientes. Lista con documento (tipo + número), nombre, celular, nivel
   efectivo, saldo y fecha de registro.
2. **Búsqueda.** Por tipo + número de documento (CLAUDE.md 3.10): el número
   se normaliza con `Cliente::normalizeDocumento` antes de buscar, así que
   espacios y minúsculas encuentran al mismo cliente, y el tipo es un filtro
   con `CC` primero. También por nombre (sin distinguir mayúsculas) o por
   celular.
3. **Ficha del cliente.** Muestra documento, nombre, celular y si está
   verificado, fecha de registro, saldo y nivel efectivo con su fecha de
   inicio. Las **tres fuentes del nivel van por separado** (regla dura
   3.5): por puntos; por membresía con su vigencia; por evento con su
   vigencia. También la protección (`proteccion_hasta`) si la hay. Una
   fuente vencida se muestra como vencida, no se oculta.
4. **Libro de puntos del cliente** (pestaña de la ficha). Cada movimiento,
   del más reciente al más viejo, con fecha, tipo, puntos (+/−), comercio,
   compra (referencia), **regla y versión aplicadas** (regla dura 3.3),
   vigencia del lote, motivo y usuario. Un `ajuste_manual` no tiene regla y
   lo dice ("Ajuste manual"). **Sin acciones de editar ni borrar** (regla
   dura 3.4): ni en la tabla, ni en la política, ni por URL.
5. **Saldo y libro cuadran.** Para el administrador del grupo, la suma de
   todos los movimientos del cliente es igual al saldo mostrado (las
   caducidades ya están en el libro como movimientos negativos).
6. **Historial de cambios de nivel** (pestaña de la ficha): fecha, nivel
   anterior, nivel nuevo y motivo, del más reciente al más viejo. Solo
   lectura.
7. **Ajuste manual.** Acción en la ficha del cliente: pide los puntos
   (entero distinto de cero, positivo o negativo) y el motivo (obligatorio).
   Solo la ve y la puede ejecutar el **administrador del grupo** (política
   de E3-02); a cualquier otro rol no le aparece, y si la intenta el
   servidor responde 403. La escritura va por una Action nueva,
   `ApplyAjusteManual`, que en una sola transacción:
   - inserta el movimiento con `RecordAjusteManual` (E3-02), con el usuario
     y el motivo;
   - si los puntos son positivos, evalúa el ascenso con
     `RaiseNivelEfectivo` y un motivo nuevo, `ascenso_ajuste` (regla dura
     3.6: el cliente ve su nivel nuevo al instante);
   - si son negativos, no toca el nivel: el saldo baja de inmediato y el
     nivel lo reevalúa el proceso nocturno.

   Un ajuste negativo que deja el saldo por debajo de cero se rechaza con el
   mensaje del libro, sin escribir nada.

8. **Transacciones, solo lectura** salvo la anulación. Lista con fecha,
   comercio, sede, cliente (o el documento si está pendiente), monto en
   pesos, estado (acreditada / pendiente / anulada), canal, referencia y
   quién la registró, de la más reciente a la más vieja. Filtros: periodo
   (desde y hasta, en `America/Bogota`), comercio, sede y estado.
9. **Anular desde el panel.** Acción "Anular" en una transacción que no
   está anulada, con un motivo obligatorio, que llama a `AnnulCompra`
   (E4-01). La política de E4-01 decide quién (el administrador del grupo,
   o el del comercio de la compra) y el plazo de reversa de E3-02 decide
   hasta cuándo (`puntos.reversal_window_days`). Fuera de plazo o de otro
   comercio, la acción no aparece, y si llega igual al servidor se rechaza.
10. **Alcance por rol** (E2-01):
    - **Administrador del grupo**: todos los clientes, movimientos y
      transacciones.
    - **Administrador de comercio**: solo los clientes que tienen al menos
      una transacción en su comercio, en cualquier estado; en el libro, solo
      los movimientos de su comercio (sin ajustes manuales ni los de otros
      comercios); solo las transacciones de su comercio. El saldo y el nivel
      del cliente sí los ve completos: los necesita para entregar
      beneficios. El filtro de comercio no le aparece.
    - El cajero no entra al panel (E2-01).
11. **Sin lógica en los recursos.** Las lecturas usan consultas con sus
    relaciones cargadas por adelantado (sin N+1). Las escrituras pasan solo
    por `ApplyAjusteManual` y `AnnulCompra`. El alcance por rol vive en una
    clase de consulta, no repetido en cada tabla.

## Criterios de aceptación

- [ ] Dado un cliente CC 1023456789, cuando se busca "1023 456 789" con
      tipo CC, entonces aparece; y con tipo CE no aparece.
- [ ] Dado un documento PPT con letras, cuando se busca en minúsculas y con
      espacios, entonces aparece el cliente.
- [ ] Dado un nombre, cuando se busca en minúsculas, entonces aparece el
      cliente (en PostgreSQL y en SQL Server).
- [ ] Dada la ficha de un cliente con membresía vencida y evento vigente,
      entonces se ven las tres fuentes por separado, cada una con su
      vigencia, y la membresía marcada como vencida.
- [ ] Dado el libro de un cliente, entonces cada movimiento muestra su
      regla y versión, y el ajuste manual muestra "Ajuste manual", el
      motivo y el usuario.
- [ ] Dado el libro, entonces no hay acción de crear, editar ni borrar, y
      la política responde que no a `update` y `delete`.
- [ ] Dado cualquier escenario del fixture, entonces la suma de los
      movimientos del cliente es igual al saldo mostrado.
- [ ] Dado un ajuste manual sin motivo o de cero puntos, entonces se
      rechaza y no se escribe nada.
- [ ] Dado un ajuste positivo que cruza un umbral, entonces inserta un
      `ajuste_manual` con el usuario y el motivo, actualiza el saldo y sube
      el nivel en la misma petición, con su `cambios_nivel`
      (`ascenso_ajuste`).
- [ ] Dado un ajuste negativo que baja el saldo por debajo de un umbral,
      entonces el saldo baja y el nivel no cambia.
- [ ] Dado un ajuste negativo mayor que el saldo, entonces se rechaza y no
      se escribe nada.
- [ ] Dado un administrador de comercio, entonces no ve la acción de ajuste
      y recibe 403 si la llama directamente.
- [ ] Dado un administrador de comercio, entonces no ve clientes sin
      compras en su comercio, ni movimientos o transacciones de otro
      comercio, ni siquiera abriendo la URL de la ficha.
- [ ] Dada una transacción de su comercio dentro del plazo, cuando el
      administrador de comercio la anula con motivo, entonces queda anulada
      y el libro tiene la reversa.
- [ ] Dada una transacción fuera de plazo, o de otro comercio, entonces la
      acción "Anular" no aparece y llamarla se rechaza.
- [ ] Dada una transacción ya anulada, entonces la acción no aparece.
- [ ] Dada la lista de clientes, la ficha y la lista de transacciones,
      entonces el número de consultas no crece con el número de filas.

## Fixtures

`tests/Fixtures/panel-clientes-escenarios.json` (nuevo, en inglés): las
búsquedas que deben encontrar o no a un cliente, los clientes con sus
movimientos, fuentes de nivel y compras por comercio, qué ve cada rol, los
ajustes (válidos y rechazados) con el saldo y el nivel esperados, y las
anulaciones (en plazo, fuera de plazo, de otro comercio, ya anulada). El
plazo de reversa no se repite: sale de `movimientos-escenarios.json` (E3-02),
y los umbrales de nivel de `reglas-validas.json`.

## Datos y diseño (decisiones técnicas, pendientes de revisión)

- **Sin tablas nuevas.** Solo un caso nuevo en `MotivoCambioNivel`:
  `ascenso_ajuste` ("Ascenso por ajuste manual"). La columna
  `cambios_nivel.motivo` ya admite 30 caracteres.
- **Recurso `ClienteResource`** (grupo "Clientes" de la navegación de 1z):
  página de lista y página de ficha (`ViewRecord`) con un infolist para los
  datos y las tres fuentes, y dos relation managers de solo lectura:
  `MovimientosRelationManager` y `CambiosNivelRelationManager`. Sin páginas
  de crear ni editar.
- **Recurso `TransaccionResource`** (grupo "Transacciones"): solo lista, con
  la acción "Anular" por fila.
- **Políticas**: `ClientePolicy` (`viewAny` y `view` con el alcance de la
  regla 10; `create`, `update`, `delete` y `deleteAny` en falso). En
  `MovimientoPuntosPolicy` y `TransaccionPolicy` se agregan `viewAny` y
  `view` con el mismo alcance, y `update` y `delete` en falso. El ajuste y
  la anulación siguen con sus políticas de E3-02 y E4-01, sin cambios.
- **Alcance** (`app/Panel/AlcancePanel.php`): recibe el usuario y aplica el
  filtro de la regla 10 a las consultas de clientes, movimientos y
  transacciones. Lo usan los recursos (`getEloquentQuery`) y las políticas.
- **Búsqueda por nombre**: `LOWER(nombre_completo) LIKE ?` con el término en
  minúsculas, que es SQL estándar y funciona igual en los dos motores (regla
  dura 3.13). Sin `ILIKE`.
- **Anular visible**: una consulta pequeña decide si la compra sigue en
  plazo (misma cuenta que `ReverseTransaccion`) para ocultar la acción; el
  rechazo real sigue en `AnnulCompra`.
- **`ApplyAjusteManual`** (`app/Actions/Puntos/`): envuelve
  `RecordAjusteManual` y `RaiseNivelEfectivo` en un `DB::transaction()`,
  igual que `RegisterCompra`. `RecordAjusteManual` no cambia.

## Decisiones a confirmar en la revisión

Propuestas por el implementador (2026-10-29); el revisor las aprueba con
esta spec.

1. Un ajuste positivo sube el nivel al instante con un motivo nuevo,
   `ascenso_ajuste`. Un ajuste negativo no lo baja: lo hace el proceso
   nocturno.
2. El ajuste manual no tiene tope de puntos: solo lo hace el administrador
   del grupo, con motivo y usuario registrados.
3. El administrador de comercio ve los clientes que tienen al menos una
   transacción en su comercio, en cualquier estado (también anulada).
4. El administrador de comercio ve el saldo y el nivel completos del
   cliente, pero en el libro solo los movimientos de su comercio. Por eso el
   criterio "saldo y libro cuadran" aplica solo al administrador del grupo.
5. Anular desde el panel pide un motivo escrito (el cajero usa uno fijo,
   E4-02).
6. Una compra pendiente (sin puntos) se puede anular sin plazo, como ya
   hace `AnnulCompra`: no hay nada que revertir en el libro.
7. El historial de cambios de nivel lo ven los dos roles de administrador.

## Reglas duras de CLAUDE.md que aplican

- 3.1 Los puntos son estatus: el ajuste corrige el nivel, no da un saldo
  canjeable. Nada de "canjear" en la interfaz.
- 3.3 Cada movimiento muestra la regla y la versión con que se calculó.
- 3.4 El libro es de solo lectura: el ajuste y la reversa insertan
  movimientos nuevos.
- 3.5 Las tres fuentes del nivel se muestran por separado.
- 3.6 Ascenso en la misma petición del ajuste; nunca un descenso fuera del
  proceso nocturno.
- 3.9 Montos en pesos enteros; periodos en `America/Bogota`.
- 3.10 Búsqueda por tipo + número normalizado, con `CC` primero.
- 3.13 Búsqueda y filtros con el query builder y SQL estándar.

## Notas de interfaz

- Skill `club-ar-ui`: Filament con los tokens de la marca. Puntos con signo
  y color (`success` los positivos, `danger` los negativos), montos con
  formato colombiano (`$85.000`) y el nivel como pastilla con su color.
- Textos: "Clientes", "Transacciones", "Libro de puntos", "Cambios de
  nivel", "Ajustar puntos", "Motivo del ajuste", "Anular", "Motivo de la
  anulación", "Ajuste manual", "Vencida".
- Pantallas de escritorio (1024 px); el panel no se diseña para celular.

## Depende de

E10-01 (navegación y tema), E3-02 (libro y ajuste manual), E3-03 (nivel
efectivo) y E4-01 (compras y anulación), todos en `main`.

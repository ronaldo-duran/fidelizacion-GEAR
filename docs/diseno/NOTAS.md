# Notas sobre el handoff de diseño

El handoff es alta fidelidad, pero se hizo antes de cerrar el acta. Estos
puntos chocan con el contrato o dependen de decisiones del cliente. Ante
la duda, manda el acta y `CLAUDE.md`.

| Pantalla | Qué muestra el diseño | Qué hacer |
|---|---|---|
| Perfil (1t) | "Avisos por WhatsApp" con toggles | WhatsApp Business está **fuera de alcance**. Proponer "Avisos por correo" y confirmarlo con el cliente. No construir nada contra WhatsApp. |
| Suscripción (1s) | "Tarjeta de crédito, débito o PSE" | Depende de la pasarela elegida. La renovación es por enlace de pago: **no** guardar tarjeta ni cobrar automático. |
| Todas | Logo provisional "AR" | El diseño de marca está fuera de alcance; el cliente entrega el logo. Solo cambia `LogoAR` y `--color-brand`. |
| PWA | No hay pantalla de ingreso del cliente | Decisión pendiente: cómo inicia sesión el cliente sin OTP (ver issue de decisiones). |
| Inicio, niveles, historial | 1 punto por $1.000, Plata 500 / Oro 1.500 / Diamante 3.000, 12 meses | Son **supuestos**. Los números viven en `tests/Fixtures/` y en las reglas configurables, nunca en las vistas. |
| Suscripción (1s) | $49.900 al mes / $499.000 al año, solo Diamante | Supuesto. Precio y planes salen de la configuración de membresías. |
| Tablero (1z) | Nombres de comercios y cifras | Son de ejemplo. |

`PROMPT.md` es el prompt original del diseñador. El orden real de trabajo
está en los issues y en `docs/plan-de-trabajo.md`.

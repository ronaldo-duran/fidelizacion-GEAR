#!/usr/bin/env bash
# Crea o actualiza etiquetas y milestones del proyecto. Es idempotente:
# se puede correr varias veces y solo corrige colores, textos y fechas.
# Uso:  ./scripts/setup-github.sh ronaldo-duran/fidelizacion-GEAR
set -euo pipefail
REPO="${1:?Uso: ./scripts/setup-github.sh usuario/repositorio}"

etiqueta () { gh label create "$1" --repo "$REPO" --color "$2" --description "$3" --force; }

# Épicas
etiqueta "epica:E1"  "1D76DB" "Fundación e infraestructura"
etiqueta "epica:E2"  "1D76DB" "Cuentas, roles y comercios"
etiqueta "epica:E3"  "1D76DB" "Motor de reglas, puntos y niveles"
etiqueta "epica:E4"  "1D76DB" "Operación en caja"
etiqueta "epica:E5"  "1D76DB" "Beneficios y redención"
etiqueta "epica:E6"  "1D76DB" "Membresías y pagos"
etiqueta "epica:E7"  "1D76DB" "Notificaciones y correo"
etiqueta "epica:E8"  "1D76DB" "Aplicación del cliente (PWA)"
etiqueta "epica:E9"  "1D76DB" "API pública para comercios"
etiqueta "epica:E10" "1D76DB" "Panel, reportes, seguridad y entrega"

# Semanas del plan interno (docs/plan-de-trabajo.md)
for n in 1 2 3 4 5 6; do
  etiqueta "semana:$n" "C5DEF5" "Semana $n del plan de trabajo"
done

etiqueta "tipo:feature" "0E8A16" "Funcionalidad nueva"
etiqueta "tipo:bug"     "D73A4A" "Defecto"
etiqueta "tipo:infra"   "5319E7" "Infraestructura y CI"
etiqueta "tipo:spec"    "7C8B9B" "Redacción de especificación"
etiqueta "tipo:docs"    "0075CA" "Documentación y capacitación"
etiqueta "riesgo:alto"  "9A3B2C" "Dinero real o costoso de revertir. Lo toma el responsable"
etiqueta "riesgo:medio" "E99695" "Requiere revisión cuidadosa"
etiqueta "decision-cliente" "FBCA04" "Necesita una definición del cliente"
etiqueta "bloqueado"    "000000" "Esperando decisión o insumo externo"
etiqueta "obsequio"     "B08422" "Trabajo sin costo, fuera de las 460 horas"
etiqueta "sin-version"  "BFD4F2" "El PR no necesita subir la versión"
etiqueta "pr-grande"    "F9D0C4" "PR por encima de 800 líneas de producción, con razón explicada"

# Milestones = hitos de pago, con las fechas del plan interno.
# Si ya existe uno que empiece por "Hito N", se actualiza en lugar de duplicarlo.
hito () {
  local prefijo="$1" titulo="$2" descripcion="$3" fecha="$4" numero
  numero=$(gh api "repos/$REPO/milestones?state=all&per_page=100" \
    --jq ".[] | select(.title | startswith(\"$prefijo\")) | .number" | head -1)
  if [ -n "$numero" ]; then
    gh api -X PATCH "repos/$REPO/milestones/$numero" \
      -f title="$titulo" -f description="$descripcion" -f due_on="$fecha" >/dev/null
    echo "Actualizado: $titulo (#$numero)"
  else
    gh api "repos/$REPO/milestones" \
      -f title="$titulo" -f description="$descripcion" -f due_on="$fecha" >/dev/null
    echo "Creado: $titulo"
  fi
}

hito "Hito 2" "Hito 2 — Motor de reglas y panel" \
  "Motor de reglas configurable y panel administrativo funcionando" "2026-10-23T23:59:59Z"
hito "Hito 3" "Hito 3 — Operación, membresías y comunicaciones" \
  "Compras, beneficios y redención; membresías con pago en línea, notificaciones y email marketing" "2026-11-06T23:59:59Z"
hito "Hito 4" "Hito 4 — PWA, API y entrega" \
  "PWA del cliente, API pública, producción, documentación y capacitación" "2026-11-20T23:59:59Z"

echo "Listo."

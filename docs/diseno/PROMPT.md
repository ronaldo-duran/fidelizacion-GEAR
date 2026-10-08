# Prompt inicial para Claude Code

Copia y pega esto en Claude Code desde la raíz del repositorio, con la carpeta `design_handoff_club_aponte_rivera/` ya dentro del proyecto (y `.claude/skills/club-ar-ui/` movida a la raíz del repo).

---

Vas a implementar la identidad visual y las pantallas del **Club Aponte Rivera**, el programa de fidelización del Grupo Aponte Rivera (15 comercios: restaurantes, heladerías, cervecerías, el Colegio Semillitas del Futuro y la Inmobiliaria Laura Rivera).

Referencias en `design_handoff_club_aponte_rivera/`:
- `README.md`: visión general, pantallas y comportamiento.
- `tokens/tokens.css`, `tokens/tokens.json`, `tokens/tailwind.preset.js`: tokens de diseño (fuente de verdad).
- `COMPONENTS.md`: especificación de cada componente con medidas exactas.
- `reference/*.dc.html`: mockups en HTML. Son referencia visual, no código para copiar. Ábrelos en el navegador.
- Skill `club-ar-ui`: reglas de UI que debes seguir siempre.

Haz esto en orden y para al final de cada paso para que lo revise:

1. Revisa el stack actual del repo (Laravel / Filament / frontend de la PWA) y propón dónde viven los tokens y los componentes. No instales librerías nuevas sin preguntar.
2. Integra los tokens: `tokens.css` global, preset de Tailwind en la PWA y en el tema personalizado de Filament (color primario = `brand`, fuentes Figtree y Bricolage Grotesque).
3. Crea los componentes base de `COMPONENTS.md` → "Primitivas compartidas", con una página interna de muestra (`/_ui`) que los muestre en todos sus estados y en los 4 niveles.
4. Implementa el flujo del cajero (pantalla 8): ModeSwitch, AmountDisplay, Keypad, IdentifyActions, Scanner y ResultScreen. Optimiza para velocidad y para una mano.
5. Implementa las pantallas del cliente en este orden: Registro (1), Inicio (2), Catálogo (3), Uso del beneficio (4), Historial (5), Niveles y suscripción (6), Perfil (7).
6. Ajusta el panel de Filament: login (10) y tablero del grupo (9).

Reglas: usa solo tokens (nada de hex sueltos), zonas táctiles de 44 px (64 px en el cajero), texto AA, textos exactos de la referencia, español de Colombia. Cuando algo de la referencia choque con una restricción técnica, avísame antes de improvisar.

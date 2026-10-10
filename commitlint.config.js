// Conventional Commits: tipo(alcance opcional): descripción en minúscula.
// Ejemplos: "feat(reglas): versiona las reglas al guardar", "fix(cajero): evita doble registro".
export default {
    extends: ['@commitlint/config-conventional'],
    rules: {
        // El cuerpo y el pie pueden llevar enlaces o descripciones de PR con líneas largas.
        'body-max-line-length': [0],
        'footer-max-line-length': [0],
    },
};

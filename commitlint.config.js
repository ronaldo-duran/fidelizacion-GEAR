// Conventional Commits: tipo(alcance opcional): descripción en minúscula.
// Ejemplos: "feat(reglas): versiona las reglas al guardar", "fix(cajero): evita doble registro".
export default {
    extends: ['@commitlint/config-conventional'],
    rules: {
        // Con squash merge el cuerpo del commit puede ser la descripción del PR, con líneas largas y enlaces.
        'body-max-line-length': [0],
        'footer-max-line-length': [0],
    },
};

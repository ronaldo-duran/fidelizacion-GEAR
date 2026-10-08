import js from '@eslint/js';
import prettier from 'eslint-config-prettier';
import globals from 'globals';

export default [
    {
        ignores: [
            'vendor/**',
            'node_modules/**',
            'public/**',
            'storage/**',
            'bootstrap/cache/**',
            'docs/diseno/**',
            'playwright-report/**',
            'test-results/**',
        ],
    },
    js.configs.recommended,
    {
        files: ['resources/js/**/*.js'],
        languageOptions: { globals: globals.browser },
    },
    {
        files: ['*.config.js', 'scripts/**/*.mjs'],
        languageOptions: { globals: globals.node },
    },
    {
        // Las pruebas E2E corren en Node pero evalúan código en el navegador.
        files: ['tests/e2e/**/*.js'],
        languageOptions: { globals: { ...globals.node, ...globals.browser } },
    },
    {
        rules: {
            curly: ['error', 'all'],
            eqeqeq: ['error', 'always'],
            'no-console': ['error', { allow: ['warn', 'error'] }],
            'no-var': 'error',
            'prefer-const': 'error',
            'prefer-template': 'error',
            'object-shorthand': 'error',
            'no-implicit-coercion': 'error',
            'no-unused-vars': ['error', { argsIgnorePattern: '^_' }],
        },
    },
    {
        // Los scripts de CI informan por consola.
        files: ['scripts/**/*.mjs'],
        rules: { 'no-console': 'off' },
    },
    prettier,
];

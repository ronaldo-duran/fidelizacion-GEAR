// Mide el tamaño de un PR contando solo código de producción.
//
// Uso: node scripts/ci/verificar-tamano.mjs <sha-base> [sha-head]
//
// - Cuenta líneas agregadas + borradas en app/, config/, database/, resources/ y routes/.
//   Pruebas, specs, docs, fixtures, locks y CI no cuentan.
// - Más de AVISO líneas: aviso, el CI sigue en verde.
// - Más de LIMITE líneas: falla, salvo que el PR tenga la etiqueta "pr-grande" (PR_GRANDE=1).
import { appendFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';

const AVISO = 400;
const LIMITE = 800;
const RUTAS_DE_PRODUCCION = [/^app\//, /^config\//, /^database\//, /^resources\//, /^routes\//];

const git = (...args) => execFileSync('git', args, { encoding: 'utf8' }).trim();

const [base, head = 'HEAD'] = process.argv.slice(2);
if (!base) {
    console.error('::error title=Tamaño del PR::Falta el commit base.');
    process.exit(1);
}

const desde = git('merge-base', base, head);
let produccion = 0;
let total = 0;
const porArchivo = [];

for (const linea of git('diff', '--numstat', desde, head).split('\n').filter(Boolean)) {
    const [agregadas, borradas, archivo] = linea.split('\t');
    // Archivos binarios aparecen con "-": no cuentan.
    const lineas = (Number.parseInt(agregadas, 10) || 0) + (Number.parseInt(borradas, 10) || 0);
    total += lineas;
    if (RUTAS_DE_PRODUCCION.some((ruta) => ruta.test(archivo))) {
        produccion += lineas;
        porArchivo.push([archivo, lineas]);
    }
}

const resumen = [
    `### Tamaño del PR`,
    `- Código de producción: **${produccion}** líneas (aviso desde ${AVISO}, límite ${LIMITE})`,
    `- Total con pruebas, docs y demás: ${total} líneas`,
    '',
    ...porArchivo
        .sort((a, b) => b[1] - a[1])
        .slice(0, 10)
        .map(([archivo, lineas]) => `  - \`${archivo}\`: ${lineas}`),
].join('\n');
console.log(resumen);
if (process.env.GITHUB_STEP_SUMMARY) {
    appendFileSync(process.env.GITHUB_STEP_SUMMARY, `${resumen}\n`);
}

if (produccion > LIMITE) {
    if (process.env.PR_GRANDE === '1') {
        console.warn(
            `::warning title=Tamaño del PR::${produccion} líneas de producción, por encima del límite de ${LIMITE}. ` +
                'Se acepta por la etiqueta pr-grande.',
        );
        process.exit(0);
    }
    console.error(
        `::error title=Tamaño del PR::${produccion} líneas de producción (límite ${LIMITE}). ` +
            'Pártelo en PRs más pequeños o agrega la etiqueta pr-grande explicando por qué en el PR.',
    );
    process.exit(1);
}

if (produccion > AVISO) {
    console.warn(
        `::warning title=Tamaño del PR::${produccion} líneas de producción. Por encima de ${AVISO} la revisión ` +
            'pierde calidad: considera partirlo.',
    );
}

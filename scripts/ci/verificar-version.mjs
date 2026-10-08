// Verifica que la versión de package.json suba cuando cambia código que llega a producción.
//
// Uso: node scripts/ci/verificar-version.mjs <sha-base> [sha-head]
//
// Excepciones:
//   - Si solo cambian rutas que no llegan a producción (docs, specs, pruebas, CI...), no exige versión.
//   - SIN_VERSION=1 (el CI lo pone cuando el PR tiene la etiqueta "sin-version").
//   - Algún commit del rango contiene "[sin-version]" en su mensaje.
import { execFileSync } from 'node:child_process';

const RUTAS_DE_PRODUCTO = [
    /^app\//,
    /^bootstrap\//,
    /^config\//,
    /^database\//,
    /^lang\//,
    /^public\//,
    /^resources\//,
    /^routes\//,
    /^composer\.(json|lock)$/,
    /^package(-lock)?\.json$/,
    /^vite\.config\.js$/,
    /^\.env\.example$/,
];
const FORMATO = /^(\d+)\.(\d+)\.(\d+)$/;

const git = (...args) => execFileSync('git', args, { encoding: 'utf8' }).trim();
const error = (mensaje) => {
    console.error(`::error title=Versión::${mensaje}`);
    process.exit(1);
};
const aviso = (mensaje) => console.warn(`::notice title=Versión::${mensaje}`);

const versionEn = (sha) => {
    try {
        return JSON.parse(git('show', `${sha}:package.json`)).version ?? '0.0.0';
    } catch {
        return '0.0.0';
    }
};
const comparar = (a, b) => {
    const [, ...x] = FORMATO.exec(a).map(Number);
    const [, ...y] = FORMATO.exec(b).map(Number);
    for (let i = 0; i < 3; i++) {
        if (x[i] !== y[i]) {
            return x[i] - y[i];
        }
    }
    return 0;
};

const [base, head = 'HEAD'] = process.argv.slice(2);
const versionNueva = versionEn(head);

if (!FORMATO.test(versionNueva)) {
    error(`"${versionNueva}" no es una versión válida. Usa tres números: MAYOR.MENOR.PARCHE (ej. 0.3.1).`);
}

if (!base || /^0+$/.test(base)) {
    aviso('Sin commit base para comparar (rama nueva). Solo se validó el formato.');
    process.exit(0);
}

let desde;
try {
    desde = git('merge-base', base, head);
} catch {
    aviso(`No se encontró el commit base ${base}. Solo se validó el formato.`);
    process.exit(0);
}

const cambiados = git('diff', '--name-only', desde, head).split('\n').filter(Boolean);
const deProducto = cambiados.filter((archivo) => RUTAS_DE_PRODUCTO.some((ruta) => ruta.test(archivo)));

if (deProducto.length === 0) {
    aviso('Solo cambian archivos que no llegan a producción: no se exige versión.');
    process.exit(0);
}

const mensajes = git('log', '--format=%B', `${desde}..${head}`);
if (process.env.SIN_VERSION === '1' || mensajes.includes('[sin-version]')) {
    aviso('Excepción explícita ([sin-version] o etiqueta sin-version): no se exige versión.');
    process.exit(0);
}

const versionAnterior = versionEn(desde);
if (comparar(versionNueva, versionAnterior) <= 0) {
    error(
        `Cambió código de producción (${deProducto.slice(0, 5).join(', ')}${deProducto.length > 5 ? '…' : ''}) ` +
            `pero la versión sigue en ${versionAnterior}. Súbela en package.json ` +
            '(npm version patch|minor|major --no-git-tag-version) o marca la excepción con [sin-version].',
    );
}

const rompe = /^[a-z]+(\(.+\))?!:|BREAKING CHANGE/m.test(mensajes);
const nueva = /^feat(\(.+\))?:/m.test(mensajes);
const sugerido = rompe ? 'major' : nueva ? 'minor' : 'patch';
console.log(
    `Versión ${versionAnterior} → ${versionNueva}. Según los commits se esperaba al menos un cambio "${sugerido}".`,
);

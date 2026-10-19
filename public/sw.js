/*
 * Service worker del Club Aponte Rivera.
 *
 * Objetivo mínimo (E8-01): que la app abra sin conexión con una página clara.
 * Estrategia: solo se precachea el casco estático (página "Sin conexión" e
 * íconos). Las navegaciones van a la red primero y, si falla, muestran la
 * página sin conexión. NO se cachean respuestas con datos del cliente.
 */
const CACHE = 'club-ar-v1';
const SIN_CONEXION = '/sin-conexion';
const CASCO = [SIN_CONEXION, '/icons/icon-192.png', '/icons/icon-512.png'];

self.addEventListener('install', (evento) => {
    evento.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(CASCO)));
    self.skipWaiting();
});

self.addEventListener('activate', (evento) => {
    evento.waitUntil(
        caches
            .keys()
            .then((claves) => Promise.all(claves.filter((clave) => clave !== CACHE).map((clave) => caches.delete(clave))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (evento) => {
    const peticion = evento.request;

    if (peticion.method !== 'GET' || peticion.mode !== 'navigate') {
        return;
    }

    evento.respondWith(fetch(peticion).catch(() => caches.match(SIN_CONEXION)));
});

import './cajero.js';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch((error) => {
            console.error('No se pudo registrar el service worker', error);
        });
    });
}

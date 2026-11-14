// Uso del beneficio en la PWA (E8-04, reglas 8 a 10).
//
// - La hora en vivo arranca con la del servidor y avanza en el teléfono, en America/Bogota,
//   para que una captura de pantalla se delate.
// - El código se renueva solo cada `refrescoMs`, y solo con la pestaña visible; al volver a
//   ella se pide uno nuevo de inmediato.
// - Sin conexión, o si el código venció sin que llegara uno nuevo, el QR y el código se ocultan:
//   nunca queda a la vista un código vencido como si sirviera.

const formatoHora = new Intl.DateTimeFormat('es-CO', {
    timeZone: 'America/Bogota',
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
    hour12: true,
});

function usoBeneficio({ servidorMs, refrescoMs }) {
    return {
        desfase: servidorMs - Date.now(),
        ahora: servidorMs,
        enLinea: navigator.onLine,
        reloj: null,
        renovacion: null,

        init() {
            this.reloj = setInterval(() => {
                this.ahora = Date.now() + this.desfase;
            }, 1000);
            this.renovacion = setInterval(() => this.renovar(), refrescoMs);
            this.alCambiarVisibilidad = () => {
                if (document.visibilityState === 'visible') {
                    this.renovar();
                }
            };
            this.alConectar = () => {
                this.enLinea = true;
                this.renovar();
            };
            this.alDesconectar = () => {
                this.enLinea = false;
            };
            document.addEventListener('visibilitychange', this.alCambiarVisibilidad);
            window.addEventListener('online', this.alConectar);
            window.addEventListener('offline', this.alDesconectar);
        },

        renovar() {
            if (document.visibilityState === 'visible' && navigator.onLine) {
                this.$wire.renovar();
            }
        },

        // El código se muestra solo con conexión y antes de su vencimiento.
        codigoVigente() {
            const vence = this.$wire.venceMs;

            return this.enLinea && vence !== null && this.ahora < vence;
        },

        hora() {
            return `Hoy · ${formatoHora.format(new Date(this.ahora))}`;
        },

        destroy() {
            clearInterval(this.reloj);
            clearInterval(this.renovacion);
            document.removeEventListener('visibilitychange', this.alCambiarVisibilidad);
            window.removeEventListener('online', this.alConectar);
            window.removeEventListener('offline', this.alDesconectar);
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('usoBeneficio', usoBeneficio);
});

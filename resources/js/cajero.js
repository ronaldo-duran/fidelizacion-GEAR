/*
 * Caja del comercio (E4-02): el teclado del monto y el lector del QR del
 * cliente. El teclado vive en el navegador para que cada tecla no espere al
 * servidor; el servidor vuelve a validar el monto al continuar.
 */

const formatoPesos = new Intl.NumberFormat('es-CO', { maximumFractionDigits: 0 });

/**
 * Teclado del monto: "000" agrega tres ceros, borrar quita el último dígito y
 * no se pasa del máximo de dígitos. Los ceros a la izquierda no cuentan. Con
 * un porcentaje (monto de la cuenta de un beneficio, E5-02) muestra el
 * descuento redondeado hacia abajo; el servidor lo vuelve a calcular.
 */
export function tecladoMonto({ maximo, porcentaje = null }) {
    return {
        digitos: '',

        get monto() {
            return Number(this.digitos || '0');
        },

        get texto() {
            return `$${formatoPesos.format(this.monto)}`;
        },

        get descuento() {
            return `$${formatoPesos.format(Math.floor((this.monto * (porcentaje ?? 0)) / 100))}`;
        },

        tecla(valor) {
            const siguiente = `${this.digitos}${valor}`.replace(/^0+/, '');

            if (siguiente.length <= maximo) {
                this.digitos = siguiente;
            }
        },

        borrar() {
            this.digitos = this.digitos.slice(0, -1);
        },
    };
}

/**
 * Lector del QR con la cámara trasera: `BarcodeDetector` donde exista (Chrome
 * en Android) y `jsqr` donde no (Safari en iPhone). Lo leído se manda tal cual
 * al servidor, que valida la firma y el vencimiento (E4-04): a `identificarQr`
 * en registrar compra y a `validarCodigo` en validar beneficio (E5-02).
 */
export function lectorQr({ accion = 'identificarQr' } = {}) {
    return {
        error: null,
        activo: false,
        flujo: null,
        detector: null,
        jsQR: null,

        async init() {
            await this.iniciar();
        },

        async iniciar() {
            this.error = null;

            if (!navigator.mediaDevices?.getUserMedia) {
                this.error = 'Este celular no deja usar la cámara aquí. Digita el código.';

                return;
            }

            try {
                this.flujo = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'environment' },
                    audio: false,
                });
            } catch {
                this.error = 'No se pudo abrir la cámara. Revisa el permiso o digita el código.';

                return;
            }

            if ('BarcodeDetector' in window) {
                this.detector = new window.BarcodeDetector({ formats: ['qr_code'] });
            } else {
                this.jsQR = (await import('jsqr')).default;
            }

            this.$refs.video.srcObject = this.flujo;
            await this.$refs.video.play();
            this.activo = true;
            this.leer();
        },

        async leer() {
            if (!this.activo) {
                return;
            }

            const texto = await this.detectar();

            if (texto) {
                this.detener();
                this.$wire[accion](texto);

                return;
            }

            setTimeout(() => this.leer(), 200);
        },

        async detectar() {
            const video = this.$refs.video;

            if (!video || video.readyState < 2) {
                return null;
            }

            if (this.detector) {
                const codigos = await this.detector.detect(video);

                return codigos[0]?.rawValue ?? null;
            }

            const lienzo = this.$refs.lienzo;
            lienzo.width = video.videoWidth;
            lienzo.height = video.videoHeight;
            const contexto = lienzo.getContext('2d', { willReadFrequently: true });
            contexto.drawImage(video, 0, 0, lienzo.width, lienzo.height);
            const imagen = contexto.getImageData(0, 0, lienzo.width, lienzo.height);

            return this.jsQR(imagen.data, imagen.width, imagen.height)?.data ?? null;
        },

        detener() {
            this.activo = false;
            this.flujo?.getTracks().forEach((pista) => pista.stop());
            this.flujo = null;
        },

        destroy() {
            this.detener();
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('tecladoMonto', tecladoMonto);
    window.Alpine.data('lectorQr', lectorQr);
});

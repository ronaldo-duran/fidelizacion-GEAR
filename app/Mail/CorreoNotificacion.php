<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\EnvioNotificacion;
use App\Notificaciones\RenderPlantilla;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * El correo de un envío (E7-01): el asunto y el cuerpo ya armados, en una
 * vista fija del sistema. El texto del administrador solo llega escapado.
 */
class CorreoNotificacion extends Mailable
{
    private readonly EnvioNotificacion $envio;

    public function __construct(EnvioNotificacion $envio)
    {
        $this->envio = $envio;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->envio->asunto);
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.notificacion',
            text: 'mail.notificacion-texto',
            with: [
                'cuerpoHtml' => app(RenderPlantilla::class)->html($this->envio->cuerpo),
                'cuerpo' => $this->envio->cuerpo,
            ],
        );
    }
}

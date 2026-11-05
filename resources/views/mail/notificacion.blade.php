{{-- Correo de un aviso (E7-01). $cuerpoHtml ya viene escapado de RenderPlantilla::html. --}}
<!DOCTYPE html>
<html lang="es-CO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
</head>
<body style="margin: 0; padding: 24px 16px; font-family: Arial, Helvetica, sans-serif; font-size: 16px; line-height: 1.5;">
    <div style="max-width: 560px; margin: 0 auto;">
        <p style="margin: 0 0 24px; font-size: 14px; font-weight: bold;">Club Aponte Rivera</p>

        {!! $cuerpoHtml !!}

        <p style="margin: 32px 0 0; font-size: 13px;">Recibes este correo porque eres parte del Club Aponte Rivera.</p>
    </div>
</body>
</html>

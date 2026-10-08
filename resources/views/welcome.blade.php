<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#faf7f2">

        <title>{{ config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-dvh">
        {{-- Página provisional hasta que exista la PWA del cliente (épica E8). --}}
        <main class="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-6 p-6">
            <div class="flex items-center gap-3">
                <span class="flex size-10 items-center justify-center rounded-sm bg-ink font-display text-sm text-paper">AR</span>
                <span class="text-lg">Club Aponte Rivera</span>
            </div>

            <h1 class="font-display text-2xl">Muy pronto vas a sumar puntos en todos los comercios del grupo.</h1>

            <div class="flex gap-1.5" aria-hidden="true">
                <span class="h-2 flex-1 rounded-full bg-tier-bronce-bg"></span>
                <span class="h-2 flex-1 rounded-full bg-tier-plata-bg"></span>
                <span class="h-2 flex-1 rounded-full bg-tier-oro-bg"></span>
                <span class="h-2 flex-1 rounded-full bg-tier-diamante-bg"></span>
            </div>

            <a href="{{ url('/admin') }}" class="flex h-btn items-center justify-center rounded-md bg-brand text-lg text-white">
                Ingreso al sistema
            </a>
        </main>
    </body>
</html>

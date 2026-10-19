@props([
    'title' => null,
    'theme' => '#faf7f2',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="{{ $theme }}">

        <title>{{ $title ? $title . ' · ' . config('app.name') : config('app.name') }}</title>

        <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
        <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
        <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="Club AR">
        <meta name="mobile-web-app-capable" content="yes">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body {{ $attributes->merge(['class' => 'min-h-dvh']) }}>
        {{ $slot }}
    </body>
</html>

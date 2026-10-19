@props(['title' => null])

<x-layouts.app :title="$title" theme="#faf7f2" class="min-h-dvh bg-paper text-ink">
    <div class="mx-auto flex min-h-dvh w-full max-w-md flex-col">
        {{ $slot }}
    </div>
</x-layouts.app>

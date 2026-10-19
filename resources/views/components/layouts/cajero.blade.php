@props(['title' => null])

<x-layouts.app :title="$title" theme="#ffffff" class="min-h-dvh bg-surface text-cashier-ink">
    <div class="mx-auto flex min-h-dvh w-full max-w-md flex-col">
        {{ $slot }}
    </div>
</x-layouts.app>

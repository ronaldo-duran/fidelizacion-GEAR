@props(['activo' => false])

<button
    type="button"
    class="flex h-11 shrink-0 items-center rounded-full px-4 font-sans text-sm {{ $activo ? 'bg-ink font-bold text-white' : 'border border-field font-semibold text-ink' }}"
>
    {{ $slot }}
</button>

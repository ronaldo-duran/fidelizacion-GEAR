@props(['name' => null, 'checked' => false])

<label class="flex min-h-touch items-start gap-3">
    <input
        type="checkbox"
        @if ($name) name="{{ $name }}" @endif
        @checked($checked)
        {{ $attributes->whereStartsWith('wire:') }}
        class="peer sr-only"
    >
    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-xs border-2 border-ink-2 peer-checked:border-brand peer-checked:bg-brand peer-checked:*:opacity-100 peer-focus-visible:outline-2 peer-focus-visible:outline-brand">
        <x-heroicon-o-check class="size-4 text-white opacity-0" />
    </span>
    <span class="text-md">{{ $slot }}</span>
</label>

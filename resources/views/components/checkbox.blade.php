@props(['name' => null, 'checked' => false])

<label class="flex items-start gap-3">
    <input type="checkbox" @if ($name) name="{{ $name }}" @endif @checked($checked) class="peer sr-only">
    <span class="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-xs border-2 border-ink-2 peer-checked:border-brand peer-checked:bg-brand">
        <x-heroicon-o-check class="size-4 text-white opacity-0 peer-checked:opacity-100" />
    </span>
    <span class="text-md">{{ $slot }}</span>
</label>

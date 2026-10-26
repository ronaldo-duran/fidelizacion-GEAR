@props(['label' => null])

{{-- Selector con la misma forma del TextField (52 de alto, radio 12, borde field). --}}
<label class="flex flex-col gap-1.5">
    @if ($label)
        <span class="text-md font-semibold">{{ $label }}</span>
    @endif
    <select
        {{ $attributes->merge([
            'class' => 'h-input rounded-md border border-field bg-surface px-4 text-lg font-normal text-ink outline-none focus:border-brand',
        ]) }}
    >
        {{ $slot }}
    </select>
</label>

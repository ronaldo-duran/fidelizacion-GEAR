@props([
    'label' => null,
    'name' => null,
    'type' => 'text',
    'placeholder' => null,
    'value' => null,
    'inputmode' => null,
])

<label class="flex flex-col gap-1.5">
    @if ($label)
        <span class="text-md font-semibold">{{ $label }}</span>
    @endif
    <input
        type="{{ $type }}"
        @if ($name) name="{{ $name }}" @endif
        @if ($placeholder) placeholder="{{ $placeholder }}" @endif
        @if ($value !== null) value="{{ $value }}" @endif
        @if ($inputmode) inputmode="{{ $inputmode }}" @endif
        {{ $attributes->merge([
            'class' => 'h-input rounded-md border border-field bg-surface px-4 text-lg text-ink outline-none placeholder:text-ink-2 focus:border-brand',
        ]) }}
    >
</label>

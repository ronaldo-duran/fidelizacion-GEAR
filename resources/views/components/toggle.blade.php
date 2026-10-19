@props(['on' => false, 'name' => null, 'label' => null])

<label class="flex items-center justify-between gap-3">
    @if ($label)
        <span class="text-md">{{ $label }}</span>
    @endif
    <input type="checkbox" @if ($name) name="{{ $name }}" @endif @checked($on) class="peer sr-only">
    <span class="relative h-7 w-12 shrink-0 rounded-full bg-field transition-colors peer-checked:bg-brand">
        <span class="absolute left-0.5 top-0.5 size-6 rounded-full bg-white transition-transform peer-checked:translate-x-5"></span>
    </span>
</label>

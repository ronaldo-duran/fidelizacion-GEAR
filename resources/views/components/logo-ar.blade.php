@props(['label' => true, 'grande' => false])

{{-- Placeholder "AR" hasta que el cliente entregue el logo (docs/diseno/NOTAS.md). En el registro va en 40×40. --}}
<span class="flex items-center {{ $grande ? 'gap-2.5' : 'gap-2' }}">
    <span class="flex items-center justify-center rounded-sm bg-ink font-display font-bold text-paper {{ $grande ? 'size-10 text-sm' : 'size-8 text-xs' }}">AR</span>
    @if ($label)
        <span class="font-sans text-md font-bold">Club Aponte Rivera</span>
    @endif
</span>

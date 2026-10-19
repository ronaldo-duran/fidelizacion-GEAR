@props(['label' => true])

{{-- Placeholder "AR" hasta que el cliente entregue el logo (docs/diseno/NOTAS.md). --}}
<span class="flex items-center gap-2">
    <span class="flex size-8 items-center justify-center rounded-sm bg-ink font-display text-xs font-bold text-paper">AR</span>
    @if ($label)
        <span class="font-sans text-md font-bold">Club Aponte Rivera</span>
    @endif
</span>

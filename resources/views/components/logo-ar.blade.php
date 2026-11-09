@props(['label' => true, 'grande' => false, 'nivel' => null])

{{-- Placeholder "AR" hasta que el cliente entregue el logo (docs/diseno/NOTAS.md). En el registro va en 40×40. Sobre el bloque de un nivel (inicio) toma los colores invertidos de ese nivel. --}}
<span class="flex items-center {{ $grande ? 'gap-2.5' : 'gap-2' }}">
    <span
        class="flex items-center justify-center rounded-sm font-display font-bold {{ $nivel ? '' : 'bg-ink text-paper' }} {{ $grande ? 'size-10 text-sm' : 'size-8 text-xs' }}"
        @if ($nivel) style="background: var(--color-tier-{{ $nivel }}-ink); color: var(--color-tier-{{ $nivel }}-bg)" @endif
    >AR</span>
    @if ($label)
        <span class="font-sans text-md font-bold">Club Aponte Rivera</span>
    @endif
</span>

{{-- ModeSwitch (pantalla 8). TODO(E5-02): "Validar beneficio" se activa con la redención de beneficios. --}}
<div class="mx-4 mt-3 grid grid-cols-2 gap-1 rounded-md bg-sunken p-1">
    <span class="flex h-13 items-center justify-center gap-2 rounded-md bg-cashier-ink text-base font-bold text-white" aria-current="page">
        <x-heroicon-o-plus-circle class="size-5.5" />
        Registrar compra
    </span>
    <button type="button" disabled class="flex h-13 items-center justify-center gap-2 rounded-md text-base font-bold text-cashier-ink-2" title="Llega con la redención de beneficios">
        <x-heroicon-o-qr-code class="size-5.5" />
        Validar beneficio
    </button>
</div>

@props(['tipo' => 'aviso'])

@php $info = $tipo === 'info'; @endphp

<div class="flex items-start gap-2 rounded-md p-3 {{ $info ? 'bg-brand-soft text-brand-strong' : 'border border-warn-line bg-warn-soft text-warn-ink' }}">
    @if ($info)
        <x-heroicon-o-information-circle class="size-5 shrink-0" />
    @else
        <x-heroicon-o-exclamation-triangle class="size-5 shrink-0" />
    @endif
    <div class="text-sm">{{ $slot }}</div>
</div>

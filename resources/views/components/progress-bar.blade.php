@props(['porcentaje' => 0, 'relleno' => 'ink'])

@php $porcentaje = max(0, min(100, (int) $porcentaje)); @endphp

<div
    class="h-2 w-full overflow-hidden rounded-full bg-sunken"
    role="progressbar"
    aria-valuenow="{{ $porcentaje }}"
    aria-valuemin="0"
    aria-valuemax="100"
>
    <div class="h-full rounded-full {{ $relleno === 'brand' ? 'bg-brand' : 'bg-ink' }}" style="width: {{ $porcentaje }}%"></div>
</div>

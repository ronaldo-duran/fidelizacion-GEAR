@props(['nivel' => 'bronce', 'tuNivel' => false, 'texto' => null])

@php $texto ??= ucfirst($nivel); @endphp

@if ($tuNivel)
    <span
        class="inline-flex items-center rounded-full px-3 py-1 font-sans text-xs font-bold uppercase tracking-wide text-white"
        style="background: var(--color-tier-{{ $nivel }}-ink)"
    >TU NIVEL</span>
@else
    <span
        class="inline-flex items-center rounded-full px-3 py-1 font-sans text-sm font-bold"
        style="background: var(--color-tier-{{ $nivel }}-bg); color: var(--color-tier-{{ $nivel }}-ink)"
    >{{ $texto }}</span>
@endif

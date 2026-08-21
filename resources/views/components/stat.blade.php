@props([
    'label',
    'nilai',
    'tone' => 'text-gray-900',
    'keterangan' => null,
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'block rounded-lg bg-white p-5 shadow-sm '.($href ? 'transition hover:shadow-md' : '')]) }}>
    <dt class="text-xs uppercase tracking-wider text-gray-500">{{ $label }}</dt>
    <dd class="mt-1 text-3xl font-semibold {{ $tone }}">{{ $nilai }}</dd>
    @if ($keterangan)
        <p class="mt-1 text-xs text-gray-500">{{ $keterangan }}</p>
    @endif
</{{ $tag }}>

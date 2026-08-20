@props(['tone' => 'bg-gray-100 text-gray-800'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium '.$tone]) }}>
    {{ $slot }}
</span>

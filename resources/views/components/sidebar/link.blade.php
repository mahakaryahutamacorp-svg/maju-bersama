@props([
    'href',
    'active' => false,
])

<a href="{{ $href }}" @if ($active) aria-current="page" @endif
   {{ $attributes->class([
       'block rounded-md py-1.5 pl-8 pr-3 text-sm transition',
       'bg-white/5 font-semibold text-sky-400' => $active,
       'text-gray-400 hover:bg-white/5 hover:text-white' => ! $active,
   ]) }}>
    {{ $slot }}
</a>

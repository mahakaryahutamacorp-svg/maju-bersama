@props([
    'label',
    'hint' => null,
    'open' => false,
    'tone' => 'sky',
])

@php
    $toneClasses = [
        'sky' => 'bg-sky-500/10 border-sky-400/20 text-sky-400',
        'emerald' => 'bg-emerald-500/10 border-emerald-400/20 text-emerald-400',
        'indigo' => 'bg-indigo-500/10 border-indigo-400/20 text-indigo-400',
        'teal' => 'bg-teal-500/10 border-teal-400/20 text-teal-400',
        'amber' => 'bg-amber-500/10 border-amber-400/20 text-amber-300',
        'slate' => 'bg-slate-500/10 border-slate-400/20 text-slate-300',
    ][$tone] ?? 'bg-sky-500/10 border-sky-400/20 text-sky-400';
@endphp

<div x-data="{ open: {{ $open ? 'true' : 'false' }} }" class="overflow-hidden rounded-xl border border-slate-800/80 bg-slate-900/40">
    <button type="button" @click="open = !open" :aria-expanded="open.toString()"
            class="flex w-full items-center justify-between px-3 py-2 text-left transition {{ $open ? 'bg-slate-800/80 text-white' : 'text-slate-300 hover:bg-slate-800/50 hover:text-white' }}">
        <span class="flex min-w-0 items-center gap-2.5">
            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg border {{ $toneClasses }}">
                {{ $icon }}
            </span>
            <span class="min-w-0">
                <span class="block truncate text-xs font-semibold">{{ $label }}</span>
                @if ($hint)
                    <span class="block truncate text-[10px] font-normal text-slate-500">{{ $hint }}</span>
                @endif
            </span>
        </span>
        <svg :class="open ? 'rotate-180 text-amber-400' : 'text-slate-500'" class="ml-2 h-3.5 w-3.5 shrink-0 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
        </svg>
    </button>
    <div x-show="open" @if (! $open) x-cloak @endif
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="space-y-0.5 border-t border-slate-800/60 bg-slate-950/40 px-1 py-1">
        {{ $slot }}
    </div>
</div>

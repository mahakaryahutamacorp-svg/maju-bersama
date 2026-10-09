@props([
    'title',
    'breadcrumbs' => [],
])

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} | Maju Bersama ERP</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    {{-- Plugin Alpine di slot head harus dimuat sebelum Alpine inti. --}}
    {{ $head ?? '' }}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
<div class="min-h-screen lg:flex" x-data="{ mobileMenu: false }">
    @include('layouts.sidebar')

    <div class="flex min-w-0 flex-1 flex-col">
        @if (count($breadcrumbs))
            <nav aria-label="Breadcrumb" class="print:hidden border-b border-slate-200 bg-white/80 px-6 py-2.5 lg:px-10">
                <ol class="flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
                    <li><a href="/backoffice" class="font-medium hover:text-slate-900">Beranda</a></li>
                    @foreach ($breadcrumbs as $crumb)
                        <li aria-hidden="true" class="text-slate-300">/</li>
                        <li>
                            @if (! empty($crumb['url']) && ! $loop->last)
                                <a href="{{ $crumb['url'] }}" class="font-medium hover:text-slate-900">{{ $crumb['label'] }}</a>
                            @else
                                <span @if ($loop->last) aria-current="page" @endif class="font-semibold text-slate-800">{{ $crumb['label'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif

        {{ $slot }}
    </div>
</div>
@auth
    @can('manage-branch-operations')
        @include('layouts.ito')
    @endcan
@endauth
{{ $scripts ?? '' }}
</body>
</html>

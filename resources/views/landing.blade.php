<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maju Bersama ERP | Platform POS &amp; Akuntansi Multi-Cabang</title>
    <meta name="description" content="Tingkatkan efisiensi bisnis ritel Anda dengan pemantauan stok real-time, pencatatan jurnal otomatis, dan manajemen cabang terintegrasi.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Styles & Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body x-data="{ mobileMenuOpen: false }" class="min-h-screen bg-slate-50/70 text-slate-800 antialiased selection:bg-blue-600 selection:text-white">

    <!-- ========================================================
        1. NAVBAR (STICKY)
    ======================================================== -->
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200/80 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-20">
                <!-- Kiri: Logo Maju Bersama ERP -->
                <a href="/" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center font-black text-xl shadow-md shadow-slate-900/10 group-hover:bg-blue-900 transition-colors">
                        <svg class="w-5 h-5 text-blue-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/>
                            <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                            <path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/>
                            <path d="M2 7h20"/>
                            <path d="M22 7v3a2 2 0 0 1-2 2v0a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12v0a2 2 0 0 1-2-2V7"/>
                        </svg>
                    </div>
                    <span class="text-xl sm:text-2xl font-extrabold tracking-tight text-slate-900">
                        Maju Bersama <span class="text-blue-600">ERP</span>
                    </span>
                </a>

                <!-- Tengah: Link Navigasi (Desktop) -->
                <nav class="hidden md:flex items-center space-x-8 text-sm font-medium text-slate-600">
                    <a href="#fitur" class="hover:text-blue-600 transition-colors py-1">Fitur</a>
                    <a href="#keunggulan" class="hover:text-blue-600 transition-colors py-1">Keunggulan</a>
                    <a href="#harga" class="hover:text-blue-600 transition-colors py-1">Harga</a>
                    <a href="#kontak" class="hover:text-blue-600 transition-colors py-1">Kontak</a>
                </nav>

                <!-- Kanan: Tombol Masuk / Dashboard -->
                <div class="hidden md:flex items-center space-x-4">
                    @auth
                        <a href="{{ url('/backoffice') }}" class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 active:scale-[0.98] transition-all shadow-sm shadow-blue-600/30">
                            Masuk Backoffice
                        </a>
                    @else
                        <a href="{{ route('login') }}" class="inline-flex items-center justify-center px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 active:scale-[0.98] transition-all shadow-sm shadow-blue-600/30">
                            Masuk
                        </a>
                    @endauth
                </div>

                <!-- Hamburger Button (Mobile) -->
                <div class="flex md:hidden">
                    <button type="button" @click="mobileMenuOpen = !mobileMenuOpen" class="p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors focus:outline-none" aria-label="Toggle Menu Navigasi">
                        <svg x-show="!mobileMenuOpen" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg x-show="mobileMenuOpen" x-cloak class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation Dropdown -->
        <div x-show="mobileMenuOpen" x-cloak x-transition class="md:hidden border-b border-slate-200 bg-white px-4 pt-3 pb-6 space-y-3">
            <a href="#fitur" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100 hover:text-blue-600">Fitur</a>
            <a href="#keunggulan" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100 hover:text-blue-600">Keunggulan</a>
            <a href="#harga" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100 hover:text-blue-600">Harga</a>
            <a href="#kontak" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100 hover:text-blue-600">Kontak</a>
            <div class="pt-2">
                @auth
                    <a href="{{ url('/backoffice') }}" class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700">
                        Buka Backoffice
                    </a>
                @else
                    <a href="{{ route('login') }}" class="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700">
                        Masuk ke Aplikasi
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <!-- ========================================================
        2. HERO SECTION & 3. INTERACTIVE BANNER SLIDER
    ======================================================== -->
    <section class="relative overflow-hidden pt-12 pb-16 lg:pt-20 lg:pb-24">
        <!-- Background Radial Glow -->
        <div class="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[350px] bg-blue-100/60 blur-[130px] -z-10 rounded-full pointer-events-none"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
                <!-- Kolom Kiri: Teks & CTA -->
                <div class="lg:col-span-6 space-y-6 text-center lg:text-left">
                    <!-- Badge Solusi -->
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200/80 shadow-xs">
                        <svg class="w-3.5 h-3.5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>
                        </svg>
                        <span>Solusi ERP Multi-Outlet No. 1 di Indonesia</span>
                    </div>

                    <!-- Headline -->
                    <h1 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-[1.15]">
                        Kelola Sistem POS dan Akuntansi <span class="text-blue-600 underline decoration-blue-200 decoration-wavy underline-offset-4">Multi-Cabang</span> dalam Satu Platform Terintegrasi.
                    </h1>

                    <!-- Sub-headline -->
                    <p class="text-base sm:text-lg text-slate-600 leading-relaxed max-w-xl mx-auto lg:mx-0">
                        Tingkatkan efisiensi bisnis ritel Anda dengan pemantauan stok real-time, pencatatan jurnal otomatis, dan manajemen cabang yang mudah diakses dari mana saja.
                    </p>

                    <!-- CTA Buttons -->
                    <div class="pt-2 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                        <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl text-base font-semibold text-white bg-blue-600 hover:bg-blue-700 active:scale-[0.98] transition-all shadow-lg shadow-blue-600/25 hover:shadow-blue-600/35">
                            <span>Coba Gratis 14 Hari</span>
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                            </svg>
                        </a>
                        <a href="#fitur" class="w-full sm:w-auto inline-flex items-center justify-center px-7 py-3.5 rounded-xl text-base font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300/80 transition-all hover:border-slate-400 active:scale-[0.98] shadow-xs">
                            Pelajari Fitur
                        </a>
                    </div>

                    <!-- Trust Badges -->
                    <div class="pt-3 flex flex-wrap items-center justify-center lg:justify-start gap-x-6 gap-y-2 text-xs text-slate-500 font-medium">
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Tanpa Kartu Kredit
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Setup Cepat 5 Menit
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <svg class="w-4 h-4 text-emerald-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Dukungan Teknis 24/7
                        </span>
                    </div>
                </div>

                <!-- Kolom Kanan: 3. INTERACTIVE BANNER SLIDER -->
                <div class="lg:col-span-6 w-full" x-data="bannerSlider()">
                    <div
                        class="relative w-full aspect-[16/10] sm:aspect-[16/9] rounded-2xl overflow-hidden shadow-2xl shadow-slate-900/10 border border-slate-200/80 group bg-slate-900"
                        @mouseenter="isPaused = true"
                        @mouseleave="isPaused = false"
                        aria-label="Slider Promo &amp; Pengumuman"
                    >
                        <!-- Slide Items -->
                        <template x-for="(item, index) in banners" :key="item.id">
                            <div
                                class="absolute inset-0 transition-opacity duration-700 ease-in-out"
                                :class="currentIndex === index ? 'opacity-100 z-10' : 'opacity-0 pointer-events-none z-0'"
                            >
                                <!-- Background Image with Gradient Overlay -->
                                <div
                                    class="absolute inset-0 bg-cover bg-center transform scale-105 transition-transform duration-1000 ease-out"
                                    :style="`background-image: url('${item.image_url}')`"
                                ></div>
                                <div class="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent"></div>

                                <!-- Banner Content -->
                                <div class="absolute bottom-0 left-0 right-0 p-5 sm:p-7 md:p-8 flex flex-col justify-end text-white">
                                    <div class="inline-flex items-center gap-1.5 self-start px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-600/90 backdrop-blur-md text-white mb-2.5 shadow-sm">
                                        <svg class="w-3.5 h-3.5 text-blue-200" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>
                                        </svg>
                                        <span x-text="item.badge"></span>
                                    </div>
                                    <h3 class="text-lg sm:text-xl md:text-2xl font-bold leading-snug line-clamp-2 drop-shadow-sm" x-text="item.title"></h3>
                                    <p class="text-xs sm:text-sm text-slate-300 mt-1.5 line-clamp-2 max-w-lg" x-text="item.description"></p>

                                    <div class="mt-3.5 flex items-center">
                                        <a :href="item.link" class="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-blue-400 hover:text-blue-300 transition-colors group/link">
                                            <span>Lihat Detail Modul</span>
                                            <svg class="w-4 h-4 transition-transform group-hover/link:translate-x-0.5 group-hover/link:-translate-y-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M7 7h10v10"/><path d="M7 17 17 7"/>
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <!-- Navigation Arrows -->
                        <button
                            @click="prevSlide()"
                            class="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-slate-900/60 hover:bg-slate-900/90 text-white backdrop-blur-md flex items-center justify-center transition-all opacity-80 sm:opacity-0 sm:group-hover:opacity-100 hover:scale-105 border border-white/10"
                            aria-label="Slide sebelumnya"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                        </button>
                        <button
                            @click="nextSlide()"
                            class="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-slate-900/60 hover:bg-slate-900/90 text-white backdrop-blur-md flex items-center justify-center transition-all opacity-80 sm:opacity-0 sm:group-hover:opacity-100 hover:scale-105 border border-white/10"
                            aria-label="Slide berikutnya"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </button>

                        <!-- Dots Indicator -->
                        <div class="absolute bottom-3 right-4 z-20 flex items-center gap-1.5 bg-slate-950/50 backdrop-blur-md px-2.5 py-1.5 rounded-full border border-white/10">
                            <template x-for="(item, idx) in banners" :key="idx">
                                <button
                                    @click="goTo(idx)"
                                    class="h-2 rounded-full transition-all duration-300"
                                    :class="currentIndex === idx ? 'w-6 bg-blue-500' : 'w-2 bg-white/40 hover:bg-white/70'"
                                    :aria-label="`Beralih ke slide ${idx + 1}`"
                                ></button>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
        4. SOCIAL PROOF (TRUSTED BY)
    ======================================================== -->
    <section class="py-10 bg-white border-y border-slate-200/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-xs uppercase tracking-widest font-semibold text-slate-400 mb-6">
                Dipercaya oleh berbagai cabang ritel &amp; mitra unggulan:
            </p>

            <div class="flex flex-wrap items-center justify-center gap-6 sm:gap-12 md:gap-16 opacity-70 grayscale hover:grayscale-0 transition-all">
                <div class="px-4 py-2 border border-slate-200 rounded-lg text-slate-700 font-black tracking-wider text-base sm:text-lg bg-slate-50/50">
                    MB PUSAT
                </div>
                <div class="px-4 py-2 border border-slate-200 rounded-lg text-slate-700 font-black tracking-wider text-base sm:text-lg bg-slate-50/50">
                    AROFAH
                </div>
                <div class="px-4 py-2 border border-slate-200 rounded-lg text-slate-700 font-black tracking-wider text-base sm:text-lg bg-slate-50/50">
                    MUTIARA TANI
                </div>
                <div class="px-4 py-2 border border-slate-200 rounded-lg text-slate-700 font-black tracking-wider text-base sm:text-lg bg-slate-50/50">
                    ECERAN MAJU BERSAMA
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
        5. KEUNGGULAN (VALUE PROPOSITION)
    ======================================================== -->
    <section id="keunggulan" class="py-20 lg:py-28">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Section Header -->
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-xs font-bold uppercase tracking-widest text-blue-600 mb-2">
                    Keunggulan Utama
                </h2>
                <p class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
                    Mengapa Memilih Maju Bersama ERP?
                </p>
                <p class="mt-4 text-base sm:text-lg text-slate-600">
                    Dirancang khusus untuk mengakomodasi kompleksitas operasional multi-toko tanpa membutuhkan tim IT internal yang besar.
                </p>
            </div>

            <!-- 3-Column Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Card 1: Multi-Cabang -->
                <div class="p-8 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-xl hover:border-blue-200 transition-all duration-300 group">
                    <div class="w-14 h-14 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-6 group-hover:scale-110 group-hover:bg-blue-600 group-hover:text-white transition-all">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="16" height="20" x="4" y="2" rx="2" ry="2"/>
                            <path d="M9 22v-4h6v4"/>
                            <path d="M8 6h.01"/><path d="M16 6h.01"/><path d="M12 6h.01"/>
                            <path d="M12 10h.01"/><path d="M12 14h.01"/>
                            <path d="M16 10h.01"/><path d="M16 14h.01"/>
                            <path d="M8 10h.01"/><path d="M8 14h.01"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">
                        Manajemen Multi-Cabang terpusat.
                    </h3>
                    <p class="text-slate-600 text-sm leading-relaxed">
                        Kontrol hak akses kasir, harga jual khusus cabang, dan pantau kinerja omset semua gerai Anda dari satu dashboard admin utama.
                    </p>
                </div>

                <!-- Card 2: Akuntansi Otomatis -->
                <div class="p-8 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-xl hover:border-blue-200 transition-all duration-300 group">
                    <div class="w-14 h-14 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-6 group-hover:scale-110 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect width="16" height="20" x="4" y="2" rx="2"/>
                            <line x1="8" x2="16" y1="6" y2="6"/>
                            <line x1="16" x2="16" y1="14" y2="18"/>
                            <path d="M16 10h.01"/><path d="M12 10h.01"/><path d="M8 10h.01"/>
                            <path d="M12 14h.01"/><path d="M8 14h.01"/>
                            <path d="M12 18h.01"/><path d="M8 18h.01"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">
                        Akuntansi Otomatis terintegrasi POS.
                    </h3>
                    <p class="text-slate-600 text-sm leading-relaxed">
                        Setiap struk belanja kasir langsung menjurnal debit-kredit, HPP, dan mutasi kas secara presisi tanpa perlu rekap manual berjam-jam.
                    </p>
                </div>

                <!-- Card 3: Cloud 24/7 -->
                <div class="p-8 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-xl hover:border-blue-200 transition-all duration-300 group">
                    <div class="w-14 h-14 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-6 group-hover:scale-110 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                        <svg class="w-7 h-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2a10 10 0 0 0-7.38 16.75"/>
                            <path d="m9 12 2 2 4-4"/>
                            <path d="M4 12v.01"/>
                            <path d="M20 12a8 8 0 0 0-8-8"/>
                            <path d="M12 22a10 10 0 0 0 8-4"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-3">
                        Aksesibilitas Cloud yang aman 24/7.
                    </h3>
                    <p class="text-slate-600 text-sm leading-relaxed">
                        Akses sistem dari browser laptop, tablet, atau smartphone. Didukung enkripsi data perbankan serta automated auto-backup harian.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
        6. DETAIL FITUR (FEATURE HIGHLIGHTS - ZIG-ZAG)
    ======================================================== -->
    <section id="fitur" class="py-20 bg-white border-t border-slate-200/80">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-24">
            <!-- Section Header -->
            <div class="text-center max-w-2xl mx-auto">
                <span class="text-xs font-bold uppercase tracking-widest text-blue-600">Modul Andalan</span>
                <h2 class="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mt-2">
                    Fitur Lengkap untuk Skala Usaha Ritel Modern
                </h2>
            </div>

            <!-- Baris 1: Teks di Kiri, Mockup di Kanan -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-5">
                    <div class="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                            <path d="M6 9V3a1 1 0 0 1 1-1h10a1 1 0 0 1 1 1v6"/>
                            <rect x="6" y="14" width="12" height="8" rx="1"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-slate-900 leading-snug">
                        Modul Kasir (POS) &amp; Printer Thermal
                    </h3>
                    <p class="text-slate-600 leading-relaxed text-base">
                        Proses checkout super cepat dengan dukungan scanner barcode USB/Bluetooth, koneksi printer kasir 58mm/80mm, serta multi-metode pembayaran (Tunai, QRIS, Transfer Bank, dan Piutang Member).
                    </p>
                    <ul class="space-y-2.5 text-sm text-slate-700">
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Cetak struk kasir thermal instan &amp; kirim nota via WhatsApp
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Mode kasir cepat (hotkey keyboard &amp; touch screen friendly)
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Buka tutup laci kasir (Cash Drawer) otomatis
                        </li>
                    </ul>
                </div>

                <!-- Visual Mockup 1: Terminal Kasir -->
                <div class="p-6 bg-slate-900 rounded-2xl shadow-xl border border-slate-800 text-white font-mono text-xs">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div class="flex items-center gap-2">
                            <div class="w-3 h-3 rounded-full bg-rose-500"></div>
                            <div class="w-3 h-3 rounded-full bg-amber-500"></div>
                            <div class="w-3 h-3 rounded-full bg-emerald-500"></div>
                            <span class="ml-2 font-sans font-semibold text-slate-300">Kasir Terminal #01 - Cabang MB PUSAT</span>
                        </div>
                        <span class="text-emerald-400 bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-800/60 font-sans font-semibold text-[11px]">ONLINE</span>
                    </div>
                    <div class="mt-5 space-y-3 font-sans">
                        <div class="flex justify-between items-center p-3 rounded-lg bg-slate-800/80">
                            <div>
                                <p class="font-semibold text-white">Pupuk NPK 16-16-16 (50kg)</p>
                                <p class="text-xs text-slate-400">2 Qty × Rp 450.000</p>
                            </div>
                            <span class="font-bold text-blue-400">Rp 900.000</span>
                        </div>
                        <div class="flex justify-between items-center p-3 rounded-lg bg-slate-800/80">
                            <div>
                                <p class="font-semibold text-white">Bibit Jagung Hibrida Premium</p>
                                <p class="text-xs text-slate-400">5 Qty × Rp 115.000</p>
                            </div>
                            <span class="font-bold text-blue-400">Rp 575.000</span>
                        </div>
                        <div class="p-4 rounded-xl bg-blue-950/60 border border-blue-900/60 flex justify-between items-center mt-4">
                            <span class="text-slate-300 font-medium">Total Pembayaran</span>
                            <span class="text-xl font-extrabold text-emerald-400">Rp 1.475.000</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Baris 2: Mockup di Kiri, Teks di Kanan (Zig-Zag) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Visual Mockup 2: Surat Jalan Mutasi -->
                <div class="order-2 lg:order-1 p-6 bg-slate-50 rounded-2xl shadow-lg border border-slate-200">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                                <path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>
                            </svg>
                            <span class="font-bold text-slate-800 text-sm">Transfer Mutasi Stok Antar Cabang</span>
                        </div>
                        <span class="text-xs font-semibold px-2.5 py-0.5 rounded-full bg-blue-100 text-blue-700">Surat Jalan #SJ-2026-0812</span>
                    </div>
                    <div class="mt-5 space-y-4 text-xs">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-3 bg-white rounded-lg border border-slate-200">
                                <p class="text-slate-500 font-medium">Asal Gudang</p>
                                <p class="font-bold text-slate-800 text-sm mt-0.5">Gudang Utama (MB PUSAT)</p>
                            </div>
                            <div class="p-3 bg-white rounded-lg border border-slate-200">
                                <p class="text-slate-500 font-medium">Tujuan Penerima</p>
                                <p class="font-bold text-slate-800 text-sm mt-0.5">Outlet Cabang AROFAH</p>
                            </div>
                        </div>

                        <div class="p-4 bg-white rounded-xl border border-slate-200 space-y-2">
                            <div class="flex justify-between text-slate-600 font-semibold border-b pb-2">
                                <span>Nama Item</span>
                                <span>Kuantitas Transfer</span>
                            </div>
                            <div class="flex justify-between text-slate-800">
                                <span>Herbisida Selektif 1 Liter</span>
                                <span class="font-bold">40 Botol</span>
                            </div>
                            <div class="flex justify-between text-slate-800">
                                <span>Sprayer Elektrik 16L</span>
                                <span class="font-bold">12 Unit</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-emerald-700 bg-emerald-50 px-3 py-2 rounded-lg border border-emerald-200">
                            <span class="flex items-center gap-1.5 font-medium">
                                <svg class="w-4 h-4 text-emerald-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                                Status: Telah Diterima &amp; Stok Masuk Otomatis
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Teks Baris 2 -->
                <div class="order-1 lg:order-2 space-y-5">
                    <div class="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
                            <path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-slate-900 leading-snug">
                        Manajemen Persediaan &amp; Transfer Antar Cabang
                    </h3>
                    <p class="text-slate-600 leading-relaxed text-base">
                        Hindari risiko <em>dead stock</em> atau kekurangan barang di gerai terpencil. Kirim permintaan restok dan lakukan transfer inventaris antar cabang dengan approval bertingkat dan surat jalan otomatis.
                    </p>
                    <ul class="space-y-2.5 text-sm text-slate-700">
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Notifikasi otomatis saat stok mendekati batas minimum
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Pelacakan riwayat mutasi barang (audit trail komprehensif)
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Penghitungan HPP dengan metode FIFO atau Rata-Rata (Average)
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Baris 3: Teks di Kiri, Mockup di Kanan -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="space-y-5">
                    <div class="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                        <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                            <polyline points="16 7 22 7 22 13"/>
                        </svg>
                    </div>
                    <h3 class="text-2xl sm:text-3xl font-bold text-slate-900 leading-snug">
                        Laporan Keuangan &amp; Neraca Real-time
                    </h3>
                    <p class="text-slate-600 leading-relaxed text-base">
                        Ketahui laba rugi bersih, arus kas (<em>cash flow</em>), serta nilai aset setiap cabang secara seketika. Semua laporan siap diekspor ke format PDF maupun Excel untuk memudahkan analisa pemilik usaha.
                    </p>
                    <ul class="space-y-2.5 text-sm text-slate-700">
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Laporan Laba/Rugi Konsolidasi &amp; Per Cabang
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Buku besar &amp; neraca saldo terisi otomatis
                        </li>
                        <li class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-emerald-600 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/></svg>
                            Analisa grafik produk terlaris (<em>best-selling</em>) dan tren omset
                        </li>
                    </ul>
                </div>

                <!-- Visual Mockup 3: Ringkasan Finansial -->
                <div class="p-6 bg-slate-900 rounded-2xl shadow-xl border border-slate-800 text-white">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div class="flex items-center gap-2">
                            <svg class="w-5 h-5 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/>
                                <polyline points="16 7 22 7 22 13"/>
                            </svg>
                            <span class="font-bold text-sm">Ringkasan Finansial Periode Berjalan</span>
                        </div>
                        <span class="text-xs text-slate-400">Update 1 Menit Lalu</span>
                    </div>
                    <div class="mt-5 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="p-4 rounded-xl bg-slate-800/90 border border-slate-700/60">
                                <p class="text-xs text-slate-400">Total Pendapatan (Omset)</p>
                                <p class="text-xl font-extrabold text-white mt-1">Rp 482.500.000</p>
                                <span class="text-[11px] text-emerald-400 font-semibold">&uarr; +18.4% vs bln lalu</span>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-800/90 border border-slate-700/60">
                                <p class="text-xs text-slate-400">Laba Bersih (Net Profit)</p>
                                <p class="text-xl font-extrabold text-emerald-400 mt-1">Rp 124.850.000</p>
                                <span class="text-[11px] text-emerald-400 font-semibold">Margin 25.8%</span>
                            </div>
                        </div>

                        <div class="p-4 rounded-xl bg-slate-800/50 border border-slate-800 space-y-2 text-xs">
                            <div class="flex justify-between text-slate-400">
                                <span>Kas di Tangan (Cash on Hand)</span>
                                <span class="text-white font-mono font-medium">Rp 45.200.000</span>
                            </div>
                            <div class="flex justify-between text-slate-400">
                                <span>Saldo Rekening Bank (BCA / Mandiri)</span>
                                <span class="text-white font-mono font-medium">Rp 210.800.000</span>
                            </div>
                            <div class="flex justify-between text-slate-400">
                                <span>Total Piutang Berjalan Member</span>
                                <span class="text-white font-mono font-medium">Rp 18.450.000</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ========================================================
        7. BOTTOM CTA
    ======================================================== -->
    <section class="py-20 bg-slate-900 text-white relative overflow-hidden">
        <!-- Glow Decoration -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[350px] bg-blue-600/20 blur-[150px] pointer-events-none rounded-full"></div>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10 space-y-6">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-300 border border-blue-400/30">
                <svg class="w-3.5 h-3.5 text-blue-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <path d="m9 12 2 2 4-4"/>
                </svg>
                <span>Garansi Keamanan Data &amp; Onboarding Gratis</span>
            </div>

            <h2 class="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white leading-tight">
                Siap Meningkatkan Skala Bisnis Anda?
            </h2>

            <p class="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto leading-relaxed">
                Bergabunglah bersama ratusan outlet ritel yang telah mempercayakan operasional harian kasir dan akuntansi kepada Maju Bersama ERP.
            </p>

            <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('login') }}" class="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl text-base font-bold text-white bg-blue-600 hover:bg-blue-500 active:scale-[0.98] transition-all shadow-xl shadow-blue-600/30 hover:shadow-blue-500/40">
                    <span>Mulai Gunakan Maju Bersama ERP</span>
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                    </svg>
                </a>
            </div>
            <p class="text-xs text-slate-400 mt-2">
                Uji coba 14 hari tanpa biaya &bull; Tanpa komitmen kontrak &bull; Bisa dibatalkan kapan saja
            </p>
        </div>
    </section>

    <!-- ========================================================
        8. FOOTER
    ======================================================== -->
    <footer class="bg-slate-950 text-slate-400 text-sm py-12 border-t border-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
                <!-- Info Kiri -->
                <div class="space-y-1">
                    <div class="flex items-center justify-center md:justify-start gap-2">
                        <div class="w-6 h-6 rounded-lg bg-blue-600/20 text-blue-400 flex items-center justify-center">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/>
                                <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/>
                                <path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/>
                            </svg>
                        </div>
                        <span class="font-bold text-white text-base">
                            Maju Bersama ERP
                        </span>
                    </div>
                    <p class="text-xs text-slate-500">
                        &copy; {{ date('Y') }} Maju Bersama ERP. Hak Cipta Dilindungi.
                    </p>
                </div>

                <!-- Link Kanan -->
                <div class="flex flex-wrap items-center justify-center gap-6 text-xs sm:text-sm">
                    <a href="#kebijakan-privasi" class="hover:text-white transition-colors">Kebijakan Privasi</a>
                    <a href="#syarat-ketentuan" class="hover:text-white transition-colors">Syarat &amp; Ketentuan</a>
                    <a href="#bantuan" class="hover:text-white transition-colors">Pusat Bantuan</a>
                    <a href="#kontak" class="hover:text-white transition-colors">Hubungi Kami</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Alpine.js Slider Logic -->
    <script>
        function bannerSlider() {
            return {
                currentIndex: 0,
                isPaused: false,
                timer: null,
                // TODO: Fetch data ini dari API Dashboard Master
                banners: [
                    {
                        id: 1,
                        image_url: 'https://images.unsplash.com/photo-1556742049-0a67c5574f73?auto=format&fit=crop&w=1200&q=80',
                        badge: 'Fitur Terbaru v2.4',
                        title: 'Sinkronisasi POS Kasir & Stok Multi-Cabang Real-Time',
                        description: 'Update stok otomatis lintas gudang dalam hitungan detik tanpa jeda.',
                        link: '#fitur'
                    },
                    {
                        id: 2,
                        image_url: 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80',
                        badge: 'Laporan Finansial',
                        title: 'Neraca & Jurnal Keuangan Otomatis Setiap Transaksi',
                        description: 'Tutup buku harian dan rekonsiliasi bank jauh lebih cepat dan akurat.',
                        link: '#keunggulan'
                    },
                    {
                        id: 3,
                        image_url: 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1200&q=80',
                        badge: 'Multi-Outlet',
                        title: 'Kelola Transfer Barang Antar Cabang & PO Supplier',
                        description: 'Pantau pengiriman stok antar cabang dengan surat jalan digital terintegrasi.',
                        link: '#fitur'
                    }
                ],
                init() {
                    this.startAutoplay();
                },
                startAutoplay() {
                    this.timer = setInterval(() => {
                        if (!this.isPaused) {
                            this.nextSlide();
                        }
                    }, 5000);
                },
                nextSlide() {
                    this.currentIndex = (this.currentIndex + 1) % this.banners.length;
                },
                prevSlide() {
                    this.currentIndex = (this.currentIndex - 1 + this.banners.length) % this.banners.length;
                },
                goTo(index) {
                    this.currentIndex = index;
                }
            };
        }
    </script>
</body>
</html>

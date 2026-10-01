"use client";

import React, { useState } from "react";
import Link from "next/link";
import BannerSlider from "@/components/BannerSlider";
import {
  Store,
  Calculator,
  CloudCheck,
  Printer,
  Boxes,
  TrendingUp,
  ShieldCheck,
  CheckCircle2,
  Menu,
  X,
  ArrowRight,
  Sparkles,
} from "lucide-react";

export default function LandingPage() {
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

  return (
    <div className="min-h-screen bg-slate-50/70 text-slate-800 antialiased selection:bg-blue-600 selection:text-white font-sans">
      {/* ========================================================
          1. NAVBAR (STICKY)
      ======================================================== */}
      <header className="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-200/80 transition-all">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex items-center justify-between h-20">
            {/* Logo Kiri */}
            <div className="flex items-center gap-3">
              <div className="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center font-black text-xl shadow-md shadow-slate-900/10">
                <Store className="w-5 h-5 text-blue-400" />
              </div>
              <span className="text-xl sm:text-2xl font-extrabold tracking-tight text-slate-900">
                Maju Bersama <span className="text-blue-600">ERP</span>
              </span>
            </div>

            {/* Navigasi Desktop (Tengah) */}
            <nav className="hidden md:flex items-center space-x-8 text-sm font-medium text-slate-600">
              <a href="#fitur" className="hover:text-blue-600 transition-colors py-1">
                Fitur
              </a>
              <a href="#keunggulan" className="hover:text-blue-600 transition-colors py-1">
                Keunggulan
              </a>
              <a href="#harga" className="hover:text-blue-600 transition-colors py-1">
                Harga
              </a>
              <a href="#kontak" className="hover:text-blue-600 transition-colors py-1">
                Kontak
              </a>
            </nav>

            {/* Tombol Kanan (Desktop) */}
            <div className="hidden md:flex items-center space-x-4">
              <Link
                href="/login"
                className="inline-flex items-center justify-center px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 active:scale-[0.98] transition-all shadow-sm shadow-blue-600/30"
              >
                Masuk
              </Link>
            </div>

            {/* Hamburger Button (Mobile) */}
            <div className="flex md:hidden">
              <button
                type="button"
                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                className="p-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors focus:outline-none"
                aria-label="Toggle Menu Navigasi"
              >
                {mobileMenuOpen ? (
                  <X className="w-6 h-6" />
                ) : (
                  <Menu className="w-6 h-6" />
                )}
              </button>
            </div>
          </div>
        </div>

        {/* Mobile Navigation Dropdown */}
        {mobileMenuOpen && (
          <div className="md:hidden border-b border-slate-200 bg-white px-4 pt-3 pb-6 space-y-3">
            <a
              href="#fitur"
              onClick={() => setMobileMenuOpen(false)}
              className="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100 hover:text-blue-600"
            >
              Fitur
            </a>
            <a
              href="#keunggulan"
              onClick={() => setMobileMenuOpen(false)}
              className="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100 hover:text-blue-600"
            >
              Keunggulan
            </a>
            <a
              href="#harga"
              onClick={() => setMobileMenuOpen(false)}
              className="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100 hover:text-blue-600"
            >
              Harga
            </a>
            <a
              href="#kontak"
              onClick={() => setMobileMenuOpen(false)}
              className="block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100 hover:text-blue-600"
            >
              Kontak
            </a>
            <div className="pt-2">
              <Link
                href="/login"
                className="w-full inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700"
              >
                Masuk ke Aplikasi
              </Link>
            </div>
          </div>
        )}
      </header>

      {/* ========================================================
          2. HERO SECTION & 3. INTERACTIVE BANNER SLIDER
      ======================================================== */}
      <section className="relative overflow-hidden pt-12 pb-16 lg:pt-20 lg:pb-24">
        {/* Subtle background glow */}
        <div className="absolute top-1/4 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[350px] bg-blue-100/60 blur-[130px] -z-10 rounded-full pointer-events-none" />

        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
            {/* Kolom Kiri: Teks & CTA */}
            <div className="lg:col-span-6 space-y-6 text-center lg:text-left">
              {/* Badge Announcement */}
              <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200/80 shadow-xs">
                <Sparkles className="w-3.5 h-3.5 text-blue-600" />
                <span>Solusi ERP Multi-Outlet No. 1 di Indonesia</span>
              </div>

              {/* Headline */}
              <h1 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold text-slate-900 tracking-tight leading-[1.15]">
                Kelola Sistem POS dan Akuntansi{" "}
                <span className="text-blue-600 underline decoration-blue-200 decoration-wavy underline-offset-4">
                  Multi-Cabang
                </span>{" "}
                dalam Satu Platform Terintegrasi.
              </h1>

              {/* Sub-headline */}
              <p className="text-base sm:text-lg text-slate-600 leading-relaxed max-w-xl mx-auto lg:mx-0">
                Tingkatkan efisiensi bisnis ritel Anda dengan pemantauan stok
                real-time, pencatatan jurnal otomatis, dan manajemen cabang yang
                mudah diakses dari mana saja.
              </p>

              {/* CTA Buttons */}
              <div className="pt-2 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-4">
                <Link
                  href="/login"
                  className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-7 py-3.5 rounded-xl text-base font-semibold text-white bg-blue-600 hover:bg-blue-700 active:scale-[0.98] transition-all shadow-lg shadow-blue-600/25 hover:shadow-blue-600/35"
                >
                  <span>Coba Gratis 14 Hari</span>
                  <ArrowRight className="w-4 h-4" />
                </Link>

                <a
                  href="#fitur"
                  className="w-full sm:w-auto inline-flex items-center justify-center px-7 py-3.5 rounded-xl text-base font-semibold text-slate-700 bg-white hover:bg-slate-50 border border-slate-300/80 transition-all hover:border-slate-400 active:scale-[0.98] shadow-xs"
                >
                  Pelajari Fitur
                </a>
              </div>

              {/* Mini Highlights */}
              <div className="pt-3 flex flex-wrap items-center justify-center lg:justify-start gap-x-6 gap-y-2 text-xs text-slate-500 font-medium">
                <span className="inline-flex items-center gap-1.5">
                  <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                  Tanpa Kartu Kredit
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                  Setup Cepat 5 Menit
                </span>
                <span className="inline-flex items-center gap-1.5">
                  <CheckCircle2 className="w-4 h-4 text-emerald-500" />
                  Dukungan Teknis 24/7
                </span>
              </div>
            </div>

            {/* Kolom Kanan: Interactive Banner Slider */}
            <div className="lg:col-span-6 w-full">
              <BannerSlider />
            </div>
          </div>
        </div>
      </section>

      {/* ========================================================
          4. SOCIAL PROOF (TRUSTED BY)
      ======================================================== */}
      <section className="py-10 bg-white border-y border-slate-200/60">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <p className="text-xs uppercase tracking-widest font-semibold text-slate-400 mb-6">
            Dipercaya oleh berbagai cabang ritel &amp; mitra unggulan:
          </p>

          <div className="flex flex-wrap items-center justify-center gap-6 sm:gap-12 md:gap-16 opacity-70 grayscale hover:grayscale-0 transition-all">
            <div className="px-4 py-2 border border-slate-200 rounded-lg text-slate-700 font-black tracking-wider text-base sm:text-lg bg-slate-50/50">
              MB PUSAT
            </div>
            <div className="px-4 py-2 border border-slate-200 rounded-lg text-slate-700 font-black tracking-wider text-base sm:text-lg bg-slate-50/50">
              AROFAH
            </div>
            <div className="px-4 py-2 border border-slate-200 rounded-lg text-slate-700 font-black tracking-wider text-base sm:text-lg bg-slate-50/50">
              MUTIARA TANI
            </div>
            <div className="px-4 py-2 border border-slate-200 rounded-lg text-slate-700 font-black tracking-wider text-base sm:text-lg bg-slate-50/50">
              ECERAN MAJU BERSAMA
            </div>
          </div>
        </div>
      </section>

      {/* ========================================================
          5. KEUNGGULAN (VALUE PROPOSITION)
      ======================================================== */}
      <section id="keunggulan" className="py-20 lg:py-28">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          {/* Section Header */}
          <div className="text-center max-w-3xl mx-auto mb-16">
            <h2 className="text-xs font-bold uppercase tracking-widest text-blue-600 mb-2">
              Keunggulan Utama
            </h2>
            <p className="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight">
              Mengapa Memilih Maju Bersama ERP?
            </p>
            <p className="mt-4 text-base sm:text-lg text-slate-600">
              Dirancang khusus untuk mengakomodasi kompleksitas operasional
              multi-toko tanpa membutuhkan tim IT internal yang besar.
            </p>
          </div>

          {/* 3-Column Grid */}
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {/* Card 1 */}
            <div className="p-8 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-xl hover:border-blue-200 transition-all duration-300 group">
              <div className="w-14 h-14 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-6 group-hover:scale-110 group-hover:bg-blue-600 group-hover:text-white transition-all">
                <Store className="w-7 h-7" />
              </div>
              <h3 className="text-xl font-bold text-slate-900 mb-3">
                Manajemen Multi-Cabang terpusat.
              </h3>
              <p className="text-slate-600 text-sm leading-relaxed">
                Kontrol hak akses kasir, harga jual khusus cabang, dan pantau
                kinerja omset semua gerai Anda dari satu dashboard admin utama.
              </p>
            </div>

            {/* Card 2 */}
            <div className="p-8 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-xl hover:border-blue-200 transition-all duration-300 group">
              <div className="w-14 h-14 rounded-xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-6 group-hover:scale-110 group-hover:bg-indigo-600 group-hover:text-white transition-all">
                <Calculator className="w-7 h-7" />
              </div>
              <h3 className="text-xl font-bold text-slate-900 mb-3">
                Akuntansi Otomatis terintegrasi POS.
              </h3>
              <p className="text-slate-600 text-sm leading-relaxed">
                Setiap struk belanja kasir langsung menjurnal debit-kredit, HPP,
                dan mutasi kas secara presisi tanpa perlu rekap manual berjam-jam.
              </p>
            </div>

            {/* Card 3 */}
            <div className="p-8 bg-white rounded-2xl border border-slate-200/80 shadow-xs hover:shadow-xl hover:border-blue-200 transition-all duration-300 group">
              <div className="w-14 h-14 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center mb-6 group-hover:scale-110 group-hover:bg-emerald-600 group-hover:text-white transition-all">
                <CloudCheck className="w-7 h-7" />
              </div>
              <h3 className="text-xl font-bold text-slate-900 mb-3">
                Aksesibilitas Cloud yang aman 24/7.
              </h3>
              <p className="text-slate-600 text-sm leading-relaxed">
                Akses sistem dari browser laptop, tablet, atau smartphone.
                Didukung enkripsi data perbankan serta automated auto-backup
                harian.
              </p>
            </div>
          </div>
        </div>
      </section>

      {/* ========================================================
          6. DETAIL FITUR (FEATURE HIGHLIGHTS - ZIG-ZAG)
      ======================================================== */}
      <section id="fitur" className="py-20 bg-white border-t border-slate-200/80">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-24">
          {/* Section Header */}
          <div className="text-center max-w-2xl mx-auto">
            <span className="text-xs font-bold uppercase tracking-widest text-blue-600">
              Modul Andalan
            </span>
            <h2 className="text-3xl sm:text-4xl font-extrabold text-slate-900 tracking-tight mt-2">
              Fitur Lengkap untuk Skala Usaha Ritel Modern
            </h2>
          </div>

          {/* Baris 1: Teks di Kiri, Mockup di Kanan */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div className="space-y-5">
              <div className="w-12 h-12 rounded-xl bg-blue-100 text-blue-600 flex items-center justify-center">
                <Printer className="w-6 h-6" />
              </div>
              <h3 className="text-2xl sm:text-3xl font-bold text-slate-900 leading-snug">
                Modul Kasir (POS) &amp; Printer Thermal
              </h3>
              <p className="text-slate-600 leading-relaxed text-base">
                Proses checkout super cepat dengan dukungan scanner barcode USB/Bluetooth,
                koneksi printer kasir 58mm/80mm, serta multi-metode pembayaran (Tunai, QRIS, Transfer Bank, dan Piutang Member).
              </p>
              <ul className="space-y-2.5 text-sm text-slate-700">
                <li className="flex items-center gap-2">
                  <CheckCircle2 className="w-4 h-4 text-blue-600" />
                  Cetak struk kasir thermal instan &amp; kirim nota via WhatsApp
                </li>
                <li className="flex items-center gap-2">
                  <CheckCircle2 className="w-4 h-4 text-blue-600" />
                  Mode kasir cepat (hotkey keyboard &amp; touch screen friendly)
                </li>
                <li className="flex items-center gap-2">
                  <CheckCircle2 className="w-4 h-4 text-blue-600" />
                  Buka tutup laci kasir (Cash Drawer) otomatis
                </li>
              </ul>
            </div>

            {/* Visual Mockup 1 */}
            <div className="p-6 bg-slate-900 rounded-2xl shadow-xl border border-slate-800 text-white font-mono text-xs">
              <div className="flex items-center justify-between pb-4 border-b border-slate-800">
                <div className="flex items-center gap-2">
                  <div className="w-3 h-3 rounded-full bg-rose-500" />
                  <div className="w-3 h-3 rounded-full bg-amber-500" />
                  <div className="w-3 h-3 rounded-full bg-emerald-500" />
                  <span className="ml-2 font-sans font-semibold text-slate-300">
                    Kasir Terminal #01 - Cabang MB PUSAT
                  </span>
                </div>
                <span className="text-emerald-400 bg-emerald-950/60 px-2 py-0.5 rounded border border-emerald-800/60">
                  ONLINE
                </span>
              </div>
              <div className="mt-5 space-y-3 font-sans">
                <div className="flex justify-between items-center p-3 rounded-lg bg-slate-800/80">
                  <div>
                    <p className="font-semibold text-white">Pupuk NPK 16-16-16 (50kg)</p>
                    <p className="text-xs text-slate-400">2 Qty × Rp 450.000</p>
                  </div>
                  <span className="font-bold text-blue-400">Rp 900.000</span>
                </div>
                <div className="flex justify-between items-center p-3 rounded-lg bg-slate-800/80">
                  <div>
                    <p className="font-semibold text-white">Bibit Jagung Hibrida Premium</p>
                    <p className="text-xs text-slate-400">5 Qty × Rp 115.000</p>
                  </div>
                  <span className="font-bold text-blue-400">Rp 575.000</span>
                </div>
                <div className="p-4 rounded-xl bg-blue-950/60 border border-blue-900/60 flex justify-between items-center mt-4">
                  <span className="text-slate-300 font-medium">Total Pembayaran</span>
                  <span className="text-xl font-extrabold text-emerald-400">Rp 1.475.000</span>
                </div>
              </div>
            </div>
          </div>

          {/* Baris 2: Mockup di Kiri, Teks di Kanan (Zig-zag) */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            {/* Visual Mockup 2 */}
            <div className="order-2 lg:order-1 p-6 bg-slate-50 rounded-2xl shadow-lg border border-slate-200">
              <div className="flex items-center justify-between pb-4 border-b border-slate-200">
                <div className="flex items-center gap-2">
                  <Boxes className="w-5 h-5 text-blue-600" />
                  <span className="font-bold text-slate-800 text-sm">
                    Transfer Mutasi Stok Antar Cabang
                  </span>
                </div>
                <span className="text-xs font-semibold px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">
                  Surat Jalan #SJ-2026-0812
                </span>
              </div>
              <div className="mt-5 space-y-4">
                <div className="grid grid-cols-2 gap-3 text-xs">
                  <div className="p-3 bg-white rounded-lg border border-slate-200">
                    <p className="text-slate-500 font-medium">Asal Gudang</p>
                    <p className="font-bold text-slate-800 text-sm mt-0.5">Gudang Utama (MB PUSAT)</p>
                  </div>
                  <div className="p-3 bg-white rounded-lg border border-slate-200">
                    <p className="text-slate-500 font-medium">Tujuan Penerima</p>
                    <p className="font-bold text-slate-800 text-sm mt-0.5">Outlet Cabang AROFAH</p>
                  </div>
                </div>

                <div className="p-4 bg-white rounded-xl border border-slate-200 space-y-2">
                  <div className="flex justify-between text-xs text-slate-600 font-semibold border-b pb-2">
                    <span>Nama Item</span>
                    <span>Kuantitas Transfer</span>
                  </div>
                  <div className="flex justify-between text-xs text-slate-800">
                    <span>Herbisida Selektif 1 Liter</span>
                    <span className="font-bold">40 Botol</span>
                  </div>
                  <div className="flex justify-between text-xs text-slate-800">
                    <span>Sprayer Elektrik 16L</span>
                    <span className="font-bold">12 Unit</span>
                  </div>
                </div>

                <div className="flex items-center justify-between text-xs text-emerald-700 bg-emerald-50 px-3 py-2 rounded-lg border border-emerald-200">
                  <span className="flex items-center gap-1.5 font-medium">
                    <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                    Status: Telah Diterima &amp; Stok Masuk Otomatis
                  </span>
                </div>
              </div>
            </div>

            {/* Teks Baris 2 */}
            <div className="order-1 lg:order-2 space-y-5">
              <div className="w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center">
                <Boxes className="w-6 h-6" />
              </div>
              <h3 className="text-2xl sm:text-3xl font-bold text-slate-900 leading-snug">
                Manajemen Persediaan &amp; Transfer Antar Cabang
              </h3>
              <p className="text-slate-600 leading-relaxed text-base">
                Hindari risiko dead stock atau kekurangan barang di gerai terpencil.
                Kirim permintaan restok dan lakukan transfer inventaris antar cabang dengan approval bertingkat dan surat jalan otomatis.
              </p>
              <ul className="space-y-2.5 text-sm text-slate-700">
                <li className="flex items-center gap-2">
                  <CheckCircle2 className="w-4 h-4 text-indigo-600" />
                  Notifikasi otomatis saat stok mendekati batas minimum
                </li>
                <li className="flex items-center gap-2">
                  <CheckCircle2 className="w-4 h-4 text-indigo-600" />
                  Pelacakan riwayat mutasi barang (audit trail komprehensif)
                </li>
                <li className="flex items-center gap-2">
                  <CheckCircle2 className="w-4 h-4 text-indigo-600" />
                  Penghitungan HPP dengan metode FIFO atau Rata-Rata (Average)
                </li>
              </ul>
            </div>
          </div>

          {/* Baris 3: Teks di Kiri, Mockup di Kanan */}
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <div className="space-y-5">
              <div className="w-12 h-12 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center">
                <TrendingUp className="w-6 h-6" />
              </div>
              <h3 className="text-2xl sm:text-3xl font-bold text-slate-900 leading-snug">
                Laporan Keuangan &amp; Neraca Real-time
              </h3>
              <p className="text-slate-600 leading-relaxed text-base">
                Ketahui laba rugi bersih, arus kas (cash flow), serta nilai aset setiap cabang secara seketika.
                Semua laporan siap diekspor ke format PDF maupun Excel untuk memudahkan analisa pemilik usaha.
              </p>
              <ul className="space-y-2.5 text-sm text-slate-700">
                <li className="flex items-center gap-2">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                  Laporan Laba/Rugi Konsolidasi &amp; Per Cabang
                </li>
                <li className="flex items-center gap-2">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                  Buku besar &amp; neraca saldo terisi otomatis
                </li>
                <li className="flex items-center gap-2">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                  Analisa grafik produk terlaris (best-selling) dan tren omset
                </li>
              </ul>
            </div>

            {/* Visual Mockup 3 */}
            <div className="p-6 bg-slate-900 rounded-2xl shadow-xl border border-slate-800 text-white">
              <div className="flex items-center justify-between pb-4 border-b border-slate-800">
                <div className="flex items-center gap-2">
                  <TrendingUp className="w-5 h-5 text-emerald-400" />
                  <span className="font-bold text-sm">Ringkasan Finansial Periode Berjalan</span>
                </div>
                <span className="text-xs text-slate-400">Update 1 Menit Lalu</span>
              </div>
              <div className="mt-5 space-y-4">
                <div className="grid grid-cols-2 gap-3">
                  <div className="p-4 rounded-xl bg-slate-800/90 border border-slate-700/60">
                    <p className="text-xs text-slate-400">Total Pendapatan (Omset)</p>
                    <p className="text-xl font-extrabold text-white mt-1">Rp 482.500.000</p>
                    <span className="text-[11px] text-emerald-400 font-semibold">↑ +18.4% vs bln lalu</span>
                  </div>
                  <div className="p-4 rounded-xl bg-slate-800/90 border border-slate-700/60">
                    <p className="text-xs text-slate-400">Laba Bersih (Net Profit)</p>
                    <p className="text-xl font-extrabold text-emerald-400 mt-1">Rp 124.850.000</p>
                    <span className="text-[11px] text-emerald-400 font-semibold">Margin 25.8%</span>
                  </div>
                </div>

                <div className="p-4 rounded-xl bg-slate-800/50 border border-slate-800 space-y-2 text-xs">
                  <div className="flex justify-between text-slate-400">
                    <span>Kas di Tangan (Cash on Hand)</span>
                    <span className="text-white font-mono font-medium">Rp 45.200.000</span>
                  </div>
                  <div className="flex justify-between text-slate-400">
                    <span>Saldo Rekening Bank (BCA / Mandiri)</span>
                    <span className="text-white font-mono font-medium">Rp 210.800.000</span>
                  </div>
                  <div className="flex justify-between text-slate-400">
                    <span>Total Piutang Berjalan Member</span>
                    <span className="text-white font-mono font-medium">Rp 18.450.000</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {/* ========================================================
          7. BOTTOM CTA
      ======================================================== */}
      <section className="py-20 bg-slate-900 text-white relative overflow-hidden">
        {/* Glow Decoration */}
        <div className="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[700px] h-[350px] bg-blue-600/20 blur-[150px] pointer-events-none rounded-full" />

        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10 space-y-6">
          <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-semibold bg-blue-500/20 text-blue-300 border border-blue-400/30">
            <ShieldCheck className="w-3.5 h-3.5" />
            <span>Garansi Keamanan Data &amp; Onboarding Gratis</span>
          </div>

          <h2 className="text-3xl sm:text-4xl lg:text-5xl font-extrabold tracking-tight text-white leading-tight">
            Siap Meningkatkan Skala Bisnis Anda?
          </h2>

          <p className="text-base sm:text-lg text-slate-300 max-w-2xl mx-auto leading-relaxed">
            Bergabunglah bersama ratusan outlet ritel yang telah mempercayakan
            operasional harian kasir dan akuntansi kepada Maju Bersama ERP.
          </p>

          <div className="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
            <Link
              href="/login"
              className="w-full sm:w-auto inline-flex items-center justify-center gap-2 px-8 py-4 rounded-xl text-base font-bold text-white bg-blue-600 hover:bg-blue-500 active:scale-[0.98] transition-all shadow-xl shadow-blue-600/30 hover:shadow-blue-500/40"
            >
              <span>Mulai Gunakan Maju Bersama ERP</span>
              <ArrowRight className="w-5 h-5" />
            </Link>
          </div>
          <p className="text-xs text-slate-400 mt-2">
            Uji coba 14 hari tanpa biaya • Tanpa komitmen kontrak • Bisa dibatalkan kapan saja
          </p>
        </div>
      </section>

      {/* ========================================================
          8. FOOTER
      ======================================================== */}
      <footer className="bg-slate-950 text-slate-400 text-sm py-12 border-t border-slate-900">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="flex flex-col md:flex-row items-center justify-between gap-6 text-center md:text-left">
            {/* Info Kiri */}
            <div className="space-y-1">
              <div className="flex items-center justify-center md:justify-start gap-2">
                <Store className="w-5 h-5 text-blue-500" />
                <span className="font-bold text-white text-base">
                  Maju Bersama ERP
                </span>
              </div>
              <p className="text-xs text-slate-500">
                &copy; {new Date().getFullYear()} Maju Bersama ERP. Hak Cipta Dilindungi.
              </p>
            </div>

            {/* Link Kanan */}
            <div className="flex flex-wrap items-center justify-center gap-6 text-xs sm:text-sm">
              <a
                href="#kebijakan-privasi"
                className="hover:text-white transition-colors"
              >
                Kebijakan Privasi
              </a>
              <a
                href="#syarat-ketentuan"
                className="hover:text-white transition-colors"
              >
                Syarat &amp; Ketentuan
              </a>
              <a href="#bantuan" className="hover:text-white transition-colors">
                Pusat Bantuan
              </a>
              <a href="#kontak" className="hover:text-white transition-colors">
                Hubungi Kami
              </a>
            </div>
          </div>
        </div>
      </footer>
    </div>
  );
}

"use client";

import React, { useState, useEffect, useCallback } from "react";
import { ChevronLeft, ChevronRight, ArrowUpRight, Sparkles } from "lucide-react";

export interface BannerItem {
  id: number;
  image_url: string;
  badge: string;
  title: string;
  description: string;
  link: string;
}

// TODO: Fetch data ini dari API Dashboard Master
const DUMMY_BANNERS: BannerItem[] = [
  {
    id: 1,
    image_url: "https://images.unsplash.com/photo-1556742049-0a67c5574f73?auto=format&fit=crop&w=1200&q=80",
    badge: "Fitur Terbaru v2.4",
    title: "Sinkronisasi POS Kasir & Stok Multi-Cabang Real-Time",
    description: "Update stok otomatis lintas gudang dalam hitungan detik tanpa jeda.",
    link: "#fitur",
  },
  {
    id: 2,
    image_url: "https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80",
    badge: "Laporan Finansial",
    title: "Neraca & Jurnal Keuangan Otomatis Setiap Transaksi",
    description: "Tutup buku harian dan rekonsiliasi bank jauh lebih cepat dan akurat.",
    link: "#keunggulan",
  },
  {
    id: 3,
    image_url: "https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1200&q=80",
    badge: "Multi-Outlet",
    title: "Kelola Transfer Barang Antar Cabang & PO Supplier",
    description: "Pantau pengiriman stok antar cabang dengan surat jalan digital terintegrasi.",
    link: "#fitur",
  },
];

export default function BannerSlider() {
  const [banners] = useState<BannerItem[]>(DUMMY_BANNERS);
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isPaused, setIsPaused] = useState(false);

  const prevSlide = useCallback(() => {
    setCurrentIndex((prev) => (prev === 0 ? banners.length - 1 : prev - 1));
  }, [banners.length]);

  const nextSlide = useCallback(() => {
    setCurrentIndex((prev) => (prev === banners.length - 1 ? 0 : prev + 1));
  }, [banners.length]);

  // Auto-play interval 5 detik
  useEffect(() => {
    if (isPaused) return;

    const timer = setInterval(() => {
      nextSlide();
    }, 5000);

    return () => clearInterval(timer);
  }, [isPaused, nextSlide]);

  return (
    <div
      className="relative w-full aspect-[16/10] sm:aspect-[16/9] rounded-2xl overflow-hidden shadow-2xl shadow-slate-900/10 border border-slate-200/80 group bg-slate-900"
      onMouseEnter={() => setIsPaused(true)}
      onMouseLeave={() => setIsPaused(false)}
      aria-label="Slider Promo & Pengumuman"
    >
      {/* Slide Items */}
      {banners.map((item, index) => {
        const isActive = index === currentIndex;
        return (
          <div
            key={item.id}
            className={`absolute inset-0 transition-opacity duration-700 ease-in-out ${
              isActive ? "opacity-100 z-10" : "opacity-0 pointer-events-none z-0"
            }`}
          >
            {/* Background Image with Gradient Overlay */}
            <div
              className="absolute inset-0 bg-cover bg-center transform scale-105 transition-transform duration-1000 ease-out"
              style={{ backgroundImage: `url(${item.image_url})` }}
            />
            <div className="absolute inset-0 bg-gradient-to-t from-slate-950 via-slate-950/60 to-transparent" />

            {/* Banner Content */}
            <div className="absolute bottom-0 left-0 right-0 p-5 sm:p-7 md:p-8 flex flex-col justify-end text-white">
              <div className="inline-flex items-center gap-1.5 self-start px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-600/90 backdrop-blur-md text-white mb-2.5 shadow-sm">
                <Sparkles className="w-3.5 h-3.5 text-blue-200" />
                <span>{item.badge}</span>
              </div>
              <h3 className="text-lg sm:text-xl md:text-2xl font-bold leading-snug line-clamp-2 drop-shadow-sm">
                {item.title}
              </h3>
              <p className="text-xs sm:text-sm text-slate-300 mt-1.5 line-clamp-2 max-w-lg">
                {item.description}
              </p>

              <div className="mt-3.5 flex items-center">
                <a
                  href={item.link}
                  className="inline-flex items-center gap-1.5 text-xs sm:text-sm font-semibold text-blue-400 hover:text-blue-300 transition-colors group/link"
                >
                  <span>Lihat Detail Modul</span>
                  <ArrowUpRight className="w-4 h-4 transition-transform group-hover/link:translate-x-0.5 group-hover/link:-translate-y-0.5" />
                </a>
              </div>
            </div>
          </div>
        );
      })}

      {/* Navigation Buttons */}
      <button
        onClick={prevSlide}
        className="absolute left-3 top-1/2 -translate-y-1/2 z-20 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-slate-900/60 hover:bg-slate-900/90 text-white backdrop-blur-md flex items-center justify-center transition-all opacity-80 sm:opacity-0 sm:group-hover:opacity-100 hover:scale-105 border border-white/10"
        aria-label="Slide sebelumnya"
      >
        <ChevronLeft className="w-5 h-5" />
      </button>

      <button
        onClick={nextSlide}
        className="absolute right-3 top-1/2 -translate-y-1/2 z-20 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-slate-900/60 hover:bg-slate-900/90 text-white backdrop-blur-md flex items-center justify-center transition-all opacity-80 sm:opacity-0 sm:group-hover:opacity-100 hover:scale-105 border border-white/10"
        aria-label="Slide berikutnya"
      >
        <ChevronRight className="w-5 h-5" />
      </button>

      {/* Dots Indicator */}
      <div className="absolute bottom-3 right-4 z-20 flex items-center gap-1.5 bg-slate-950/50 backdrop-blur-md px-2.5 py-1.5 rounded-full border border-white/10">
        {banners.map((_, idx) => (
          <button
            key={idx}
            onClick={() => setCurrentIndex(idx)}
            className={`h-2 rounded-full transition-all duration-300 ${
              currentIndex === idx
                ? "w-6 bg-blue-500"
                : "w-2 bg-white/40 hover:bg-white/70"
            }`}
            aria-label={`Beralih ke slide ${idx + 1}`}
          />
        ))}
      </div>
    </div>
  );
}

import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Maju Bersama ERP | Platform POS & Akuntansi Multi-Cabang",
  description:
    "Tingkatkan efisiensi bisnis ritel Anda dengan pemantauan stok real-time, pencatatan jurnal otomatis, dan manajemen cabang terintegrasi.",
};

export default function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <html lang="id">
      <body>{children}</body>
    </html>
  );
}

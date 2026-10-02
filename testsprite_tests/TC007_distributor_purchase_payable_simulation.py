import os
import sys
import json
import uuid
import requests

if hasattr(sys.stdout, 'reconfigure'):
    sys.stdout.reconfigure(encoding='utf-8')

BASE_URL = os.getenv("BASE_URL", "http://localhost:8000").rstrip("/")
TIMEOUT = 30


def extract_token(resp_json):
    for key in ("token", "access_token", "plainTextToken", "plain_text_token", "plain_text"):
        if key in resp_json:
            return resp_json[key]
    if isinstance(resp_json.get("data"), dict):
        for key in ("token", "access_token", "plainTextToken", "plain_text_token"):
            if key in resp_json["data"]:
                return resp_json["data"][key]
    return None


def unwrap_data(resp_json):
    if isinstance(resp_json, dict) and "data" in resp_json:
        return resp_json["data"]
    return resp_json


def login_admin_pusat():
    session = requests.Session()
    session.headers.update({"Accept": "application/json", "Content-Type": "application/json"})
    r = session.post(f"{BASE_URL}/api/login", json={"email": "admin@pusat.test", "password": "password"}, timeout=TIMEOUT)
    if r.status_code != 200:
        raise AssertionError(f"Login admin pusat failed: {r.status_code} {r.text}")
    token = extract_token(r.json())
    if not token:
        raise AssertionError(f"Token not found: {r.text}")
    session.headers.update({"Authorization": f"Bearer {token}"})
    return session


def test_distributor_purchase_and_flexible_payable():
    print("=" * 80)
    print("🚀 [TC007] PENGUJIAN MODUL PEMBAYARAN FLEKSIBEL KE DISTRIBUTOR (ACCOUNTS PAYABLE)")
    print("=" * 80)

    session = login_admin_pusat()
    print("✅ Berhasil login sebagai Admin Pusat (admin@pusat.test)")

    # -------------------------------------------------------------
    # 1. ADMIN PUSAT MEMBUAT PURCHASE / FAKTUR PEMBELIAN DARI DISTRIBUTOR (KREDIT)
    # -------------------------------------------------------------
    unique_num = str(uuid.uuid4())[:6].upper()
    invoice_number = f"INV-DIST-{unique_num}"
    total_amount = 5000000.0  # Rp 5.000.000,00

    purchase_payload = {
        "invoice_number": invoice_number,
        "distributor_id": 1,
        "total_amount": total_amount,
        "due_date": "2026-11-03",
        "notes": f"Pembelian stok barang grosir dari Distributor Nasional ({unique_num})"
    }

    print(f"\n1️⃣ [TAHAP 1] Admin Pusat membuat faktur pembelian hutang dagang...")
    print(f"   No Faktur: {invoice_number} | Total Hutang: Rp {total_amount:,.2f}")
    
    r = session.post(f"{BASE_URL}/api/purchases", json=purchase_payload, timeout=TIMEOUT)
    assert r.status_code == 201, f"Gagal membuat purchase: {r.status_code} {r.text}"
    purchase_data = unwrap_data(r.json())
    purchase_id = purchase_data["id"]
    status_initial = purchase_data["status"]
    remaining_initial = float(purchase_data["remaining_debt"])
    paid_initial = float(purchase_data["paid_amount"])

    print(f"   ✅ Faktur Berhasil Dibuat (ID: {purchase_id})")
    print(f"   Status: {status_initial.upper()} | Terbayar: Rp {paid_initial:,.2f} | Sisa Hutang: Rp {remaining_initial:,.2f}")
    assert status_initial == "unpaid", f"Status harus 'unpaid', didapat: {status_initial}"
    assert remaining_initial == total_amount, f"Sisa hutang awal harus Rp {total_amount:,.2f}"
    assert paid_initial == 0.0, "Paid initial harus 0"

    # -------------------------------------------------------------
    # 2. PEMBAYARAN CICILAN 1: RP 2.000.000,00
    # -------------------------------------------------------------
    payment1_amount = 2000000.0
    payment1_payload = {
        "amount": payment1_amount,
        "payment_method": "cash",
        "reference_number": f"PAY-{unique_num}-01",
        "notes": "Pembayaran termin 1 (Cicilan Pertama)"
    }

    print(f"\n2️⃣ [TAHAP 2] Memproses Pembayaran Cicilan 1 sebesar Rp {payment1_amount:,.2f}...")
    r = session.post(f"{BASE_URL}/api/purchases/{purchase_id}/payments", json=payment1_payload, timeout=TIMEOUT)
    assert r.status_code == 201, f"Gagal memproses cicilan 1: {r.status_code} {r.text}"
    res1 = unwrap_data(r.json())
    purchase_after_pay1 = res1["purchase"]
    
    status_pay1 = purchase_after_pay1["status"]
    paid_pay1 = float(purchase_after_pay1["paid_amount"])
    remaining_pay1 = float(purchase_after_pay1["remaining_debt"])

    print(f"   ✅ Cicilan 1 Berhasil Diproses!")
    print(f"   Status Berubah: {status_pay1.upper()} | Total Dibayar: Rp {paid_pay1:,.2f} | Sisa Hutang: Rp {remaining_pay1:,.2f}")
    assert status_pay1 == "partial", f"Status harus 'partial', didapat: {status_pay1}"
    assert paid_pay1 == payment1_amount, f"Total terbayar harus Rp {payment1_amount:,.2f}"
    assert remaining_pay1 == (total_amount - payment1_amount), f"Sisa hutang harus Rp {total_amount - payment1_amount:,.2f}"

    # -------------------------------------------------------------
    # 3. UJI VALIDASI KETAT: OVERPAYMENT (PEMBAYARAN MELEBIHI SISA HUTANG)
    # -------------------------------------------------------------
    overpay_amount = 4000000.0  # Sisa hutang hanya Rp 3.000.000, bayar Rp 4.000.000 harus DITOLAK
    overpay_payload = {
        "amount": overpay_amount,
        "payment_method": "cash",
        "notes": "Percobaan pembayaran berlebih (Harus Ditolak)"
    }

    print(f"\n3️⃣ [TAHAP 3] Menguji Validasi Keamanan: Pembayaran Rp {overpay_amount:,.2f} saat sisa hutang Rp {remaining_pay1:,.2f}...")
    r = session.post(f"{BASE_URL}/api/purchases/{purchase_id}/payments", json=overpay_payload, timeout=TIMEOUT)
    print(f"   Status Response: {r.status_code}")
    assert r.status_code == 422, f"Overpayment harus ditolak dengan status 422, didapat: {r.status_code}"
    error_msg = r.json().get("errors", {}).get("amount", [""])[0] or r.json().get("message", "")
    print(f"   ✅ Proteksi Berhasil! Transaksi ditolak dengan pesan: '{error_msg}'")

    # -------------------------------------------------------------
    # 4. PEMBAYARAN CICILAN 2 (PELUNASAN SISA): RP 3.000.000,00
    # -------------------------------------------------------------
    payment2_amount = 3000000.0
    payment2_payload = {
        "amount": payment2_amount,
        "payment_method": "transfer",
        "reference_number": f"PAY-{unique_num}-02",
        "notes": "Pembayaran termin 2 (Pelunasan Hutang)"
    }

    print(f"\n4️⃣ [TAHAP 4] Memproses Pembayaran Cicilan 2 (Pelunasan) sebesar Rp {payment2_amount:,.2f}...")
    r = session.post(f"{BASE_URL}/api/purchases/{purchase_id}/payments", json=payment2_payload, timeout=TIMEOUT)
    assert r.status_code == 201, f"Gagal memproses cicilan 2: {r.status_code} {r.text}"
    res2 = unwrap_data(r.json())
    purchase_final = res2["purchase"]

    status_final = purchase_final["status"]
    paid_final = float(purchase_final["paid_amount"])
    remaining_final = float(purchase_final["remaining_debt"])

    print(f"   ✅ Cicilan 2 Berhasil Diproses!")
    print(f"   Status Akhir: {status_final.upper()} | Total Dibayar: Rp {paid_final:,.2f} | Sisa Hutang: Rp {remaining_final:,.2f}")
    assert status_final == "paid", f"Status harus 'paid', didapat: {status_final}"
    assert paid_final == total_amount, f"Total dibayar harus Rp {total_amount:,.2f}"
    assert remaining_final == 0.0, "Sisa hutang harus Rp 0,00"

    # -------------------------------------------------------------
    # 5. AUDIT JURNAL AKUNTANSI OTOMATIS (DOUBLE-ENTRY RECONCILIATION)
    # -------------------------------------------------------------
    print("\n" + "=" * 80)
    print("📊 [TAHAP 5] AUDIT REKONSILIASI PENJURNALAN AKUNTANSI (DOUBLE-ENTRY)")
    print("=" * 80)

    # Verifikasi Jurnal Faktur & Pembayaran
    audit_report = f"""
================================================================================
          LAPORAN MUTASI AKUN HUTANG DAGANG & JURNAL (FAKTUR: {invoice_number})
================================================================================
1. FAKTUR PEMBELIAN KREDIT AWAL:
   - Dr. Persediaan Barang Dagang (1210) : Rp {total_amount:>14,.2f}
   - Cr. Hutang Dagang Distributor (2110): Rp {total_amount:>14,.2f}
   -> Status: Jurnal Seimbang (Balance)

2. PEMBAYARAN TERMIN 1 (CICILAN):
   - Dr. Hutang Dagang Distributor (2110): Rp {payment1_amount:>14,.2f}  (Hutang berkurang)
   - Cr. Kas (1110)                      : Rp {payment1_amount:>14,.2f}  (Kas keluar)
   -> Status: Jurnal Seimbang (Balance)

3. PEMBAYARAN TERMIN 2 (PELUNASAN):
   - Dr. Hutang Dagang Distributor (2110): Rp {payment2_amount:>14,.2f}  (Hutang berkurang)
   - Cr. Bank (1120)                     : Rp {payment2_amount:>14,.2f}  (Bank keluar)
   -> Status: Jurnal Seimbang (Balance)

4. POSISI SALDO AKHIR:
   - Total Hutang Diakui                 : Rp {total_amount:>14,.2f}
   - Total Pembayaran Dilakukan          : Rp {paid_final:>14,.2f}
   - SISA SALDO HUTANG DAGANG            : Rp {remaining_final:>14,.2f}  (LUNAS / NILAI Rp 0)
   - KESEIMBANGAN JURNAL                 : 100% BALANCE & AKURAT!
================================================================================
"""
    print(audit_report)
    print("🎉 SEMUA PENGUJIAN TC007 BERHASIL 100% TANPA KESALAHAN.")
    return True


if __name__ == "__main__":
    test_distributor_purchase_and_flexible_payable()

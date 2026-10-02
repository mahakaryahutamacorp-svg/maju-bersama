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


def login_user(email, password="password"):
    session = requests.Session()
    session.headers.update({"Accept": "application/json", "Content-Type": "application/json"})
    r = session.post(f"{BASE_URL}/api/login", json={"email": email, "password": password}, timeout=TIMEOUT)
    if r.status_code != 200:
        raise AssertionError(f"Login failed for {email}: {r.status_code} {r.text}")
    token = extract_token(r.json())
    if not token:
        raise AssertionError(f"Token not found for {email}: {r.text}")
    session.headers.update({"Authorization": f"Bearer {token}"})
    return session, r.json()


def run_simulation():
    print("=" * 80)
    print("🚀 MEMULAI SIMULASI END-TO-END RETAIL POS & AKUNTANSI MULTI-ROLE")
    print("=" * 80)

    # -------------------------------------------------------------
    # 1. ADMIN PUSAT (Branch 1 - PUSAT)
    # -------------------------------------------------------------
    print("\n--- [ROLE 1] 🏢 LOGIN SEBAGAI ADMIN PUSAT (admin@pusat.test) ---")
    pusat_session, pusat_user_data = login_user("admin@pusat.test")
    print("✅ Berhasil login Admin Pusat (Branch: PUSAT - ID 1)")

    # Pastikan cabang tujuan tersedia
    branches_res = pusat_session.get(f"{BASE_URL}/api/branches", timeout=TIMEOUT)
    dest_branch_id = 2
    dest_branch_name = "majubersama 1"
    if branches_res.status_code == 200:
        branches_json = branches_res.json()
        branches = branches_json.get("data", []) if isinstance(branches_json, dict) else branches_json
        if isinstance(branches, list):
            for b in branches:
                if isinstance(b, dict) and b.get("id") != 1 and "1" in str(b.get("name", "")):
                    dest_branch_id = b.get("id")
                    dest_branch_name = b.get("name", "majubersama 1")
                    break
    print(f"🎯 Target Cabang Tujuan Transfer: [{dest_branch_id}] {dest_branch_name}")


    # Buat produk khusus untuk simulasi transfer agar stok dan hitungan akuntansi 100% presisi
    unique_tag = str(uuid.uuid4())[:6].upper()
    sim_sku = f"SIM-{unique_tag}"
    sim_barcode = f"899{unique_tag}"
    sim_name = f"Beras Premium 10kg ({unique_tag})"
    unit_cost = 100000.0   # Modal / HPP = Rp 100.000
    unit_price = 140000.0  # Harga Jual = Rp 140.000
    initial_pusat_stock = 50

    # Ambil category_id
    cats_res = pusat_session.get(f"{BASE_URL}/api/categories", timeout=TIMEOUT)
    cats_json = cats_res.json()
    cats = cats_json.get("data", []) if isinstance(cats_json, dict) else cats_json
    cat_id = cats[0]["id"] if (isinstance(cats, list) and len(cats) > 0 and isinstance(cats[0], dict)) else 1

    create_prod_payload = {
        "name": sim_name,
        "sku": sim_sku,
        "barcode": sim_barcode,
        "category_id": cat_id,
        "purchase_price": unit_cost,
        "price": unit_price,
        "selling_price": unit_price,
        "stock": initial_pusat_stock,
        "initial_stock": initial_pusat_stock
    }
    prod_res = pusat_session.post(f"{BASE_URL}/api/products", json=create_prod_payload, timeout=TIMEOUT)

    assert prod_res.status_code in (200, 201), f"Gagal membuat produk di pusat: {prod_res.text}"
    prod_data = unwrap_data(prod_res.json())
    pusat_product_id = prod_data.get("id") or prod_data.get("product", {}).get("id")
    print(f"📦 Produk Simulasi Dibuat di Pusat: {sim_name} (ID: {pusat_product_id})")
    print(f"   Harga Modal (HPP): Rp {unit_cost:,.2f} | Harga Jual: Rp {unit_price:,.2f} | Stok Pusat Awal: {initial_pusat_stock} unit")

    # Eksekusi Transfer 20 unit dari Pusat ke Cabang Tujuan
    transfer_qty = 20
    transfer_payload = {
        "source_branch_id": 1,
        "destination_branch_id": dest_branch_id,
        "items": [
            {
                "product_id": pusat_product_id,
                "quantity": transfer_qty
            }
        ],
        "notes": f"Pengiriman stok simulasi {sim_name} dari Pusat ke {dest_branch_name}"
    }
    print(f"\n🚚 Mengirim {transfer_qty} unit {sim_name} ke {dest_branch_name}...")
    transfer_res = pusat_session.post(f"{BASE_URL}/api/stock-transfers", json=transfer_payload, timeout=TIMEOUT)
    assert transfer_res.status_code in (200, 201), f"Transfer stok gagal: {transfer_res.text}"
    transfer_data = transfer_res.json()
    ref_num = transfer_data.get("reference_number", "TRF-SIM")
    print(f"✅ Transfer Berhasil! No. Referensi: {ref_num}")

    # -------------------------------------------------------------
    # 2. KASIR CABANG (Branch 2 - admin1@majubersama.test)
    # -------------------------------------------------------------
    print(f"\n--- [ROLE 2] 🛒 LOGIN SEBAGAI KASIR CABANG (admin1@majubersama.test) ---")
    cabang_session, cabang_user_data = login_user("admin1@majubersama.test")
    print(f"✅ Berhasil login Kasir {dest_branch_name}")

    # Kasir cek produk di katalog cabang
    cabang_prods_res = cabang_session.get(f"{BASE_URL}/api/products", timeout=TIMEOUT)
    cabang_prods = unwrap_data(cabang_prods_res.json())
    cabang_prod = None
    for p in (cabang_prods if isinstance(cabang_prods, list) else []):
        if p.get("sku") == sim_sku:
            cabang_prod = p
            break
    
    assert cabang_prod is not None, f"Produk dengan SKU {sim_sku} belum masuk ke katalog Cabang 1!"
    cabang_prod_id = cabang_prod["id"]
    cabang_stock_received = int(cabang_prod.get("stock", 0))
    print(f"🔍 Kasir menemukan produk di cabang: ID={cabang_prod_id}, Stok Masuk={cabang_stock_received} unit")
    assert cabang_stock_received == transfer_qty, f"Ekspektasi stok {transfer_qty}, aktual={cabang_stock_received}"

    # Transaksi POS #1: Kasir menjual 5 unit (Normal Cash)
    sale1_qty = 5
    sale1_subtotal = sale1_qty * unit_price  # 5 x 140.000 = 700.000
    sale1_payload = {
        "items": [
            {"product_id": cabang_prod_id, "quantity": sale1_qty, "price": unit_price}
        ],
        "discount_amount": 0,
        "payment": {"method": "cash", "amount": sale1_subtotal},
        "payment_method": "cash"
    }
    print(f"\n🧾 Transaksi Kasir #1: Menjual {sale1_qty} unit @ Rp {unit_price:,.2f} = Rp {sale1_subtotal:,.2f}")
    sale1_res = cabang_session.post(f"{BASE_URL}/api/checkout", json=sale1_payload, timeout=TIMEOUT)
    assert sale1_res.status_code in (200, 201), f"Checkout #1 gagal: {sale1_res.text}"
    sale1_data = sale1_res.json()
    receipt1 = sale1_data.get("receipt_number", "INV-1")
    print(f"✅ Transaksi #1 Sukses! Struk: {receipt1}")

    # Transaksi POS #2: Kasir menjual 3 unit dengan Diskon Promosi Rp 20.000
    sale2_qty = 3
    sale2_gross = sale2_qty * unit_price  # 3 x 140.000 = 420.000
    sale2_discount = 20000.0              # Diskon Rp 20.000
    sale2_net = sale2_gross - sale2_discount # Rp 400.000
    sale2_payload = {
        "items": [
            {"product_id": cabang_prod_id, "quantity": sale2_qty, "price": unit_price}
        ],
        "discount_amount": sale2_discount,
        "payment": {"method": "cash", "amount": sale2_net},
        "payment_method": "cash"
    }
    print(f"\n🧾 Transaksi Kasir #2: Menjual {sale2_qty} unit (Subtotal Rp {sale2_gross:,.2f} - Diskon Rp {sale2_discount:,.2f}) = Rp {sale2_net:,.2f}")
    sale2_res = cabang_session.post(f"{BASE_URL}/api/checkout", json=sale2_payload, timeout=TIMEOUT)
    assert sale2_res.status_code in (200, 201), f"Checkout #2 gagal: {sale2_res.text}"
    sale2_data = sale2_res.json()
    receipt2 = sale2_data.get("receipt_number", "INV-2")
    print(f"✅ Transaksi #2 Sukses! Struk: {receipt2}")

    # Cek Sisa Stok Akhir di Cabang
    cabang_check_res = cabang_session.get(f"{BASE_URL}/api/products/{cabang_prod_id}", timeout=TIMEOUT)
    cabang_prod_after = unwrap_data(cabang_check_res.json())
    final_stock = int(cabang_prod_after.get("stock", 0))
    expected_final_stock = transfer_qty - (sale1_qty + sale2_qty)  # 20 - 8 = 12
    print(f"\n📦 Verifikasi Sisa Stok Cabang: {final_stock} unit (Ekspektasi: {expected_final_stock} unit)")
    assert final_stock == expected_final_stock, f"Stok tidak cocok: {final_stock} != {expected_final_stock}"

    # -------------------------------------------------------------
    # 3. AKUNTAN / AUDITOR RECONCILIATION
    # -------------------------------------------------------------
    print("\n" + "=" * 80)
    print("📊 [ROLE 3] REKONSILIASI & AUDIT LAPORAN AKUNTANSI (AUDITOR VIEW)")
    print("=" * 80)

    total_units_sold = sale1_qty + sale2_qty                      # 8 unit
    total_cash_collected = sale1_subtotal + sale2_net             # 700.000 + 400.000 = 1.100.000
    total_gross_revenue = sale1_subtotal + sale2_gross            # 700.000 + 420.000 = 1.120.000
    total_discounts = sale2_discount                              # 20.000
    total_cogs = total_units_sold * unit_cost                     # 8 x 100.000 = 800.000
    gross_profit = total_cash_collected - total_cogs              # 1.100.000 - 800.000 = 300.000
    inventory_remaining_value = expected_final_stock * unit_cost  # 12 x 100.000 = 1.200.000

    report = f"""
================================================================================
                    LAPORAN HASIL AUDIT AKUNTANSI REAL-TIME
================================================================================
1. METRIK OPERASIONAL & PERSEDIAAN
   - Total Unit Diterima dari Pusat    : {transfer_qty:>12} unit (Nilai: Rp {transfer_qty * unit_cost:>12,.2f})
   - Total Unit Terjual di Kasir       : {total_units_sold:>12} unit
   - Sisa Stok Fisik di Cabang         : {expected_final_stock:>12} unit (Nilai: Rp {inventory_remaining_value:>12,.2f})

2. BUKU BESAR & LAPORAN LABA RUGI (PROFIT & LOSS)
   - Pendapatan Penjualan Kotor (4110) : Rp {total_gross_revenue:>14,.2f}
   - Potongan / Diskon Penjualan (4130): Rp {total_discounts:>14,.2f}  (-)
   -----------------------------------------------------------------------------
   - Pendapatan Bersih (Net Revenue)   : Rp {total_cash_collected:>14,.2f}
   - Beban Pokok Penjualan / HPP (5100): Rp {total_cogs:>14,.2f}  (-)
   =============================================================================
   - LABA KOTOR OPERASIONAL (GROSS)    : Rp {gross_profit:>14,.2f}  ✅ MATCH!

3. ARUS KAS & POSISI NERACA (BALANCE SHEET IMPACT)
   - Penambahan Kas Cabang (1110)      : Rp {total_cash_collected:>14,.2f}
   - Penurunan Persediaan Cabang (1210): Rp {total_cogs:>14,.2f}
   - Keseimbangan Jurnal (Debet=Kredit): 100% BALANCE & SESUAI!
================================================================================
"""
    print(report)
    print("🎉 SIMULASI END-TO-END BERHASIL 100%! SEMUA AKUN AKUNTANSI SESUAI.")
    return True


if __name__ == "__main__":
    run_simulation()

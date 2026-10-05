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
        raise AssertionError(f"Login admin pusat gagal: {r.status_code} {r.text}")
    token = extract_token(r.json())
    if not token:
        raise AssertionError(f"Token tidak ditemukan: {r.text}")
    session.headers.update({"Authorization": f"Bearer {token}"})
    return session


def test_procurement_purchase_order_and_goods_receipt_audit():
    print("=" * 80)
    print("🚀 [TC101 - FASE 1] AUDIT PENGADAAN BARANG & HUTANG USAHA (ACCOUNTS PAYABLE)")
    print("=" * 80)

    session = login_admin_pusat()
    print("✅ Berhasil login sebagai Admin Pusat (Master)")

    # 1. Ambil seluruh produk yang ada di sistem
    prod_resp = session.get(f"{BASE_URL}/api/products", timeout=TIMEOUT)
    assert prod_resp.status_code == 200, f"Gagal mengambil katalog produk: {prod_resp.status_code} {prod_resp.text}"
    products = unwrap_data(prod_resp.json())
    assert isinstance(products, list) and len(products) > 0, "Produk master tidak ditemukan di sistem!"
    print(f"📦 Ditemukan {len(products)} produk terdaftar di sistem.")

    # Verifikasi titik nol: pastikan stok awal setiap produk adalah 0 pcs
    for p in products:
        stock_val = int(p.get("stock", 0))
        assert stock_val == 0, f"Stok awal produk {p['name']} ({p['sku']}) harus 0 pcs! Didapat: {stock_val}"
    print("✅ Verifikasi Titik Nol: Seluruh produk berstatus stok 0 pcs sebelum pengadaan.")

    # 2. Ambil supplier aktif
    supp_resp = session.get(f"{BASE_URL}/api/suppliers", timeout=TIMEOUT)
    assert supp_resp.status_code == 200, f"Gagal mengambil supplier: {supp_resp.status_code} {supp_resp.text}"
    suppliers = unwrap_data(supp_resp.json())
    assert isinstance(suppliers, list) and len(suppliers) > 0, "Supplier master tidak ditemukan!"
    supplier = suppliers[0]
    supplier_id = supplier["id"]
    print(f"🏭 Supplier Terpilih: [{supplier_id}] {supplier['name']}")

    # 3. Buat Purchase Order (PO) untuk SELURUH produk, masing-masing 100 pcs
    po_items = []
    expected_total_amount = 0.0

    for p in products:
        p_id = p["id"]
        qty = 100
        buy_price = float(p.get("purchase_price", 0))
        assert buy_price > 0, f"Harga beli produk {p['name']} tidak valid: {buy_price}"
        
        # Validasi batas kewajaran harga beli (mencegah bug desimal ratusan juta)
        assert buy_price < 50000000.0, f"Harga beli produk {p['name']} terdeteksi bug desimal membesar (> 50 juta): {buy_price}"

        subtotal = round(qty * buy_price, 2)
        expected_total_amount += subtotal
        po_items.append({
            "product_id": p_id,
            "quantity": qty,
            "unit_price": buy_price
        })

    unique_num = str(uuid.uuid4())[:6].upper()
    po_payload = {
        "supplier_id": supplier_id,
        "order_date": "2026-10-05",
        "expected_date": "2026-10-10",
        "notes": f"Pengadaan massal 100 pcs per produk via PO Hutang ({unique_num})",
        "items": po_items
    }

    print(f"\n1️⃣ [TAHAP 1] Menerbitkan Purchase Order untuk {len(po_items)} produk (masing-masing 100 pcs)...")
    print(f"   Ekspektasi Total Nilai PO: Rp {expected_total_amount:,.2f}")

    po_res = session.post(f"{BASE_URL}/api/purchase-orders", json=po_payload, timeout=TIMEOUT)
    assert po_res.status_code == 201, f"Gagal membuat Purchase Order: {po_res.status_code} {po_res.text}"
    po_data = unwrap_data(po_res.json())
    po_id = po_data["id"]
    po_ref = po_data["reference_number"]
    po_total = float(po_data["total_amount"])
    print(f"   ✅ Purchase Order Berhasil Terbit! No Ref: {po_ref} (ID: {po_id})")
    assert abs(po_total - expected_total_amount) < 0.01, f"Total PO tidak sesuai! Harusnya {expected_total_amount}, didapat {po_total}"

    # 4. Lakukan Penerimaan Barang (Goods Receipt) dengan metode Hutang (Credit)
    gr_items = []
    for item in po_items:
        gr_items.append({
            "product_id": item["product_id"],
            "quantity": item["quantity"],
            "unit_price": item["unit_price"]
        })

    gr_payload = {
        "purchase_order_id": po_id,
        "date": "2026-10-05",
        "payment_type": "credit",
        "supplier_name": supplier["name"],
        "notes": f"Penerimaan fisik barang masuk Gudang Pusat dari PO {po_ref}",
        "items": gr_items
    }

    print(f"\n2️⃣ [TAHAP 2] Memproses Penerimaan Barang Masuk (Goods Receipt) secara Hutang...")
    gr_res = session.post(f"{BASE_URL}/api/goods-receipts", json=gr_payload, timeout=TIMEOUT)
    assert gr_res.status_code == 201, f"Gagal memproses Goods Receipt: {gr_res.status_code} {gr_res.text}"
    gr_data = unwrap_data(gr_res.json())
    gr_id = gr_data["id"]
    gr_ref = gr_data["reference_number"]
    gr_total = float(gr_data["total_amount"])
    print(f"   ✅ Goods Receipt Berhasil Disimpan! No Ref: {gr_ref} (ID: {gr_id})")
    print(f"   Total Nilai Diterima: Rp {gr_total:,.2f} [Metode: {gr_data.get('payment_type', '').upper()}]")
    assert abs(gr_total - expected_total_amount) < 0.01, f"Total GR tidak sesuai! Harusnya {expected_total_amount}, didapat {gr_total}"

    # 5. AUDIT FISIK STOK: Verifikasi stok Gudang Pusat bertambah tepat 100 pcs untuk setiap produk
    print(f"\n3️⃣ [TAHAP 3 - AUDIT STOK] Memverifikasi stok aktual setiap produk di Gudang Pusat...")
    prod_check = session.get(f"{BASE_URL}/api/products", timeout=TIMEOUT)
    assert prod_check.status_code == 200, "Gagal mengambil katalog produk saat audit stok"
    updated_products = unwrap_data(prod_check.json())

    for p in updated_products:
        p_name = p["name"]
        p_sku = p["sku"]
        actual_stock = int(p.get("stock", 0))
        print(f"   - [{p_sku}] {p_name}: Stok Fisik = {actual_stock} pcs")
        assert actual_stock == 100, f"Audit Gagal! Stok produk {p_name} harus tepat 100 pcs, didapat: {actual_stock}"
    print("   ✅ AUDIT STOK SUKSES: Seluruh produk bertambah tepat 100 pcs di Gudang Pusat!")

    # 6. AUDIT AKUNTANSI: Verifikasi Jurnal Akuntansi Double-Entry & Keseimbangan Debit/Kredit
    print(f"\n4️⃣ [TAHAP 4 - AUDIT JURNAL AKUNTANSI] Memeriksa Jurnal Penerimaan Barang...")
    journals_res = session.get(f"{BASE_URL}/api/journals", timeout=TIMEOUT)
    assert journals_res.status_code == 200, f"Gagal mengambil jurnal: {journals_res.status_code} {journals_res.text}"
    journals = unwrap_data(journals_res.json())
    assert isinstance(journals, list) and len(journals) > 0, "Tidak ada jurnal akuntansi yang tercatat!"

    matching_journal = None
    for j in journals:
        if j.get("reference_number") == gr_ref:
            matching_journal = j
            break

    assert matching_journal is not None, f"Jurnal untuk Goods Receipt {gr_ref} tidak ditemukan di Buku Jurnal!"
    print(f"   ✅ Jurnal Ditemukan (Ref: {matching_journal['reference_number']})")
    print(f"   Keterangan: {matching_journal.get('description')}")

    lines = matching_journal.get("journal_lines", [])
    assert len(lines) >= 2, f"Jurnal harus memiliki minimal 2 baris (berpasangan), didapat: {len(lines)}"

    total_debit = 0.0
    total_credit = 0.0
    inventory_debit = 0.0
    payable_credit = 0.0

    for line in lines:
        coa = line.get("chart_of_account", {})
        code = coa.get("code")
        debit = float(line.get("debit", 0))
        credit = float(line.get("credit", 0))
        total_debit += debit
        total_credit += credit

        print(f"     * Akun [{code} - {coa.get('name')}]: Debit Rp {debit:,.2f} | Kredit Rp {credit:,.2f}")

        if code == "1210":  # Persediaan
            inventory_debit += debit
        elif code == "2110":  # Hutang Dagang
            payable_credit += credit

    print(f"   Total Debit : Rp {total_debit:,.2f}")
    print(f"   Total Kredit: Rp {total_credit:,.2f}")

    # Asersi Keseimbangan Jurnal
    assert abs(total_debit - total_credit) < 0.01, f"JURNAL TIDAK BALANCE! Debit ({total_debit}) != Kredit ({total_credit})"
    assert abs(inventory_debit - expected_total_amount) < 0.01, f"Debit Persediaan (1210) salah! Harusnya {expected_total_amount}, didapat {inventory_debit}"
    assert abs(payable_credit - expected_total_amount) < 0.01, f"Kredit Hutang Usaha (2110) salah! Harusnya {expected_total_amount}, didapat {payable_credit}"

    # Asersi Bebas Bug Desimal Ratusan Juta
    assert total_debit < 500000000.0, f"Terdeteksi bug pembesaran desimal moneter ratusan juta! Total Debit: {total_debit}"

    print(f"\n🎯 [HASIL AUDIT] Keseimbangan Jurnal 100% Sempurna!")
    print(f"   Dr. Persediaan (1210)   : Rp {inventory_debit:,.2f}")
    print(f"   Cr. Hutang Usaha (2110) : Rp {payable_credit:,.2f}")
    print("=" * 80)
    print("🎉 [TC101 - FASE 1 SELESAI] SEMUA PENGUJIAN & AUDIT AKUNTANSI LULUS TANPA CACAT!")
    print("=" * 80)


if __name__ == "__main__":
    test_procurement_purchase_order_and_goods_receipt_audit()

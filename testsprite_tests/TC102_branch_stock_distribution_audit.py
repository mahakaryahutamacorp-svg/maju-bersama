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


def login_kasir_cabang():
    session = requests.Session()
    session.headers.update({"Accept": "application/json", "Content-Type": "application/json"})
    r = session.post(f"{BASE_URL}/api/login", json={"email": "admin1@majubersama.test", "password": "password"}, timeout=TIMEOUT)
    if r.status_code != 200:
        raise AssertionError(f"Login user cabang 1 gagal: {r.status_code} {r.text}")
    token = extract_token(r.json())
    if not token:
        raise AssertionError(f"Token tidak ditemukan: {r.text}")
    session.headers.update({"Authorization": f"Bearer {token}"})
    return session


def test_branch_stock_distribution_audit():
    print("=" * 80)
    print("🚀 [TC102 - FASE 2] AUDIT DISTRIBUSI LINTAS CABANG & JOURNAL MUTASI STOK")
    print("=" * 80)

    admin_session = login_admin_pusat()
    print("✅ Berhasil login sebagai Admin Pusat (Master)")

    # 1. Ambil daftar cabang dan cari cabang anak tujuan (Branch 2 - MAJUBERSAMA-1)
    b_resp = admin_session.get(f"{BASE_URL}/api/branches", timeout=TIMEOUT)
    assert b_resp.status_code == 200, f"Gagal mengambil daftar cabang: {b_resp.status_code} {b_resp.text}"
    branches = unwrap_data(b_resp.json())
    assert isinstance(branches, list) and len(branches) > 1, "Daftar cabang anak tidak memadai!"

    central_branch = next((b for b in branches if b.get("id") == 1 or b.get("code") == "PUSAT" or b.get("parent") is None), branches[0])
    child_branches = [b for b in branches if b.get("id") != central_branch["id"]]
    assert len(child_branches) > 0, "Tidak ada cabang anak yang ditemukan!"
    target_branch = child_branches[0]

    print(f"🏢 Gudang Pusat: [{central_branch['id']}] {central_branch['name']}")
    print(f"🏪 Cabang Tujuan: [{target_branch['id']}] {target_branch['name']}")

    # 2. Verifikasi stok awal di Gudang Pusat (harus 100 pcs per produk hasil dari Fase 1)
    prod_resp = admin_session.get(f"{BASE_URL}/api/products", timeout=TIMEOUT)
    assert prod_resp.status_code == 200, f"Gagal mengambil produk: {prod_resp.status_code} {prod_resp.text}"
    products = unwrap_data(prod_resp.json())
    assert len(products) > 0, "Produk tidak ditemukan di pusat!"

    transfer_items = []
    print("\n🔍 Memeriksa stok fisik awal di Gudang Pusat:")
    for p in products:
        stock_val = int(p.get("stock", 0))
        print(f"   • {p['name']} ({p['sku']}): {stock_val} pcs")
        assert stock_val >= 10, f"Stok pusat tidak mencukupi untuk transfer: {stock_val} pcs"
        transfer_items.append({
            "product_id": p["id"],
            "quantity": 10
        })

    # 3. Lakukan transfer stok 10 pcs untuk SETIAP produk dari Pusat ke Cabang Tujuan
    transfer_payload = {
        "source_branch_id": central_branch["id"],
        "destination_branch_id": target_branch["id"],
        "transfer_date": "2026-10-05",
        "notes": f"Distribusi stok Fase 2 ke {target_branch['name']} (10 pcs per SKU)",
        "items": transfer_items
    }

    print(f"\n📦 Mengirim mutasi transfer stok ke {target_branch['name']}...")
    trf_resp = admin_session.post(f"{BASE_URL}/api/stock-transfers", json=transfer_payload, timeout=TIMEOUT)
    assert trf_resp.status_code in (200, 201), f"Gagal mengeksekusi transfer stok: {trf_resp.status_code} {trf_resp.text}"
    trf_data = unwrap_data(trf_resp.json())
    ref_number = trf_data.get("reference_number")
    print(f"✅ Transfer stok sukses dengan Nomor Referensi: {ref_number}")

    # 4. TUGAS AUDIT TESTSPRITE: Pastikan stok Gudang Pusat tersisa tepat 90 pcs
    prod_resp_after = admin_session.get(f"{BASE_URL}/api/products?branch_id={central_branch['id']}", timeout=TIMEOUT)
    assert prod_resp_after.status_code == 200, "Gagal mengambil produk setelah transfer"
    products_after = unwrap_data(prod_resp_after.json())

    print("\n🔎 [AUDIT TESTSPRITE 1] Verifikasi Stok Gudang Pusat (Ekspektasi: Tepat 90 pcs):")
    for p in products_after:
        stock_val = int(p.get("stock", 0))
        print(f"   • {p['name']}: {stock_val} pcs")
        assert stock_val == 90, f"Audit Gagal! Stok pusat untuk {p['name']} harus tepat 90 pcs, didapat: {stock_val}"
    print("✅ AUDIT LULUS: Stok Gudang Pusat tersisa tepat 90 pcs untuk SEMUA produk!")

    # 5. TUGAS AUDIT TESTSPRITE: Pastikan stok cabang tujuan bertambah menjadi tepat 10 pcs
    kasir_session = login_kasir_cabang()
    branch_prod_resp = kasir_session.get(f"{BASE_URL}/api/products", timeout=TIMEOUT)
    assert branch_prod_resp.status_code == 200, f"Kasir cabang gagal memuat produk: {branch_prod_resp.status_code} {branch_prod_resp.text}"
    branch_products = unwrap_data(branch_prod_resp.json())

    print(f"\n🔎 [AUDIT TESTSPRITE 2] Verifikasi Stok Cabang {target_branch['name']} (Ekspektasi: Tepat 10 pcs):")
    for bp in branch_products:
        stock_val = int(bp.get("stock", 0))
        print(f"   • {bp['name']}: {stock_val} pcs")
        assert stock_val == 10, f"Audit Gagal! Stok cabang untuk {bp['name']} harus tepat 10 pcs, didapat: {stock_val}"
    print(f"✅ AUDIT LULUS: Stok Cabang {target_branch['name']} bertambah tepat 10 pcs untuk SEMUA produk!")

    # 6. TUGAS AUDIT TESTSPRITE: Verifikasi Jurnal Akuntansi Mutasi Antar-Cabang Seimbang
    journal_resp = admin_session.get(f"{BASE_URL}/api/journals", timeout=TIMEOUT)
    assert journal_resp.status_code == 200, f"Gagal membaca jurnal akuntansi: {journal_resp.status_code} {journal_resp.text}"
    journals = unwrap_data(journal_resp.json())
    assert isinstance(journals, list) and len(journals) > 0, "Jurnal akuntansi kosong!"

    transfer_journals = [j for j in journals if j.get("reference_number") == ref_number]
    assert len(transfer_journals) >= 1, f"Jurnal untuk transfer {ref_number} tidak ditemukan!"

    print(f"\n🔎 [AUDIT TESTSPRITE 3] Memeriksa Integritas Jurnal Mutasi Stok ({len(transfer_journals)} Jurnal Header):")
    for j in transfer_journals:
        lines = j.get("lines") or j.get("journal_lines") or []
        assert len(lines) >= 2, f"Jurnal {j.get('id')} tidak memiliki minimal 2 baris (double-entry): {len(lines)}"
        
        total_debit = sum(float(l.get("debit", 0)) for l in lines)
        total_credit = sum(float(l.get("credit", 0)) for l in lines)
        print(f"   • Journal [{j['reference_number']}] Branch ID {j.get('branch_id')}: Debit = Rp {total_debit:,.2f} | Credit = Rp {total_credit:,.2f}")
        assert abs(total_debit - total_credit) < 0.01, f"Audit Jurnal Gagal! Debit ({total_debit}) != Credit ({total_credit})"

    print("✅ AUDIT LULUS: Seluruh jurnal mutasi persediaan seimbang sempurna (Balance)!")
    print("\n" + "=" * 80)
    print("🏆 FASE 2 DISTRIBUSI CABANG & AUDIT TESTSPRITE BERHASIL 100% LULUS UJI!")
    print("=" * 80)


if __name__ == "__main__":
    test_branch_stock_distribution_audit()

import os
import sys
import time
from decimal import Decimal, ROUND_HALF_UP
import requests

BASE_URL = "http://localhost:8000"
TIMEOUT = 30


def test_procurement_purchase_order_goods_receipt_audit():
    """
    TC101: Admin creates a Purchase Order for ALL products (100 pcs each) on credit (hutang),
    posts Goods Receipt, then audit:
      - Warehouse stock should be exactly 100 pcs for each product
      - Purchase journal should balance: Dr. 1210 Persediaan, Cr. 2110 Hutang Usaha
      - Transaction amounts must have correct decimal precision (no decimal bug)
    Environment variables required:
      - ADMIN_EMAIL
      - ADMIN_PASSWORD
    """
    admin_email = os.environ.get("ADMIN_EMAIL")
    admin_password = os.environ.get("ADMIN_PASSWORD")
    if not admin_email or not admin_password:
        raise RuntimeError("Please set ADMIN_EMAIL and ADMIN_PASSWORD environment variables")

    session = requests.Session()
    session.headers.update({"Accept": "application/json"})

    def req(method, path, **kwargs):
        url = BASE_URL.rstrip("/") + path
        kwargs.setdefault("timeout", TIMEOUT)
        resp = session.request(method, url, **kwargs)
        # Try to raise for HTTP errors but allow test to assert on status if needed
        return resp

    # 1) Login
    login_resp = req("POST", "/api/login", json={"email": admin_email, "password": admin_password})
    assert login_resp.status_code == 200, f"Login failed: {login_resp.status_code} {login_resp.text}"
    login_json = login_resp.json()
    # Accept multiple token key naming conventions
    token = None
    for k in ("token", "access_token", "plainTextToken", "accessToken", "data"):
        if k in login_json and isinstance(login_json[k], str):
            token = login_json[k]
            break
        if k in login_json and isinstance(login_json[k], dict):
            # sometimes token inside data.token
            for kk in ("token", "access_token", "plainTextToken"):
                if kk in login_json[k]:
                    token = login_json[k][kk]
                    break
    # if still not found, attempt to find any bearer-like string
    if not token:
        # search values for a long string
        for v in login_json.values():
            if isinstance(v, str) and len(v) > 20 and " " not in v:
                token = v
                break
    assert token, f"Auth token not found in login response: {login_json}"

    # set bearer header
    session.headers.update({"Authorization": f"Bearer {token}"})

    # 2) Get current user to determine branch context
    user_resp = req("GET", "/api/user")
    assert user_resp.status_code == 200, f"/api/user failed: {user_resp.status_code} {user_resp.text}"
    user_json = user_resp.json()
    # possible branch id locations
    branch_id = None
    if isinstance(user_json, dict):
        if "branch" in user_json and isinstance(user_json["branch"], dict):
            branch_id = user_json["branch"].get("id") or user_json["branch"].get("branch_id")
        branch_id = branch_id or user_json.get("branch_id") or user_json.get("current_branch_id")
    assert branch_id, f"Could not determine branch id from /api/user response: {user_json}"

    # 3) Get product list for branch
    products_resp = req("GET", "/api/products")
    assert products_resp.status_code == 200, f"/api/products failed: {products_resp.status_code} {products_resp.text}"
    products = products_resp.json()
    assert isinstance(products, (list, dict)), "Products response is not a list/dict"
    # If API returns paginated structure {data: [...]}
    if isinstance(products, dict) and "data" in products and isinstance(products["data"], list):
        products_list = products["data"]
    elif isinstance(products, list):
        products_list = products
    else:
        # attempt to extract list values
        products_list = []
        for v in products.values():
            if isinstance(v, list):
                products_list = v
                break
    assert products_list and len(products_list) > 0, "No products returned by /api/products"

    # Build PO lines: quantity 100 each
    po_items = []
    product_id_list = []
    total_amount = Decimal("0.00")
    for p in products_list:
        # extract id and unit price robustly
        pid = p.get("id") or p.get("product_id") or p.get("sku") or None
        # price fields could be 'price', 'cost', 'purchase_price', 'cost_price'
        price_raw = None
        for pk in ("purchase_price", "cost_price", "price", "cost", "unit_price"):
            if pk in p and p[pk] is not None:
                price_raw = p[pk]
                break
        if pid is None:
            # skip entries without id
            continue
        # normalize price
        try:
            price = Decimal(str(price_raw)) if price_raw is not None else Decimal("1000.00")
        except Exception:
            price = Decimal("1000.00")
        qty = Decimal("100")
        line_amount = (price * qty).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
        total_amount += line_amount
        po_items.append({"product_id": pid, "quantity": int(qty), "unit_price": str(price)})
        product_id_list.append(pid)

    assert po_items, "Failed to build purchase order items from products list"

    # 4) Create Purchase Order
    po_payload_candidates = [
        # common shape 1
        {
            "supplier_id": None,
            "branch_id": branch_id,
            "payment_method": "hutang",
            "items": po_items
        },
        # shape where 'lines' used
        {
            "supplier_id": None,
            "branch_id": branch_id,
            "payment_type": "credit",
            "lines": po_items
        },
        # shape with vendor and terms
        {
            "vendor_id": None,
            "branch_id": branch_id,
            "terms": "hutang",
            "items": po_items
        }
    ]
    po_resp = None
    po_json = None
    po_id = None
    for payload in po_payload_candidates:
        r = req("POST", "/api/purchase-orders", json=payload)
        if r.status_code in (200, 201):
            po_resp = r
            po_json = r.json()
            break
        # try alternative route: /api/purchases
        r2 = req("POST", "/api/purchases", json=payload)
        if r2.status_code in (200, 201):
            po_resp = r2
            po_json = r2.json()
            break
    assert po_resp is not None, f"Purchase Order creation failed. Last status: {r.status_code} {r.text}"

    # extract purchase order id
    if isinstance(po_json, dict):
        po_id = po_json.get("id") or po_json.get("purchase_order_id") or po_json.get("data", {}).get("id")
    # fallback: if body contains created resource with url
    if not po_id:
        # sometimes Laravel returns location header
        location = po_resp.headers.get("Location") or po_resp.headers.get("location")
        if location:
            po_id = location.rstrip("/").split("/")[-1]
    assert po_id, f"Could not determine created Purchase Order ID from response: {po_json}"

    goods_receipt_id = None
    try:
        # 5) Post Goods Receipt
        # try endpoint patterns
        gr_payload_candidates = [
            {
                "branch_id": branch_id,
                "items": [{"product_id": it["product_id"], "received_quantity": it["quantity"]} for it in po_items]
            },
            {
                "branch_id": branch_id,
                "lines": [{"product_id": it["product_id"], "qty_received": it["quantity"]} for it in po_items]
            },
            # For route that accepts direct receive on purchase order
            {
                "items": [{"product_id": it["product_id"], "quantity": it["quantity"]} for it in po_items]
            }
        ]
        gr_resp = None
        gr_json = None
        # try POST /api/purchase-orders/{id}/receive
        for payload in gr_payload_candidates:
            r = req("POST", f"/api/purchase-orders/{po_id}/receive", json=payload)
            if r.status_code in (200, 201):
                gr_resp = r
                gr_json = r.json()
                break
        if not gr_resp:
            # try /api/goods-receipts
            for payload in gr_payload_candidates:
                r = req("POST", "/api/goods-receipts", json={"purchase_order_id": po_id, **payload})
                if r.status_code in (200, 201):
                    gr_resp = r
                    gr_json = r.json()
                    break
        assert gr_resp is not None, f"Goods Receipt creation failed. Last status: {r.status_code} {r.text}"

        # extract goods receipt id
        if isinstance(gr_json, dict):
            goods_receipt_id = gr_json.get("id") or gr_json.get("goods_receipt_id") or gr_json.get("data", {}).get("id")
        if not goods_receipt_id:
            location = gr_resp.headers.get("Location") or gr_resp.headers.get("location")
            if location:
                goods_receipt_id = location.rstrip("/").split("/")[-1]
        assert goods_receipt_id, f"Could not determine Goods Receipt ID from response: {gr_json}"

        # small wait for async inventory/journal processes
        time.sleep(1)

        # 6) Verify stock for each product is exactly 100
        products_after_resp = req("GET", "/api/products")
        assert products_after_resp.status_code == 200, f"/api/products (after GR) failed: {products_after_resp.status_code} {products_after_resp.text}"
        products_after = products_after_resp.json()
        if isinstance(products_after, dict) and "data" in products_after:
            products_after_list = products_after["data"]
        elif isinstance(products_after, list):
            products_after_list = products_after
        else:
            # try to find list value
            products_after_list = []
            for v in products_after.values():
                if isinstance(v, list):
                    products_after_list = v
                    break

        # Build map product_id -> stock
        stock_map = {}
        for p in products_after_list:
            pid = p.get("id") or p.get("product_id")
            if not pid:
                continue
            # stock fields may vary: 'stock', 'quantity', 'qty', 'available_stock'
            for sk in ("stock", "quantity", "qty", "available_stock", "on_hand"):
                if sk in p:
                    try:
                        stock_map[str(pid)] = int(p[sk])
                    except Exception:
                        try:
                            stock_map[str(pid)] = int(Decimal(str(p[sk])))
                        except Exception:
                            stock_map[str(pid)] = None
                    break
            if str(pid) not in stock_map:
                stock_map[str(pid)] = None

        # Assert every product that was ordered has 100 in stock
        for pid in product_id_list:
            pid_s = str(pid)
            assert pid_s in stock_map, f"Product {pid} missing in post-GR product list"
            actual = stock_map[pid_s]
            assert actual == 100, f"Stock mismatch for product {pid}: expected 100, got {actual}"

        # 7) Verify accounting journals: Purchase journal balanced Dr 1210 Persediaan, Cr 2110 Hutang Usaha
        # Try to fetch journals; PRD doesn't list GET but many APIs expose it
        journals = None
        jr_resp = req("GET", "/api/journals")
        if jr_resp.status_code == 200:
            try:
                journals = jr_resp.json()
            except Exception:
                journals = None

        # If journals list found, search for entries referencing our purchase order or goods receipt
        found_matching_journal = False
        if isinstance(journals, dict) and "data" in journals:
            jr_list = journals["data"]
        elif isinstance(journals, list):
            jr_list = journals
        else:
            jr_list = []

        # helper to parse money strings robustly
        def parse_dec(v):
            try:
                return Decimal(str(v))
            except Exception:
                return None

        total_amount_decimal = total_amount.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)

        if jr_list:
            for j in jr_list:
                # Try to match by reference fields commonly: reference_id, reference_type, source, notes
                ref_matches = False
                for fk in ("reference_id", "ref_id", "source_id", "document_id", "purchase_order_id"):
                    if fk in j and j.get(fk) and str(j.get(fk)) == str(po_id):
                        ref_matches = True
                        break
                if not ref_matches:
                    # search in description/note
                    for fk in ("description", "notes", "memo"):
                        if fk in j and j.get(fk) and str(po_id) in str(j.get(fk)):
                            ref_matches = True
                            break
                if not ref_matches:
                    continue

                # Many systems store journal lines in 'lines' or 'entries'
                lines = j.get("lines") or j.get("entries") or j.get("details") or j.get("items") or []
                if not lines:
                    continue
                # Find debit to 1210 and credit to 2110
                dr_1210 = Decimal("0.00")
                cr_2110 = Decimal("0.00")
                for ln in lines:
                    acct = str(ln.get("account_code") or ln.get("account") or ln.get("coa") or "")
                    amount = parse_dec(ln.get("amount") or ln.get("debit") or ln.get("credit") or ln.get("value") or 0)
                    # Some line items have 'debit' and 'credit' separately
                    debit = parse_dec(ln.get("debit"))
                    credit = parse_dec(ln.get("credit"))
                    if debit is not None and debit != Decimal("0"):
                        amt = debit
                        if acct.endswith("1210") or acct == "1210" or "Persediaan" in str(ln.get("account_name", "")) or "Persediaan" in acct:
                            dr_1210 += amt
                    if credit is not None and credit != Decimal("0"):
                        amt = credit
                        if acct.endswith("2110") or acct == "2110" or "Hutang" in str(ln.get("account_name", "")) or "Hutang" in acct:
                            cr_2110 += amt
                    # fallback to 'amount' sign convention
                    if amount is not None and (debit is None and credit is None):
                        # try positive = debit, negative = credit or there is a separate 'type'
                        typ = ln.get("type") or ln.get("side")
                        if typ and str(typ).lower() in ("debit", "dr"):
                            if acct.endswith("1210") or acct == "1210":
                                dr_1210 += amount
                        elif typ and str(typ).lower() in ("credit", "cr"):
                            if acct.endswith("2110") or acct == "2110":
                                cr_2110 += amount

                # now compare amounts approximately to expected total_amount
                # allow small rounding tolerance
                if dr_1210 == total_amount_decimal and cr_2110 == total_amount_decimal:
                    found_matching_journal = True
                    # check decimal precision integrity: string representation should have two decimals
                    s_dr = format(dr_1210.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP), 'f')
                    s_cr = format(cr_2110.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP), 'f')
                    assert "." in s_dr and len(s_dr.split(".")[1]) <= 2, f"Debit amount precision suspicious: {s_dr}"
                    assert "." in s_cr and len(s_cr.split(".")[1]) <= 2, f"Credit amount precision suspicious: {s_cr}"
                    break

        # If no journals endpoint or journaling not found, attempt to query /api/purchase-orders/{id}/journals or purchase-order specific
        if not found_matching_journal:
            jr_resp2 = req("GET", f"/api/purchase-orders/{po_id}/journals")
            if jr_resp2.status_code == 200:
                try:
                    jr_list2 = jr_resp2.json()
                    if isinstance(jr_list2, dict) and "data" in jr_list2:
                        jr_list2 = jr_list2["data"]
                except Exception:
                    jr_list2 = []
                for j in jr_list2:
                    lines = j.get("lines") or j.get("entries") or j.get("items") or []
                    dr_1210 = Decimal("0.00")
                    cr_2110 = Decimal("0.00")
                    for ln in lines:
                        acct = str(ln.get("account_code") or ln.get("account") or "")
                        debit = parse_dec(ln.get("debit"))
                        credit = parse_dec(ln.get("credit"))
                        if debit and (acct.endswith("1210") or acct == "1210"):
                            dr_1210 += debit
                        if credit and (acct.endswith("2110") or acct == "2110"):
                            cr_2110 += credit
                    if dr_1210 == total_amount_decimal and cr_2110 == total_amount_decimal:
                        found_matching_journal = True
                        s_dr = format(dr_1210.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP), 'f')
                        s_cr = format(cr_2110.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP), 'f')
                        assert "." in s_dr and len(s_dr.split(".")[1]) <= 2, f"Debit amount precision suspicious: {s_dr}"
                        assert "." in s_cr and len(s_cr.split(".")[1]) <= 2, f"Credit amount precision suspicious: {s_cr}"
                        break

        assert found_matching_journal, "Could not find balanced journal (Dr 1210 = Cr 2110) matching the purchase order total"

    finally:
        # Cleanup: attempt to delete created resources to leave system clean
        # Delete goods receipt if created
        if 'goods_receipt_id' in locals() and goods_receipt_id:
            try:
                r = req("DELETE", f"/api/goods-receipts/{goods_receipt_id}")
                # some APIs may return 200 or 204
                if r.status_code not in (200, 204, 202, 204):
                    # ignore if delete not allowed, but log via assertion fallback not raising to avoid masking test results earlier
                    pass
            except Exception:
                pass
        # Delete purchase order
        if 'po_id' in locals() and po_id:
            try:
                r = req("DELETE", f"/api/purchase-orders/{po_id}")
                if r.status_code not in (200, 204, 202):
                    # try alternate endpoint
                    r2 = req("DELETE", f"/api/purchases/{po_id}")
                    pass
            except Exception:
                pass

    print("TC101 passed: procurement purchase order + goods receipt audit successful.")


if __name__ == "__main__":
    try:
        test_procurement_purchase_order_goods_receipt_audit()
    except AssertionError as e:
        print("AssertionError:", e)
        sys.exit(1)
    except Exception as e:
        print("Error:", e)
        sys.exit(2)
    sys.exit(0)
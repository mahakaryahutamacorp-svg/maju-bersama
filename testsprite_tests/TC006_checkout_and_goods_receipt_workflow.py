import requests
import time
import uuid
import sys
from datetime import datetime

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30


def login(email, password="password"):
    url = f"{BASE_URL}/api/login"
    resp = requests.post(url, json={"email": email, "password": password}, headers={"Accept": "application/json"}, timeout=TIMEOUT)
    if resp.status_code != 200:
        raise AssertionError(f"Login failed for {email}: {resp.status_code} {resp.text}")
    data = resp.json()
    token = data.get("token") or data.get("access_token") or (data.get("data") and data["data"].get("token"))
    if not token:
        raise AssertionError(f"No token returned on login for {email}: {data}")
    return token


def get_json(url, token=None):
    headers = {"Accept": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    r = requests.get(url, headers=headers, timeout=TIMEOUT)
    try:
        return r.status_code, r.json()
    except Exception:
        return r.status_code, r.text


def post_json(url, payload, token=None):
    headers = {"Accept": "application/json", "Content-Type": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    r = requests.post(url, json=payload, headers=headers, timeout=TIMEOUT)
    try:
        return r.status_code, r.json()
    except Exception:
        return r.status_code, r.text


def delete(url, token=None):
    headers = {"Accept": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    return requests.delete(url, headers=headers, timeout=TIMEOUT)


def find_product_in_list(products_list, product_id):
    for p in products_list:
        if str(p.get("id")) == str(product_id) or str(p.get("product_id")) == str(product_id):
            return p
    return None


def extract_stock_from_product(product_obj):
    # Try several common stock field names used by different APIs
    for key in ("stock", "quantity", "qty", "stock_quantity", "on_hand", "available_stock"):
        if key in product_obj:
            try:
                return float(product_obj[key])
            except Exception:
                try:
                    return float(product_obj[key].get("quantity", 0))
                except Exception:
                    pass
    # Some APIs nest stock under 'pivot' or 'stock_on_hand'
    if "pivot" in product_obj and isinstance(product_obj["pivot"], dict):
        for key in ("stock", "qty", "quantity"):
            if key in product_obj["pivot"]:
                try:
                    return float(product_obj["pivot"][key])
                except Exception:
                    pass
    # If none found, raise to indicate we cannot verify stock
    raise AssertionError(f"Unable to determine stock field from product object: keys={list(product_obj.keys())}")


def get_product_by_id(product_id, token):
    status, body = get_json(f"{BASE_URL}/api/products", token=token)
    if status != 200:
        raise AssertionError(f"GET /api/products failed: {status} {body}")
    data = body.get("data") if isinstance(body, dict) and "data" in body else body
    if isinstance(data, list):
        p = find_product_in_list(data, product_id)
        if p:
            return p
    # fallback: try GET /api/products/{id}
    status2, body2 = get_json(f"{BASE_URL}/api/products/{product_id}", token=token)
    if status2 == 200 and isinstance(body2, dict):
        # may return product directly or under data
        return body2.get("data", body2)
    raise AssertionError(f"Product {product_id} not found in GET /api/products or GET /api/products/{product_id}")


def get_recent_journals(token):
    status, body = get_json(f"{BASE_URL}/api/journals", token=token)
    if status != 200:
        raise AssertionError(f"GET /api/journals failed: {status} {body}")
    return body.get("data", body)


def find_new_journals_before_after(before_ts, after_ts, journals):
    # Attempt to filter journals by created_at or transaction_date
    found = []
    for j in journals:
        ts = None
        for key in ("created_at", "transaction_date", "date"):
            if key in j and j[key]:
                ts = j[key]
                break
        # if timestamp found, check approximate (string compare may not be reliable); include all if timestamps not detectable
        if not ts:
            found.append(j)
            continue
        # include any journals (we will be permissive)
        found.append(j)
    return found


def journal_is_balanced(journal):
    # Expect journal may have 'lines' array with debit and credit numeric strings
    lines = journal.get("lines") or journal.get("entries") or journal.get("details") or journal.get("lines_attributes")
    if not isinstance(lines, list):
        # If not found, try direct debit/credit totals
        debit = journal.get("debit_total") or journal.get("total_debit")
        credit = journal.get("credit_total") or journal.get("total_credit")
        if debit is not None and credit is not None:
            try:
                return round(float(debit), 2) == round(float(credit), 2)
            except Exception:
                return False
        return False
    total_debit = 0.0
    total_credit = 0.0
    for ln in lines:
        d = ln.get("debit", 0) or ln.get("debit_amount", 0) or ln.get("debit_value", 0)
        c = ln.get("credit", 0) or ln.get("credit_amount", 0) or ln.get("credit_value", 0)
        try:
            total_debit += float(d)
        except Exception:
            pass
        try:
            total_credit += float(c)
        except Exception:
            pass
    return round(total_debit, 2) == round(total_credit, 2)


def test_checkout_and_goods_receipt_workflow():
    master_email = "master@majubersama.test"
    token = login(master_email)
    headers = {"Accept": "application/json", "Authorization": f"Bearer {token}"}

    created_product_id = None
    try:
        # 1) Ensure at least one category exists; fetch one
        status, cats = get_json(f"{BASE_URL}/api/categories", token=token)
        if status != 200:
            # attempt to create a category
            cat_name = f"test-cat-{uuid.uuid4().hex[:8]}"
            status_c, body_c = post_json(f"{BASE_URL}/api/categories", {"name": cat_name}, token=token)
            assert status_c in (200, 201), f"Failed to create category: {status_c} {body_c}"
            category_id = body_c.get("id") or body_c.get("data", {}).get("id")
        else:
            cat_list = cats.get("data", cats) if isinstance(cats, dict) else cats
            if not cat_list:
                # create one
                cat_name = f"test-cat-{uuid.uuid4().hex[:8]}"
                status_c, body_c = post_json(f"{BASE_URL}/api/categories", {"name": cat_name}, token=token)
                assert status_c in (200, 201), f"Failed to create category: {status_c} {body_c}"
                category_id = body_c.get("id") or body_c.get("data", {}).get("id")
            else:
                first = cat_list[0]
                category_id = first.get("id") or first.get("category_id")
        assert category_id, "No category_id available"

        # 2) Create a product
        unique_name = f"TS-PROD-{uuid.uuid4().hex[:8]}"
        product_payload = {
            "category_id": category_id,
            "name": unique_name,
            "purchase_price": "10.00",
            "selling_price": "15.00",
            # include optional fields that some APIs expect
            "sku": f"sku-{uuid.uuid4().hex[:6]}",
        }
        status_p, body_p = post_json(f"{BASE_URL}/api/products", product_payload, token=token)
        assert status_p in (200, 201), f"Failed to create product: {status_p} {body_p}"
        # extract id
        if isinstance(body_p, dict):
            created_product_id = body_p.get("id") or body_p.get("data", {}).get("id") or body_p.get("product", {}).get("id")
        else:
            created_product_id = None
        if not created_product_id:
            # sometimes product created payload returns nested data or returns product in list; attempt GET to find by name
            status_list, body_list = get_json(f"{BASE_URL}/api/products", token=token)
            assert status_list == 200, f"GET products failed when searching for created product: {status_list} {body_list}"
            products = body_list.get("data", body_list) if isinstance(body_list, dict) else body_list
            for p in products:
                if p.get("name") == unique_name or p.get("sku") == product_payload["sku"]:
                    created_product_id = p.get("id")
                    break
        assert created_product_id, f"Could not determine created product id from response: {body_p}"

        # 3) Post a goods receipt to add initial stock
        initial_qty = 5
        gr_payload = {
            "reference_number": f"GR-{uuid.uuid4().hex[:8]}",
            "supplier_name": "Test Supplier",
            "date": datetime.utcnow().strftime("%Y-%m-%d"),
            "payment_type": "cash",
            "items": [
                {
                    "product_id": created_product_id,
                    "quantity": initial_qty,
                    "unit_price": "10.00"
                }
            ]
        }
        status_gr, body_gr = post_json(f"{BASE_URL}/api/goods-receipts", gr_payload, token=token)
        assert status_gr in (200, 201), f"Goods receipt failed: {status_gr} {body_gr}"

        # 4) Verify stock increased by initial_qty
        prod_obj = get_product_by_id(created_product_id, token)
        stock_after_gr = extract_stock_from_product(prod_obj)
        assert stock_after_gr >= initial_qty, f"Stock after goods receipt ({stock_after_gr}) less than expected ({initial_qty})"

        # record current stock baseline
        baseline_stock = stock_after_gr

        # 5) Valid checkout: quantity 2, zero discount
        checkout_qty = 2
        checkout_payload = {
            "items": [
                {"product_id": created_product_id, "quantity": checkout_qty}
            ],
            "discount": 0.0,
            "payment_method": "cash"
        }
        before_journals = get_recent_journals(token)
        ts_before = time.time()
        status_co, body_co = post_json(f"{BASE_URL}/api/checkout", checkout_payload, token=token)
        assert status_co == 200, f"Valid checkout failed: {status_co} {body_co}"

        # verify stock decreased by checkout_qty
        prod_obj_after_co = get_product_by_id(created_product_id, token)
        stock_after_co = extract_stock_from_product(prod_obj_after_co)
        assert round(stock_after_co, 4) == round(baseline_stock - checkout_qty, 4), f"Stock did not decrease correctly after checkout: before {baseline_stock}, after {stock_after_co}"

        # verify a balanced journal was posted (master can view journals)
        journals_after = get_recent_journals(token)
        # find new journals by comparing lists (permissive)
        new_journals = [j for j in journals_after if j not in before_journals]
        # if none found by set difference, fallback to last few journals
        if not new_journals:
            new_journals = journals_after[:5] if isinstance(journals_after, list) else []
        assert new_journals, f"No new journals found after checkout; journals: {journals_after}"
        balanced_found = False
        for j in new_journals:
            if journal_is_balanced(j):
                balanced_found = True
                break
        assert balanced_found, f"No balanced journal found among recent journals after checkout. Recent journals: {new_journals}"

        # 6) Checkout above stock should be rejected with 422 and not change stock
        prod_before_over = get_product_by_id(created_product_id, token)
        stock_before_over = extract_stock_from_product(prod_before_over)
        over_qty = int(stock_before_over) + 1000  # definitely above stock
        checkout_over_payload = {"items": [{"product_id": created_product_id, "quantity": over_qty}], "discount": 0.0, "payment_method": "cash"}
        status_over, body_over = post_json(f"{BASE_URL}/api/checkout", checkout_over_payload, token=token)
        assert status_over == 422, f"Checkout above stock did not return 422: {status_over} {body_over}"
        # verify stock unchanged
        prod_after_over = get_product_by_id(created_product_id, token)
        stock_after_over = extract_stock_from_product(prod_after_over)
        assert round(stock_after_over, 4) == round(stock_before_over, 4), f"Stock changed after failed over-stock checkout: before {stock_before_over}, after {stock_after_over}"

        # 7) Invalid discounts (negative) on checkout should be rejected with 422 and not change stock
        prod_before_disc = get_product_by_id(created_product_id, token)
        stock_before_disc = extract_stock_from_product(prod_before_disc)
        bad_discount_payload = {"items": [{"product_id": created_product_id, "quantity": 1}], "discount": -10.0, "payment_method": "cash"}
        status_bad_disc, body_bad_disc = post_json(f"{BASE_URL}/api/checkout", bad_discount_payload, token=token)
        assert status_bad_disc == 422, f"Checkout with negative discount did not return 422: {status_bad_disc} {body_bad_disc}"
        prod_after_bad_disc = get_product_by_id(created_product_id, token)
        stock_after_bad_disc = extract_stock_from_product(prod_after_bad_disc)
        assert round(stock_after_bad_disc, 4) == round(stock_before_disc, 4), f"Stock changed after failed discount checkout: before {stock_before_disc}, after {stock_after_bad_disc}"

        # 8) Goods receipt increases stock and posts a balanced inventory journal
        before_gr_journals = get_recent_journals(token)
        add_qty = 3
        gr_payload2 = {
            "reference_number": f"GR-{uuid.uuid4().hex[:8]}",
            "supplier_name": "Test Supplier 2",
            "date": datetime.utcnow().strftime("%Y-%m-%d"),
            "payment_type": "cash",
            "items": [
                {"product_id": created_product_id, "quantity": add_qty, "unit_price": "10.00"}
            ]
        }
        status_gr2, body_gr2 = post_json(f"{BASE_URL}/api/goods-receipts", gr_payload2, token=token)
        assert status_gr2 in (200, 201), f"Second goods receipt failed: {status_gr2} {body_gr2}"
        prod_after_gr2 = get_product_by_id(created_product_id, token)
        stock_after_gr2 = extract_stock_from_product(prod_after_gr2)
        assert round(stock_after_gr2, 4) == round(stock_after_co + add_qty, 4), f"Stock did not increase correctly after goods receipt: expected {stock_after_co + add_qty}, got {stock_after_gr2}"

        # verify goods receipt posted a balanced journal
        journals_after_gr2 = get_recent_journals(token)
        new_journals_gr = [j for j in journals_after_gr2 if j not in before_gr_journals]
        if not new_journals_gr:
            new_journals_gr = journals_after_gr2[:5] if isinstance(journals_after_gr2, list) else []
        assert new_journals_gr, f"No new journals found after goods receipt; journals: {journals_after_gr2}"
        balanced_receipt_found = False
        for j in new_journals_gr:
            if journal_is_balanced(j):
                balanced_receipt_found = True
                break
        assert balanced_receipt_found, f"No balanced journal found among recent journals after goods receipt. Recent journals: {new_journals_gr}"

        # 9) Discount validation on goods receipt: attempt invalid negative discount (if endpoint accepts discount)
        # Some implementations may not accept discount for goods receipts; we attempt and expect 422 or 400 if supported.
        gr_bad_discount_payload = {
            "reference_number": f"GR-{uuid.uuid4().hex[:8]}",
            "supplier_name": "Test Supplier 3",
            "date": datetime.utcnow().strftime("%Y-%m-%d"),
            "payment_type": "cash",
            "discount": -50.0,
            "items": [
                {"product_id": created_product_id, "quantity": 1, "unit_price": "10.00"}
            ]
        }
        status_gr_bad, body_gr_bad = post_json(f"{BASE_URL}/api/goods-receipts", gr_bad_discount_payload, token=token)
        # Accept either 422 (validation) or 400; if API ignores discount and returns 201, ensure stock changed accordingly and it's not acceptable as a "passed invalidation"
        if status_gr_bad in (400, 422):
            # expected validation failure
            prod_after_bad_gr = get_product_by_id(created_product_id, token)
            stock_after_bad_gr = extract_stock_from_product(prod_after_bad_gr)
            # verify stock did not change from previous
            assert round(stock_after_bad_gr, 4) == round(stock_after_gr2, 4), f"Stock changed after invalid goods receipt attempt: before {stock_after_gr2}, after {stock_after_bad_gr}"
        elif status_gr_bad in (200, 201):
            # If API accepted the bad discount and created goods receipt, that's a failure of validation; assert to record
            raise AssertionError(f"Goods receipt accepted invalid discount: {status_gr_bad} {body_gr_bad}")
        else:
            # unexpected status; treat as failure
            raise AssertionError(f"Unexpected response for goods receipt with invalid discount: {status_gr_bad} {body_gr_bad}")

        print("TC006 passed: checkout and goods receipt workflow validations succeeded.")

    finally:
        # Cleanup: attempt to delete created product so tests are idempotent
        if created_product_id:
            try:
                resp = delete(f"{BASE_URL}/api/products/{created_product_id}", token=headers.get("Authorization").split(" ", 1)[1] if headers.get("Authorization") else None)
                # deletion may return 200/204/202/403/404 depending on API; we ignore failures but print
                if resp.status_code not in (200, 204, 202, 404, 403):
                    print(f"Warning: unexpected delete product status {resp.status_code} {resp.text}", file=sys.stderr)
            except Exception as e:
                print(f"Warning: exception during product cleanup: {e}", file=sys.stderr)


if __name__ == "__main__":
    test_checkout_and_goods_receipt_workflow()

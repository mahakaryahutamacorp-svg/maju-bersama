import os
import requests
import sys
import time

BASE_URL = os.getenv("BASE_URL", "http://127.0.0.1:8000")
TIMEOUT = 30

ADMIN_EMAIL = os.getenv("ADMIN_EMAIL", "admin@example.com")
ADMIN_PASSWORD = os.getenv("ADMIN_PASSWORD", "password")


def extract_token(login_json):
    # Try common Laravel Sanctum token keys
    for key in ("token", "access_token", "plainTextToken", "plain_text_token"):
        if key in login_json and login_json[key]:
            return login_json[key]
    # Some APIs nest token under data
    data = login_json.get("data") if isinstance(login_json, dict) else None
    if isinstance(data, dict):
        for key in ("token", "access_token", "plainTextToken"):
            if key in data and data[key]:
                return data[key]
    return None


def get_stock_from_product(product_json):
    # Try common stock field names
    for key in ("stock", "quantity", "qty", "current_stock", "stock_level", "available_stock"):
        if key in product_json:
            return product_json[key]
    # nested data
    data = product_json.get("data") if isinstance(product_json, dict) else None
    if isinstance(data, dict):
        for key in ("stock", "quantity", "qty", "current_stock"):
            if key in data:
                return data[key]
    return None


def get_product_id(product_json):
    for key in ("id", "product_id", "uuid"):
        if key in product_json:
            return product_json[key]
    data = product_json.get("data") if isinstance(product_json, dict) else None
    if isinstance(data, dict):
        for key in ("id", "product_id", "uuid"):
            if key in data:
                return data[key]
    return None


def test_post_api_checkout_process_pos_sale():
    session = requests.Session()
    session.headers.update({"Accept": "application/json"})
    token = None
    created_product_id = None
    created_product_was_created = False

    # 1) Login to obtain token
    login_url = f"{BASE_URL.rstrip('/')}/api/login"
    try:
        r = session.post(login_url, json={"email": ADMIN_EMAIL, "password": ADMIN_PASSWORD}, timeout=TIMEOUT)
    except Exception as e:
        raise AssertionError(f"Login request failed: {e}")

    assert r.status_code == 200, f"Login failed, expected 200, got {r.status_code}, body={r.text}"
    login_json = r.json()
    token = extract_token(login_json)
    assert token, f"Auth token not found in login response: {login_json}"

    session.headers.update({"Authorization": f"Bearer {token}"})

    # Helper urls
    products_url = f"{BASE_URL.rstrip('/')}/api/products"
    checkout_url = f"{BASE_URL.rstrip('/')}/api/checkout"
    logout_url = f"{BASE_URL.rstrip('/')}/api/logout"

    try:
        # 2) Create a product to be used in checkout (if creation fails, fallback to first available product)
        product_payload = {
            "name": f"TC001 Test Product {int(time.time())}",
            "sku": f"TC001-{int(time.time())}",
            "barcode": f"TC001{int(time.time())}",
            "price": 10000,
            "cost": 8000,
            # Common field names for initial stock
            "stock": 10,
            "quantity": 10
        }
        try:
            r = session.post(products_url, json=product_payload, timeout=TIMEOUT)
        except Exception as e:
            raise AssertionError(f"Create product request failed: {e}")

        if r.status_code in (200, 201):
            prod_json = r.json()
            created_product_id = get_product_id(prod_json)
            if not created_product_id:
                # try nested data
                created_product_id = get_product_id(prod_json.get("data", {}) if isinstance(prod_json, dict) else None)
            assert created_product_id, f"Created product response did not contain id: {prod_json}"
            created_product_was_created = True
        else:
            # Fallback: fetch product list and pick one
            r_list = session.get(products_url, timeout=TIMEOUT)
            assert r_list.status_code == 200, f"Failed to list products for fallback selection: {r_list.status_code}"
            list_json = r_list.json()
            # attempt to find first product id
            candidate = None
            if isinstance(list_json, list) and len(list_json) > 0:
                candidate = list_json[0]
            elif isinstance(list_json, dict):
                # maybe nested under data
                data = list_json.get("data")
                if isinstance(data, list) and len(data) > 0:
                    candidate = data[0]
            assert candidate, f"No existing product available to use for test: {list_json}"
            created_product_id = get_product_id(candidate)
            assert created_product_id, f"Could not extract id from product candidate: {candidate}"
            created_product_was_created = False

        # 3) Read product to get initial stock and price
        product_get_url = f"{products_url.rstrip('/')}/{created_product_id}"
        r = session.get(product_get_url, timeout=TIMEOUT)
        assert r.status_code == 200, f"Failed to GET product {created_product_id}, status {r.status_code}, body={r.text}"
        prod_info = r.json()
        pre_stock = get_stock_from_product(prod_info)
        # If stock is None, treat as unknown but proceed; otherwise ensure it's an int
        if pre_stock is None:
            pre_stock_val = None
        else:
            try:
                pre_stock_val = int(pre_stock)
            except Exception:
                pre_stock_val = None

        # Determine unit price from product info if available
        unit_price = None
        for key in ("price", "selling_price", "unit_price", "amount"):
            if key in prod_info:
                unit_price = prod_info[key]
                break
        if unit_price is None:
            data = prod_info.get("data") if isinstance(prod_info, dict) else None
            if isinstance(data, dict):
                for key in ("price", "selling_price", "unit_price", "amount"):
                    if key in data:
                        unit_price = data[key]
                        break
        if unit_price is None:
            unit_price = product_payload.get("price", 10000)

        # 4) Perform checkout with quantity 2
        buy_qty = 2
        checkout_payload = {
            "items": [
                {
                    # try both product_id and id keys
                    "product_id": created_product_id,
                    "id": created_product_id,
                    "quantity": buy_qty,
                    "price": unit_price
                }
            ],
            "payment_method": "CASH",
            "tendered_amount": unit_price * buy_qty
        }
        try:
            r = session.post(checkout_url, json=checkout_payload, timeout=TIMEOUT)
        except Exception as e:
            raise AssertionError(f"Checkout request failed: {e}")

        # Accept success 200 or 201 depending on implementation
        assert r.status_code in (200, 201), f"Checkout failed, expected 200/201, got {r.status_code}, body={r.text}"
        checkout_resp = r.json()

        # Validate response contains expected sale/receipt info
        # Look for invoice/receipt id, items, totals
        keys_expected = ("invoice", "receipt", "id", "items", "total", "grand_total", "subtotal")
        found_any = any(k in checkout_resp for k in keys_expected)
        # also check nested data
        if not found_any and isinstance(checkout_resp.get("data"), dict):
            found_any = any(k in checkout_resp.get("data") for k in keys_expected)
        assert found_any, f"Checkout response missing expected keys. Response: {checkout_resp}"

        # Validate items present and include our product and quantity
        items = None
        if "items" in checkout_resp and isinstance(checkout_resp["items"], list):
            items = checkout_resp["items"]
        else:
            data = checkout_resp.get("data")
            if isinstance(data, dict) and "items" in data and isinstance(data["items"], list):
                items = data["items"]

        assert items and isinstance(items, list) and len(items) > 0, f"Checkout response items missing or empty: {checkout_resp}"
        # Find our item
        matched = None
        for it in items:
            if any(str(it.get(k)) == str(created_product_id) for k in ("product_id", "id", "sku")):
                matched = it
                break
            # fallback check by barcode/name if id not present
            if str(it.get("name", "")).startswith("TC001") or str(it.get("barcode", "")).startswith("TC001"):
                matched = it
                break
        assert matched is not None, f"Checkout items did not include the purchased product. Items: {items}"

        # Check quantity and price in response item
        resp_qty = matched.get("quantity") or matched.get("qty") or matched.get("amount") or matched.get("quantity_sold")
        try:
            resp_qty_val = int(resp_qty) if resp_qty is not None else None
        except Exception:
            resp_qty_val = None
        assert resp_qty_val == buy_qty or resp_qty_val is None, f"Response item quantity {resp_qty_val} does not match purchased qty {buy_qty}"

        # 5) Verify inventory stock deducted
        r = session.get(product_get_url, timeout=TIMEOUT)
        assert r.status_code == 200, f"Failed to GET product after checkout, status {r.status_code}, body={r.text}"
        post_info = r.json()
        post_stock = get_stock_from_product(post_info)
        if pre_stock_val is not None and post_stock is not None:
            try:
                post_stock_val = int(post_stock)
                assert post_stock_val == pre_stock_val - buy_qty, f"Stock did not decrease correctly: before={pre_stock_val}, after={post_stock_val}"
            except Exception:
                # If cannot parse ints, at least ensure it's different when expected
                assert post_stock != pre_stock, f"Stock value unchanged after checkout: {post_stock}"
        else:
            # Cannot assert numeric change if stock unknown, at least ensure product still retrievable
            assert post_info, "Product retrieval after checkout returned empty data"

        # 6) Check for accounting/journal evidence in checkout response (optional)
        journ_keys = ("journal", "journals", "journal_entries", "accounting_entries")
        journal_found = any(k in checkout_resp for k in journ_keys)
        if not journal_found and isinstance(checkout_resp.get("data"), dict):
            journal_found = any(k in checkout_resp.get("data") for k in journ_keys)
        # Journal generation is expected but may not be returned in response; if present, validate format
        if journal_found:
            # ensure entries are present and balanced if provided
            journal = None
            for k in journ_keys:
                if k in checkout_resp:
                    journal = checkout_resp[k]
                    break
            if journal is None and isinstance(checkout_resp.get("data"), dict):
                for k in journ_keys:
                    if k in checkout_resp.get("data"):
                        journal = checkout_resp["data"][k]
                        break
            if isinstance(journal, dict):
                entries = journal.get("entries") or journal.get("lines") or journal.get("items")
                if entries:
                    # Check debits and credits sum equality if amounts present
                    total_debit = 0
                    total_credit = 0
                    for en in entries:
                        amt = en.get("amount") or en.get("debit") or en.get("credit") or 0
                        try:
                            amt_val = float(amt)
                        except Exception:
                            amt_val = 0
                        if en.get("type") == "debit" or ("debit" in en and en.get("debit")):
                            total_debit += amt_val
                        if en.get("type") == "credit" or ("credit" in en and en.get("credit")):
                            total_credit += amt_val
                    # If both >0, assert balanced
                    if total_debit > 0 or total_credit > 0:
                        assert abs(total_debit - total_credit) < 0.0001, f"Journal entries unbalanced: debit={total_debit}, credit={total_credit}"

    finally:
        # cleanup: delete created product if we created it
        if created_product_was_created and created_product_id:
            try:
                del_url = f"{products_url.rstrip('/')}/{created_product_id}"
                r = session.delete(del_url, timeout=TIMEOUT)
                # Accept 200 or 204
                if r.status_code not in (200, 204):
                    # try force delete via direct endpoint (some implementations return 202 or 201)
                    pass
            except Exception:
                pass
        # logout
        try:
            session.post(logout_url, timeout=TIMEOUT)
        except Exception:
            pass

    print("TC001 passed: POST /api/checkout processed POS sale as expected.")


if __name__ == "__main__":
    try:
        test_post_api_checkout_process_pos_sale()
    except AssertionError as e:
        print(f"Test failed: {e}")
        sys.exit(1)
    except Exception as e:
        print(f"Unexpected error during test: {e}")
        sys.exit(2)
    sys.exit(0)
import os
import sys
import time
import random
import string
import requests

BASE_URL = os.getenv("BASE_URL", "http://127.0.0.1:8000").rstrip("/")
TEST_EMAIL = os.getenv("TEST_EMAIL", "admin@example.com")
TEST_PASSWORD = os.getenv("TEST_PASSWORD", "password")
TIMEOUT = 30


def test_get_api_products_barcode_lookup_TC004():
    session = requests.Session()
    token = None
    created_product_id = None
    created_product_barcode = None

    try:
        # 1) Authenticate
        login_url = f"{BASE_URL}/api/login"
        resp = session.post(
            login_url,
            json={"email": TEST_EMAIL, "password": TEST_PASSWORD},
            headers={"Accept": "application/json"},
            timeout=TIMEOUT,
        )
        assert resp.status_code == 200, f"Login failed: {resp.status_code} {resp.text}"
        login_json = resp.json()
        # Try common token fields
        token = (
            login_json.get("token")
            or login_json.get("access_token")
            or login_json.get("plainTextToken")
            or (login_json.get("data") or {}).get("token")
            or (login_json.get("data") or {}).get("access_token")
        )
        assert token, f"Auth token not found in login response: {login_json}"

        headers = {"Authorization": f"Bearer {token}", "Accept": "application/json"}

        # 2) Try to find an existing product with a barcode
        products_url = f"{BASE_URL}/api/products"
        resp = session.get(products_url, headers=headers, timeout=TIMEOUT)
        assert resp.status_code == 200, f"GET /api/products failed: {resp.status_code} {resp.text}"
        products_json = resp.json()

        # products might be a list or wrapped in 'data'
        if isinstance(products_json, list):
            products_list = products_json
        else:
            products_list = products_json.get("data") if isinstance(products_json.get("data"), list) else products_json.get("items") or []

        chosen_barcode = None
        chosen_product_price = None

        for p in products_list:
            # handle item possibly wrapped
            item = p if isinstance(p, dict) else {}
            barcode = item.get("barcode") or item.get("sku") or item.get("scannable_code")
            price = item.get("price") or item.get("selling_price") or item.get("price_sell") or item.get("unit_price")
            if barcode:
                chosen_barcode = barcode
                chosen_product_price = price
                break

        # 3) If no product with barcode, create one
        if not chosen_barcode:
            rand = "".join(random.choices(string.ascii_uppercase + string.digits, k=6))
            created_product_barcode = f"TESTBAR{int(time.time())}{rand}"
            payload = {
                "name": f"Test Product {rand}",
                "sku": f"TESTSKU{rand}",
                "barcode": created_product_barcode,
                # include several common price fields to match API variations
                "price": 19.99,
                "selling_price": 19.99,
                "cost_price": 10.0,
                "quantity": 50,
            }
            create_url = f"{BASE_URL}/api/products"
            resp = session.post(create_url, json=payload, headers=headers, timeout=TIMEOUT)
            assert resp.status_code in (200, 201), f"Create product failed: {resp.status_code} {resp.text}"
            create_json = resp.json()
            # extract id from various possible shapes
            created_product_id = (
                create_json.get("id")
                or (create_json.get("data") or {}).get("id")
                or (create_json.get("data") or {}).get("product_id")
                or create_json.get("product_id")
            )
            # If id not found, try to infer from response or list newly created product by barcode
            if not created_product_id:
                # try to lookup via GET products and match barcode
                resp2 = session.get(products_url, headers=headers, timeout=TIMEOUT)
                if resp2.status_code == 200:
                    pj = resp2.json()
                    pl = pj if isinstance(pj, list) else pj.get("data") or []
                    for it in pl:
                        if (it.get("barcode") or it.get("sku")) == created_product_barcode:
                            created_product_id = it.get("id")
                            chosen_product_price = it.get("price") or it.get("selling_price")
                            break
            chosen_barcode = created_product_barcode
            # fallback price from payload
            if not chosen_product_price:
                chosen_product_price = payload["price"]

        assert chosen_barcode, "No barcode available to test barcode lookup."

        # 4) Perform barcode lookup
        barcode_lookup_url = f"{BASE_URL}/api/products/barcode/{chosen_barcode}"
        resp = session.get(barcode_lookup_url, headers=headers, timeout=TIMEOUT)
        assert resp.status_code == 200, f"Barcode lookup failed: {resp.status_code} {resp.text}"
        lookup_json = resp.json()

        # Normalize lookup response to product dict
        product = lookup_json if isinstance(lookup_json, dict) and lookup_json.get("id") else lookup_json.get("data") if isinstance(lookup_json.get("data"), dict) else (lookup_json[0] if isinstance(lookup_json, list) and lookup_json else lookup_json)

        assert isinstance(product, dict), f"Unexpected product payload shape: {lookup_json}"

        # Verify barcode and price presence
        returned_barcode = product.get("barcode") or product.get("sku") or product.get("scannable_code")
        assert returned_barcode == chosen_barcode, f"Returned barcode mismatch: expected {chosen_barcode}, got {returned_barcode}"

        returned_price = product.get("price") or product.get("selling_price") or product.get("unit_price")
        assert returned_price is not None, f"Returned product missing price: {product}"

        # If we had an expected price, compare numeric values (tolerant)
        if chosen_product_price is not None:
            try:
                # cast to float for comparison tolerance
                assert abs(float(returned_price) - float(chosen_product_price)) < 0.001, (
                    f"Price mismatch: expected {chosen_product_price}, got {returned_price}"
                )
            except (ValueError, TypeError):
                # if not comparable, just ensure equality as strings
                assert str(returned_price) == str(chosen_product_price), (
                    f"Price mismatch (string): expected {chosen_product_price}, got {returned_price}"
                )

        print("TC004 passed: barcode lookup returned correct product metadata and price.")

    except requests.RequestException as e:
        raise AssertionError(f"HTTP request failed: {e}") from e
    finally:
        # Cleanup created product if any
        if created_product_id:
            try:
                del_url = f"{BASE_URL}/api/products/{created_product_id}"
                resp = session.delete(del_url, headers=headers, timeout=TIMEOUT)
                # allow 200 or 204
                if resp.status_code not in (200, 204):
                    # try delete by searching product by barcode if id delete failed
                    alt_del = f"{BASE_URL}/api/products"
                    resp2 = session.get(alt_del, headers=headers, timeout=TIMEOUT)
                    if resp2.status_code == 200:
                        pl = resp2.json()
                        pl = pl if isinstance(pl, list) else pl.get("data") or []
                        for it in pl:
                            if (it.get("barcode") or it.get("sku")) == created_product_barcode:
                                alt_id = it.get("id")
                                if alt_id and alt_id != created_product_id:
                                    _ = session.delete(f"{BASE_URL}/api/products/{alt_id}", headers=headers, timeout=TIMEOUT)
            except Exception:
                # don't re-raise to avoid masking test result
                pass


if __name__ == "__main__":
    test_get_api_products_barcode_lookup_TC004()
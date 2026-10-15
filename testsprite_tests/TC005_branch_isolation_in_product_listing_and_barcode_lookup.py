import requests
import json
import sys

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30

PAKAN_ADMIN_EMAIL = "asep@mb.pkn"
PUPUK_ADMIN_EMAIL = "admin@mb.abi"
PASSWORD = "password"

PAKAN_BRANCH_MARKERS = ["GUDANG-PAKAN", "CBG-PKN", "CBG-PKN TOKO PAKAN", "PAKAN"]
PUPUK_BRANCH_MARKERS = ["GUDANG-PUPUK", "CBG-ABI", "CBG-MAMA", "CBG-AROFAH", "CBG-TULUSAYU", "CBG-LUBUKHARJO", "PUPUK"]


def login(email: str, password: str):
    url = f"{BASE_URL}/api/login"
    try:
        r = requests.post(url, json={"email": email, "password": password}, timeout=TIMEOUT)
    except Exception as e:
        return None, f"exception on login request for {email}: {e}"
    try:
        data = r.json()
    except Exception:
        data = {}
    if r.status_code != 200:
        return None, f"login failed for {email}: status {r.status_code} body={data}"
    # token may be in various places; try typical keys
    token = None
    if isinstance(data, dict):
        if "token" in data and isinstance(data["token"], str):
            token = data["token"]
        elif "data" in data and isinstance(data["data"], dict) and "token" in data["data"]:
            token = data["data"]["token"]
        elif "access_token" in data:
            token = data["access_token"]
        elif "meta" in data and isinstance(data["meta"], dict) and "token" in data["meta"]:
            token = data["meta"]["token"]
    if not token:
        return None, f"login response for {email} missing token: {data}"
    return token, None


def auth_headers(token: str):
    return {"Authorization": f"Bearer {token}", "Accept": "application/json"}


def extract_barcode_from_product(prod):
    # try common keys
    keys = ["barcode", "sku", "code", "barcode_number", "barcode_no"]
    if not isinstance(prod, dict):
        return None
    for k in keys:
        v = prod.get(k)
        if v and isinstance(v, (str, int)):
            return str(v)
    # sometimes SKU under 'product' nested
    for k in ["product", "item"]:
        nested = prod.get(k)
        if isinstance(nested, dict):
            for kk in keys:
                v = nested.get(kk)
                if v and isinstance(v, (str, int)):
                    return str(v)
    return None


def stringify_json(obj):
    try:
        return json.dumps(obj).lower()
    except Exception:
        return str(obj).lower()


def test_branch_isolation_in_product_listing_and_barcode_lookup():
    pass_count = 0
    fail_count = 0

    def expect(condition, message):
        nonlocal pass_count, fail_count
        try:
            assert condition, message
            pass_count += 1
            print("PASS:", message)
        except AssertionError as e:
            fail_count += 1
            print("FAIL:", message, "-", str(e))

    # 1) Unauthenticated GET /api/products -> expect 401
    try:
        r = requests.get(f"{BASE_URL}/api/products", timeout=TIMEOUT)
        expect(r.status_code == 401, f"Unauthenticated GET /api/products should return 401, got {r.status_code}")
    except Exception as e:
        fail_count += 1
        print("FAIL: Unauthenticated GET /api/products raised exception:", e)

    # 2) Login both admins
    token_pakan, err = login(PAKAN_ADMIN_EMAIL, PASSWORD)
    expect(token_pakan is not None, f"Login Pakan admin ({PAKAN_ADMIN_EMAIL}) succeeded")
    if err:
        print("Login error (pakan):", err)

    token_pupuk, err2 = login(PUPUK_ADMIN_EMAIL, PASSWORD)
    expect(token_pupuk is not None, f"Login Pupuk admin ({PUPUK_ADMIN_EMAIL}) succeeded")
    if err2:
        print("Login error (pupuk):", err2)

    if not token_pakan or not token_pupuk:
        print("Aborting further checks due to login failures.")
        print(f"PASS: {pass_count}, FAIL: {fail_count}")
        if fail_count:
            sys.exit(1)
        else:
            return

    headers_pakan = auth_headers(token_pakan)
    headers_pupuk = auth_headers(token_pupuk)

    # 3) GET /api/products with Pakan token
    try:
        r_pakan = requests.get(f"{BASE_URL}/api/products", headers=headers_pakan, timeout=TIMEOUT)
    except Exception as e:
        r_pakan = None
        print("FAIL: Exception requesting /api/products with Pakan token:", e)
        fail_count += 1
    if r_pakan is not None:
        expect(r_pakan.status_code == 200, f"Pakan GET /api/products returned 200, got {r_pakan.status_code}")
        try:
            pakan_products = r_pakan.json()
        except Exception:
            pakan_products = r_pakan.text
        # Check no Pupuk branch markers anywhere in response
        response_text = stringify_json(pakan_products)
        pupuk_leak = any(marker.lower() in response_text for marker in PUPUK_BRANCH_MARKERS)
        expect(not pupuk_leak, "Pakan product listing does not contain Pupuk branch names/markers")
        # also check other_branch_stock fields if present
        try:
            items = pakan_products.get("data", pakan_products) if isinstance(pakan_products, dict) else pakan_products
            if isinstance(items, dict) and "data" in items:
                items = items["data"]
            if not isinstance(items, list):
                items = list(items) if hasattr(items, "__iter__") else []
        except Exception:
            items = []
        other_stock_safe = True
        for it in items:
            if isinstance(it, dict) and "other_branch_stock" in it:
                obs = it.get("other_branch_stock")
                if obs is None:
                    continue
                obs_text = stringify_json(obs)
                if any(marker.lower() in obs_text for marker in PUPUK_BRANCH_MARKERS):
                    other_stock_safe = False
                    break
        expect(other_stock_safe, "Pakan other_branch_stock does not expose Pupuk branch names or stock")

    # 4) GET /api/products with Pupuk token
    try:
        r_pupuk = requests.get(f"{BASE_URL}/api/products", headers=headers_pupuk, timeout=TIMEOUT)
    except Exception as e:
        r_pupuk = None
        print("FAIL: Exception requesting /api/products with Pupuk token:", e)
        fail_count += 1
    if r_pupuk is not None:
        expect(r_pupuk.status_code == 200, f"Pupuk GET /api/products returned 200, got {r_pupuk.status_code}")
        try:
            pupuk_products = r_pupuk.json()
        except Exception:
            pupuk_products = r_pupuk.text
        response_text = stringify_json(pupuk_products)
        pakan_leak = any(marker.lower() in response_text for marker in PAKAN_BRANCH_MARKERS)
        expect(not pakan_leak, "Pupuk product listing does not contain Pakan branch names/markers")
        # check other_branch_stock for Pakan names
        try:
            items = pupuk_products.get("data", pupuk_products) if isinstance(pupuk_products, dict) else pupuk_products
            if isinstance(items, dict) and "data" in items:
                items = items["data"]
            if not isinstance(items, list):
                items = list(items) if hasattr(items, "__iter__") else []
        except Exception:
            items = []
        other_stock_safe = True
        for it in items:
            if isinstance(it, dict) and "other_branch_stock" in it:
                obs = it.get("other_branch_stock")
                if obs is None:
                    continue
                obs_text = stringify_json(obs)
                if any(marker.lower() in obs_text for marker in PAKAN_BRANCH_MARKERS):
                    other_stock_safe = False
                    break
        expect(other_stock_safe, "Pupuk other_branch_stock does not expose Pakan branch names or stock")

    # 5) Barcode cross-lookup:
    # Extract one barcode from each admin's product lists
    pakan_barcode = None
    pupuk_barcode = None

    # helper to find barcode in list response
    def find_first_barcode(products_response):
        try:
            if isinstance(products_response, dict) and "data" in products_response:
                items = products_response["data"]
            elif isinstance(products_response, list):
                items = products_response
            elif isinstance(products_response, dict):
                # maybe top-level object with items under 'items'
                items = products_response.get("items", [])
                if not items:
                    # fallback to values
                    items = list(products_response.values())
            else:
                return None
        except Exception:
            return None
        if not isinstance(items, list):
            return None
        for it in items:
            bc = extract_barcode_from_product(it)
            if bc:
                return bc
        return None

    try:
        if r_pakan is not None and r_pakan.status_code == 200:
            try:
                pakan_products_json = r_pakan.json()
            except Exception:
                pakan_products_json = {}
            pakan_barcode = find_first_barcode(pakan_products_json)
            expect(pakan_barcode is not None, "Found a barcode in Pakan product listing for barcode tests")
        else:
            expect(False, "Pakan product listing not available for barcode extraction")
    except Exception as e:
        print("Exception extracting pakan barcode:", e)
        fail_count += 1

    try:
        if r_pupuk is not None and r_pupuk.status_code == 200:
            try:
                pupuk_products_json = r_pupuk.json()
            except Exception:
                pupuk_products_json = {}
            pupuk_barcode = find_first_barcode(pupuk_products_json)
            expect(pupuk_barcode is not None, "Found a barcode in Pupuk product listing for barcode tests")
        else:
            expect(False, "Pupuk product listing not available for barcode extraction")
    except Exception as e:
        print("Exception extracting pupuk barcode:", e)
        fail_count += 1

    # If barcodes found, perform cross-lookup tests
    if pakan_barcode:
        # Pakan lookup own barcode -> expect 200
        try:
            r = requests.get(f"{BASE_URL}/api/products/barcode/{pakan_barcode}", headers=headers_pakan, timeout=TIMEOUT)
            expect(r.status_code == 200, f"Pakan admin lookup of own barcode {pakan_barcode} should return 200 (got {r.status_code})")
        except Exception as e:
            fail_count += 1
            print("FAIL: Exception during Pakan own barcode lookup:", e)

        # Pupuk lookup Pakan barcode -> expect 404 (not in caller branch)
        try:
            r = requests.get(f"{BASE_URL}/api/products/barcode/{pakan_barcode}", headers=headers_pupuk, timeout=TIMEOUT)
            expect(r.status_code in (404, 200) and r.status_code == 404, f"Pupuk admin lookup of Pakan barcode {pakan_barcode} should return 404 (got {r.status_code})")
        except Exception as e:
            fail_count += 1
            print("FAIL: Exception during Pupuk lookup of Pakan barcode:", e)
    else:
        expect(False, "Skipping Pakan barcode lookup tests because no Pakan barcode was found")

    if pupuk_barcode:
        # Pupuk lookup own barcode -> expect 200
        try:
            r = requests.get(f"{BASE_URL}/api/products/barcode/{pupuk_barcode}", headers=headers_pupuk, timeout=TIMEOUT)
            expect(r.status_code == 200, f"Pupuk admin lookup of own barcode {pupuk_barcode} should return 200 (got {r.status_code})")
        except Exception as e:
            fail_count += 1
            print("FAIL: Exception during Pupuk own barcode lookup:", e)

        # Pakan lookup Pupuk barcode -> expect 404
        try:
            r = requests.get(f"{BASE_URL}/api/products/barcode/{pupuk_barcode}", headers=headers_pakan, timeout=TIMEOUT)
            expect(r.status_code in (404, 200) and r.status_code == 404, f"Pakan admin lookup of Pupuk barcode {pupuk_barcode} should return 404 (got {r.status_code})")
        except Exception as e:
            fail_count += 1
            print("FAIL: Exception during Pakan lookup of Pupuk barcode:", e)
    else:
        expect(False, "Skipping Pupuk barcode lookup tests because no Pupuk barcode was found")

    # Summary
    print(f"PASS: {pass_count}, FAIL: {fail_count}")
    if fail_count:
        sys.exit(1)


if __name__ == "__main__":
    test_branch_isolation_in_product_listing_and_barcode_lookup()
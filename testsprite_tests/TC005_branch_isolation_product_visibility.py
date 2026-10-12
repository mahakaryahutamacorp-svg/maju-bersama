import requests
import json
import sys

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30

PAKAN_ADMINS = [
    "asep@mb.pkn",
    "gudang@pakan.178"
]

PUPUK_ADMINS = [
    "gudang@pupuk.178",
    "admin@mb.abi"
]

PASSWORD = "password"

PUPUK_BRANCH_NAMES = [
    "OFFICE-PUPUK",
    "GUDANG-PUPUK",
    "CBG-ABI",
    "CBG-MAMA",
    "CBG-AROFAH",
    "CBG-TULUSAYU",
    "CBG-LUBUKHARJO",
    "TOKO MAJU BERSAMA",
    "TOKO AROFAH",
    "TOKO MUTIARA TANI",
    "TOKO LUBUK HARJO"
]

PAKAN_BRANCH_NAMES = [
    "TOKO PAKAN",
    "GUDANG-PAKAN",
    "CV.DEKA AGRO MANDIRI"
]


def login(email):
    url = f"{BASE_URL}/api/login"
    try:
        r = requests.post(url, json={"email": email, "password": PASSWORD}, headers={"Accept": "application/json"}, timeout=TIMEOUT)
    except requests.RequestException as e:
        raise AssertionError(f"Login request failed for {email}: {e}")
    if r.status_code != 200:
        raise AssertionError(f"Login failed for {email}: HTTP {r.status_code} - {r.text}")
    try:
        body = r.json()
    except ValueError:
        raise AssertionError(f"Login response not JSON for {email}: {r.text}")
    token = body.get("token")
    if not token:
        # Some implementations may return token under other keys or nested; fail if not found
        raise AssertionError(f"No token in login response for {email}: {body}")
    return token, body.get("user")


def get_products(token, params=None):
    url = f"{BASE_URL}/api/products"
    headers = {"Accept": "application/json", "Authorization": f"Bearer {token}"}
    try:
        r = requests.get(url, headers=headers, params=params or {}, timeout=TIMEOUT)
    except requests.RequestException as e:
        raise AssertionError(f"GET /api/products failed: {e}")
    if r.status_code != 200:
        raise AssertionError(f"GET /api/products returned {r.status_code}: {r.text}")
    try:
        return r.json()
    except ValueError:
        raise AssertionError(f"GET /api/products returned non-JSON: {r.text}")


def get_product_by_barcode(token, barcode):
    url = f"{BASE_URL}/api/products/barcode/{barcode}"
    headers = {"Accept": "application/json", "Authorization": f"Bearer {token}"}
    try:
        r = requests.get(url, headers=headers, timeout=TIMEOUT)
    except requests.RequestException as e:
        raise AssertionError(f"GET /api/products/barcode/{barcode} failed: {e}")
    return r


def ensure_no_forbidden_names_in_response(resp_json, forbidden_names, context=""):
    # Accept either top-level array or {data: []}
    products = resp_json.get("data") if isinstance(resp_json, dict) and "data" in resp_json else resp_json
    if not isinstance(products, list):
        # If the structure is unexpected, still stringify and scan
        serialized = json.dumps(resp_json, ensure_ascii=False).lower()
        for name in forbidden_names:
            if name.lower() in serialized:
                raise AssertionError(f"Forbidden name '{name}' found in {context} response")
        return
    # iterate products
    for idx, p in enumerate(products):
        serialized = json.dumps(p, ensure_ascii=False).lower()
        for name in forbidden_names:
            if name.lower() in serialized:
                raise AssertionError(f"Forbidden name '{name}' found in product index response (product index {idx}) in {context}")
        # check other_branch_stock specifically if present
        obs = p.get("other_branch_stock") if isinstance(p, dict) else None
        if obs:
            # stringify obs and search
            obs_serialized = json.dumps(obs, ensure_ascii=False).lower()
            for name in forbidden_names:
                if name.lower() in obs_serialized:
                    raise AssertionError(f"Forbidden name '{name}' found inside other_branch_stock for product index {idx} in {context}")


def extract_barcodes_from_products(resp_json):
    products = resp_json.get("data") if isinstance(resp_json, dict) and "data" in resp_json else resp_json
    barcodes = []
    if not isinstance(products, list):
        return barcodes
    for p in products:
        if isinstance(p, dict):
            bc = p.get("barcode") or p.get("sku") or p.get("code")
            if bc:
                barcodes.append(str(bc))
    return barcodes


def test_branch_isolation_product_visibility():
    pakan_barcodes = []
    pupuk_barcodes = []

    # Test Pakan admins do not see Pupuk branches/names/stock
    for email in PAKAN_ADMINS:
        token, user = login(email)
        products_json = get_products(token)
        ensure_no_forbidden_names_in_response(products_json, PUPUK_BRANCH_NAMES, context=f"Pakan admin {email} product index")
        # Also test passing an arbitrary branch_id param should not leak forbidden names
        products_json_branchparam = get_products(token, params={"branch_id": "999999"})
        ensure_no_forbidden_names_in_response(products_json_branchparam, PUPUK_BRANCH_NAMES, context=f"Pakan admin {email} product index with branch_id=999999")
        # collect barcodes for cross-lookup
        pakan_barcodes.extend(extract_barcodes_from_products(products_json))

    # Test Pupuk admins do not see Pakan branches/names/stock
    for email in PUPUK_ADMINS:
        token, user = login(email)
        products_json = get_products(token)
        ensure_no_forbidden_names_in_response(products_json, PAKAN_BRANCH_NAMES, context=f"Pupuk admin {email} product index")
        products_json_branchparam = get_products(token, params={"branch_id": "999999"})
        ensure_no_forbidden_names_in_response(products_json_branchparam, PAKAN_BRANCH_NAMES, context=f"Pupuk admin {email} product index with branch_id=999999")
        pupuk_barcodes.extend(extract_barcodes_from_products(products_json))

    # Deduplicate barcodes
    pakan_barcodes = list(dict.fromkeys(pakan_barcodes))
    pupuk_barcodes = list(dict.fromkeys(pupuk_barcodes))

    # Cross-check: a barcode from Pakan should not be visible via Pupuk admins (should return 404)
    if pakan_barcodes:
        sample = pakan_barcodes[0]
        for email in PUPUK_ADMINS:
            token, _ = login(email)
            r = get_product_by_barcode(token, sample)
            if r.status_code == 200:
                # If the product exists in this caller's branch, that's a leakage
                raise AssertionError(f"Pupuk admin {email} unexpectedly can access Pakan product barcode {sample}: HTTP 200")
            if r.status_code != 404:
                # Expecting 404 for outside-branch product; other statuses are failures
                raise AssertionError(f"Pupuk admin {email} lookup for Pakan barcode {sample} returned {r.status_code}, expected 404")
    else:
        print("No Pakan barcodes discovered to perform cross-division barcode lookup; barcode cross-check skipped for Pakan -> Pupuk.")

    # Reverse: a barcode from Pupuk should not be visible via Pakan admins
    if pupuk_barcodes:
        sample = pupuk_barcodes[0]
        for email in PAKAN_ADMINS:
            token, _ = login(email)
            r = get_product_by_barcode(token, sample)
            if r.status_code == 200:
                raise AssertionError(f"Pakan admin {email} unexpectedly can access Pupuk product barcode {sample}: HTTP 200")
            if r.status_code != 404:
                raise AssertionError(f"Pakan admin {email} lookup for Pupuk barcode {sample} returned {r.status_code}, expected 404")
    else:
        print("No Pupuk barcodes discovered to perform cross-division barcode lookup; barcode cross-check skipped for Pupuk -> Pakan.")

    print("TC005 branch isolation checks passed.")


if __name__ == "__main__":
    try:
        test_branch_isolation_product_visibility()
    except AssertionError as e:
        print("TEST FAILED:", e)
        sys.exit(1)
    except Exception as e:
        print("UNEXPECTED ERROR:", e)
        sys.exit(2)
    sys.exit(0)
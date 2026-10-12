import requests
import uuid
import sys
from requests.exceptions import RequestException

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30
HEADERS_JSON = {"Accept": "application/json", "Content-Type": "application/json"}


def login(email: str, password: str = "password"):
    url = f"{BASE_URL}/api/login"
    try:
        r = requests.post(url, json={"email": email, "password": password}, headers=HEADERS_JSON, timeout=TIMEOUT)
    except RequestException as e:
        raise AssertionError(f"Login request failed for {email}: {e}")
    assert r.status_code == 200, f"Login failed for {email}, expected 200 got {r.status_code}, body: {r.text}"
    body = r.json()
    token = body.get("token")
    assert token, f"No token returned for {email}: {body}"
    return token


def safe_request(method, path, token=None, json=None, expected_statuses=None):
    url = BASE_URL + path
    headers = dict(HEADERS_JSON)
    if token:
        headers["Authorization"] = f"Bearer {token}"
    try:
        r = requests.request(method, url, headers=headers, json=json, timeout=TIMEOUT)
    except RequestException as e:
        raise AssertionError(f"Request to {path} failed: {e}")
    if expected_statuses:
        if r.status_code not in expected_statuses:
            raise AssertionError(f"Unexpected status for {method} {path}: got {r.status_code}, expected one of {expected_statuses}. Body: {r.text}")
    return r


def extract_id_from_response(resp):
    try:
        body = resp.json()
    except Exception:
        return None
    if isinstance(body, dict):
        # common shapes
        if "id" in body and isinstance(body["id"], (int, str)):
            return str(body["id"])
        if "data" in body:
            data = body["data"]
            if isinstance(data, dict) and "id" in data:
                return str(data["id"])
            if isinstance(data, list) and len(data) > 0 and isinstance(data[0], dict) and "id" in data[0]:
                return str(data[0]["id"])
        # possible nested resource key (branch, category, product)
        for key in ("branch", "category", "product"):
            if key in body and isinstance(body[key], dict) and "id" in body[key]:
                return str(body[key]["id"])
    # try Location header
    loc = resp.headers.get("Location") or resp.headers.get("location")
    if loc:
        try:
            return loc.rstrip("/").split("/")[-1]
        except Exception:
            return None
    return None


def test_master_data_access_authorization_TC004():
    # ---- Cashier checks ----
    cashier_email = "kasir@pakan.mb"
    cashier_token = login(cashier_email)

    # Cashier GET /api/branches -> 403
    r = safe_request("GET", "/api/branches", token=cashier_token)
    assert r.status_code == 403, f"Cashier GET /api/branches expected 403, got {r.status_code}, body: {r.text}"

    # Cashier POST /api/branches -> 403
    r = safe_request("POST", "/api/branches", token=cashier_token, json={"name": "unauthorized-branch-attempt"},)
    assert r.status_code == 403, f"Cashier POST /api/branches expected 403, got {r.status_code}, body: {r.text}"

    # Cashier GET /api/categories -> 200 (authenticated users may read categories)
    r = safe_request("GET", "/api/categories", token=cashier_token)
    assert r.status_code == 200, f"Cashier GET /api/categories expected 200, got {r.status_code}, body: {r.text}"
    try:
        categories_body = r.json()
    except Exception:
        raise AssertionError(f"GET /api/categories did not return JSON. Body: {r.text}")
    # basic sanity: categories should be list or dict
    assert isinstance(categories_body, (list, dict)), f"Unexpected categories response shape: {categories_body}"

    # Cashier POST /api/categories -> 403 (if 201 treat as failure)
    cat_name = f"cashier-attempt-{uuid.uuid4().hex[:6]}"
    r = safe_request("POST", "/api/categories", token=cashier_token, json={"name": cat_name})
    if r.status_code == 201:
        raise AssertionError(f"RBAC failure: cashier was able to create category (201). Body: {r.text}")
    assert r.status_code == 403, f"Cashier POST /api/categories expected 403, got {r.status_code}, body: {r.text}"

    # Cashier POST /api/products -> 403 (if 201 treat as failure)
    # Need category_id; attempt to use first category id from categories_body if possible
    category_id_for_product = None
    try:
        if isinstance(categories_body, dict) and "data" in categories_body:
            data = categories_body["data"]
            if isinstance(data, list) and len(data) > 0 and isinstance(data[0], dict):
                category_id_for_product = data[0].get("id") or data[0].get("category_id")
        elif isinstance(categories_body, list) and len(categories_body) > 0 and isinstance(categories_body[0], dict):
            category_id_for_product = categories_body[0].get("id") or categories_body[0].get("category_id")
    except Exception:
        category_id_for_product = None
    if not category_id_for_product:
        # fallback to 1
        category_id_for_product = 1

    prod_payload = {
        "category_id": category_id_for_product,
        "name": f"cashier-product-{uuid.uuid4().hex[:6]}",
        "purchase_price": "10.00",
        "selling_price": "15.00"
    }
    r = safe_request("POST", "/api/products", token=cashier_token, json=prod_payload)
    if r.status_code == 201:
        raise AssertionError(f"RBAC failure: cashier was able to create product (201). Body: {r.text}")
    assert r.status_code == 403, f"Cashier POST /api/products expected 403, got {r.status_code}, body: {r.text}"

    # ---- Master checks ----
    master_email = "master@majubersama.test"
    master_token = login(master_email)

    # Master GET /api/branches -> 200
    r = safe_request("GET", "/api/branches", token=master_token)
    assert r.status_code == 200, f"Master GET /api/branches expected 200, got {r.status_code}, body: {r.text}"

    created_branch_id = None
    # Master POST /api/branches -> allowed (200 or 201)
    branch_payload = {
        "name": f"Test Branch {uuid.uuid4().hex[:6]}",
        # add common optional fields if API accepts them
        "code": f"TB{uuid.uuid4().hex[:6]}",
    }
    r = safe_request("POST", "/api/branches", token=master_token, json=branch_payload)
    assert r.status_code in (200, 201), f"Master POST /api/branches expected 200/201, got {r.status_code}, body: {r.text}"
    created_branch_id = extract_id_from_response(r)

    # Master GET /api/categories -> 200 (also confirm create)
    r = safe_request("GET", "/api/categories", token=master_token)
    assert r.status_code == 200, f"Master GET /api/categories expected 200, got {r.status_code}, body: {r.text}"
    # Create a category as master (201)
    created_category_id = None
    cat_payload_master = {"name": f"master-created-{uuid.uuid4().hex[:6]}"}
    r = safe_request("POST", "/api/categories", token=master_token, json=cat_payload_master)
    assert r.status_code in (200, 201), f"Master POST /api/categories expected 200/201, got {r.status_code}, body: {r.text}"
    created_category_id = extract_id_from_response(r)

    # Ensure we have a valid category id to create product
    if not created_category_id:
        # try to pick from GET /api/categories result
        try:
            body = r.json()
        except Exception:
            body = None
        if body:
            if isinstance(body, dict) and "data" in body and isinstance(body["data"], list) and len(body["data"]) > 0:
                created_category_id = str(body["data"][0].get("id") or body["data"][0].get("category_id") or "1")
        if not created_category_id:
            created_category_id = "1"

    # Master POST /api/products -> should be allowed (201 or 200)
    created_product_id = None
    product_payload_master = {
        "category_id": created_category_id,
        "name": f"master-product-{uuid.uuid4().hex[:6]}",
        "purchase_price": "12.00",
        "selling_price": "18.00"
    }
    r = safe_request("POST", "/api/products", token=master_token, json=product_payload_master)
    assert r.status_code in (200, 201), f"Master POST /api/products expected 200/201, got {r.status_code}, body: {r.text}"
    created_product_id = extract_id_from_response(r)

    # Cleanup created resources with try/finally semantics
    # Delete product, category, branch if they were created
    delete_errors = []
    if created_product_id:
        try:
            dr = safe_request("DELETE", f"/api/products/{created_product_id}", token=master_token)
            if dr.status_code < 200 or dr.status_code >= 300:
                delete_errors.append(f"Failed to delete product {created_product_id}, status {dr.status_code}, body: {dr.text}")
        except AssertionError as e:
            delete_errors.append(f"Exception deleting product {created_product_id}: {e}")

    if created_category_id:
        try:
            dr = safe_request("DELETE", f"/api/categories/{created_category_id}", token=master_token)
            if dr.status_code < 200 or dr.status_code >= 300:
                # some APIs may not support DELETE; attempt best-effort and record
                delete_errors.append(f"Failed to delete category {created_category_id}, status {dr.status_code}, body: {dr.text}")
        except AssertionError as e:
            delete_errors.append(f"Exception deleting category {created_category_id}: {e}")

    if created_branch_id:
        try:
            dr = safe_request("DELETE", f"/api/branches/{created_branch_id}", token=master_token)
            if dr.status_code < 200 or dr.status_code >= 300:
                delete_errors.append(f"Failed to delete branch {created_branch_id}, status {dr.status_code}, body: {dr.text}")
        except AssertionError as e:
            delete_errors.append(f"Exception deleting branch {created_branch_id}: {e}")

    # If we collected delete errors, fail the test to make cleanup visible, but show messages
    if delete_errors:
        raise AssertionError("Cleanup errors: " + " | ".join(delete_errors))

    print("TC004 passed: master_data_access_authorization")


if __name__ == "__main__":
    try:
        test_master_data_access_authorization_TC004()
    except AssertionError as e:
        print("Test failed:", e)
        sys.exit(1)
    except Exception as e:
        print("Unexpected error during test:", e)
        sys.exit(2)
    print("Test completed successfully.")
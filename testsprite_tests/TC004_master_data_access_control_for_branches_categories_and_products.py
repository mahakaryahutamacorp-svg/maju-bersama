import requests
import sys
from requests.exceptions import RequestException

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30.0

MASTER = {"email": "master@majubersama.test", "password": "password"}
BRANCH_ADMIN = {"email": "asep@mb.pkn", "password": "password"}
CASHIER = {"email": "kasir@pakan.mb", "password": "password"}


def login(user):
    url = f"{BASE_URL}/api/login"
    try:
        r = requests.post(url, json=user, timeout=TIMEOUT)
    except RequestException as e:
        raise SystemExit(f"Login request failed for {user['email']}: {e}")
    if r.status_code != 200:
        raise SystemExit(f"Login failed for {user['email']}: status {r.status_code}, body: {r.text}")
    data = r.json()
    token = data.get("token")
    if not token:
        raise SystemExit(f"No token returned for {user['email']}: {data}")
    return token


def auth_header(token):
    return {"Authorization": f"Bearer {token}", "Accept": "application/json"}


def safe_request(method, path, token=None, json=None):
    url = f"{BASE_URL}{path}"
    headers = {"Accept": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    try:
        r = requests.request(method, url, headers=headers, json=json, timeout=TIMEOUT)
    except RequestException as e:
        raise SystemExit(f"Request failed {method} {url}: {e}")
    return r


def test_master_data_access_control_for_branches_categories_and_products():
    pass_count = 0
    fail_count = 0

    # Login users
    try:
        master_token = login(MASTER)
        branch_admin_token = login(BRANCH_ADMIN)
        cashier_token = login(CASHIER)
    except SystemExit as e:
        print(str(e))
        sys.exit(2)

    created_branch_id = None
    created_category_id = None
    created_product_ids = []

    try:
        # 1) Branches: GET as master (expect 200)
        try:
            r = safe_request("GET", "/api/branches", token=master_token)
            assert r.status_code == 200, f"Master GET /api/branches expected 200, got {r.status_code}"
            pass_count += 1
        except AssertionError as e:
            print("FAIL:", e)
            fail_count += 1

        # 2) Branches: GET as branch admin (expect 403)
        try:
            r = safe_request("GET", "/api/branches", token=branch_admin_token)
            assert r.status_code == 403, f"Branch admin GET /api/branches expected 403, got {r.status_code}"
            pass_count += 1
        except AssertionError as e:
            print("FAIL:", e)
            fail_count += 1

        # 3) Branches: GET as cashier (expect 403)
        try:
            r = safe_request("GET", "/api/branches", token=cashier_token)
            assert r.status_code == 403, f"Cashier GET /api/branches expected 403, got {r.status_code}"
            pass_count += 1
        except AssertionError as e:
            print("FAIL:", e)
            fail_count += 1

        # 4) Branches: POST create as master (expect 200 or 201)
        branch_payload = {
            "name": "TC004 Branch",
            "code": "TC004-BR",
            "address": "Test Address TC004",
            "phone": "0000000000",
            "email": "branch-tc004@example.test"
        }
        try:
            r = safe_request("POST", "/api/branches", token=master_token, json=branch_payload)
            assert r.status_code in (200, 201), f"Master POST /api/branches expected 200/201, got {r.status_code}, body: {r.text}"
            # attempt to extract id from response json
            try:
                created_branch_id = r.json().get("id") or r.json().get("data", {}).get("id")
            except Exception:
                created_branch_id = None
            pass_count += 1
        except AssertionError as e:
            print("FAIL:", e)
            fail_count += 1

        # 5) Branches: POST as branch admin (expect 403)
        try:
            r = safe_request("POST", "/api/branches", token=branch_admin_token, json=branch_payload)
            assert r.status_code == 403, f"Branch admin POST /api/branches expected 403, got {r.status_code}, body: {r.text}"
            pass_count += 1
        except AssertionError as e:
            print("FAIL:", e)
            fail_count += 1

        # 6) Branches: POST as cashier (expect 403)
        try:
            r = safe_request("POST", "/api/branches", token=cashier_token, json=branch_payload)
            assert r.status_code == 403, f"Cashier POST /api/branches expected 403, got {r.status_code}, body: {r.text}"
            pass_count += 1
        except AssertionError as e:
            print("FAIL:", e)
            fail_count += 1

        # 7) Categories: GET as master (expect 200)
        try:
            r = safe_request("GET", "/api/categories", token=master_token)
            assert r.status_code == 200, f"Master GET /api/categories expected 200, got {r.status_code}"
            # try to find an existing category id to use if creation fails
            existing_categories = []
            try:
                existing_categories = r.json().get("data") or r.json()
            except Exception:
                existing_categories = []
            if existing_categories and isinstance(existing_categories, list) and len(existing_categories) > 0:
                maybe_cat = existing_categories[0]
                if isinstance(maybe_cat, dict):
                    existing_cat_id = maybe_cat.get("id")
                else:
                    existing_cat_id = None
            else:
                existing_cat_id = None
            pass_count += 1
        except AssertionError as e:
            print("FAIL:", e)
            fail_count += 1
            existing_cat_id = None

        # 8) Categories: POST as master (expect 200 or 201)
        category_payload = {"name": "TC004 Category"}
        try:
            r = safe_request("POST", "/api/categories", token=master_token, json=category_payload)
            assert r.status_code in (200, 201), f"Master POST /api/categories expected 200/201, got {r.status_code}, body: {r.text}"
            try:
                created_category_id = r.json().get("id") or r.json().get("data", {}).get("id")
            except Exception:
                created_category_id = None
            # If no id, but GET categories returned an id earlier, use that
            if not created_category_id and existing_cat_id:
                created_category_id = existing_cat_id
            # If still not found, we will attempt to fetch categories and pick one
            if not created_category_id:
                r2 = safe_request("GET", "/api/categories", token=master_token)
                if r2.status_code == 200:
                    try:
                        arr = r2.json().get("data") or r2.json()
                        if isinstance(arr, list) and len(arr) > 0:
                            created_category_id = arr[0].get("id")
                    except Exception:
                        created_category_id = None
            assert created_category_id is not None, f"No category id available after POST, response: {r.text}"
            pass_count += 1
        except AssertionError as e:
            print("FAIL:", e)
            fail_count += 1

        # 9) Categories: POST as branch admin (expect 403)
        try:
            r = safe_request("POST", "/api/categories", token=branch_admin_token, json=category_payload)
            assert r.status_code == 403, f"Branch admin POST /api/categories expected 403, got {r.status_code}, body: {r.text}"
            pass_count += 1
        except AssertionError as e:
            print("FAIL:", e)
            fail_count += 1

        # 10) Categories: POST as cashier (expect 403)
        try:
            r = safe_request("POST", "/api/categories", token=cashier_token, json=category_payload)
            assert r.status_code == 403, f"Cashier POST /api/categories expected 403, got {r.status_code}, body: {r.text}"
            pass_count += 1
        except AssertionError as e:
            print("FAIL:", e)
            fail_count += 1

        # Prepare product payload using category id
        if not created_category_id:
            # Attempt to fetch categories again
            r = safe_request("GET", "/api/categories", token=master_token)
            try:
                arr = r.json().get("data") or r.json()
                if isinstance(arr, list) and len(arr) > 0:
                    created_category_id = arr[0].get("id")
            except Exception:
                created_category_id = None

        if not created_category_id:
            print("ERROR: No category available to create product; skipping product creation checks.")
            # still count as failures for product creation expectations
            fail_count += 1  # master product creation expected but skipped
            fail_count += 1  # cashier product creation expected but skipped
        else:
            product_payload = {
                "category_id": created_category_id,
                "name": "TC004 Product",
                "purchase_price": "10.00",
                "selling_price": "15.00",
                # include minimal SKU or code if required by API; try safe defaults
                "sku": "TC004-PROD-001"
            }

            # 11) Products: POST as master (expect 201 or 200)
            try:
                r = safe_request("POST", "/api/products", token=master_token, json=product_payload)
                assert r.status_code in (200, 201), f"Master POST /api/products expected 200/201, got {r.status_code}, body: {r.text}"
                try:
                    pid = r.json().get("id") or r.json().get("data", {}).get("id")
                    if pid:
                        created_product_ids.append(pid)
                except Exception:
                    pass
                pass_count += 1
            except AssertionError as e:
                print("FAIL:", e)
                fail_count += 1

            # 12) Products: POST as branch admin (may be allowed or forbidden by policy)
            try:
                r = safe_request("POST", "/api/products", token=branch_admin_token, json=product_payload)
                # branch admin may be allowed or receive 403; accept both but record outcome
                assert r.status_code in (200, 201, 403), f"Branch admin POST /api/products expected 200/201/403, got {r.status_code}, body: {r.text}"
                if r.status_code in (200, 201):
                    try:
                        pid = r.json().get("id") or r.json().get("data", {}).get("id")
                        if pid:
                            created_product_ids.append(pid)
                    except Exception:
                        pass
                pass_count += 1
            except AssertionError as e:
                print("FAIL:", e)
                fail_count += 1

            # 13) Products: POST as cashier (expect 403)
            try:
                r = safe_request("POST", "/api/products", token=cashier_token, json=product_payload)
                assert r.status_code == 403, f"Cashier POST /api/products expected 403, got {r.status_code}, body: {r.text}"
                pass_count += 1
            except AssertionError as e:
                print("FAIL:", e)
                fail_count += 1

    finally:
        # Cleanup created products
        if created_product_ids:
            for pid in created_product_ids:
                try:
                    r = safe_request("DELETE", f"/api/products/{pid}", token=master_token)
                    if r.status_code not in (200, 204):
                        print(f"Warning: failed to delete product {pid}, status {r.status_code}, body: {r.text}")
                except Exception as e:
                    print(f"Warning: exception deleting product {pid}: {e}")

        # Cleanup created category
        if created_category_id:
            try:
                r = safe_request("DELETE", f"/api/categories/{created_category_id}", token=master_token)
                if r.status_code not in (200, 204):
                    print(f"Warning: failed to delete category {created_category_id}, status {r.status_code}, body: {r.text}")
            except Exception as e:
                print(f"Warning: exception deleting category {created_category_id}: {e}")

        # Cleanup created branch
        if created_branch_id:
            try:
                r = safe_request("DELETE", f"/api/branches/{created_branch_id}", token=master_token)
                if r.status_code not in (200, 204):
                    print(f"Warning: failed to delete branch {created_branch_id}, status {r.status_code}, body: {r.text}")
            except Exception as e:
                print(f"Warning: exception deleting branch {created_branch_id}: {e}")

    print(f"PASS: {pass_count}, FAIL: {fail_count}")
    # exit with non-zero if any failures
    if fail_count > 0:
        sys.exit(1)


if __name__ == "__main__":
    test_master_data_access_control_for_branches_categories_and_products()
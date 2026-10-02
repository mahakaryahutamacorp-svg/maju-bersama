import requests
import time
import os
import sys

BASE_URL = "http://localhost:8000"
LOGIN_EMAIL = os.getenv("API_TEST_EMAIL", "admin@example.com")
LOGIN_PASSWORD = os.getenv("API_TEST_PASSWORD", "password")
TIMEOUT = 30


def test_post_api_stock_transfers_movement():
    session = requests.Session()
    headers = {"Accept": "application/json"}
    token = None
    created_product_id = None
    created_branch_id = None
    created_transfer_id = None

    try:
        # 1) Login
        resp = session.post(
            f"{BASE_URL}/api/login",
            json={"email": LOGIN_EMAIL, "password": LOGIN_PASSWORD},
            headers=headers,
            timeout=TIMEOUT,
        )
        assert resp.status_code == 200, f"Login failed: {resp.status_code} {resp.text}"
        login_json = resp.json()
        # try common token fields
        token = (
            login_json.get("token")
            or login_json.get("access_token")
            or login_json.get("plainTextToken")
            or login_json.get("plain_text_token")
            or login_json.get("data", {}).get("token")
        )
        assert token, f"No auth token found in login response: {login_json}"
        auth_headers = {**headers, "Authorization": f"Bearer {token}"}

        # 2) Get authenticated user to determine source branch
        resp = session.get(f"{BASE_URL}/api/user", headers=auth_headers, timeout=TIMEOUT)
        assert resp.status_code == 200, f"GET /api/user failed: {resp.status_code} {resp.text}"
        user_json = resp.json()
        # try multiple shapes
        source_branch_id = None
        if isinstance(user_json, dict):
            # common shapes: user.branch.id, branch_id, user['data']['branch']['id']
            if "branch" in user_json and isinstance(user_json["branch"], dict) and "id" in user_json["branch"]:
                source_branch_id = user_json["branch"]["id"]
            elif "branch_id" in user_json:
                source_branch_id = user_json["branch_id"]
            elif "data" in user_json and isinstance(user_json["data"], dict):
                d = user_json["data"]
                if "branch" in d and isinstance(d["branch"], dict) and "id" in d["branch"]:
                    source_branch_id = d["branch"]["id"]
                elif "branch_id" in d:
                    source_branch_id = d["branch_id"]
        assert source_branch_id, f"Could not determine user's branch from /api/user response: {user_json}"

        # 3) Ensure there is a destination branch different from source_branch_id
        resp = session.get(f"{BASE_URL}/api/branches", headers=auth_headers, timeout=TIMEOUT)
        assert resp.status_code == 200, f"GET /api/branches failed: {resp.status_code} {resp.text}"
        branches = resp.json() if resp.content else []
        dest_branch_id = None
        if isinstance(branches, list):
            for b in branches:
                bid = b.get("id") if isinstance(b, dict) else None
                if bid and bid != source_branch_id:
                    dest_branch_id = bid
                    break

        if not dest_branch_id:
            # create a new branch to act as destination
            branch_payload = {"name": f"Test Destination Branch {int(time.time())}"}
            resp = session.post(
                f"{BASE_URL}/api/branches",
                json=branch_payload,
                headers=auth_headers,
                timeout=TIMEOUT,
            )
            assert resp.status_code in (200, 201), f"Failed to create branch: {resp.status_code} {resp.text}"
            branch_json = resp.json()
            created_branch_id = branch_json.get("id") or branch_json.get("data", {}).get("id")
            assert created_branch_id, f"Created branch id not found: {branch_json}"
            dest_branch_id = created_branch_id

        assert dest_branch_id != source_branch_id, "Destination branch must be different from source branch"

        # 4) Find or create a product in source branch with sufficient stock
        transfer_qty = 5
        product_id = None
        current_stock = None

        # Fetch products for source branch (user context is source branch)
        resp = session.get(f"{BASE_URL}/api/products", headers=auth_headers, timeout=TIMEOUT)
        assert resp.status_code == 200, f"GET /api/products failed: {resp.status_code} {resp.text}"
        products = resp.json() if resp.content else []

        # try to find product with enough stock
        def product_has_stock(p):
            if not isinstance(p, dict):
                return False
            stock = p.get("stock") or p.get("quantity") or p.get("qty")
            try:
                return int(stock) >= transfer_qty
            except Exception:
                return False

        if isinstance(products, list):
            for p in products:
                if product_has_stock(p):
                    product_id = p.get("id")
                    current_stock = int(p.get("stock") or p.get("quantity") or p.get("qty") or 0)
                    break

        if not product_id:
            # create a product with initial stock >= transfer_qty
            barcode = f"TEST-BAR-{int(time.time())}"
            product_payload = {
                "name": f"Test Product {int(time.time())}",
                "sku": f"TESTSKU-{int(time.time())}",
                "barcode": barcode,
                "price": 1000,
                "cost": 800,
                # common key names for initial stock:
                "stock": 20,
                "opening_stock": 20,
                "initial_stock": 20,
            }
            # Some APIs ignore irrelevant fields; attempt POST and pick id
            resp = session.post(
                f"{BASE_URL}/api/products", json=product_payload, headers=auth_headers, timeout=TIMEOUT
            )
            assert resp.status_code in (200, 201), f"Failed to create product: {resp.status_code} {resp.text}"
            prod_json = resp.json()
            product_id = prod_json.get("id") or prod_json.get("data", {}).get("id")
            assert product_id, f"Created product id not found: {prod_json}"
            created_product_id = product_id
            # fetch created product to read stock
            resp = session.get(f"{BASE_URL}/api/products/{product_id}", headers=auth_headers, timeout=TIMEOUT)
            assert resp.status_code == 200, f"GET /api/products/{product_id} failed: {resp.status_code} {resp.text}"
            prod_detail = resp.json()
            current_stock = int(prod_detail.get("stock") or prod_detail.get("quantity") or prod_detail.get("qty") or 20)

        # 5) Create a stock transfer (successful case)
        transfer_payload = {
            "source_branch_id": source_branch_id,
            "destination_branch_id": dest_branch_id,
            "items": [{"product_id": product_id, "quantity": transfer_qty}],
        }
        resp = session.post(
            f"{BASE_URL}/api/stock-transfers", json=transfer_payload, headers=auth_headers, timeout=TIMEOUT
        )
        assert resp.status_code in (200, 201), f"POST /api/stock-transfers failed: {resp.status_code} {resp.text}"
        transfer_json = resp.json()
        created_transfer_id = transfer_json.get("id") or transfer_json.get("data", {}).get("id")
        assert created_transfer_id, f"Created transfer id not found: {transfer_json}"

        # 6) Verify product stock decreased at source branch by transfer_qty
        resp = session.get(f"{BASE_URL}/api/products/{product_id}", headers=auth_headers, timeout=TIMEOUT)
        assert resp.status_code == 200, f"GET /api/products/{product_id} failed after transfer: {resp.status_code} {resp.text}"
        prod_after = resp.json()
        after_stock = int(prod_after.get("stock") or prod_after.get("quantity") or prod_after.get("qty") or current_stock)
        assert after_stock == (current_stock - transfer_qty), f"Source branch stock did not decrease correctly: before={current_stock}, after={after_stock}"

        # 7) GET /api/stock-transfers (list) and ensure transfer present
        resp = session.get(f"{BASE_URL}/api/stock-transfers", headers=auth_headers, timeout=TIMEOUT)
        assert resp.status_code == 200, f"GET /api/stock-transfers failed: {resp.status_code} {resp.text}"
        transfers_list = resp.json() if resp.content else []
        found = False
        if isinstance(transfers_list, list):
            for t in transfers_list:
                tid = t.get("id")
                if tid and str(tid) == str(created_transfer_id):
                    found = True
                    break
        else:
            # sometimes API returns {"data": [...]}
            data = transfers_list.get("data") if isinstance(transfers_list, dict) else None
            if isinstance(data, list):
                for t in data:
                    if str(t.get("id")) == str(created_transfer_id):
                        found = True
                        break
        assert found, f"Created transfer {created_transfer_id} not found in /api/stock-transfers list"

        # 8) GET /api/stock-transfers/{id} and validate items and quantities
        resp = session.get(f"{BASE_URL}/api/stock-transfers/{created_transfer_id}", headers=auth_headers, timeout=TIMEOUT)
        assert resp.status_code == 200, f"GET /api/stock-transfers/{created_transfer_id} failed: {resp.status_code} {resp.text}"
        transfer_detail = resp.json()
        items = transfer_detail.get("items") or transfer_detail.get("data", {}).get("items")
        assert items and isinstance(items, list), f"No items in transfer detail: {transfer_detail}"
        matched = False
        for it in items:
            pid = it.get("product_id") or it.get("product") and it.get("product").get("id")
            qty = it.get("quantity") or it.get("qty") or it.get("quantity_requested")
            if str(pid) == str(product_id) and int(qty) == transfer_qty:
                matched = True
                break
        assert matched, f"Transfer items do not include expected product/quantity: {items}"

        # 9) Attempt an insufficient-stock transfer -> expect 422
        # prepare payload with quantity greater than available
        excessive_qty = after_stock + 1000
        bad_payload = {
            "source_branch_id": source_branch_id,
            "destination_branch_id": dest_branch_id,
            "items": [{"product_id": product_id, "quantity": excessive_qty}],
        }
        resp = session.post(
            f"{BASE_URL}/api/stock-transfers", json=bad_payload, headers=auth_headers, timeout=TIMEOUT
        )
        # Accept either 422 or 400 depending on implementation, but require non-success
        assert resp.status_code in (422, 400), f"Expected validation error for insufficient stock, got {resp.status_code}: {resp.text}"

    except requests.RequestException as e:
        raise AssertionError(f"HTTP request failed: {e}") from e
    finally:
        # cleanup created product and branch if we created them
        # delete product
        try:
            if created_product_id:
                resp = session.delete(f"{BASE_URL}/api/products/{created_product_id}", headers=auth_headers, timeout=TIMEOUT)
                # allow 200 or 204
                if resp.status_code not in (200, 204, 202):
                    print(f"Warning: deleting product {created_product_id} returned {resp.status_code}: {resp.text}", file=sys.stderr)
        except Exception as e:
            print(f"Warning: exception while deleting product: {e}", file=sys.stderr)

        # delete branch
        try:
            if created_branch_id:
                resp = session.delete(f"{BASE_URL}/api/branches/{created_branch_id}", headers=auth_headers, timeout=TIMEOUT)
                if resp.status_code not in (200, 204, 202):
                    print(f"Warning: deleting branch {created_branch_id} returned {resp.status_code}: {resp.text}", file=sys.stderr)
        except Exception as e:
            print(f"Warning: exception while deleting branch: {e}", file=sys.stderr)


if __name__ == "__main__":
    test_post_api_stock_transfers_movement()
    print("TC002: postapistocktransfersmovement passed")
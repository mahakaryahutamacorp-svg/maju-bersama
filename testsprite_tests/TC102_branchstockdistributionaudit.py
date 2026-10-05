import requests
import time
import uuid
import sys

BASE_URL = "http://localhost:8000"
ADMIN_EMAIL = "admin@example.com"
ADMIN_PASSWORD = "password"
TIMEOUT = 30

def login(email, password):
    url = f"{BASE_URL}/api/login"
    resp = requests.post(url, json={"email": email, "password": password}, timeout=TIMEOUT)
    resp.raise_for_status()
    data = resp.json()
    # Accept common token field names
    token = data.get("token") or data.get("access_token") or data.get("plainTextToken") or data.get("data", {}).get("token")
    if not token:
        # Try nested structures
        for v in data.values() if isinstance(data, dict) else []:
            if isinstance(v, str) and "token" in v:
                token = v
                break
    if not token:
        raise RuntimeError(f"Login succeeded but no token found in response: {data}")
    return token

def auth_headers(token):
    return {"Authorization": f"Bearer {token}", "Accept": "application/json"}

def create_branch(headers, name):
    url = f"{BASE_URL}/api/branches"
    payload = {"name": name}
    resp = requests.post(url, json=payload, headers=headers, timeout=TIMEOUT)
    resp.raise_for_status()
    return resp.json()

def delete_branch(headers, branch_id):
    url = f"{BASE_URL}/api/branches/{branch_id}"
    resp = requests.delete(url, headers=headers, timeout=TIMEOUT)
    # allow non-2xx but don't raise
    return resp

def create_product(headers, payload):
    url = f"{BASE_URL}/api/products"
    resp = requests.post(url, json=payload, headers=headers, timeout=TIMEOUT)
    resp.raise_for_status()
    return resp.json()

def delete_product(headers, product_id):
    url = f"{BASE_URL}/api/products/{product_id}"
    resp = requests.delete(url, headers=headers, timeout=TIMEOUT)
    return resp

def get_product(headers, product_id, branch_id=None):
    # Try with branch query param first
    url = f"{BASE_URL}/api/products/{product_id}"
    params = {}
    if branch_id:
        params["branch_id"] = branch_id
    resp = requests.get(url, headers=headers, params=params, timeout=TIMEOUT)
    if resp.status_code == 200:
        return resp.json()
    # fallback: list products scoped to branch and filter
    url2 = f"{BASE_URL}/api/products"
    params2 = {}
    if branch_id:
        params2["branch_id"] = branch_id
    resp2 = requests.get(url2, headers=headers, params=params2, timeout=TIMEOUT)
    resp2.raise_for_status()
    items = resp2.json()
    if isinstance(items, dict) and items.get("data"):
        items = items["data"]
    for it in items:
        if str(it.get("id")) == str(product_id) or str(it.get("product_id")) == str(product_id):
            return it
    return None

def post_stock_transfer(headers, payload):
    url = f"{BASE_URL}/api/stock-transfers"
    resp = requests.post(url, json=payload, headers=headers, timeout=TIMEOUT)
    resp.raise_for_status()
    return resp.json()

def get_stock_transfer(headers, transfer_id):
    url = f"{BASE_URL}/api/stock-transfers/{transfer_id}"
    resp = requests.get(url, headers=headers, timeout=TIMEOUT)
    resp.raise_for_status()
    return resp.json()

def get_journals(headers, params=None):
    url = f"{BASE_URL}/api/journals"
    resp = requests.get(url, headers=headers, params=params or {}, timeout=TIMEOUT)
    if resp.status_code == 200:
        return resp.json()
    return None

def test_branchstockdistributionaudit():
    token = None
    headers = {}
    created_branches = []
    created_products = []
    created_transfer = None
    try:
        # 1. Authenticate
        token = login(ADMIN_EMAIL, ADMIN_PASSWORD)
        headers = auth_headers(token)

        # 2. Create source (Gudang Pusat) and destination (Majubersama 1) branches
        uniq = str(uuid.uuid4())[:8]
        source_name = f"Gudang Pusat Test {uniq}"
        dest_name = f"Cabang Majubersama 1 Test {uniq}"

        src_branch_resp = create_branch(headers, source_name)
        dest_branch_resp = create_branch(headers, dest_name)

        # Extract ids (support possible shapes)
        src_id = src_branch_resp.get("id") or src_branch_resp.get("data", {}).get("id") or src_branch_resp.get("branch", {}).get("id")
        dest_id = dest_branch_resp.get("id") or dest_branch_resp.get("data", {}).get("id") or dest_branch_resp.get("branch", {}).get("id")
        assert src_id, f"Source branch creation did not return id: {src_branch_resp}"
        assert dest_id, f"Destination branch creation did not return id: {dest_branch_resp}"
        created_branches.extend([src_id, dest_id])

        # 3. Create sample products in source branch with initial stock 100 each
        sample_products = []
        for i in range(2):  # create 2 products to represent "ALL products" for test scope
            sku = f"TC102-{uniq}-{i}"
            payload = {
                "name": f"TC102 Product {i} {uniq}",
                "sku": sku,
                "barcode": f"999{int(time.time()*1000)%100000 + i}",
                "price": 10000,
                # Attempt to set branch-scoped stock using common field names
                "branch_id": src_id,
                "stock": 100,
                "initial_stock": 100
            }
            prod_resp = create_product(headers, payload)
            # Extract product id
            prod_id = prod_resp.get("id") or prod_resp.get("data", {}).get("id") or prod_resp.get("product", {}).get("id")
            assert prod_id, f"Product creation did not return id: {prod_resp}"
            created_products.append(prod_id)
            sample_products.append(prod_id)

        # 4. Build stock transfer: transfer 10 pcs for ALL created products
        items = []
        for pid in sample_products:
            items.append({"product_id": pid, "quantity": 10})
        transfer_payload = {
            "source_branch_id": src_id,
            "destination_branch_id": dest_id,
            "items": items
        }
        transfer_resp = post_stock_transfer(headers, transfer_payload)
        # Extract transfer id
        transfer_id = transfer_resp.get("id") or transfer_resp.get("data", {}).get("id") or transfer_resp.get("stock_transfer", {}).get("id") or transfer_resp.get("transfer_id")
        assert transfer_id, f"Stock transfer creation did not return id: {transfer_resp}"
        created_transfer = transfer_id

        # Wait briefly for async processing (if any)
        time.sleep(1)

        # 5. Retrieve transfer details and ensure items present
        transfer_details = get_stock_transfer(headers, transfer_id)
        # Validate transfer items exist and quantities match
        transfer_items = transfer_details.get("items") or transfer_details.get("data", {}).get("items") or transfer_details.get("stock_transfer_items") or transfer_details.get("products") or transfer_details.get("items_transferred")
        assert transfer_items, f"Transfer details did not include items: {transfer_details}"
        # Build mapping from product id to quantity moved
        moved_map = {}
        for it in transfer_items:
            pid = it.get("product_id") or it.get("id") or it.get("product") and it.get("product").get("id")
            qty = it.get("quantity") or it.get("qty") or it.get("amount")
            if pid:
                moved_map[int(pid)] = int(qty or 0)
        for pid in sample_products:
            assert moved_map.get(int(pid)) == 10, f"Transfer moved quantity mismatch for product {pid}: expected 10, got {moved_map.get(int(pid))}"

        # 6. Validate stock levels per branch for each product
        for pid in sample_products:
            # Destination branch stock should be 10
            dest_product = get_product(headers, pid, branch_id=dest_id)
            assert dest_product, f"Could not retrieve product {pid} for destination branch {dest_id}"
            # Try common stock field names
            dest_stock = None
            for k in ("stock", "quantity", "qty", "available_stock", "stock_on_hand", "balance"):
                if isinstance(dest_product, dict) and k in dest_product:
                    dest_stock = dest_product[k]
                    break
            # Also handle nested data
            if dest_stock is None:
                if isinstance(dest_product, dict):
                    if dest_product.get("data") and isinstance(dest_product.get("data"), dict):
                        for k in ("stock", "quantity", "qty"):
                            if k in dest_product["data"]:
                                dest_stock = dest_product["data"][k]
                                break
            assert dest_stock is not None, f"Destination stock field not found for product {pid}: {dest_product}"
            assert int(dest_stock) == 10, f"Destination branch stock for product {pid} expected 10 but got {dest_stock}"

            # Source branch stock should be 90 (100 - 10)
            src_product = get_product(headers, pid, branch_id=src_id)
            assert src_product, f"Could not retrieve product {pid} for source branch {src_id}"
            src_stock = None
            for k in ("stock", "quantity", "qty", "available_stock", "stock_on_hand", "balance"):
                if isinstance(src_product, dict) and k in src_product:
                    src_stock = src_product[k]
                    break
            if src_stock is None:
                if isinstance(src_product, dict):
                    if src_product.get("data") and isinstance(src_product.get("data"), dict):
                        for k in ("stock", "quantity", "qty"):
                            if k in src_product["data"]:
                                src_stock = src_product["data"][k]
                                break
            assert src_stock is not None, f"Source stock field not found for product {pid}: {src_product}"
            assert int(src_stock) == 90, f"Source branch stock for product {pid} expected 90 but got {src_stock}"

        # 7. Validate accounting journals: ensure double-entry for account 1210 Persediaan balanced
        # First, try to find journals linked in transfer details
        journal_entries = transfer_details.get("journals") or transfer_details.get("journal_entries") or transfer_details.get("journals_posted")
        if not journal_entries:
            # Try to fetch all journals and filter by transfer id or branch names
            journals_resp = get_journals(headers)
            if isinstance(journals_resp, dict) and journals_resp.get("data"):
                journals_list = journals_resp["data"]
            elif isinstance(journals_resp, list):
                journals_list = journals_resp
            else:
                journals_list = []
            # Filter journals possibly referencing transfer id or branch names
            candidate_journals = []
            for j in journals_list:
                # Search in narration or reference fields
                narration = (j.get("narration") or j.get("description") or j.get("reference") or "") if isinstance(j, dict) else ""
                if str(transfer_id) in str(j) or str(transfer_id) in str(narration) or source_name in narration or dest_name in narration:
                    candidate_journals.append(j)
            # If found, gather entries inside them
            aggregated_entries = []
            for cj in candidate_journals:
                # entries could be in 'entries' or 'lines'
                lines = cj.get("entries") or cj.get("lines") or cj.get("journal_lines") or []
                aggregated_entries.extend(lines)
            journal_entries = aggregated_entries if aggregated_entries else None

        # If still no journal entries from transfer, try fetching journals endpoint and inspect recent entries for account 1210
        if not journal_entries:
            journals_resp = get_journals(headers, params={"limit": 50})
            journals_list = []
            if journals_resp:
                if isinstance(journals_resp, dict) and journals_resp.get("data"):
                    journals_list = journals_resp["data"]
                elif isinstance(journals_resp, list):
                    journals_list = journals_resp
            # Collect lines from recent journals
            lines = []
            for j in journals_list:
                for key in ("entries", "lines", "journal_lines", "entries_data"):
                    if isinstance(j, dict) and key in j and isinstance(j[key], list):
                        lines.extend(j[key])
            journal_entries = lines if lines else None

        assert journal_entries, f"No journal entries could be located for transfer {transfer_id}. Response transfer details: {transfer_details}"

        # Now ensure that account 1210 Persediaan has balanced debit and credit for the movement
        debit_total = 0.0
        credit_total = 0.0
        for line in journal_entries:
            # line may have fields: account_code, account_number, account, debit, credit, amount, type
            acct = line.get("account_code") or line.get("account_number") or line.get("account") or ""
            acct_str = str(acct)
            # normalize account representation
            if "1210" in acct_str or "Persediaan" in str(line.get("account")):
                # get debit and credit values
                d = line.get("debit") if "debit" in line else line.get("amount_debit") if "amount_debit" in line else line.get("debit_amount")
                c = line.get("credit") if "credit" in line else line.get("amount_credit") if "amount_credit" in line else line.get("credit_amount")
                # If amounts stored as single signed amount field
                if d is None and c is None:
                    amt = line.get("amount") or line.get("value")
                    if isinstance(amt, (int, float)):
                        # can't know sign: skip
                        continue
                debit_total += float(d or 0)
                credit_total += float(c or 0)

        # Accept slight float rounding differences
        assert abs(debit_total - credit_total) < 0.0001 and debit_total > 0, f"Journal entries for account 1210 are not balanced or absent: debit_total={debit_total}, credit_total={credit_total}. Raw journal entries: {journal_entries}"

        print("TC102 passed: branch stock distribution and journal audit validated successfully.")
    except Exception as e:
        print(f"TC102 failed: {e}")
        raise
    finally:
        # Cleanup: delete products and branches created
        # Attempt to delete transfer if endpoint exists (not specified), ignore errors
        if created_transfer:
            try:
                url = f"{BASE_URL}/api/stock-transfers/{created_transfer}"
                requests.delete(url, headers=headers, timeout=TIMEOUT)
            except Exception:
                pass
        for pid in created_products:
            try:
                delete_product(headers, pid)
            except Exception:
                pass
        for bid in created_branches:
            try:
                delete_branch(headers, bid)
            except Exception:
                pass

if __name__ == "__main__":
    test_branchstockdistributionaudit()
import requests
import sys
from typing import Any, Dict, List, Optional, Union

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30
HEADERS_JSON = {"Accept": "application/json", "Content-Type": "application/json"}


def _request(method: str, path: str, token: Optional[str] = None, **kwargs) -> requests.Response:
    url = BASE_URL.rstrip("/") + path
    headers = dict(HEADERS_JSON)
    if token:
        headers["Authorization"] = f"Bearer {token}"
    try:
        resp = requests.request(method, url, headers=headers, timeout=TIMEOUT, **kwargs)
    except requests.RequestException as e:
        raise AssertionError(f"Request to {url} failed: {e}")
    return resp


def login(email: str, password: str = "password") -> Dict[str, Any]:
    resp = _request("POST", "/api/login", json={"email": email, "password": password})
    assert resp.status_code == 200, f"Login for {email} failed: status {resp.status_code}, body: {resp.text}"
    j = resp.json()
    # tolerate different token key names
    token = j.get("token") or j.get("access_token") or j.get("plainTextToken") or (j.get("data") or {}).get("token")
    assert token, f"No token found in login response for {email}: {j}"
    user = j.get("user") or j.get("data") or {}
    # If user not in login response, fetch /api/user
    if not user or not isinstance(user, dict) or ("id" not in user and "email" not in user):
        user_resp = _request("GET", "/api/user", token=token)
        assert user_resp.status_code == 200, f"Failed to GET /api/user after login for {email}: {user_resp.status_code}"
        user = user_resp.json()
    return {"token": token, "user": user}


def _find_first_key(obj: Any, key: str) -> Optional[Any]:
    if isinstance(obj, dict):
        if key in obj:
            return obj[key]
        for v in obj.values():
            res = _find_first_key(v, key)
            if res is not None:
                return res
    elif isinstance(obj, list):
        for item in obj:
            res = _find_first_key(item, key)
            if res is not None:
                return res
    return None


def _collect_all_branch_ids(obj: Any) -> List[Any]:
    found = []
    if isinstance(obj, dict):
        for k, v in obj.items():
            if k in ("branch_id", "branchId", "branch") and v is not None:
                if k == "branch":
                    # branch may be an object with id
                    if isinstance(v, dict) and "id" in v:
                        found.append(v["id"])
                else:
                    found.append(v)
            else:
                found.extend(_collect_all_branch_ids(v))
    elif isinstance(obj, list):
        for item in obj:
            found.extend(_collect_all_branch_ids(item))
    return found


def test_consolidated_financial_reports_access():
    # Master checks: consolidated balance-sheet and income-statement
    master_email = "master@majubersama.test"
    master = login(master_email)
    master_token = master["token"]

    # Balance sheet
    bs_resp = _request("GET", "/api/reports/balance-sheet", token=master_token)
    assert bs_resp.status_code == 200, f"Master balance-sheet expected 200, got {bs_resp.status_code}: {bs_resp.text}"
    bs_json = bs_resp.json()
    # Find 'difference' key anywhere in response
    diff = _find_first_key(bs_json, "difference")
    assert diff is not None, f"No 'difference' field found in balance-sheet response: {bs_json}"
    try:
        diff_val = float(diff)
    except Exception:
        raise AssertionError(f"'difference' field is not numeric: {diff}")
    # Must be 0.00 at two decimal places
    assert round(diff_val, 2) == 0.00, f"Balance sheet difference not 0.00 for master: {diff_val}"

    # Income statement - just ensure accessible
    inc_resp = _request("GET", "/api/reports/income-statement", token=master_token)
    assert inc_resp.status_code == 200, f"Master income-statement expected 200, got {inc_resp.status_code}: {inc_resp.text}"
    # Basic sanity: JSON parseable
    _ = inc_resp.json()

    # Branch admin sales scoping checks
    branch_admins = [
        "asep@mb.pkn",          # Pakan branch admin (CBG-PKN / TOKO PAKAN)
        "gudang@pakan.178",     # Pakan gudang
        "gudang@pupuk.178",     # Pupuk gudang
        "admin@mb.abi",         # Pupuk branch admin (CBG-ABI)
    ]

    for email in branch_admins:
        auth = login(email)
        token = auth["token"]
        user = auth["user"]
        # try to determine user's branch id
        user_branch_id = None
        if isinstance(user, dict):
            user_branch_id = user.get("branch_id") or user.get("branchId") or user.get("branch", {}).get("id")
        # GET sales report
        sales_resp = _request("GET", "/api/reports/sales", token=token)
        assert sales_resp.status_code == 200, f"{email} GET /api/reports/sales expected 200, got {sales_resp.status_code}: {sales_resp.text}"
        sales_json = sales_resp.json()
        # Derive rows list from response flexibly
        rows = []
        if isinstance(sales_json, dict):
            # common wrappers
            if isinstance(sales_json.get("data"), list):
                rows = sales_json.get("data")
            elif isinstance(sales_json.get("data"), dict):
                potential = sales_json.get("data")
                # try to find list inside potential
                found = False
                for k in ("items", "rows", "data", "results"):
                    if isinstance(potential.get(k), list):
                        rows = potential.get(k)
                        found = True
                        break
                if not found:
                    rows = [potential]
            elif isinstance(sales_json.get("items"), list):
                rows = sales_json.get("items")
            elif isinstance(sales_json.get("rows"), list):
                rows = sales_json.get("rows")
            else:
                # treat the dict itself as a single row if it contains meaningful keys
                if sales_json:
                    rows = [sales_json]
                else:
                    rows = []
        elif isinstance(sales_json, list):
            rows = sales_json
        else:
            rows = []

        # If rows present and user branch id known, assert each entry belongs to user's branch
        if rows and user_branch_id is not None:
            for idx, row in enumerate(rows):
                # Try to extract branch id from row
                row_branch_candidates = _collect_all_branch_ids(row)
                if row_branch_candidates:
                    # ensure at least one branch id matches user's branch id
                    if str(user_branch_id) not in [str(x) for x in row_branch_candidates]:
                        raise AssertionError(f"{email} received sales row not scoped to their branch. Expected branch {user_branch_id}, row branches {row_branch_candidates}, row index {idx}, row: {row}")
                else:
                    # if no branch info in row, fall back to checking absence of other-division names is not possible here;
                    # at least ensure there's some data (don't assume leak if no branch_id present)
                    continue

    # Non-master role checks: cashiers should not get consolidated master data.
    cashiers = [
        "kasir@pakan.mb",
        "pipin@kasir.pupuk",
    ]
    for email in cashiers:
        auth = login(email)
        token = auth["token"]
        user = auth["user"]
        # Attempt to GET balance-sheet
        resp = _request("GET", "/api/reports/balance-sheet", token=token)
        if resp.status_code == 200:
            # If allowed, ensure it's branch-scoped (i.e., not consolidated). We check any branch ids present equal user's branch
            bs_json = resp.json()
            user_branch_id = None
            if isinstance(user, dict):
                user_branch_id = user.get("branch_id") or user.get("branchId") or user.get("branch", {}).get("id")
            if user_branch_id is not None:
                branch_ids = _collect_all_branch_ids(bs_json)
                if branch_ids:
                    for b in branch_ids:
                        assert str(b) == str(user_branch_id), f"Cashier {email} received balance-sheet with branch_id {b} not equal to their branch {user_branch_id}"
                else:
                    # No branch ids to validate; accept 200 but warn by asserting that balance sheet should not include a consolidated 'difference' of zero
                    diff = _find_first_key(bs_json, "difference")
                    if diff is not None:
                        try:
                            diff_val = float(diff)
                        except Exception:
                            diff_val = None
                        # If they received a consolidated difference of 0.00, that's a potential permission leak
                        if diff_val is not None and round(diff_val, 2) == 0.00:
                            raise AssertionError(f"Cashier {email} received consolidated balance-sheet difference 0.00 — potential permission leak.")
        else:
            # Accept 403/401 as properly restricted
            assert resp.status_code in (401, 403), f"Unexpected status for cashier {email} GET balance-sheet: {resp.status_code}, body: {resp.text}"

    print("TC003 passed: consolidated reports accessible to master, sales scoped to branch admins, non-master access validated.")


if __name__ == "__main__":
    try:
        test_consolidated_financial_reports_access()
    except AssertionError as e:
        print("Test failed:", e)
        sys.exit(1)
    except Exception as e:
        print("Unexpected error during test:", e)
        sys.exit(2)
    sys.exit(0)

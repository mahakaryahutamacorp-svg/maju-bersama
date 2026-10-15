import requests
import sys
import traceback

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30.0
MASTER_CRED = {"email": "master@majubersama.test", "password": "password"}
NON_MASTER_CRED = {"email": "kasir@pakan.mb", "password": "password"}  # cashier / branch-scoped user

REPORT_ENDPOINTS = [
    ("/api/reports/balance-sheet", "balance_sheet"),
    ("/api/reports/income-statement", "income_statement"),
    ("/api/reports/sales", "sales"),
]

def login(creds):
    url = BASE_URL + "/api/login"
    try:
        r = requests.post(url, json=creds, timeout=TIMEOUT)
    except requests.RequestException as e:
        raise RuntimeError(f"Login request failed: {e}")
    if r.status_code != 200:
        raise RuntimeError(f"Login failed ({r.status_code}): {r.text}")
    j = r.json()
    token = j.get("token") or j.get("data", {}).get("token")
    user = j.get("user") or j.get("data", {}).get("user") or j
    if not token:
        raise RuntimeError(f"No token in login response: {j}")
    return token, user

def logout(token):
    url = BASE_URL + "/api/logout"
    headers = {"Authorization": f"Bearer {token}"}
    try:
        requests.post(url, headers=headers, timeout=TIMEOUT)
    except requests.RequestException:
        pass  # best-effort logout

def auth_get(path, token=None, accept_json=True):
    url = BASE_URL + path
    headers = {}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    if accept_json:
        headers["Accept"] = "application/json"
    try:
        r = requests.get(url, headers=headers, timeout=TIMEOUT)
    except requests.RequestException as e:
        raise RuntimeError(f"GET {path} failed: {e}")
    return r

def find_branch_ids(obj):
    found = []
    if isinstance(obj, dict):
        for k, v in obj.items():
            if k == "branch_id":
                found.append(v)
            else:
                found.extend(find_branch_ids(v))
    elif isinstance(obj, list):
        for item in obj:
            found.extend(find_branch_ids(item))
    return found

def safe_get_json(resp):
    try:
        return resp.json()
    except Exception:
        return None

def assert_almost_equal_zero(value):
    try:
        fv = float(value)
    except Exception:
        raise AssertionError(f"Value is not numeric: {value}")
    if round(abs(fv), 2) != 0.00:
        raise AssertionError(f"Difference is not zero to 2 decimals: {fv}")

def test_consolidated_financial_reports_access_and_data_scope():
    passed = 0
    failed = 0
    master_token = None
    non_master_token = None

    try:
        # 1) Unauthenticated requests to each report endpoint should return 401
        for path, name in REPORT_ENDPOINTS:
            try:
                resp = auth_get(path, token=None)
                try:
                    assert resp.status_code == 401, f"Unauthenticated GET {path} expected 401, got {resp.status_code}, body: {resp.text}"
                    passed += 1
                    print(f"PASS: unauthenticated GET {path} -> 401")
                except AssertionError as e:
                    failed += 1
                    print(f"FAIL: unauthenticated GET {path} -> {e}")
            except Exception as e:
                failed += 1
                print(f"ERROR: unauthenticated GET {path} raised exception: {e}")
                traceback.print_exc()

        # 2) Login as master
        try:
            master_token, master_user = login(MASTER_CRED)
            print("INFO: master login succeeded")
        except Exception as e:
            print(f"FATAL: master login failed: {e}")
            raise

        # 3) Master: GET balance-sheet must return 200 and difference == 0.00
        try:
            resp = auth_get("/api/reports/balance-sheet", token=master_token)
            assert resp.status_code == 200, f"Master GET /api/reports/balance-sheet expected 200, got {resp.status_code}, body: {resp.text}"
            j = safe_get_json(resp)
            # Try common shapes: j['difference'] or j['data']['difference'] or compute from totals
            diff = None
            if isinstance(j, dict):
                if "difference" in j:
                    diff = j["difference"]
                elif "data" in j and isinstance(j["data"], dict) and "difference" in j["data"]:
                    diff = j["data"]["difference"]
                else:
                    # attempt to compute: look for totals keys
                    data = j.get("data", {})
                    assets = data.get("assets_total") or data.get("total_assets") or data.get("assets")
                    liabilities = data.get("liabilities_total") or data.get("total_liabilities") or data.get("liabilities")
                    if assets is not None and liabilities is not None:
                        try:
                            diff = float(assets) - float(liabilities)
                        except Exception:
                            diff = None
            if diff is None:
                raise AssertionError(f"Could not determine balance sheet difference from response: {j}")
            assert_almost_equal_zero(diff)
            passed += 1
            print("PASS: master GET /api/reports/balance-sheet -> 200 and difference == 0.00")
        except AssertionError as e:
            failed += 1
            print(f"FAIL: master balance-sheet assertion failed: {e}")
        except Exception as e:
            failed += 1
            print(f"ERROR: master balance-sheet request failed: {e}")
            traceback.print_exc()

        # 4) Master: GET income-statement and sales should return 200
        for path, name in [("/api/reports/income-statement", "income-statement"), ("/api/reports/sales", "sales")]:
            try:
                resp = auth_get(path, token=master_token)
                assert resp.status_code == 200, f"Master GET {path} expected 200, got {resp.status_code}, body: {resp.text}"
                passed += 1
                print(f"PASS: master GET {path} -> 200")
            except AssertionError as e:
                failed += 1
                print(f"FAIL: master GET {path} -> {e}")
            except Exception as e:
                failed += 1
                print(f"ERROR: master GET {path} exception: {e}")
                traceback.print_exc()

        # 5) Login as non-master (cashier)
        try:
            non_master_token, non_master_user = login(NON_MASTER_CRED)
            print("INFO: non-master login succeeded")
        except Exception as e:
            print(f"FATAL: non-master login failed: {e}")
            raise

        # Attempt to determine user's branch_id for scoping checks
        user_branch_id = None
        try:
            resp = auth_get("/api/user", token=non_master_token)
            if resp.status_code == 200:
                uj = safe_get_json(resp)
                # try typical shapes
                if isinstance(uj, dict):
                    if "branch_id" in uj:
                        user_branch_id = uj["branch_id"]
                    elif "data" in uj and isinstance(uj["data"], dict) and "branch_id" in uj["data"]:
                        user_branch_id = uj["data"]["branch_id"]
                    elif "user" in uj and isinstance(uj["user"], dict) and "branch_id" in uj["user"]:
                        user_branch_id = uj["user"]["branch_id"]
            # If still None, it's okay; we'll only run a subset of checks
        except Exception:
            pass

        # 6) Non-master: GET each report should be 200 and scoped to their branch (no leaked branch_id)
        for path, name in REPORT_ENDPOINTS:
            try:
                resp = auth_get(path, token=non_master_token)
                # Expect 200 for scoped reports per PRD (cashier may read branch-scoped statements)
                assert resp.status_code == 200, f"Non-master GET {path} expected 200, got {resp.status_code}, body: {resp.text}"
                j = safe_get_json(resp)
                # If we could get branch_id for the user, ensure no other branch_ids are present
                if user_branch_id is not None and j is not None:
                    found_branch_ids = find_branch_ids(j)
                    # convert to comparable types (strings/ints)
                    normalized = [str(x) for x in found_branch_ids]
                    # If there are any branch_id occurrences, ensure all equal user's
                    if len(normalized) > 0:
                        if not all(x == str(user_branch_id) for x in normalized):
                            raise AssertionError(f"Non-master report {path} contains branch_id values not equal to user's branch_id {user_branch_id}: {normalized}")
                passed += 1
                print(f"PASS: non-master GET {path} -> 200 and branch-scoping checks passed")
            except AssertionError as e:
                failed += 1
                print(f"FAIL: non-master GET {path} -> {e}")
            except Exception as e:
                failed += 1
                print(f"ERROR: non-master GET {path} exception: {e}")
                traceback.print_exc()

    finally:
        # cleanup: attempt to logout both tokens
        if non_master_token:
            try:
                logout(non_master_token)
            except Exception:
                pass
        if master_token:
            try:
                logout(master_token)
            except Exception:
                pass

    # summary
    total = passed + failed
    print("----- TEST SUMMARY -----")
    print(f"Total assertions: {total}")
    print(f"Passed: {passed}")
    print(f"Failed: {failed}")

    # exit non-zero on failures to signal test failure in CI
    if failed > 0:
        sys.exit(1)
    else:
        sys.exit(0)

if __name__ == "__main__":
    test_consolidated_financial_reports_access_and_data_scope()
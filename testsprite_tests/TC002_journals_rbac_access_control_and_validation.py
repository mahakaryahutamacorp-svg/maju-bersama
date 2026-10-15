import requests
import uuid
import sys
import traceback

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30.0

def run_tc002_journals_rbac_access_control_and_validation():
    passed = 0
    failed = 0

    session = requests.Session()
    session.headers.update({"Accept": "application/json"})

    def report(ok, name, err=None):
        nonlocal passed, failed
        if ok:
            passed += 1
            print(f"[PASS] {name}")
        else:
            failed += 1
            print(f"[FAIL] {name}")
            if err:
                print(err)

    def login(email, password="password"):
        url = f"{BASE_URL}/api/login"
        try:
            r = session.post(url, json={"email": email, "password": password}, timeout=TIMEOUT)
        except Exception as e:
            raise RuntimeError(f"Login request failed for {email}: {e}")
        if r.status_code != 200:
            raise RuntimeError(f"Login failed for {email}: status={r.status_code} body={r.text}")
        try:
            data = r.json()
        except Exception:
            raise RuntimeError(f"Login response not JSON for {email}: {r.text}")
        token = data.get("token") or (data.get("data") and data["data"].get("token"))
        if not token:
            # Some implementations return access_token or similar
            token = data.get("access_token") or data.get("accessToken") or data.get("meta", {}).get("token")
        if not token:
            raise RuntimeError(f"No token found in login response for {email}: {data}")
        return token

    def try_extract_id(resp_json):
        # Accept common shapes: { "id": 1 }, { "data": { "id": 1 } }, { "data": { "journal": { "id": 1 } } }
        if not isinstance(resp_json, dict):
            return None
        if "id" in resp_json and isinstance(resp_json["id"], (int, str)):
            return str(resp_json["id"])
        data = resp_json.get("data")
        if isinstance(data, dict):
            if "id" in data:
                return str(data["id"])
            # nested
            for v in data.values():
                if isinstance(v, dict) and "id" in v:
                    return str(v["id"])
        return None

    # Prepare payloads
    def balanced_journal_payload():
        return {
            "transaction_date": "2026-10-15",
            "reference_number": f"TEST-{uuid.uuid4()}",
            "description": "Automated balanced journal for TC002",
            "lines": [
                {"chart_of_account_id": 1110, "debit": "25.00", "credit": "0.00"},
                {"chart_of_account_id": 4110, "debit": "0.00", "credit": "25.00"},
            ]
        }

    def unbalanced_journal_payload():
        return {
            "transaction_date": "2026-10-15",
            "reference_number": f"TEST-{uuid.uuid4()}",
            "description": "Automated unbalanced journal for TC002",
            "lines": [
                {"chart_of_account_id": 1110, "debit": "30.00", "credit": "0.00"},
                {"chart_of_account_id": 4110, "debit": "0.00", "credit": "25.00"},
            ]
        }

    created_journal_id = None
    master_token = None

    # Subtest 1: Unauthenticated POST /api/journals -> 401
    try:
        r = session.post(f"{BASE_URL}/api/journals", json=balanced_journal_payload(), timeout=TIMEOUT)
        assert r.status_code == 401, f"Expected 401 for unauthenticated POST, got {r.status_code}, body={r.text}"
        report(True, "Unauthenticated POST /api/journals returns 401")
    except AssertionError as e:
        report(False, "Unauthenticated POST /api/journals returns 401", str(e))
    except Exception as e:
        report(False, "Unauthenticated POST /api/journals returns 401", traceback.format_exc())

    # Subtest 2: Unauthenticated GET /api/journals -> 401
    try:
        r = session.get(f"{BASE_URL}/api/journals", timeout=TIMEOUT)
        assert r.status_code == 401, f"Expected 401 for unauthenticated GET, got {r.status_code}, body={r.text}"
        report(True, "Unauthenticated GET /api/journals returns 401")
    except AssertionError as e:
        report(False, "Unauthenticated GET /api/journals returns 401", str(e))
    except Exception as e:
        report(False, "Unauthenticated GET /api/journals returns 401", traceback.format_exc())

    # Subtest 3: Cashier role should receive 403 on POST and GET
    cashier_email = "kasir@pakan.mb"
    cashier_token = None
    try:
        cashier_token = login(cashier_email)
    except Exception as e:
        report(False, f"Login cashier {cashier_email}", traceback.format_exc())
        cashier_token = None

    if cashier_token:
        headers = {"Authorization": f"Bearer {cashier_token}", "Accept": "application/json"}
        try:
            r = session.post(f"{BASE_URL}/api/journals", json=balanced_journal_payload(), headers=headers, timeout=TIMEOUT)
            assert r.status_code == 403, f"Expected 403 for cashier POST, got {r.status_code}, body={r.text}"
            report(True, "Cashier POST /api/journals returns 403")
        except AssertionError as e:
            report(False, "Cashier POST /api/journals returns 403", str(e))
        except Exception as e:
            report(False, "Cashier POST /api/journals returns 403", traceback.format_exc())

        try:
            r = session.get(f"{BASE_URL}/api/journals", headers=headers, timeout=TIMEOUT)
            assert r.status_code == 403, f"Expected 403 for cashier GET, got {r.status_code}, body={r.text}"
            report(True, "Cashier GET /api/journals returns 403")
        except AssertionError as e:
            report(False, "Cashier GET /api/journals returns 403", str(e))
        except Exception as e:
            report(False, "Cashier GET /api/journals returns 403", traceback.format_exc())

    # Subtest 4: Branch admin should receive 403 on POST and GET
    branch_admin_email = "asep@mb.pkn"
    branch_admin_token = None
    try:
        branch_admin_token = login(branch_admin_email)
    except Exception as e:
        report(False, f"Login branch admin {branch_admin_email}", traceback.format_exc())
        branch_admin_token = None

    if branch_admin_token:
        headers = {"Authorization": f"Bearer {branch_admin_token}", "Accept": "application/json"}
        try:
            r = session.post(f"{BASE_URL}/api/journals", json=balanced_journal_payload(), headers=headers, timeout=TIMEOUT)
            assert r.status_code == 403, f"Expected 403 for branch admin POST, got {r.status_code}, body={r.text}"
            report(True, "Branch admin POST /api/journals returns 403")
        except AssertionError as e:
            report(False, "Branch admin POST /api/journals returns 403", str(e))
        except Exception as e:
            report(False, "Branch admin POST /api/journals returns 403", traceback.format_exc())

        try:
            r = session.get(f"{BASE_URL}/api/journals", headers=headers, timeout=TIMEOUT)
            assert r.status_code == 403, f"Expected 403 for branch admin GET, got {r.status_code}, body={r.text}"
            report(True, "Branch admin GET /api/journals returns 403")
        except AssertionError as e:
            report(False, "Branch admin GET /api/journals returns 403", str(e))
        except Exception as e:
            report(False, "Branch admin GET /api/journals returns 403", traceback.format_exc())

    # Subtest 5: Master - balanced POST -> 201, unbalanced POST -> 422, GET -> 200
    master_email = "master@majubersama.test"
    try:
        master_token = login(master_email)
    except Exception as e:
        report(False, f"Login master {master_email}", traceback.format_exc())
        master_token = None

    if master_token:
        headers = {"Authorization": f"Bearer {master_token}", "Accept": "application/json", "Content-Type": "application/json"}
        # Balanced POST
        try:
            r = session.post(f"{BASE_URL}/api/journals", json=balanced_journal_payload(), headers=headers, timeout=TIMEOUT)
            assert r.status_code == 201, f"Expected 201 for master balanced POST, got {r.status_code}, body={r.text}"
            report(True, "Master balanced POST /api/journals returns 201")
            # attempt to extract created id
            try:
                resp_json = r.json()
            except Exception:
                resp_json = {}
            created_journal_id = try_extract_id(resp_json)
        except AssertionError as e:
            report(False, "Master balanced POST /api/journals returns 201", str(e))
        except Exception as e:
            report(False, "Master balanced POST /api/journals returns 201", traceback.format_exc())

        # Unbalanced POST
        try:
            r = session.post(f"{BASE_URL}/api/journals", json=unbalanced_journal_payload(), headers=headers, timeout=TIMEOUT)
            assert r.status_code == 422, f"Expected 422 for master unbalanced POST, got {r.status_code}, body={r.text}"
            report(True, "Master unbalanced POST /api/journals returns 422")
        except AssertionError as e:
            report(False, "Master unbalanced POST /api/journals returns 422", str(e))
        except Exception as e:
            report(False, "Master unbalanced POST /api/journals returns 422", traceback.format_exc())

        # GET journals
        try:
            r = session.get(f"{BASE_URL}/api/journals", headers=headers, timeout=TIMEOUT)
            assert r.status_code == 200, f"Expected 200 for master GET, got {r.status_code}, body={r.text}"
            report(True, "Master GET /api/journals returns 200")
        except AssertionError as e:
            report(False, "Master GET /api/journals returns 200", str(e))
        except Exception as e:
            report(False, "Master GET /api/journals returns 200", traceback.format_exc())

    # Cleanup: attempt to delete created journal if ID available
    if master_token and created_journal_id:
        headers = {"Authorization": f"Bearer {master_token}", "Accept": "application/json"}
        try:
            del_url = f"{BASE_URL}/api/journals/{created_journal_id}"
            r = session.delete(del_url, headers=headers, timeout=TIMEOUT)
            # Accept 200, 202, 204. If 404 or 405, that's acceptable but report.
            if r.status_code in (200, 202, 204):
                report(True, f"Cleanup DELETE /api/journals/{created_journal_id} returned {r.status_code}")
            else:
                # Not necessarily a failure per PRD (delete may not exist), but note it.
                report(False, f"Cleanup DELETE /api/journals/{created_journal_id} unexpected status {r.status_code}", f"Body: {r.text}")
        except Exception as e:
            report(False, f"Cleanup DELETE /api/journals/{created_journal_id}", traceback.format_exc())

    # Summary
    print("TC002 Summary:")
    print(f"Passed: {passed}")
    print(f"Failed: {failed}")

    # Exit non-zero if any failure (useful for CI)
    if failed > 0:
        sys.exit(1)
    else:
        sys.exit(0)

if __name__ == "__main__":
    run_tc002_journals_rbac_access_control_and_validation()
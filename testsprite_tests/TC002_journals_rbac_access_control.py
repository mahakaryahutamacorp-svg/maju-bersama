import requests
import uuid
import datetime
import sys

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30
HEADERS_JSON = {"Accept": "application/json", "Content-Type": "application/json"}


def login_get_token(email, password="password"):
    url = f"{BASE_URL}/api/login"
    payload = {"email": email, "password": password}
    try:
        r = requests.post(url, json=payload, headers=HEADERS_JSON, timeout=TIMEOUT)
    except Exception as e:
        raise RuntimeError(f"Login request failed for {email}: {e}")
    if r.status_code != 200:
        raise AssertionError(f"Expected 200 login for {email}, got {r.status_code}: {r.text}")
    body = r.json()
    token = body.get("token") or body.get("access_token") or (body.get("data") and body["data"].get("token"))
    if not token:
        raise AssertionError(f"No token found in login response for {email}: {body}")
    return token


def post_journal(token=None, payload=None):
    url = f"{BASE_URL}/api/journals"
    headers = {"Accept": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    try:
        r = requests.post(url, json=payload, headers=headers, timeout=TIMEOUT)
    except Exception as e:
        raise RuntimeError(f"POST /api/journals failed: {e}")
    return r


def get_journals(token):
    url = f"{BASE_URL}/api/journals"
    headers = {"Accept": "application/json", "Authorization": f"Bearer {token}"}
    try:
        r = requests.get(url, headers=headers, timeout=TIMEOUT)
    except Exception as e:
        raise RuntimeError(f"GET /api/journals failed: {e}")
    return r


def delete_journal(token, journal_id):
    url = f"{BASE_URL}/api/journals/{journal_id}"
    headers = {"Accept": "application/json", "Authorization": f"Bearer {token}"}
    try:
        r = requests.delete(url, headers=headers, timeout=TIMEOUT)
    except Exception as e:
        # Deletion is best-effort; surface error but don't stop finally block
        return None, f"DELETE request exception: {e}"
    return r, None


def test_journals_rbac_access_control():
    # Test unauthenticated POST => 401
    balanced_ref_guest = f"guest-{uuid.uuid4()}"
    today = datetime.date.today().isoformat()
    balanced_payload_guest = {
        "transaction_date": today,
        "reference_number": balanced_ref_guest,
        "description": "Guest attempt balanced",
        "lines": [
            {"chart_of_account_id": 1, "debit": "25.00", "credit": "0.00"},
            {"chart_of_account_id": 2, "debit": "0.00", "credit": "25.00"},
        ],
    }
    r = post_journal(token=None, payload=balanced_payload_guest)
    assert r.status_code == 401, f"Expected 401 for unauthenticated POST /api/journals, got {r.status_code}: {r.text}"

    # Users to test that must be forbidden (403) on GET and POST /api/journals
    cashier_users = ["kasir@pakan.mb", "pipin@kasir.pupuk"]
    branch_admins = ["asep@mb.pkn", "gudang@pakan.178", "gudang@pupuk.178", "admin@mb.abi"]

    # Test cashier accounts
    for email in cashier_users:
        token = login_get_token(email)
        # GET should be 403
        r_get = None
        try:
            r_get = get_journals(token)
        except Exception as e:
            raise
        assert r_get.status_code == 403, f"Expected 403 for GET /api/journals for cashier {email}, got {r_get.status_code}: {r_get.text}"
        # POST should be 403
        ref = f"{email}-attempt-{uuid.uuid4()}"
        payload = {
            "transaction_date": today,
            "reference_number": ref,
            "description": f"Cashier attempt by {email}",
            "lines": [
                {"chart_of_account_id": 1, "debit": "10.00", "credit": "0.00"},
                {"chart_of_account_id": 2, "debit": "0.00", "credit": "10.00"},
            ],
        }
        r_post = post_journal(token=token, payload=payload)
        assert r_post.status_code == 403, f"Expected 403 for POST /api/journals for cashier {email}, got {r_post.status_code}: {r_post.text}"

    # Test branch admin accounts
    for email in branch_admins:
        token = login_get_token(email)
        # GET should be 403
        r_get = get_journals(token)
        assert r_get.status_code == 403, f"Expected 403 for GET /api/journals for branch admin {email}, got {r_get.status_code}: {r_get.text}"
        # POST should be 403
        ref = f"{email}-attempt-{uuid.uuid4()}"
        payload = {
            "transaction_date": today,
            "reference_number": ref,
            "description": f"Branch admin attempt by {email}",
            "lines": [
                {"chart_of_account_id": 1, "debit": "15.00", "credit": "0.00"},
                {"chart_of_account_id": 2, "debit": "0.00", "credit": "15.00"},
            ],
        }
        r_post = post_journal(token=token, payload=payload)
        assert r_post.status_code == 403, f"Expected 403 for POST /api/journals for branch admin {email}, got {r_post.status_code}: {r_post.text}"

    # Master tests
    master_email = "master@majubersama.test"
    master_token = login_get_token(master_email)

    # Master POST balanced journal => 201 and created resource. Use try/finally to delete it.
    balanced_ref = f"master-balanced-{uuid.uuid4()}"
    balanced_payload = {
        "transaction_date": today,
        "reference_number": balanced_ref,
        "description": "Master balanced journal test",
        "lines": [
            {"chart_of_account_id": 1, "debit": "25.00", "credit": "0.00"},
            {"chart_of_account_id": 2, "debit": "0.00", "credit": "25.00"},
        ],
    }

    created_id = None
    try:
        r_balanced = post_journal(token=master_token, payload=balanced_payload)
        assert r_balanced.status_code == 201, f"Expected 201 for balanced journal by master, got {r_balanced.status_code}: {r_balanced.text}"
        # attempt to extract created id from response
        try:
            jbody = r_balanced.json()
            # common shapes: { "id": ..., ... } or { "data": { "id": ... } }
            if isinstance(jbody, dict):
                created_id = jbody.get("id") or (jbody.get("data") and jbody["data"].get("id"))
        except Exception:
            created_id = None

        # GET /api/journals should return 200 and include our reference
        r_get_master = get_journals(master_token)
        assert r_get_master.status_code == 200, f"Expected 200 for GET /api/journals as master, got {r_get_master.status_code}: {r_get_master.text}"
        try:
            list_body = r_get_master.json()
            found = False
            # search recursively for reference_number
            def search_for_ref(obj, ref):
                if isinstance(obj, dict):
                    for k, v in obj.items():
                        if k == "reference_number" and v == ref:
                            return True
                        if search_for_ref(v, ref):
                            return True
                elif isinstance(obj, list):
                    for item in obj:
                        if search_for_ref(item, ref):
                            return True
                return False
            found = search_for_ref(list_body, balanced_ref)
            assert found, f"Balanced journal with reference {balanced_ref} not found in GET /api/journals response"
        except ValueError:
            raise AssertionError(f"GET /api/journals did not return JSON: {r_get_master.text}")

        # Master POST unbalanced journal => 422 and must not create header
        unbalanced_ref = f"master-unbalanced-{uuid.uuid4()}"
        unbalanced_payload = {
            "transaction_date": today,
            "reference_number": unbalanced_ref,
            "description": "Master unbalanced journal test",
            "lines": [
                {"chart_of_account_id": 1, "debit": "25.00", "credit": "0.00"},
                {"chart_of_account_id": 2, "debit": "0.00", "credit": "20.00"},
            ],
        }
        r_unbalanced = post_journal(token=master_token, payload=unbalanced_payload)
        assert r_unbalanced.status_code == 422, f"Expected 422 for unbalanced journal by master, got {r_unbalanced.status_code}: {r_unbalanced.text}"

        # Verify unbalanced reference does not exist in journal list
        r_get_after_unbalanced = get_journals(master_token)
        assert r_get_after_unbalanced.status_code == 200, f"Expected 200 for GET /api/journals after unbalanced attempt, got {r_get_after_unbalanced.status_code}: {r_get_after_unbalanced.text}"
        try:
            list_body2 = r_get_after_unbalanced.json()
            def search_ref_absent(obj, ref):
                if isinstance(obj, dict):
                    for k, v in obj.items():
                        if k == "reference_number" and v == ref:
                            return True
                        if search_ref_absent(v, ref):
                            return True
                elif isinstance(obj, list):
                    for item in obj:
                        if search_ref_absent(item, ref):
                            return True
                return False
            present = search_ref_absent(list_body2, unbalanced_ref)
            assert not present, f"Unbalanced journal with reference {unbalanced_ref} was found in GET /api/journals but should not have been created"
        except ValueError:
            raise AssertionError(f"GET /api/journals did not return JSON: {r_get_after_unbalanced.text}")

    finally:
        # Cleanup: delete the created balanced journal if we have an id. Attempt best-effort deletion.
        if created_id:
            r_del, err = delete_journal(master_token, created_id)
            if err:
                # deletion attempt had exception; print to stderr but don't fail
                print(f"Warning: deletion attempt raised error: {err}", file=sys.stderr)
            else:
                if r_del.status_code not in (200, 204, 202):
                    # If deletion failed, print warning but do not raise
                    print(f"Warning: Failed to delete journal id={created_id}, status={r_del.status_code}, body={r_del.text}", file=sys.stderr)


if __name__ == "__main__":
    test_journals_rbac_access_control()
    print("TC002 completed successfully.")
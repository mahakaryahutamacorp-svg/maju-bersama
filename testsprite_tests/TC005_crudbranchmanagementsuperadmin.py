import requests
import uuid
import sys
import time

BASE_URL = "http://localhost:8000"
TIMEOUT = 30.0

def test_crud_branch_management_superadmin():
    session = requests.Session()
    session.headers.update({"Accept": "application/json"})
    admin_email = "admin@example.com"
    admin_password = "password"

    token = None
    created_branch_id = None

    try:
        # 1) Login as superadmin (seeded credentials)
        login_url = f"{BASE_URL}/api/login"
        login_payload = {"email": admin_email, "password": admin_password}
        resp = session.post(login_url, json=login_payload, timeout=TIMEOUT)
        # If unauthorized, fail the test explicitly
        assert resp.status_code == 200, f"Login failed: {resp.status_code} {resp.text}"
        login_json = {}
        try:
            login_json = resp.json()
        except ValueError:
            assert False, f"Login response is not JSON: {resp.text}"

        # Extract Sanctum token from common fields
        token_fields = ["token", "access_token", "plainTextToken", "plain_text_token", "data"]
        possible_token = None
        for key in token_fields:
            if key in login_json and isinstance(login_json[key], str):
                possible_token = login_json[key]
                break
            if key in login_json and isinstance(login_json[key], dict):
                # nested 'data': { 'token': '...' }
                for subk in ["token", "access_token", "plainTextToken", "plain_text_token"]:
                    if subk in login_json[key] and isinstance(login_json[key][subk], str):
                        possible_token = login_json[key][subk]
                        break
            if possible_token:
                break
        # Also handle case: login returns { "token": { "plainTextToken": "..." } }
        if not possible_token:
            # try searching anywhere in JSON for a string token-like value
            def find_token(obj):
                if isinstance(obj, dict):
                    for k,v in obj.items():
                        if isinstance(v, str) and len(v) > 20:
                            return v
                        t = find_token(v)
                        if t:
                            return t
                if isinstance(obj, list):
                    for item in obj:
                        t = find_token(item)
                        if t:
                            return t
                return None
            possible_token = find_token(login_json)

        assert possible_token, f"Auth token not found in login response: {login_json}"
        token = possible_token

        # Set auth header for subsequent requests
        session.headers.update({"Authorization": f"Bearer {token}"})

        # 2) GET /api/branches -> should return 200 and a list of branches
        branches_url = f"{BASE_URL}/api/branches"
        resp = session.get(branches_url, timeout=TIMEOUT)
        assert resp.status_code == 200, f"GET /api/branches failed: {resp.status_code} {resp.text}"
        branches_json = resp.json()
        # normalize to a list
        if isinstance(branches_json, dict) and "data" in branches_json and isinstance(branches_json["data"], list):
            branches_list = branches_json["data"]
        elif isinstance(branches_json, list):
            branches_list = branches_json
        else:
            # unknown shape but try to infer
            branches_list = []
            for v in (branches_json.get("data", []) if isinstance(branches_json, dict) else []):
                branches_list.append(v)
        initial_count = len(branches_list)

        # 3) POST /api/branches -> create a new branch (unique)
        unique_suffix = uuid.uuid4().hex[:8]
        new_branch_payload = {
            "name": f"Test Branch {unique_suffix}",
            "code": f"TB{unique_suffix}",
            "address": "123 Test St",
            "phone": "081234567890",
            "is_active": True
        }
        resp = session.post(branches_url, json=new_branch_payload, timeout=TIMEOUT)
        # Expect 201 Created for successful branch creation
        assert resp.status_code in (200, 201), f"POST /api/branches failed: {resp.status_code} {resp.text}"
        created_json = resp.json()
        # Branch may be returned directly or under data
        if isinstance(created_json, dict) and "data" in created_json and isinstance(created_json["data"], dict):
            created_branch = created_json["data"]
        elif isinstance(created_json, dict) and ("id" in created_json or "name" in created_json):
            created_branch = created_json
        else:
            # fallback: if response is list or other, attempt to find by name
            created_branch = None
        # try to extract id
        created_branch_id = None
        if created_branch and "id" in created_branch:
            created_branch_id = created_branch["id"]
        else:
            # try common places
            for possible in (created_json, created_json.get("data") if isinstance(created_json, dict) else None):
                if isinstance(possible, dict) and "id" in possible:
                    created_branch_id = possible["id"]
                    created_branch = possible
                    break
        # If still no id, try to search branches list after a short wait
        if not created_branch_id:
            time.sleep(0.5)
            resp = session.get(branches_url, timeout=TIMEOUT)
            assert resp.status_code == 200, f"GET after create failed: {resp.status_code} {resp.text}"
            resp_json = resp.json()
            if isinstance(resp_json, dict) and "data" in resp_json and isinstance(resp_json["data"], list):
                search_list = resp_json["data"]
            elif isinstance(resp_json, list):
                search_list = resp_json
            else:
                search_list = []
            for b in search_list:
                if isinstance(b, dict) and b.get("name") == new_branch_payload["name"]:
                    created_branch_id = b.get("id")
                    created_branch = b
                    break
        assert created_branch_id is not None, f"Created branch id not found. Response: {created_json}"

        # 4) GET /api/branches -> verify count increased and new branch present
        resp = session.get(branches_url, timeout=TIMEOUT)
        assert resp.status_code == 200, f"GET /api/branches after create failed: {resp.status_code} {resp.text}"
        resp_json = resp.json()
        if isinstance(resp_json, dict) and "data" in resp_json and isinstance(resp_json["data"], list):
            branches_list_after = resp_json["data"]
        elif isinstance(resp_json, list):
            branches_list_after = resp_json
        else:
            branches_list_after = []
        assert len(branches_list_after) >= initial_count + 1, "Branch count did not increase after creation"
        found = any((isinstance(b, dict) and (b.get("id") == created_branch_id or b.get("name") == new_branch_payload["name"])) for b in branches_list_after)
        assert found, "Created branch not found in branch listing"

        # 5) GET /api/branches/{id} -> verify details
        branch_detail_url = f"{BASE_URL}/api/branches/{created_branch_id}"
        resp = session.get(branch_detail_url, timeout=TIMEOUT)
        assert resp.status_code == 200, f"GET /api/branches/{{id}} failed: {resp.status_code} {resp.text}"
        detail_json = resp.json()
        # normalize
        if isinstance(detail_json, dict) and "data" in detail_json and isinstance(detail_json["data"], dict):
            detail = detail_json["data"]
        elif isinstance(detail_json, dict):
            detail = detail_json
        else:
            detail = {}
        assert detail.get("id") == created_branch_id or detail.get("name") == new_branch_payload["name"], "Branch details do not match created branch"

        # 6) Multi-store tenant isolation check (best-effort):
        # If the branch detail contains tenant identifier, assert it's present and consistent.
        # This is a soft assertion: if tenant fields are not present, we skip strict tenant checks.
        tenant_field_candidates = ["tenant_id", "store_id", "company_id", "owner_id"]
        tenant_value = None
        for f in tenant_field_candidates:
            if f in detail:
                tenant_value = detail[f]
                break
        if tenant_value is not None:
            # Ensure all branches returned in listing have either same tenant or do not expose tenant (isolation)
            for b in branches_list_after:
                if isinstance(b, dict) and any(k in b for k in tenant_field_candidates):
                    # compare if exposed
                    b_tenant = None
                    for f in tenant_field_candidates:
                        if f in b:
                            b_tenant = b[f]
                            break
                    assert b_tenant == tenant_value, "Tenant mismatch across branches listing - potential cross-tenant leakage"

        print("TC005 passed: Branch listing, creation, and basic isolation checks succeeded.")

    except AssertionError as ae:
        print(f"AssertionError: {ae}")
        raise
    except requests.RequestException as re:
        print(f"RequestException: {re}")
        raise
    finally:
        # Cleanup: delete created branch if exists
        if created_branch_id:
            try:
                del_url = f"{BASE_URL}/api/branches/{created_branch_id}"
                resp = session.delete(del_url, timeout=TIMEOUT)
                # Accept 200, 202, 204 as successful deletions
                if resp.status_code not in (200, 202, 204):
                    # If deletion failed due to auth or not found, surface warning but not raise to ensure cleanup attempt was made
                    print(f"Warning: cleanup DELETE returned {resp.status_code}: {resp.text}")
                else:
                    print(f"Cleanup: deleted branch {created_branch_id}")
            except requests.RequestException as re:
                print(f"Cleanup RequestException: {re}")

if __name__ == "__main__":
    try:
        test_crud_branch_management_superadmin()
    except Exception as e:
        print(f"Test failed: {e}")
        sys.exit(1)
    sys.exit(0)
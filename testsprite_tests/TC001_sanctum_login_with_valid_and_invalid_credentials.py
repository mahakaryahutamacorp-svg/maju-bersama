import requests
import traceback
import sys

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30
JSON_HEADERS = {"Accept": "application/json", "Content-Type": "application/json"}


def _extract_token(json_body):
    if not isinstance(json_body, dict):
        return None
    for key in ("token", "access_token", "plainTextToken", "plain_text_token", "plain_token"):
        if key in json_body and isinstance(json_body[key], str) and json_body[key].strip():
            return json_body[key]
    # sometimes token may be nested under data
    if "data" in json_body and isinstance(json_body["data"], dict):
        for key in ("token", "access_token", "plainTextToken", "plain_text_token"):
            if key in json_body["data"] and isinstance(json_body["data"][key], str):
                return json_body["data"][key]
    return None


def test_sanctum_login_with_valid_and_invalid_credentials():
    pass_count = 0
    fail_count = 0
    results = []

    session = requests.Session()

    # Test 1: Valid master login, GET /api/user, logout, then GET /api/user returns 401
    try:
        resp = session.post(
            f"{BASE_URL}/api/login",
            json={"email": "master@majubersama.test", "password": "password"},
            headers=JSON_HEADERS,
            timeout=TIMEOUT,
        )
        assert resp.status_code == 200, f"Expected 200 on master login, got {resp.status_code}: {resp.text}"
        body = resp.json()
        token = _extract_token(body)
        assert token, f"No token found in master login response: {body}"

        auth_headers = JSON_HEADERS.copy()
        auth_headers["Authorization"] = f"Bearer {token}"

        resp_user = session.get(f"{BASE_URL}/api/user", headers=auth_headers, timeout=TIMEOUT)
        assert resp_user.status_code == 200, f"Expected 200 on GET /api/user with master token, got {resp_user.status_code}: {resp_user.text}"
        user_body = resp_user.json()
        # try to find email in common shapes
        email = None
        if isinstance(user_body, dict):
            email = user_body.get("email") or (user_body.get("data") or {}).get("email")
        assert email == "master@majubersama.test", f"Unexpected master email in /api/user: {email} body: {user_body}"

        resp_logout = session.post(f"{BASE_URL}/api/logout", headers=auth_headers, timeout=TIMEOUT)
        assert resp_logout.status_code == 200, f"Expected 200 on logout for master, got {resp_logout.status_code}: {resp_logout.text}"

        resp_user_after_logout = session.get(f"{BASE_URL}/api/user", headers=auth_headers, timeout=TIMEOUT)
        assert resp_user_after_logout.status_code == 401, f"Expected 401 after master logout on GET /api/user, got {resp_user_after_logout.status_code}: {resp_user_after_logout.text}"

        pass_count += 1
        results.append(("master_login_logout_flow", True, "passed"))
    except Exception as e:
        fail_count += 1
        results.append(("master_login_logout_flow", False, f"{type(e).__name__}: {e}\n{traceback.format_exc()}"))

    # Test 2: Invalid password returns 401
    try:
        resp_invalid = session.post(
            f"{BASE_URL}/api/login",
            json={"email": "master@majubersama.test", "password": "wrongpassword"},
            headers=JSON_HEADERS,
            timeout=TIMEOUT,
        )
        assert resp_invalid.status_code == 401, f"Expected 401 on invalid master credentials, got {resp_invalid.status_code}: {resp_invalid.text}"
        pass_count += 1
        results.append(("invalid_master_password", True, "passed"))
    except Exception as e:
        fail_count += 1
        results.append(("invalid_master_password", False, f"{type(e).__name__}: {e}\n{traceback.format_exc()}"))

    # Test 3: GET /api/user without token returns 401
    try:
        resp_no_token = session.get(f"{BASE_URL}/api/user", headers=JSON_HEADERS, timeout=TIMEOUT)
        assert resp_no_token.status_code == 401, f"Expected 401 on GET /api/user without token, got {resp_no_token.status_code}: {resp_no_token.text}"
        pass_count += 1
        results.append(("get_user_without_token", True, "passed"))
    except Exception as e:
        fail_count += 1
        results.append(("get_user_without_token", False, f"{type(e).__name__}: {e}\n{traceback.format_exc()}"))

    # Test 4: Valid cashier login, GET /api/user, logout, then GET /api/user returns 401
    try:
        resp_cashier = session.post(
            f"{BASE_URL}/api/login",
            json={"email": "kasir@pakan.mb", "password": "password"},
            headers=JSON_HEADERS,
            timeout=TIMEOUT,
        )
        assert resp_cashier.status_code == 200, f"Expected 200 on cashier login, got {resp_cashier.status_code}: {resp_cashier.text}"
        body_cashier = resp_cashier.json()
        token_cashier = _extract_token(body_cashier)
        assert token_cashier, f"No token found in cashier login response: {body_cashier}"

        auth_headers_cashier = JSON_HEADERS.copy()
        auth_headers_cashier["Authorization"] = f"Bearer {token_cashier}"

        resp_user_cashier = session.get(f"{BASE_URL}/api/user", headers=auth_headers_cashier, timeout=TIMEOUT)
        assert resp_user_cashier.status_code == 200, f"Expected 200 on GET /api/user with cashier token, got {resp_user_cashier.status_code}: {resp_user_cashier.text}"

        resp_logout_cashier = session.post(f"{BASE_URL}/api/logout", headers=auth_headers_cashier, timeout=TIMEOUT)
        assert resp_logout_cashier.status_code == 200, f"Expected 200 on logout for cashier, got {resp_logout_cashier.status_code}: {resp_logout_cashier.text}"

        resp_user_cashier_after = session.get(f"{BASE_URL}/api/user", headers=auth_headers_cashier, timeout=TIMEOUT)
        assert resp_user_cashier_after.status_code == 401, f"Expected 401 after cashier logout on GET /api/user, got {resp_user_cashier_after.status_code}: {resp_user_cashier_after.text}"

        pass_count += 1
        results.append(("cashier_login_logout_flow", True, "passed"))
    except Exception as e:
        fail_count += 1
        results.append(("cashier_login_logout_flow", False, f"{type(e).__name__}: {e}\n{traceback.format_exc()}"))

    total = pass_count + fail_count
    print(f"Total checks: {total}, Passed: {pass_count}, Failed: {fail_count}")
    for name, ok, info in results:
        status = "PASS" if ok else "FAIL"
        print(f"{status}: {name} - {info}")

    if fail_count:
        # exit with non-zero to indicate failure in CI
        sys.exit(1)


if __name__ == "__main__":
    test_sanctum_login_with_valid_and_invalid_credentials()
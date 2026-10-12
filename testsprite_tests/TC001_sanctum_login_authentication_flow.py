import sys
import requests
from requests.exceptions import RequestException

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30
HEADERS_JSON = {"Accept": "application/json", "Content-Type": "application/json"}

def test_sanctum_login_authentication_flow():
    """
    Test the Sanctum login endpoint with valid and invalid credentials,
    verify profile retrieval with valid token and error on missing token,
    and ensure logout revokes the token.
    """
    login_url = f"{BASE_URL}/api/login"
    user_url = f"{BASE_URL}/api/user"
    logout_url = f"{BASE_URL}/api/logout"

    master_email = "master@majubersama.test"
    correct_password = "password"
    wrong_password = "wrong-password-123"

    # 1) Invalid login (wrong password) -> 401
    try:
        r = requests.post(
            login_url,
            headers=HEADERS_JSON,
            json={"email": master_email, "password": wrong_password},
            timeout=TIMEOUT,
        )
    except RequestException as e:
        raise AssertionError(f"HTTP request to {login_url} failed: {e}")

    assert r.status_code == 401, f"Expected 401 for invalid credentials, got {r.status_code}, body: {r.text}"

    # 2) Valid login -> 200 with token and user
    try:
        r = requests.post(
            login_url,
            headers=HEADERS_JSON,
            json={"email": master_email, "password": correct_password},
            timeout=TIMEOUT,
        )
    except RequestException as e:
        raise AssertionError(f"HTTP request to {login_url} failed: {e}")

    assert r.status_code == 200, f"Expected 200 for valid login, got {r.status_code}, body: {r.text}"

    try:
        login_json = r.json()
    except ValueError:
        raise AssertionError(f"Login response is not valid JSON: {r.text}")

    # token may be named 'token' or 'access_token'
    token = None
    if isinstance(login_json, dict):
        token = login_json.get("token") or login_json.get("access_token")
        user_obj = login_json.get("user")
    else:
        token = None
        user_obj = None

    assert token and isinstance(token, str), f"Login response missing token field, body: {login_json}"
    assert user_obj is not None, f"Login response missing user object, body: {login_json}"

    # 3) GET /api/user with valid token -> 200 and contains email, role/branch info
    auth_headers = HEADERS_JSON.copy()
    auth_headers["Authorization"] = f"Bearer {token}"

    try:
        r = requests.get(user_url, headers=auth_headers, timeout=TIMEOUT)
    except RequestException as e:
        raise AssertionError(f"HTTP request to {user_url} failed: {e}")

    assert r.status_code == 200, f"Expected 200 for GET /api/user with token, got {r.status_code}, body: {r.text}"
    try:
        user_json = r.json()
    except ValueError:
        raise AssertionError(f"GET /api/user response is not valid JSON: {r.text}")

    # Normalize user object: some APIs return {user: {...}} others return {...}
    if isinstance(user_json, dict) and "user" in user_json and isinstance(user_json["user"], dict):
        profile = user_json["user"]
    elif isinstance(user_json, dict):
        profile = user_json
    else:
        profile = {}

    assert isinstance(profile, dict), f"Profile is not an object: {user_json}"
    assert profile.get("email") == master_email or profile.get("email") == login_json.get("user", {}).get("email"), f"Profile email mismatch, got: {profile.get('email')}"
    # Role and branch are expected per PRD; check existence if present
    assert ("role" in profile) or ("roles" in profile) or ("branch" in profile) or ("branch_id" in profile), f"Profile seems to lack role/branch info: {profile}"

    # 4) GET /api/user without token -> 401
    try:
        r = requests.get(user_url, headers=HEADERS_JSON, timeout=TIMEOUT)
    except RequestException as e:
        raise AssertionError(f"HTTP request to {user_url} failed: {e}")

    assert r.status_code == 401, f"Expected 401 for GET /api/user without token, got {r.status_code}, body: {r.text}"

    # 5) POST /api/logout with token -> 200
    try:
        r = requests.post(logout_url, headers=auth_headers, timeout=TIMEOUT)
    except RequestException as e:
        raise AssertionError(f"HTTP request to {logout_url} failed: {e}")

    assert r.status_code == 200, f"Expected 200 for POST /api/logout, got {r.status_code}, body: {r.text}"

    # 6) After logout, token must be revoked: GET /api/user with same token -> 401
    try:
        r = requests.get(user_url, headers=auth_headers, timeout=TIMEOUT)
    except RequestException as e:
        raise AssertionError(f"HTTP request to {user_url} failed: {e}")

    assert r.status_code == 401, f"Expected 401 for GET /api/user after logout (token revoked), got {r.status_code}, body: {r.text}"

    print("TC001 passed: Sanctum login/authentication flow validated successfully.")

if __name__ == "__main__":
    try:
        test_sanctum_login_authentication_flow()
    except AssertionError as e:
        print("TC001 FAILED:", e)
        sys.exit(1)
    except Exception as e:
        print("TC001 ERROR:", e)
        sys.exit(2)
    sys.exit(0)
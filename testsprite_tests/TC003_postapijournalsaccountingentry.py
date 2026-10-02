import os
import sys
import time
import requests

BASE_URL = os.environ.get("BASE_ENDPOINT", "http://localhost:8000").rstrip("/")
LOGIN_EMAIL = os.environ.get("LOGIN_EMAIL", "admin@example.com")
LOGIN_PASSWORD = os.environ.get("LOGIN_PASSWORD", "password")
TIMEOUT = 30


def get_bearer_token(session):
    login_url = f"{BASE_URL}/api/login"
    payload = {"email": LOGIN_EMAIL, "password": LOGIN_PASSWORD}
    headers = {"Accept": "application/json", "Content-Type": "application/json"}
    resp = session.post(login_url, json=payload, headers=headers, timeout=TIMEOUT)
    try:
        resp.raise_for_status()
    except requests.HTTPError:
        raise AssertionError(f"Login failed: {resp.status_code} {resp.text}")
    data = {}
    try:
        data = resp.json()
    except ValueError:
        raise AssertionError("Login response is not valid JSON")

    # Try several common token keys used by Laravel APIs
    token = None
    for key in ("token", "access_token", "plain_text_token", "plainTextToken", "plainTextToken", "plain_token"):
        if isinstance(data, dict) and key in data:
            token = data[key]
            break
    # Sometimes token is nested under data
    if not token and isinstance(data, dict) and "data" in data and isinstance(data["data"], dict):
        for key in ("token", "access_token", "plain_text_token", "plainTextToken", "plain_token"):
            if key in data["data"]:
                token = data["data"][key]
                break
    if not token:
        # As a last resort, if the login returned a string token
        if isinstance(data, str):
            token = data
    if not token:
        raise AssertionError(f"Unable to locate token in login response: {data}")
    return token


def extract_id_from_response(json_obj):
    if not isinstance(json_obj, dict):
        return None
    # common places: id at top, data.id, data->id
    if "id" in json_obj:
        return json_obj["id"]
    if "data" in json_obj and isinstance(json_obj["data"], dict) and "id" in json_obj["data"]:
        return json_obj["data"]["id"]
    # sometimes resource key e.g. journal: {id: ...}
    for v in json_obj.values():
        if isinstance(v, dict) and "id" in v:
            return v["id"]
    return None


def test_post_api_journals_accounting_entry():
    session = requests.Session()
    session.headers.update({"Accept": "application/json", "Content-Type": "application/json"})
    token = get_bearer_token(session)
    session.headers.update({"Authorization": f"Bearer {token}"})

    created_journal_id = None
    try:
        # 1) Positive case: balanced debit and credit -> expect 201
        balanced_payload = {
            "date": time.strftime("%Y-%m-%d"),
            "description": "Automated test - balanced journal entry",
            "entries": [
                {"account_code": "1000", "debit": 150.00, "credit": 0.00},
                {"account_code": "2000", "debit": 0.00, "credit": 150.00}
            ]
        }
        url = f"{BASE_URL}/api/journals"
        resp = session.post(url, json=balanced_payload, timeout=TIMEOUT)
        # Accept either 201 Created
        assert resp.status_code == 201, f"Expected 201 for balanced journal, got {resp.status_code}: {resp.text}"
        try:
            body = resp.json()
        except ValueError:
            raise AssertionError("Balanced journal response is not valid JSON")
        created_journal_id = extract_id_from_response(body)
        assert created_journal_id is not None, f"Created journal id not found in response: {body}"

        # Additional sanity checks on response body
        assert isinstance(body, dict), "Balanced journal response JSON must be an object"
        # Ensure the response acknowledges entries or totals
        if "entries" in body:
            assert isinstance(body["entries"], list) and len(body["entries"]) >= 2, "Response 'entries' missing or invalid"
        # Optionally check that totals balance if provided
        if "total_debit" in body or "total_credit" in body:
            tot_debit = body.get("total_debit")
            tot_credit = body.get("total_credit")
            if tot_debit is not None and tot_credit is not None:
                assert float(tot_debit) == float(tot_credit), f"Returned totals not balanced: debit={tot_debit} credit={tot_credit}"

        # 2) Negative case: unbalanced debit and credit -> expect 422
        unbalanced_payload = {
            "date": time.strftime("%Y-%m-%d"),
            "description": "Automated test - unbalanced journal entry",
            "entries": [
                {"account_code": "1000", "debit": 120.00, "credit": 0.00},
                {"account_code": "2000", "debit": 0.00, "credit": 100.00}
            ]
        }
        resp2 = session.post(url, json=unbalanced_payload, timeout=TIMEOUT)
        # Expect 422 Unprocessable Entity for validation error
        assert resp2.status_code == 422, f"Expected 422 for unbalanced journal, got {resp2.status_code}: {resp2.text}"
        try:
            body2 = resp2.json()
        except ValueError:
            raise AssertionError("Unbalanced journal response is not valid JSON")
        # Expect validation error structure
        assert isinstance(body2, dict), "Unbalanced journal response JSON must be an object"
        # Common Laravel validation shape includes 'errors' or a message
        assert "errors" in body2 or "message" in body2, f"Expected validation error details in response, got: {body2}"

        print("TC003 passed: balanced journal created (201) and unbalanced rejected (422).")
    finally:
        # Attempt to clean up created resource if possible
        if created_journal_id:
            delete_url = f"{BASE_URL}/api/journals/{created_journal_id}"
            try:
                del_resp = session.delete(delete_url, timeout=TIMEOUT)
                # Accept 200/204/202 as successful deletion; if 404/405, just warn
                if del_resp.status_code in (200, 202, 204):
                    print(f"Cleaned up created journal id={created_journal_id} (status {del_resp.status_code}).")
                else:
                    print(f"Cleanup request returned status {del_resp.status_code}: {del_resp.text}")
            except requests.RequestException as e:
                print(f"Exception during cleanup request: {e}")


if __name__ == "__main__":
    try:
        test_post_api_journals_accounting_entry()
    except AssertionError as e:
        print(f"TEST FAILED: {e}")
        sys.exit(1)
    except Exception as e:
        print(f"UNEXPECTED ERROR: {e}")
        sys.exit(2)
    sys.exit(0)
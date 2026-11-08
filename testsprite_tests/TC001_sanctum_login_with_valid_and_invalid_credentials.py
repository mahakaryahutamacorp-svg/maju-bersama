import requests


def test_sanctum_login_with_valid_and_invalid_credentials():
    base_url = "http://localhost:8000"
    timeout = 30
    json_headers = {"Accept": "application/json", "Content-Type": "application/json"}

    def login_json(email, password):
        return requests.post(
            f"{base_url}/api/login",
            json={"email": email, "password": password},
            headers=json_headers,
            timeout=timeout,
        )

    def extract_token(body):
        if not isinstance(body, dict):
            return None
        for key in ("token", "access_token", "plainTextToken"):
            if isinstance(body.get(key), str) and body[key]:
                return body[key]
        data = body.get("data")
        if isinstance(data, dict):
            for key in ("token", "access_token"):
                if isinstance(data.get(key), str):
                    return data[key]
        return None

    invalid = login_json("master@majubersama.test", "invalid-password")
    assert invalid.status_code == 422, (
        f"Invalid credentials should return 422 via /api/login, got "
        f"{invalid.status_code}: {invalid.text[:500]}"
    )

    for email in ("master@majubersama.test", "kasir@pakan.mb"):
        login = login_json(email, "password")
        assert login.status_code == 200, (
            f"Login failed for {email}: {login.status_code} {login.text}"
        )
        token = extract_token(login.json())
        assert token, f"Login for {email} did not return a Sanctum token"

        profile = requests.get(
            f"{base_url}/api/user",
            headers={**json_headers, "Authorization": f"Bearer {token}"},
            timeout=timeout,
        )
        assert profile.status_code == 200, profile.text

        logout = requests.post(
            f"{base_url}/api/logout",
            headers={**json_headers, "Authorization": f"Bearer {token}"},
            timeout=timeout,
        )
        assert logout.status_code == 200, logout.text

        after = requests.get(
            f"{base_url}/api/user",
            headers={**json_headers, "Authorization": f"Bearer {token}"},
            timeout=timeout,
        )
        assert after.status_code == 401, (
            f"Revoked token should return 401, got {after.status_code}"
        )

    unauthenticated = requests.get(
        f"{base_url}/api/user",
        headers=json_headers,
        timeout=timeout,
    )
    assert unauthenticated.status_code == 401


if __name__ == "__main__":
    test_sanctum_login_with_valid_and_invalid_credentials()

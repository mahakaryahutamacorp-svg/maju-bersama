import requests
from decimal import Decimal, InvalidOperation

BASE_URL = "http://localhost:8000"
TIMEOUT = 30


def test_consolidated_financial_reports_access_and_data_scope():
    session = requests.Session()
    report_paths = (
        "/api/reports/balance-sheet",
        "/api/reports/income-statement",
        "/api/reports/sales",
    )
    accounts = {
        "master": ("master@majubersama.test", "master"),
        "pakan_admin_1": ("asep@mb.pkn", "pakan"),
        "pakan_admin_2": ("gudang@pakan.178", "pakan"),
        "pupuk_admin_1": ("gudang@pupuk.178", "pupuk"),
        "pupuk_admin_2": ("admin@mb.abi", "pupuk"),
    }

    def request_json(method, path, **kwargs):
        try:
            response = session.request(
                method,
                f"{BASE_URL}{path}",
                timeout=TIMEOUT,
                **kwargs,
            )
        except requests.RequestException as exc:
            raise AssertionError(f"Request to {path} failed: {exc}") from exc

        try:
            body = response.json()
        except ValueError:
            body = None
        return response, body

    def find_token(value):
        if isinstance(value, dict):
            for key in ("token", "access_token", "plainTextToken"):
                if value.get(key):
                    return value[key]
            for nested in value.values():
                token = find_token(nested)
                if token:
                    return token
        elif isinstance(value, list):
            for nested in value:
                token = find_token(nested)
                if token:
                    return token
        return None

    def context_markers(value, in_branch_context=False):
        markers = set()
        if isinstance(value, dict):
            for key, nested in value.items():
                normalized = str(key).lower().replace("-", "_")
                is_branch_context = (
                    in_branch_context
                    or "branch" in normalized
                    or normalized == "store"
                    or normalized == "stores"
                )

                if normalized in ("branch_id", "branch_name", "store_id", "store_name"):
                    if isinstance(nested, (str, int, float)):
                        markers.add(str(nested).strip().lower())

                if is_branch_context and normalized in ("id", "name", "code"):
                    if isinstance(nested, (str, int, float)):
                        markers.add(str(nested).strip().lower())

                markers.update(context_markers(nested, is_branch_context))
        elif isinstance(value, list):
            for nested in value:
                markers.update(context_markers(nested, in_branch_context))
        return markers

    def find_balance_difference(value):
        if isinstance(value, dict):
            for key, nested in value.items():
                normalized = str(key).lower().replace("-", "_")
                if "difference" in normalized and not isinstance(nested, (dict, list)):
                    try:
                        return Decimal(str(nested))
                    except (InvalidOperation, TypeError):
                        pass
                result = find_balance_difference(nested)
                if result is not None:
                    return result
        elif isinstance(value, list):
            for nested in value:
                result = find_balance_difference(nested)
                if result is not None:
                    return result
        return None

    tokens = {}
    profiles = {}
    try:
        for account_name, (email, _) in accounts.items():
            response, body = request_json(
                "POST",
                "/api/login",
                json={"email": email, "password": "password"},
                headers={"Accept": "application/json"},
            )
            assert response.status_code == 200, (
                f"Login for {email} returned {response.status_code}: {body}"
            )
            token = find_token(body)
            assert token, f"Login response for {email} did not include a token"
            tokens[account_name] = token

        for account_name, (email, _) in accounts.items():
            headers = {
                "Accept": "application/json",
                "Authorization": f"Bearer {tokens[account_name]}",
            }
            response, body = request_json("GET", "/api/user", headers=headers)
            assert response.status_code == 200, (
                f"GET /api/user for {email} returned {response.status_code}: {body}"
            )
            assert isinstance(body, (dict, list)), f"Invalid user response for {email}"
            profiles[account_name] = body

        master_headers = {
            "Accept": "application/json",
            "Authorization": f"Bearer {tokens['master']}",
        }
        master_reports = {}
        for path in report_paths:
            response, body = request_json("GET", path, headers=master_headers)
            assert response.status_code == 200, (
                f"Master GET {path} returned {response.status_code}: {body}"
            )
            assert isinstance(body, (dict, list)), f"Invalid JSON response from {path}"
            master_reports[path] = body

        difference = find_balance_difference(master_reports["/api/reports/balance-sheet"])
        assert difference is not None, (
            "Master balance-sheet response did not include a difference value"
        )
        assert difference == Decimal("0.00"), (
            f"Expected a zero balance-sheet difference, received {difference}"
        )

        pakan_markers = set()
        pupuk_markers = set()
        for account_name, (_, division) in accounts.items():
            if division == "master":
                continue
            markers = context_markers(profiles[account_name])
            if division == "pakan":
                pakan_markers.update(markers)
            else:
                pupuk_markers.update(markers)

        for account_name, (_, division) in accounts.items():
            if division == "master":
                continue

            headers = {
                "Accept": "application/json",
                "Authorization": f"Bearer {tokens[account_name]}",
            }
            opposite_markers = pupuk_markers if division == "pakan" else pakan_markers

            for path in report_paths:
                response, body = request_json("GET", path, headers=headers)
                assert response.status_code == 200, (
                    f"{account_name} GET {path} returned {response.status_code}: {body}"
                )
                assert isinstance(body, (dict, list)), (
                    f"Invalid JSON response from {path} for {account_name}"
                )

                report_markers = context_markers(body)
                leaked_markers = report_markers.intersection(opposite_markers)
                assert not leaked_markers, (
                    f"{account_name} received cross-division branch data from {path}: "
                    f"{sorted(leaked_markers)}"
                )

        for path in report_paths:
            response, body = request_json(
                "GET",
                path,
                headers={"Accept": "application/json"},
            )
            assert response.status_code == 401, (
                f"Unauthenticated GET {path} returned {response.status_code}: {body}"
            )
    finally:
        session.close()


if __name__ == "__main__":
    test_consolidated_financial_reports_access_and_data_scope()
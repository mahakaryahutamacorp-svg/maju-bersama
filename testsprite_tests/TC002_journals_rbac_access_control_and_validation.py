import json
import uuid
from datetime import date

import requests


def _bootstrap_headers(token: str) -> dict:
    return {
        "Accept": "application/json",
        "Content-Type": "application/json",
        "Authorization": f"Bearer {token}",
    }


def _first_product_id(products_body) -> int | None:
    data = products_body.get("data", products_body)
    if isinstance(data, list) and data:
        return data[0].get("id")
    return None


def _branch_id(branches_body, code: str) -> int | None:
    data = branches_body.get("data", branches_body)
    if not isinstance(data, list):
        return None
    for branch in data:
        if isinstance(branch, dict) and branch.get("code") == code:
            return branch.get("id")
    return None


def bootstrap_pakan_sellable(base_url: str, login, *, timeout: int = 30, min_stock: int = 5) -> int:
    master_token = login("master@majubersama.test")
    master_headers = _bootstrap_headers(master_token)
    kasir_headers = _bootstrap_headers(login("kasir@pakan.mb"))
    listed = requests.get(f"{base_url}/api/products", headers=kasir_headers, timeout=timeout)
    assert listed.status_code == 200, listed.text
    rows = listed.json().get("data", listed.json())
    if isinstance(rows, list):
        for row in rows:
            stock = row.get("stock")
            if row.get("id") and isinstance(stock, (int, float)) and stock >= 1:
                return int(row["id"])
    branches = requests.get(f"{base_url}/api/branches", headers=master_headers, timeout=timeout)
    assert branches.status_code == 200, branches.text
    pakan_id = _branch_id(branches.json(), "PAKAN")
    pusat_id = _branch_id(branches.json(), "PUSAT")
    if pusat_id is None:
        data = branches.json().get("data", [])
        pusat_id = data[0]["id"] if data else None
    assert pakan_id and pusat_id, "PAKAN/PUSAT branch ids required"
    master_products = requests.get(f"{base_url}/api/products", headers=master_headers, timeout=timeout)
    assert master_products.status_code == 200, master_products.text
    source_product_id = _first_product_id(master_products.json())
    assert source_product_id is not None, "Master catalog has no products"
    adjustment = requests.post(
        f"{base_url}/api/stock-adjustments",
        headers=master_headers,
        json={"branch_id": pusat_id, "items": [{"product_id": source_product_id, "actual_qty": min_stock}]},
        timeout=timeout,
    )
    assert adjustment.status_code == 201, adjustment.text
    transfer = requests.post(
        f"{base_url}/api/stock-transfers",
        headers=master_headers,
        json={
            "source_branch_id": pusat_id,
            "destination_branch_id": pakan_id,
            "items": [{"product_id": source_product_id, "quantity": min_stock}],
        },
        timeout=timeout,
    )
    assert transfer.status_code == 201, transfer.text
    listed = requests.get(f"{base_url}/api/products", headers=kasir_headers, timeout=timeout)
    assert listed.status_code == 200, listed.text
    rows = listed.json().get("data", listed.json())
    assert isinstance(rows, list) and rows, json.dumps(listed.json())[:300]
    destination = next(
        (row for row in rows if isinstance(row.get("stock"), (int, float)) and row["stock"] >= 1),
        rows[0],
    )
    product_id = destination.get("id")
    assert product_id is not None
    return int(product_id)


def test_journals_rbac_access_control_and_validation():
    base_url = "http://localhost:8000"
    timeout = 30
    created_journal_id = None
    master_headers = None

    def login(email):
        response = requests.post(
            f"{base_url}/api/login",
            json={"email": email, "password": "password"},
            headers={"Accept": "application/json", "Content-Type": "application/json"},
            timeout=timeout,
        )
        assert response.status_code == 200, (
            f"Login failed for {email}: {response.status_code} {response.text}"
        )
        try:
            payload = response.json()
        except ValueError as exc:
            raise AssertionError(f"Login for {email} did not return JSON") from exc

        def find_token(value):
            if isinstance(value, dict):
                for key in ("token", "access_token", "plainTextToken"):
                    if isinstance(value.get(key), str) and value[key]:
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

        token = find_token(payload)
        assert token, f"Login response for {email} did not contain a Sanctum token"
        return {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "Authorization": f"Bearer {token}",
        }

    def walk(value):
        if isinstance(value, dict):
            yield value
            for nested in value.values():
                yield from walk(nested)
        elif isinstance(value, list):
            for nested in value:
                yield from walk(nested)

    try:
        master_headers = login("master@majubersama.test")
        admin_headers = login("gudang@pakan.178")
        cashier_headers = login("kasir@pakan.mb")

        def token_from_headers(headers):
            auth = headers.get("Authorization", "")
            return auth.split(" ", 1)[1] if auth.lower().startswith("bearer ") else auth

        product_id = bootstrap_pakan_sellable(
            base_url,
            lambda email: token_from_headers(login(email)),
        )

        checkout_response = requests.post(
            f"{base_url}/api/checkout",
            json={"items": [{"product_id": product_id, "quantity": 1}]},
            headers=cashier_headers,
            timeout=timeout,
        )
        assert checkout_response.status_code == 201, (
            f"Checkout bootstrap expected 201, received "
            f"{checkout_response.status_code}: {checkout_response.text}"
        )

        journals_response = requests.get(
            f"{base_url}/api/journals",
            headers=master_headers,
            timeout=timeout,
        )
        assert journals_response.status_code == 200, (
            f"Master could not read journals: {journals_response.status_code} "
            f"{journals_response.text}"
        )
        journals_payload = journals_response.json()
        account_ids = {}
        for item in walk(journals_payload):
            account = item.get("chart_of_account")
            if isinstance(account, dict):
                code = str(account.get("code", account.get("account_code", "")))
                account_id = item.get("chart_of_account_id", account.get("id"))
                if code in {"1110", "4110"} and account_id is not None:
                    account_ids[code] = account_id

        assert "1110" in account_ids and "4110" in account_ids, (
            "Could not find chart of account IDs for codes 1110 and 4110 "
            "in journal_lines.chart_of_account"
        )

        reference = f"TC002-{uuid.uuid4().hex[:12]}"
        balanced_payload = {
            "transaction_date": date.today().isoformat(),
            "reference_number": reference,
            "description": "TC002 balanced journal RBAC validation",
            "lines": [
                {
                    "chart_of_account_id": account_ids["1110"],
                    "debit": 1000,
                    "credit": 0,
                },
                {
                    "chart_of_account_id": account_ids["4110"],
                    "debit": 0,
                    "credit": 1000,
                },
            ],
        }

        unauthenticated_post = requests.post(
            f"{base_url}/api/journals",
            json=balanced_payload,
            headers={"Accept": "application/json", "Content-Type": "application/json"},
            timeout=timeout,
        )
        assert unauthenticated_post.status_code == 401, (
            f"Unauthenticated journal POST expected 401, received "
            f"{unauthenticated_post.status_code}: {unauthenticated_post.text}"
        )

        unauthenticated_get = requests.get(
            f"{base_url}/api/journals",
            headers={"Accept": "application/json"},
            timeout=timeout,
        )
        assert unauthenticated_get.status_code == 401, (
            f"Unauthenticated journal GET expected 401, received "
            f"{unauthenticated_get.status_code}: {unauthenticated_get.text}"
        )

        for role, headers in (
            ("cashier", cashier_headers),
            ("branch admin", admin_headers),
        ):
            post_response = requests.post(
                f"{base_url}/api/journals",
                json=balanced_payload,
                headers=headers,
                timeout=timeout,
            )
            assert post_response.status_code == 403, (
                f"{role} journal POST expected 403, received "
                f"{post_response.status_code}: {post_response.text}"
            )

            get_response = requests.get(
                f"{base_url}/api/journals",
                headers=headers,
                timeout=timeout,
            )
            assert get_response.status_code == 403, (
                f"{role} journal GET expected 403, received "
                f"{get_response.status_code}: {get_response.text}"
            )

        balanced_response = requests.post(
            f"{base_url}/api/journals",
            json=balanced_payload,
            headers=master_headers,
            timeout=timeout,
        )
        assert balanced_response.status_code == 201, (
            f"Master balanced journal POST expected 201, received "
            f"{balanced_response.status_code}: {balanced_response.text}"
        )

        try:
            balanced_result = balanced_response.json()
        except ValueError:
            balanced_result = {}
        for item in walk(balanced_result):
            if item.get("id") is not None and (
                "transaction_date" in item
                or "reference_number" in item
                or "lines" in item
                or "journal_lines" in item
            ):
                created_journal_id = item["id"]
                break
        if created_journal_id is None and isinstance(balanced_result, dict):
            candidate = balanced_result.get("data")
            if isinstance(candidate, dict):
                created_journal_id = candidate.get("id")
            elif balanced_result.get("id") is not None:
                created_journal_id = balanced_result["id"]

        unbalanced_payload = {
            "transaction_date": date.today().isoformat(),
            "reference_number": f"TC002-UNBAL-{uuid.uuid4().hex[:12]}",
            "description": "TC002 unbalanced journal validation",
            "lines": [
                {
                    "chart_of_account_id": account_ids["1110"],
                    "debit": 1000,
                    "credit": 0,
                },
                {
                    "chart_of_account_id": account_ids["4110"],
                    "debit": 0,
                    "credit": 999,
                },
            ],
        }
        unbalanced_response = requests.post(
            f"{base_url}/api/journals",
            json=unbalanced_payload,
            headers=master_headers,
            timeout=timeout,
        )
        assert unbalanced_response.status_code == 422, (
            f"Master unbalanced journal POST expected 422, received "
            f"{unbalanced_response.status_code}: {unbalanced_response.text}"
        )
    finally:
        if created_journal_id is not None and master_headers is not None:
            try:
                requests.delete(
                    f"{base_url}/api/journals/{created_journal_id}",
                    headers=master_headers,
                    timeout=timeout,
                )
            except requests.RequestException:
                pass


if __name__ == "__main__":
    test_journals_rbac_access_control_and_validation()
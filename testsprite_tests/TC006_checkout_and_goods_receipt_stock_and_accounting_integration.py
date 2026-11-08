from datetime import date
import json

import requests

BASE_URL = "http://localhost:8000"


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


def test_checkout_and_goods_receipt_stock_and_accounting_integration():
    session = requests.Session()
    session.headers.update({"Accept": "application/json"})

    def api(method, path, token=None, **kwargs):
        headers = kwargs.pop("headers", {})
        if token:
            headers["Authorization"] = f"Bearer {token}"
        response = session.request(
            method,
            f"{BASE_URL}{path}",
            headers=headers,
            timeout=30,
            **kwargs,
        )
        return response

    def response_json(response):
        try:
            return response.json()
        except ValueError:
            return {}

    def login(email):
        response = api(
            "POST",
            "/api/login",
            json={"email": email, "password": "password"},
        )
        assert response.status_code == 200, (
            f"Login failed for {email}: {response.status_code} {response.text}"
        )
        data = response_json(response)
        token = data.get("token") or data.get("access_token")
        if not token and isinstance(data.get("data"), dict):
            token = data["data"].get("token") or data["data"].get("access_token")
        assert token, f"No Sanctum token returned for {email}: {data}"
        return token

    def records_from(data):
        if isinstance(data, list):
            return data
        if not isinstance(data, dict):
            return []
        for key in ("data", "products", "suppliers", "items", "results"):
            value = data.get(key)
            if isinstance(value, list):
                return value
            if isinstance(value, dict):
                nested = records_from(value)
                if nested:
                    return nested
        return []

    def find_record(data, record_id):
        for record in records_from(data):
            if isinstance(record, dict) and str(record.get("id")) == str(record_id):
                return record
        return None

    def read_product(token, product_id):
        response = api("GET", "/api/products", token=token)
        assert response.status_code == 200, (
            f"Could not read products: {response.status_code} {response.text}"
        )
        product = find_record(response_json(response), product_id)
        assert product is not None, f"Product {product_id} not found in product listing"
        assert isinstance(product.get("stock"), int), (
            f"Product stock must be an integer: {product}"
        )
        return product

    def entry_lines(entry):
        for key in ("journal_lines", "lines"):
            value = entry.get(key)
            if isinstance(value, list):
                return value
        return []

    def journal_entries(data):
        if isinstance(data, list):
            return [item for item in data if isinstance(item, dict)]
        if not isinstance(data, dict):
            return []
        for key in ("data", "journals", "results"):
            value = data.get(key)
            if isinstance(value, list):
                return [item for item in value if isinstance(item, dict)]
            if isinstance(value, dict):
                result = journal_entries(value)
                if result:
                    return result
        return []

    def assert_balanced_journal(entries, previous_ids):
        new_entries = [
            entry
            for entry in entries
            if str(entry.get("id")) not in previous_ids
            and entry_lines(entry)
        ]
        assert new_entries, "No new journal entry with journal lines was posted"
        entry = new_entries[0]
        debit_total = 0.0
        credit_total = 0.0
        for line in entry_lines(entry):
            debit = line.get("debit", line.get("debit_amount", 0))
            credit = line.get("credit", line.get("credit_amount", 0))
            debit_total += float(debit or 0)
            credit_total += float(credit or 0)
        assert round(debit_total, 2) == round(credit_total, 2), (
            f"Journal is unbalanced: debit={debit_total}, credit={credit_total}"
        )

    warehouse_token = login("gudang@pakan.178")
    cashier_token = login("kasir@pakan.mb")
    master_token = login("master@majubersama.test")

    product_id = bootstrap_pakan_sellable(BASE_URL, login)

    stock_before_tests = read_product(warehouse_token, product_id)["stock"]
    assert stock_before_tests >= 1, (
        f"Expected positive stock after bootstrap checkout, got {stock_before_tests}"
    )

    journals_before_response = api("GET", "/api/journals", token=master_token)
    assert journals_before_response.status_code == 200, (
        f"Could not read journals: {journals_before_response.status_code} "
        f"{journals_before_response.text}"
    )
    journals_before = journal_entries(response_json(journals_before_response))
    journal_ids_before = {str(entry.get("id")) for entry in journals_before}

    valid_checkout = api(
        "POST",
        "/api/checkout",
        token=cashier_token,
        json={
            "items": [
                {
                    "product_id": product_id,
                    "quantity": 1,
                    "discount_amount": 0,
                }
            ]
        },
    )
    assert valid_checkout.status_code == 201, (
        f"Valid checkout should return 201: {valid_checkout.status_code} "
        f"{valid_checkout.text}"
    )
    stock_after_valid_checkout = read_product(warehouse_token, product_id)["stock"]
    assert stock_after_valid_checkout == stock_before_tests - 1, (
        f"Valid checkout did not decrement stock by one: before={stock_before_tests}, "
        f"after={stock_after_valid_checkout}"
    )

    journals_after_checkout_response = api(
        "GET", "/api/journals", token=master_token
    )
    assert journals_after_checkout_response.status_code == 200, (
        f"Could not read journals after checkout: "
        f"{journals_after_checkout_response.status_code} "
        f"{journals_after_checkout_response.text}"
    )
    journals_after_checkout = journal_entries(
        response_json(journals_after_checkout_response)
    )
    assert_balanced_journal(journals_after_checkout, journal_ids_before)

    overstock_response = api(
        "POST",
        "/api/checkout",
        token=cashier_token,
        json={
            "items": [
                {
                    "product_id": product_id,
                    "quantity": stock_after_valid_checkout + 100,
                    "discount_amount": 0,
                }
            ]
        },
    )
    assert overstock_response.status_code == 422, (
        f"Over-stock checkout should return 422: {overstock_response.status_code} "
        f"{overstock_response.text}"
    )
    stock_after_overstock_attempt = read_product(warehouse_token, product_id)["stock"]
    assert stock_after_overstock_attempt == stock_after_valid_checkout, (
        "Stock changed after over-stock checkout was rejected"
    )

    invalid_discount_response = api(
        "POST",
        "/api/checkout",
        token=cashier_token,
        json={
            "items": [
                {
                    "product_id": product_id,
                    "quantity": 1,
                    "discount_amount": 999999999,
                }
            ]
        },
    )
    assert invalid_discount_response.status_code == 422, (
        f"Invalid discount should return 422: "
        f"{invalid_discount_response.status_code} {invalid_discount_response.text}"
    )
    stock_after_discount_attempt = read_product(warehouse_token, product_id)["stock"]
    assert stock_after_discount_attempt == stock_after_valid_checkout, (
        "Stock changed after invalid-discount checkout was rejected"
    )

    suppliers_response = api("GET", "/api/suppliers", token=warehouse_token)
    assert suppliers_response.status_code == 200, (
        f"Could not list suppliers: {suppliers_response.status_code} "
        f"{suppliers_response.text}"
    )
    suppliers = records_from(response_json(suppliers_response))
    if not suppliers:
        master_suppliers = api("GET", "/api/suppliers", token=master_token)
        assert master_suppliers.status_code == 200, master_suppliers.text
        suppliers = records_from(response_json(master_suppliers))

    current_product = read_product(warehouse_token, product_id)
    unit_cost = (
        current_product.get("cost_price")
        or current_product.get("purchase_price")
        or current_product.get("price")
        or 1
    )

    stock_after_purchase_order = stock_after_valid_checkout
    if suppliers:
        supplier_id = suppliers[0].get("id")
        assert supplier_id is not None, f"Supplier has no ID: {suppliers[0]}"
        purchase_order_response = api(
            "POST",
            "/api/purchase-orders",
            token=warehouse_token,
            json={
                "supplier_id": supplier_id,
                "order_date": date.today().isoformat(),
                "items": [
                    {
                        "product_id": product_id,
                        "quantity": 2,
                        "unit_price": unit_cost,
                    }
                ],
            },
        )
        assert purchase_order_response.status_code in (200, 201), (
            f"Purchase order creation failed: {purchase_order_response.status_code} "
            f"{purchase_order_response.text}"
        )
        stock_after_purchase_order = read_product(warehouse_token, product_id)["stock"]
        assert stock_after_purchase_order == stock_after_valid_checkout, (
            "Creating a purchase order must not change product stock"
        )

    journals_before_receipt_response = api("GET", "/api/journals", token=master_token)
    assert journals_before_receipt_response.status_code == 200, (
        f"Could not read journals before goods receipt: "
        f"{journals_before_receipt_response.status_code} "
        f"{journals_before_receipt_response.text}"
    )
    journals_before_receipt = journal_entries(
        response_json(journals_before_receipt_response)
    )
    journal_ids_before_receipt = {
        str(entry.get("id")) for entry in journals_before_receipt
    }

    goods_receipt_payload = {
        "payment_type": "cash",
        "supplier_name": "TC006 Test Supplier",
        "date": date.today().isoformat(),
        "items": [
            {
                "product_id": product_id,
                "quantity": 2,
                "unit_price": unit_cost,
            }
        ],
    }
    goods_receipt_response = api(
        "POST",
        "/api/goods-receipts",
        token=warehouse_token,
        json=goods_receipt_payload,
    )
    assert goods_receipt_response.status_code in (200, 201), (
        f"Goods receipt creation failed: {goods_receipt_response.status_code} "
        f"{goods_receipt_response.text}"
    )

    stock_after_receipt = read_product(warehouse_token, product_id)["stock"]
    assert stock_after_receipt == stock_after_purchase_order + 2, (
        f"Goods receipt should increase stock by two: "
        f"before={stock_after_purchase_order}, after={stock_after_receipt}"
    )

    journals_after_receipt_response = api("GET", "/api/journals", token=master_token)
    assert journals_after_receipt_response.status_code == 200, (
        f"Could not read journals after goods receipt: "
        f"{journals_after_receipt_response.status_code} "
        f"{journals_after_receipt_response.text}"
    )
    journals_after_receipt = journal_entries(
        response_json(journals_after_receipt_response)
    )
    assert_balanced_journal(journals_after_receipt, journal_ids_before_receipt)


if __name__ == "__main__":
    test_checkout_and_goods_receipt_stock_and_accounting_integration()
"""HTTP-only fixture: product with stock on PAKAN for kasir@pakan.mb."""

from __future__ import annotations

import json

import requests


def _headers(token: str) -> dict:
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


def bootstrap_pakan_sellable(
    base_url: str,
    login,
    *,
    timeout: int = 30,
    min_stock: int = 5,
) -> int:
    """Return product_id on PAKAN with stock >= 1 (creates via transfer if needed)."""
    master_token = login("master@majubersama.test")
    master_headers = _headers(master_token)

    kasir_headers = _headers(login("kasir@pakan.mb"))
    listed = requests.get(
        f"{base_url}/api/products",
        headers=kasir_headers,
        timeout=timeout,
    )
    assert listed.status_code == 200, listed.text
    rows = listed.json().get("data", listed.json())
    if isinstance(rows, list):
        for row in rows:
            stock = row.get("stock")
            if row.get("id") and isinstance(stock, (int, float)) and stock >= 1:
                return int(row["id"])

    branches = requests.get(
        f"{base_url}/api/branches",
        headers=master_headers,
        timeout=timeout,
    )
    assert branches.status_code == 200, branches.text
    pakan_id = _branch_id(branches.json(), "PAKAN")
    pusat_id = _branch_id(branches.json(), "PUSAT")
    if pusat_id is None:
        data = branches.json().get("data", [])
        pusat_id = data[0]["id"] if data else None
    assert pakan_id and pusat_id, "PAKAN/PUSAT branch ids required for bootstrap"

    master_products = requests.get(
        f"{base_url}/api/products",
        headers=master_headers,
        timeout=timeout,
    )
    assert master_products.status_code == 200, master_products.text
    source_product_id = _first_product_id(master_products.json())
    assert source_product_id is not None, "Master catalog has no products to transfer"

    adjustment = requests.post(
        f"{base_url}/api/stock-adjustments",
        headers=master_headers,
        json={
            "branch_id": pusat_id,
            "items": [{"product_id": source_product_id, "actual_qty": min_stock}],
        },
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

    listed = requests.get(
        f"{base_url}/api/products",
        headers=kasir_headers,
        timeout=timeout,
    )
    assert listed.status_code == 200, listed.text
    rows = listed.json().get("data", listed.json())
    assert isinstance(rows, list) and rows, (
        f"No PAKAN products after transfer: {json.dumps(listed.json())[:500]}"
    )
    destination = next(
        (
            row
            for row in rows
            if isinstance(row.get("stock"), (int, float)) and row["stock"] >= 1
        ),
        rows[0],
    )
    product_id = destination.get("id")
    assert product_id is not None
    return int(product_id)

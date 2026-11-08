import uuid

import requests


def test_master_data_access_control_for_branches_categories_and_products():
    base_url = "http://localhost:8000"
    timeout = 30
    created_resources = []

    def login(email):
        response = requests.post(
            f"{base_url}/api/login",
            json={"email": email, "password": "password"},
            headers={"Accept": "application/json"},
            timeout=timeout,
        )
        assert response.status_code == 200, (
            f"Login failed for {email}: {response.status_code} {response.text}"
        )
        data = response.json()
        token = data.get("token") or data.get("access_token")
        if not token and isinstance(data.get("data"), dict):
            token = data["data"].get("token") or data["data"].get("access_token")
        assert token, f"Login response for {email} did not contain a token"
        return token

    def headers(token):
        return {
            "Accept": "application/json",
            "Content-Type": "application/json",
            "Authorization": f"Bearer {token}",
        }

    def response_data(response):
        try:
            data = response.json()
        except ValueError:
            return {}
        return data.get("data", data) if isinstance(data, dict) else data

    def resource_id(response):
        data = response_data(response)
        if isinstance(data, dict):
            return data.get("id") or (
                data.get("data", {}).get("id")
                if isinstance(data.get("data"), dict)
                else None
            )
        return None

    master_token = login("master@majubersama.test")
    branch_admin_token = login("asep@mb.pkn")
    cashier_token = login("kasir@pakan.mb")

    master_headers = headers(master_token)
    branch_admin_headers = headers(branch_admin_token)
    cashier_headers = headers(cashier_token)

    try:
        master_branches = requests.get(
            f"{base_url}/api/branches",
            headers=master_headers,
            timeout=timeout,
        )
        assert master_branches.status_code == 200, (
            f"Master branch listing failed: {master_branches.status_code} "
            f"{master_branches.text}"
        )

        admin_branches = requests.get(
            f"{base_url}/api/branches",
            headers=branch_admin_headers,
            timeout=timeout,
        )
        assert admin_branches.status_code == 403, (
            f"Branch admin GET /api/branches should return 403, got "
            f"{admin_branches.status_code}: {admin_branches.text}"
        )

        cashier_branches = requests.get(
            f"{base_url}/api/branches",
            headers=cashier_headers,
            timeout=timeout,
        )
        assert cashier_branches.status_code == 403, (
            f"Cashier GET /api/branches should return 403, got "
            f"{cashier_branches.status_code}: {cashier_branches.text}"
        )

        branches_data = response_data(master_branches)
        existing_branch = None
        if isinstance(branches_data, list) and branches_data:
            existing_branch = branches_data[0]
        elif isinstance(branches_data, dict):
            branch_items = branches_data.get("data")
            if isinstance(branch_items, list) and branch_items:
                existing_branch = branch_items[0]

        unique = uuid.uuid4().hex[:10]
        branch_payload = {
            "name": f"TC004 Test Branch {unique}",
            "code": f"TC4-{unique}",
            "address": "TC004 test address",
        }
        if isinstance(existing_branch, dict) and existing_branch.get("company_id"):
            branch_payload["company_id"] = existing_branch["company_id"]

        master_create_branch = requests.post(
            f"{base_url}/api/branches",
            json=branch_payload,
            headers=master_headers,
            timeout=timeout,
        )
        assert master_create_branch.status_code in (200, 201), (
            f"Master POST /api/branches failed: {master_create_branch.status_code} "
            f"{master_create_branch.text}"
        )
        branch_id = resource_id(master_create_branch)
        if branch_id:
            created_resources.append(("branches", branch_id))

        admin_create_branch = requests.post(
            f"{base_url}/api/branches",
            json={
                "name": f"TC004 Unauthorized Branch {unique}",
                "code": f"TC4-U-{unique}",
                "address": "TC004 test address",
            },
            headers=branch_admin_headers,
            timeout=timeout,
        )
        assert admin_create_branch.status_code == 403, (
            f"Branch admin POST /api/branches should return 403, got "
            f"{admin_create_branch.status_code}: {admin_create_branch.text}"
        )

        cashier_create_branch = requests.post(
            f"{base_url}/api/branches",
            json={
                "name": f"TC004 Cashier Branch {unique}",
                "code": f"TC4-C-{unique}",
                "address": "TC004 test address",
            },
            headers=cashier_headers,
            timeout=timeout,
        )
        assert cashier_create_branch.status_code == 403, (
            f"Cashier POST /api/branches should return 403, got "
            f"{cashier_create_branch.status_code}: {cashier_create_branch.text}"
        )

        category_payload = {
            "name": f"TC004 Test Category {unique}",
            "description": "Category created for access-control testing",
        }
        master_categories = requests.get(
            f"{base_url}/api/categories",
            headers=master_headers,
            timeout=timeout,
        )
        assert master_categories.status_code == 200, (
            f"Master GET /api/categories failed: {master_categories.status_code} "
            f"{master_categories.text}"
        )

        master_create_category = requests.post(
            f"{base_url}/api/categories",
            json=category_payload,
            headers=master_headers,
            timeout=timeout,
        )
        assert master_create_category.status_code in (200, 201), (
            f"Master POST /api/categories failed: "
            f"{master_create_category.status_code} {master_create_category.text}"
        )
        category_id = resource_id(master_create_category)
        if category_id:
            created_resources.append(("categories", category_id))

        for role, role_headers in (
            ("branch admin", branch_admin_headers),
            ("cashier", cashier_headers),
        ):
            category_response = requests.get(
                f"{base_url}/api/categories",
                headers=role_headers,
                timeout=timeout,
            )
            assert category_response.status_code == 200, (
                f"Authenticated {role} GET /api/categories should return 200, got "
                f"{category_response.status_code}: {category_response.text}"
            )

            create_category_response = requests.post(
                f"{base_url}/api/categories",
                json={
                    "name": f"TC004 Unauthorized Category {role} {unique}",
                    "description": "Unauthorized access-control test",
                },
                headers=role_headers,
                timeout=timeout,
            )
            assert create_category_response.status_code == 403, (
                f"{role} POST /api/categories should return 403, got "
                f"{create_category_response.status_code}: "
                f"{create_category_response.text}"
            )

        for role, role_headers in (
            ("master", master_headers),
            ("branch admin", branch_admin_headers),
            ("cashier", cashier_headers),
        ):
            products_response = requests.get(
                f"{base_url}/api/products",
                headers=role_headers,
                timeout=timeout,
            )
            assert products_response.status_code == 200, (
                f"Authenticated {role} GET /api/products failed: "
                f"{products_response.status_code} {products_response.text}"
            )

        product_payload = {
            "name": "TC004 Test Product",
            "sku": f"TC4-{unique}",
            "barcode": "TC004000001",
            "category_id": category_id,
            "price": 1000,
            "cost_price": 500,
            "stock": 0,
        }

        branch_admin_user = requests.get(
            f"{base_url}/api/user",
            headers=branch_admin_headers,
            timeout=timeout,
        )
        if branch_admin_user.status_code == 200:
            user_data = response_data(branch_admin_user)
            if isinstance(user_data, dict) and user_data.get("branch_id"):
                product_payload["branch_id"] = user_data["branch_id"]
            elif isinstance(existing_branch, dict) and existing_branch.get("id"):
                product_payload["branch_id"] = existing_branch["id"]
        elif isinstance(existing_branch, dict) and existing_branch.get("id"):
            product_payload["branch_id"] = existing_branch["id"]

        admin_create_product = requests.post(
            f"{base_url}/api/products",
            json=product_payload,
            headers=branch_admin_headers,
            timeout=timeout,
        )
        assert admin_create_product.status_code in (200, 201, 403), (
            f"Branch admin product creation should either be allowed or forbidden "
            f"by policy, got {admin_create_product.status_code}: "
            f"{admin_create_product.text}"
        )
        if admin_create_product.status_code in (200, 201):
            product_id = resource_id(admin_create_product)
            if product_id:
                created_resources.append(("products", product_id))

        cashier_create_product = requests.post(
            f"{base_url}/api/products",
            json={
                **product_payload,
                "name": "TC004 Cashier Unauthorized Product",
                "sku": f"TC4-CASH-{unique}",
                "barcode": "TC004000002",
            },
            headers=cashier_headers,
            timeout=timeout,
        )
        assert cashier_create_product.status_code == 403, (
            f"Cashier POST /api/products should return 403, got "
            f"{cashier_create_product.status_code}: {cashier_create_product.text}"
        )
    finally:
        for resource, identifier in reversed(created_resources):
            try:
                requests.delete(
                    f"{base_url}/api/{resource}/{identifier}",
                    headers=master_headers,
                    timeout=timeout,
                )
            except requests.RequestException:
                pass


if __name__ == "__main__":
    test_master_data_access_control_for_branches_categories_and_products()
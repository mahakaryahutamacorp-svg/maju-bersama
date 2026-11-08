import requests
from urllib.parse import quote


def test_tc005_branch_isolation_in_product_listing_and_barcode_lookup():
    base_url = "http://localhost:8000"
    timeout = 30
    accounts = [
        ("pakan", "asep@mb.pkn", "password"),
        ("pakan", "gudang@pakan.178", "password"),
        ("pupuk", "gudang@pupuk.178", "password"),
        ("pupuk", "admin@mb.abi", "password"),
    ]
    session = requests.Session()
    headers = {"Accept": "application/json"}

    def response_json(response):
        try:
            return response.json()
        except ValueError as exc:
            raise AssertionError(
                f"Expected JSON response from {response.url}; got {response.text[:500]!r}"
            ) from exc

    def unwrap_object(value):
        if isinstance(value, dict) and isinstance(value.get("data"), dict):
            return value["data"]
        return value if isinstance(value, dict) else {}

    def extract_products(payload):
        if isinstance(payload, list):
            return payload
        if not isinstance(payload, dict):
            return []
        data = payload.get("data")
        if isinstance(data, list):
            return data
        for key in ("products", "items", "results"):
            value = payload.get(key)
            if isinstance(value, list):
                return value
        if isinstance(data, dict):
            for key in ("products", "items", "results"):
                value = data.get(key)
                if isinstance(value, list):
                    return value
        return []

    def branch_id_from(profile):
        profile = unwrap_object(profile)
        branch_id = profile.get("branch_id")
        if branch_id is not None:
            return branch_id
        branch = profile.get("branch")
        if isinstance(branch, dict):
            return branch.get("id", branch.get("branch_id"))
        user = profile.get("user")
        if isinstance(user, dict):
            if user.get("branch_id") is not None:
                return user["branch_id"]
            branch = user.get("branch")
            if isinstance(branch, dict):
                return branch.get("id", branch.get("branch_id"))
        return None

    def product_branch_id(product):
        if not isinstance(product, dict):
            return None
        if product.get("branch_id") is not None:
            return product["branch_id"]
        branch = product.get("branch")
        if isinstance(branch, dict):
            return branch.get("id", branch.get("branch_id"))
        return None

    def product_barcode(product):
        if not isinstance(product, dict):
            return None
        for key in ("barcode", "bar_code", "barcode_number"):
            if product.get(key) not in (None, ""):
                return str(product[key])
        return None

    def referenced_branch_ids(value):
        found = set()

        def walk(item, parent_key=None):
            if isinstance(item, dict):
                for key, child in item.items():
                    normalized_key = str(key).lower()
                    if normalized_key in {
                        "branch_id",
                        "branchid",
                        "store_id",
                        "storeid",
                    } and child is not None:
                        found.add(str(child))
                    elif normalized_key in {"branch", "store"} and isinstance(child, dict):
                        branch_identifier = child.get("id", child.get("branch_id"))
                        if branch_identifier is not None:
                            found.add(str(branch_identifier))
                    elif parent_key == "other_branch_stock" and str(key).isdigit():
                        found.add(str(key))
                    walk(child, normalized_key)
            elif isinstance(item, list):
                for child in item:
                    walk(child, parent_key)

        walk(value)
        return found

    logged_in = []
    division_branch_ids = {"pakan": set(), "pupuk": set()}
    products_by_division = {"pakan": [], "pupuk": []}

    for division, email, password in accounts:
        login_response = session.post(
            f"{base_url}/api/login",
            json={"email": email, "password": password},
            headers=headers,
            timeout=timeout,
        )
        assert login_response.status_code == 200, (
            f"Login failed for {email}: {login_response.status_code} "
            f"{login_response.text[:500]}"
        )
        login_payload = response_json(login_response)
        token = login_payload.get("token") or login_payload.get("access_token")
        if token is None and isinstance(login_payload.get("data"), dict):
            token = login_payload["data"].get("token") or login_payload["data"].get(
                "access_token"
            )
        assert token, f"Login response for {email} did not contain a Sanctum token"

        auth_headers = {**headers, "Authorization": f"Bearer {token}"}
        user_response = session.get(
            f"{base_url}/api/user",
            headers=auth_headers,
            timeout=timeout,
        )
        assert user_response.status_code == 200, (
            f"Could not retrieve authenticated profile for {email}: "
            f"{user_response.status_code} {user_response.text[:500]}"
        )
        profile = response_json(user_response)
        caller_branch_id = branch_id_from(profile)
        if caller_branch_id is not None:
            division_branch_ids[division].add(str(caller_branch_id))

        products_response = session.get(
            f"{base_url}/api/products",
            headers=auth_headers,
            timeout=timeout,
        )
        assert products_response.status_code == 200, (
            f"Product listing failed for {email}: {products_response.status_code} "
            f"{products_response.text[:500]}"
        )
        products = extract_products(response_json(products_response))
        for product in products:
            listed_branch_id = product_branch_id(product)
            if caller_branch_id is not None and listed_branch_id is not None:
                assert str(listed_branch_id) == str(caller_branch_id), (
                    f"{email} received a product from branch {listed_branch_id}; "
                    f"expected branch {caller_branch_id}"
                )
            products_by_division[division].append(
                {
                    "product": product,
                    "branch_id": caller_branch_id,
                    "headers": auth_headers,
                    "email": email,
                }
            )

        logged_in.append(
            {
                "division": division,
                "email": email,
                "headers": auth_headers,
                "branch_id": caller_branch_id,
            }
        )

    for entry in products_by_division["pakan"]:
        other_branch_stock = entry["product"].get("other_branch_stock")
        if other_branch_stock is not None:
            foreign_ids = division_branch_ids["pupuk"]
            exposed_ids = referenced_branch_ids(
                {"other_branch_stock": other_branch_stock}
            )
            assert not (exposed_ids & foreign_ids), (
                f"{entry['email']} product exposes Pupuk branch IDs in "
                f"other_branch_stock: {exposed_ids & foreign_ids}"
            )

    for entry in products_by_division["pupuk"]:
        other_branch_stock = entry["product"].get("other_branch_stock")
        if other_branch_stock is not None:
            foreign_ids = division_branch_ids["pakan"]
            exposed_ids = referenced_branch_ids(
                {"other_branch_stock": other_branch_stock}
            )
            assert not (exposed_ids & foreign_ids), (
                f"{entry['email']} product exposes Pakan branch IDs in "
                f"other_branch_stock: {exposed_ids & foreign_ids}"
            )

    for caller in logged_in:
        foreign_division = "pupuk" if caller["division"] == "pakan" else "pakan"
        foreign_barcodes = {
            barcode
            for entry in products_by_division[foreign_division]
            if (barcode := product_barcode(entry["product"])) is not None
        }
        for barcode in foreign_barcodes:
            lookup_response = session.get(
                f"{base_url}/api/products/barcode/{quote(barcode, safe='')}",
                headers=caller["headers"],
                timeout=timeout,
            )
            assert lookup_response.status_code == 404, (
                f"{caller['email']} looked up barcode {barcode!r} from the "
                f"{foreign_division} division and received "
                f"{lookup_response.status_code}: {lookup_response.text[:500]}"
            )

        for entry in products_by_division[caller["division"]]:
            barcode = product_barcode(entry["product"])
            if barcode is None:
                continue
            lookup_response = session.get(
                f"{base_url}/api/products/barcode/{quote(barcode, safe='')}",
                headers=caller["headers"],
                timeout=timeout,
            )
            assert lookup_response.status_code == 200, (
                f"{caller['email']} could not look up its listed barcode "
                f"{barcode!r}: {lookup_response.status_code} "
                f"{lookup_response.text[:500]}"
            )
            lookup_payload = unwrap_object(response_json(lookup_response))
            lookup_product = lookup_payload.get("product", lookup_payload)
            if caller["branch_id"] is not None:
                lookup_branch_id = product_branch_id(lookup_product)
                if lookup_branch_id is not None:
                    assert str(lookup_branch_id) == str(caller["branch_id"]), (
                        f"{caller['email']} barcode lookup returned branch "
                        f"{lookup_branch_id}; expected {caller['branch_id']}"
                    )
            other_branch_stock = lookup_product.get("other_branch_stock")
            if other_branch_stock is not None:
                foreign_ids = division_branch_ids[foreign_division]
                exposed_ids = referenced_branch_ids(
                    {"other_branch_stock": other_branch_stock}
                )
                assert not (exposed_ids & foreign_ids), (
                    f"{caller['email']} barcode lookup exposed foreign branch "
                    f"IDs in other_branch_stock: {exposed_ids & foreign_ids}"
                )

    unauthenticated_products = session.get(
        f"{base_url}/api/products",
        headers=headers,
        timeout=timeout,
    )
    assert unauthenticated_products.status_code == 401, (
        f"Unauthenticated product listing returned "
        f"{unauthenticated_products.status_code}, expected 401"
    )

    unauthenticated_barcode = session.get(
        f"{base_url}/api/products/barcode/0000000000000",
        headers=headers,
        timeout=timeout,
    )
    assert unauthenticated_barcode.status_code == 401, (
        f"Unauthenticated barcode lookup returned "
        f"{unauthenticated_barcode.status_code}, expected 401"
    )


if __name__ == "__main__":
    test_tc005_branch_isolation_in_product_listing_and_barcode_lookup()
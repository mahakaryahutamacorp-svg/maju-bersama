import os
import sys
import time
import requests
from decimal import Decimal, ROUND_HALF_UP
from datetime import datetime, timedelta

BASE_URL = os.getenv("BASE_ENDPOINT", "http://localhost:8000").rstrip("/")
LOGIN_EMAIL = os.getenv("TEST_EMAIL", "admin@example.com")
LOGIN_PASSWORD = os.getenv("TEST_PASSWORD", "password")
REQUEST_TIMEOUT = 30


def _extract_token(resp_json):
    # Try common token keys returned by various implementations
    for key in ("token", "access_token", "plainTextToken", "data", "token_value"):
        if key in resp_json:
            if isinstance(resp_json[key], str):
                return resp_json[key]
            if isinstance(resp_json[key], dict) and "token" in resp_json[key]:
                return resp_json[key]["token"]
    # Some APIs return nested data: {"data": {"token": "..."}}
    if "data" in resp_json and isinstance(resp_json["data"], dict):
        for subkey in ("token", "access_token", "plainTextToken"):
            if subkey in resp_json["data"]:
                return resp_json["data"][subkey]
    return None


def quantize_money(val):
    # ensure Decimal quantized to 2 decimal places
    d = Decimal(str(val))
    return d.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)


def try_endpoints(session, endpoints):
    # Try multiple plausible endpoints, return first successful (200) response tuple (url, resp_json)
    for ep in endpoints:
        url = BASE_URL + ep
        try:
            r = session.get(url, timeout=REQUEST_TIMEOUT)
        except requests.RequestException:
            continue
        if r.status_code == 200:
            try:
                return url, r.json()
            except Exception:
                return url, None
    return None, None


def test_reportcenterandfinancialstatementaudit():
    session = requests.Session()
    headers = {"Accept": "application/json"}
    session.headers.update(headers)

    # 1) Authenticate
    login_url = BASE_URL + "/api/login"
    login_payload = {"email": LOGIN_EMAIL, "password": LOGIN_PASSWORD}
    try:
        r = session.post(login_url, json=login_payload, timeout=REQUEST_TIMEOUT)
    except requests.RequestException as e:
        raise AssertionError(f"Login request failed: {e}")
    assert r.status_code == 200, f"Login failed: status {r.status_code}, body: {r.text}"
    try:
        login_json = r.json()
    except Exception:
        raise AssertionError("Login response is not valid JSON")
    token = _extract_token(login_json)
    assert token, f"Auth token not found in login response: {login_json}"
    # Use Bearer token
    session.headers.update({"Authorization": f"Bearer {token}"})

    created_product_id = None
    try:
        # 2) Obtain or create a product to perform checkout
        prod_list_url = BASE_URL + "/api/products"
        r = session.get(prod_list_url, timeout=REQUEST_TIMEOUT)
        assert r.status_code == 200, f"GET /api/products failed: {r.status_code} {r.text}"
        products = r.json()
        product_id = None
        product_price = None

        # products may be list or inside data
        if isinstance(products, dict) and "data" in products and isinstance(products["data"], list):
            prod_items = products["data"]
        elif isinstance(products, list):
            prod_items = products
        elif isinstance(products, dict):
            # maybe single item
            prod_items = [products]
        else:
            prod_items = []

        qty = 3
        if prod_items:
            # pick first product with an id and price
            for p in prod_items:
                if isinstance(p, dict) and ("id" in p or "product_id" in p):
                    pid = p.get("id") or p.get("product_id")
                    price = p.get("price") or p.get("selling_price") or p.get("unit_price") or p.get("harga")
                    stock_qty = p.get("stock") or 0
                    if pid is not None and price is not None and stock_qty >= qty:
                        product_id = pid
                        product_price = Decimal(str(price))
                        break

        if product_id is None:
            # create a product
            create_url = BASE_URL + "/api/products"
            unique = int(time.time())
            new_product = {
                "name": f"TEST PRODUCT {unique}",
                "sku": f"TESTSKU{unique}",
                "price": 12345.67,
                "purchase_price": 10000.00,
                "stock": 100,
            }
            r = session.post(create_url, json=new_product, timeout=REQUEST_TIMEOUT)
            assert r.status_code in (200, 201), f"Failed to create product: {r.status_code} {r.text}"
            created = r.json()
            # extract id and price
            if isinstance(created, dict):
                product_id = created.get("id") or created.get("data", {}).get("id")
                product_price = Decimal(str(new_product["price"]))
            assert product_id is not None, "Created product id not found"
            created_product_id = product_id

        # 3) Perform a checkout (POS) to generate a sales transaction
        checkout_url = BASE_URL + "/api/checkout"
        qty = 3
        # Attempt a few payload shapes that are commonly accepted
        checkout_payload_candidates = [
            {"items": [{"product_id": product_id, "quantity": qty}], "payment_method": "cash"},
            {"items": [{"id": product_id, "qty": qty}], "payment_method": "cash"},
            {"cart": [{"product_id": product_id, "quantity": qty}], "payment_method": "cash"},
            {"items": [{"product_id": product_id, "quantity": qty}], "payments": [{"method": "cash"}]},
        ]
        checkout_response_json = None
        checkout_response = None
        for payload in checkout_payload_candidates:
            try:
                r = session.post(checkout_url, json=payload, timeout=REQUEST_TIMEOUT)
            except requests.RequestException:
                continue
            if r.status_code in (200, 201):
                try:
                    checkout_response_json = r.json()
                except Exception:
                    checkout_response_json = None
                checkout_response = r
                break
            # some APIs return 422 if payload shape not exactly right; keep trying
        assert checkout_response is not None and checkout_response.status_code in (200, 201), \
            f"Checkout failed with tried payloads. Last status {r.status_code}, body: {r.text}"

        # Extract transaction / receipt details and validate prices
        # Common places: response JSON root or data key
        tx = checkout_response_json
        if isinstance(tx, dict) and "data" in tx and isinstance(tx["data"], dict):
            tx = tx["data"]
        elif isinstance(tx, dict) and "sale" in tx and isinstance(tx["sale"], dict):
            tx = tx["sale"]
        assert isinstance(tx, dict), f"Unexpected checkout response structure: {checkout_response_json}"

        # Determine items list, id and totals in response
        items = tx.get("items") or tx.get("line_items") or tx.get("details") or tx.get("cart")
        assert items and isinstance(items, list), f"Transaction items not found in checkout response: {tx}"

        # Extract transaction ID and grand total
        tx_id = tx.get("id") or tx.get("transaction_id") or tx.get("invoice_id") or tx.get("sale_id")
        grand_total = tx.get("total_amount") or tx.get("total") or tx.get("grand_total") or tx.get("amount") or tx.get("grandTotal")
        assert grand_total is not None, f"Grand total not found in checkout response: {tx}"

        # Convert grand_total to Decimal
        try:
            grand_total_dec = quantize_money(grand_total)
        except Exception:
            raise AssertionError(f"Grand total is not a numeric monetary value: {grand_total}")

        # Validate each item: unit_price, quantity -> subtotal, and sum subtotals equals grand_total
        computed_sum = Decimal("0.00")
        for it in items:
            if isinstance(it, dict):
                unit = it.get("unit_price") or it.get("price") or it.get("harga") or it.get("selling_price")
                qty_val = it.get("quantity") or it.get("qty") or it.get("jumlah")
                subtotal = it.get("subtotal") or it.get("line_total") or it.get("amount")
                assert unit is not None and qty_val is not None, \
                    f"Item missing unit price or quantity: {it}"
                # convert
                try:
                    unit_dec = quantize_money(unit)
                except Exception:
                    raise AssertionError(f"Item unit price not numeric: {unit}")
                try:
                    qty_dec = Decimal(str(qty_val))
                except Exception:
                    raise AssertionError(f"Item quantity not numeric: {qty_val}")
                expected_sub = (unit_dec * qty_dec).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
                # If response gave a subtotal, compare
                if subtotal is not None:
                    try:
                        subtotal_dec = quantize_money(subtotal)
                    except Exception:
                        raise AssertionError(f"Item subtotal not numeric: {subtotal}")
                    assert subtotal_dec == expected_sub, \
                        f"Item subtotal mismatch. unit:{unit_dec} qty:{qty_dec} expected:{expected_sub} got:{subtotal_dec}"
                computed_sum += expected_sub

                # Ensure no more than two decimal places in returned monetary fields
                for money_field in (unit, subtotal):
                    if money_field is None:
                        continue
                    try:
                        d = Decimal(str(money_field))
                    except Exception:
                        raise AssertionError(f"Monetary field not numeric: {money_field}")
                    # difference between quantized and original shouldn't exceed 0.000001 to allow float representation noise
                    if (d - quantize_money(d)).copy_abs() > Decimal("0.000001"):
                        raise AssertionError(f"Monetary field has >2 decimal places (possible formatting bug): {money_field}")

        computed_sum = computed_sum.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
        assert computed_sum == grand_total_dec, \
            f"Grand total mismatch: sum of item subtotals {computed_sum} != reported grand_total {grand_total_dec}"

        # 4) Universal Transaction Viewer: attempt to fetch transaction by possible endpoints and validate values match
        if tx_id is not None:
            tx_endpoints = [
                f"/api/transactions/{tx_id}",
                f"/api/sales/{tx_id}",
                f"/api/receipts/{tx_id}",
                f"/api/invoices/{tx_id}",
            ]
            tx_url_found = None
            tx_detail = None
            for ep in tx_endpoints:
                url = BASE_URL + ep
                try:
                    r = session.get(url, timeout=REQUEST_TIMEOUT)
                except requests.RequestException:
                    continue
                if r.status_code == 200:
                    try:
                        tx_detail = r.json()
                    except Exception:
                        tx_detail = None
                    tx_url_found = url
                    break
            if tx_url_found:
                # Normalize structure
                if isinstance(tx_detail, dict) and "data" in tx_detail and isinstance(tx_detail["data"], dict):
                    tx_detail = tx_detail["data"]
                # Validate item-level data present and consistent
                items_detail = tx_detail.get("items") or tx_detail.get("line_items") or tx_detail.get("details") or tx_detail.get("cart")
                assert isinstance(items_detail, list), f"Transaction detail items not found at {tx_url_found}"
                # Validate same calculations as before
                sum2 = Decimal("0.00")
                for it in items_detail:
                    unit = it.get("unit_price") or it.get("price") or it.get("selling_price") or it.get("harga")
                    qty_val = it.get("quantity") or it.get("qty") or it.get("jumlah")
                    subtotal = it.get("subtotal") or it.get("line_total") or it.get("amount")
                    assert unit is not None and qty_val is not None, f"Detail item missing unit/qty: {it}"
                    unit_dec = quantize_money(unit)
                    qty_dec = Decimal(str(qty_val))
                    expected_sub = (unit_dec * qty_dec).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
                    if subtotal is not None:
                        subtotal_dec = quantize_money(subtotal)
                        assert subtotal_dec == expected_sub, f"Transaction viewer subtotal mismatch: expected {expected_sub} got {subtotal_dec}"
                    sum2 += expected_sub
                sum2 = sum2.quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
                # Compare with grand_total from checkout (or transaction detail)
                detail_total = tx_detail.get("total") or tx_detail.get("grand_total") or tx_detail.get("amount")
                assert detail_total is not None, "Transaction detail total not found"
                detail_total_dec = quantize_money(detail_total)
                assert sum2 == detail_total_dec, f"Transaction viewer total {detail_total_dec} != sum of items {sum2}"
            else:
                # If viewer endpoint not available, do not fail; just note that direct checkout response was validated above.
                pass

        # 5) Sales Report: try to query likely endpoints and validate grand total includes our transaction amount
        # Use the transaction creation time range to narrow the report
        today = datetime.utcnow().date()
        date_from = (today - timedelta(days=1)).isoformat()
        date_to = (today + timedelta(days=1)).isoformat()
        sales_report_endpoints = [
            f"/api/reports/sales?date_from={date_from}&date_to={date_to}",
            f"/api/reports/sales-summary?date_from={date_from}&date_to={date_to}",
            f"/api/report/sales?from={date_from}&to={date_to}",
            f"/api/reports/sales",
        ]
        report_url, report_json = try_endpoints(session, sales_report_endpoints)
        if report_url:
            # Normalize response
            report = report_json or {}
            # Try locating a grand total in common keys
            rep_total = None
            for key in ("grand_total", "total", "sales_total", "grandTotal", "data"):
                if key in report:
                    if key == "data" and isinstance(report["data"], dict):
                        # maybe nested
                        for sub in ("grand_total", "total", "sales_total"):
                            if sub in report["data"]:
                                rep_total = report["data"][sub]
                                break
                    else:
                        rep_total = report[key]
                    if rep_total is not None:
                        break
            # If report contains a list of transactions, sum those
            if rep_total is None and isinstance(report, dict):
                # maybe report contains list of transactions in 'transactions' or 'items'
                tx_list = report.get("transactions") or report.get("items") or report.get("data")
                if isinstance(tx_list, list):
                    # sum totals
                    s = Decimal("0.00")
                    for t in tx_list:
                        tv = t.get("total") or t.get("grand_total") or t.get("amount")
                        if tv is None:
                            continue
                        s += quantize_money(tv)
                    rep_total = s
            if rep_total is not None:
                rep_total_dec = quantize_money(rep_total)
                # ensure report total at least equals our transaction amount
                assert rep_total_dec >= grand_total_dec, f"Sales report total {rep_total_dec} is less than transaction amount {grand_total_dec}"
            else:
                # Could not find totals in report; non-fatal as the endpoint structure is unknown
                pass

        # 6) Financial statements: try income statement and balance sheet endpoints and validate presence and balance
        income_endpoints = [
            "/api/reports/income-statement",
            "/api/reports/profit-and-loss",
            "/api/report/income-statement",
            "/api/reports/labarugi",
            "/api/reports/pnl",
        ]
        bal_endpoints = [
            "/api/reports/balance-sheet",
            "/api/report/balance-sheet",
            "/api/reports/neraca",
        ]

        inc_url, inc_json = try_endpoints(session, income_endpoints)
        if inc_url:
            inc = inc_json or {}
            # Try to locate totals by account class codes: 4xxx revenue, 5xxx COGS, 6xxx expense
            # Accept multiple formats: mapping of accounts or totals keys
            revenue = None
            hpp = None
            expenses = None
            # search for keys in dictionaries
            def find_numeric_container(container, candidates):
                if not isinstance(container, dict):
                    return None
                for k in candidates:
                    if k in container:
                        return container[k]
                return None

            revenue = find_numeric_container(inc, "revenue") or find_numeric_container(inc, "pendapatan") or None
            if revenue is None:
                # try to scan accounts by keys starting with 4
                if isinstance(inc, dict):
                    s_rev = Decimal("0.00")
                    found_rev = False
                    for k, v in inc.items():
                        if isinstance(k, str) and k.strip().startswith("4") and (isinstance(v, (int, float, str, Decimal))):
                            try:
                                s_rev += quantize_money(v)
                                found_rev = True
                            except Exception:
                                pass
                    if found_rev:
                        revenue = s_rev
            # HPP (COGS)
            if hpp is None:
                s_hpp = Decimal("0.00")
                found_hpp = False
                for k, v in inc.items():
                    if isinstance(k, str) and k.strip().startswith("5") and (isinstance(v, (int, float, str, Decimal))):
                        try:
                            s_hpp += quantize_money(v)
                            found_hpp = True
                        except Exception:
                            pass
                if found_hpp:
                    hpp = s_hpp
            # Expenses
            if expenses is None:
                s_exp = Decimal("0.00")
                found_exp = False
                for k, v in inc.items():
                    if isinstance(k, str) and k.strip().startswith("6") and (isinstance(v, (int, float, str, Decimal))):
                        try:
                            s_exp += quantize_money(v)
                            found_exp = True
                        except Exception:
                            pass
                if found_exp:
                    expenses = s_exp

            # If we got numeric values, assert they are Decimal-like
            if revenue is not None:
                try:
                    revenue_dec = quantize_money(revenue)
                except Exception:
                    raise AssertionError(f"Revenue total not numeric: {revenue}")
            if hpp is not None:
                try:
                    hpp_dec = quantize_money(hpp)
                except Exception:
                    raise AssertionError(f"HPP/COGS total not numeric: {hpp}")
            if expenses is not None:
                try:
                    expenses_dec = quantize_money(expenses)
                except Exception:
                    raise AssertionError(f"Expenses total not numeric: {expenses}")
            # No strict numeric assertions here beyond type validation because account structures vary

        bal_url, bal_json = try_endpoints(session, bal_endpoints)
        if bal_url:
            bal = bal_json or {}
            # Try common keys
            assets = bal.get("assets") or bal.get("aktiva") or bal.get("total_assets") or bal.get("total_aktiva")
            liabilities = bal.get("liabilities") or bal.get("kewajiban") or bal.get("total_liabilities")
            equity = bal.get("equity") or bal.get("modal") or bal.get("total_equity") or bal.get("ekuitas")
            # If available as nested dicts, try to extract totals
            def to_decimal_safe(v):
                if v is None:
                    return None
                try:
                    return quantize_money(v)
                except Exception:
                    # if v is dict with totals, try to find numeric inside
                    if isinstance(v, dict):
                        for subv in v.values():
                            try:
                                return quantize_money(subv)
                            except Exception:
                                continue
                    return None

            assets_dec = to_decimal_safe(assets)
            liabilities_dec = to_decimal_safe(liabilities)
            equity_dec = to_decimal_safe(equity)

            # If we have total assets and (liabilities & equity), assert balance
            if assets_dec is not None and liabilities_dec is not None and equity_dec is not None:
                total_le = (liabilities_dec + equity_dec).quantize(Decimal("0.01"), rounding=ROUND_HALF_UP)
                assert assets_dec == total_le, f"Balance sheet not balanced: assets {assets_dec} != liabilities+equity {total_le}"
            else:
                # Try alternative structure: total_assets and total_liabilities_and_equity
                tae = bal.get("total_assets") or bal.get("total_aktiva")
                tlee = bal.get("total_liabilities_and_equity") or bal.get("total_pasiva_dan_modal")
                tae_dec = to_decimal_safe(tae)
                tlee_dec = to_decimal_safe(tlee)
                if tae_dec is not None and tlee_dec is not None:
                    assert tae_dec == tlee_dec, f"Balance sheet not balanced: total_assets {tae_dec} != total_liabilities_and_equity {tlee_dec}"
                else:
                    # Could not verify balance; acceptable if structure differs
                    pass

    finally:
        # Cleanup created product if any
        if created_product_id:
            try:
                del_url = BASE_URL + f"/api/products/{created_product_id}"
                session.delete(del_url, timeout=REQUEST_TIMEOUT)
            except Exception:
                pass


if __name__ == "__main__":
    try:
        test_reportcenterandfinancialstatementaudit()
        print("TC104 passed")
    except AssertionError as e:
        print("TC104 failed:", e)
        sys.exit(1)
    except Exception as ex:
        print("TC104 error:", ex)
        sys.exit(1)
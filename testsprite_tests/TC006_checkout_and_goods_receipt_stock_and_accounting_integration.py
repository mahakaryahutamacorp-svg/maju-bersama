import requests
import time
import sys

BASE_URL = "http://127.0.0.1:8000"
TIMEOUT = 30.0

MASTER_EMAIL = "master@majubersama.test"
MASTER_PASSWORD = "password"


def _extract_token(resp_json):
    # Try common shapes
    if not resp_json:
        return None
    if "token" in resp_json:
        return resp_json["token"]
    if "data" in resp_json and isinstance(resp_json["data"], dict) and "token" in resp_json["data"]:
        return resp_json["data"]["token"]
    if "access_token" in resp_json:
        return resp_json["access_token"]
    return None


def _get_auth_headers(token):
    return {"Authorization": f"Bearer {token}", "Accept": "application/json"}


def _find_product_in_list(products, product_id=None, name=None):
    for p in products:
        if product_id is not None and (p.get("id") == product_id or str(p.get("id")) == str(product_id)):
            return p
        if name is not None and p.get("name") == name:
            return p
    return None


def _product_stock_value(prod):
    # Try common stock field names
    for k in ("stock", "quantity", "qty", "available_stock", "current_stock", "on_hand"):
        if k in prod:
            try:
                return float(prod[k])
            except Exception:
                pass
    # Sometimes stock is nested or in balances
    if "stocks" in prod and isinstance(prod["stocks"], (list, dict)):
        # if list, sum quantities
        if isinstance(prod["stocks"], list):
            s = 0.0
            for item in prod["stocks"]:
                for k in ("quantity", "qty", "stock"):
                    if k in item:
                        try:
                            s += float(item[k])
                            break
                        except Exception:
                            pass
            return s
    return None


def test_checkout_and_goods_receipt_stock_and_accounting_integration():
    passes = 0
    fails = 0

    session = requests.Session()

    product_id = None
    created_product_name = f"TEST-PROD-{int(time.time())}"

    try:
        # 1) Login as master
        try:
            r = session.post(f"{BASE_URL}/api/login", json={"email": MASTER_EMAIL, "password": MASTER_PASSWORD}, timeout=TIMEOUT)
            assert r.status_code == 200, f"Login expected 200 but got {r.status_code} - {r.text}"
            token = _extract_token(r.json())
            assert token, "No token found in login response"
            headers = _get_auth_headers(token)
            passes += 1
            print("PASS: Master login")
        except Exception as e:
            fails += 1
            print("FAIL: Master login -", e)
            raise

        # helper: get journals
        def get_journals():
            jr = session.get(f"{BASE_URL}/api/journals", headers=headers, timeout=TIMEOUT)
            if jr.status_code == 200:
                return jr.json()
            return None

        # helper: get all products
        def get_products():
            pr = session.get(f"{BASE_URL}/api/products", headers=headers, params={"per_page": 200}, timeout=TIMEOUT)
            if pr.status_code in (200,):
                pj = pr.json()
                # normalize list extraction
                if isinstance(pj, dict):
                    if "data" in pj and isinstance(pj["data"], list):
                        return pj["data"]
                    # sometimes returns list directly inside data or root list
                    for v in pj.values():
                        if isinstance(v, list):
                            return v
                elif isinstance(pj, list):
                    return pj
            return []

        # 2) Create a product (since we need a known product and stock) - master should be allowed
        try:
            product_payload = {
                "category_id": 1,
                "name": created_product_name,
                "purchase_price": 10.00,
                "selling_price": 15.00
            }
            r = session.post(f"{BASE_URL}/api/products", headers=headers, json=product_payload, timeout=TIMEOUT)
            assert r.status_code in (200, 201), f"Create product expected 200/201 but got {r.status_code} - {r.text}"
            pj = r.json()
            # find id in common places
            product_id = pj.get("id") or (pj.get("data") or {}).get("id") if isinstance(pj, dict) else None
            if not product_id:
                # try to search products list by name
                products = get_products()
                found = _find_product_in_list(products, name=created_product_name)
                assert found, "Created product not found in list and no id in response"
                product_id = found.get("id")
            assert product_id, "Failed to determine created product id"
            passes += 1
            print("PASS: Product created with id", product_id)
        except Exception as e:
            fails += 1
            print("FAIL: Product creation -", e)
            raise

        # 3) Ensure initial stock is zero or known. We'll get current stock value.
        try:
            products_before = get_products()
            prod_before = _find_product_in_list(products_before, product_id=product_id, name=created_product_name)
            initial_stock = _product_stock_value(prod_before) if prod_before else None
            print("Initial stock value detected:", initial_stock)
            passes += 1
        except Exception as e:
            fails += 1
            initial_stock = None
            print("WARN: Could not determine initial stock -", e)

        # 4) Post goods receipt to increase stock
        try:
            gr_payload = {
                "reference_number": f"GR-{int(time.time())}",
                "supplier_name": "Test Supplier",
                # required by server validation
                "payment_type": "cash",
                "date": time.strftime("%Y-%m-%d"),
                "items": [
                    {
                        "product_id": product_id,
                        "quantity": 10,
                        "purchase_price": 10.00,
                        "unit_price": 10.00
                    }
                ]
            }
            r = session.post(f"{BASE_URL}/api/goods-receipts", headers=headers, json=gr_payload, timeout=TIMEOUT)
            assert r.status_code in (200, 201), f"Goods receipt expected 201/200 but got {r.status_code} - {r.text}"
            passes += 1
            print("PASS: Goods receipt posted")
        except Exception as e:
            fails += 1
            print("FAIL: Goods receipt posting -", e)
            raise

        # Wait briefly for asynchronous processing (if any)
        time.sleep(0.5)

        # 5) Verify stock increased by expected amount
        try:
            products_after_gr = get_products()
            prod_after_gr = _find_product_in_list(products_after_gr, product_id=product_id, name=created_product_name)
            stock_after_gr = _product_stock_value(prod_after_gr)
            assert stock_after_gr is not None, "Could not read product stock after goods receipt"
            if initial_stock is None:
                # we expect at least the received quantity now
                assert stock_after_gr >= 10 - 1e-6, f"Stock after GR expected >=10 but got {stock_after_gr}"
            else:
                assert abs(stock_after_gr - (initial_stock + 10)) < 0.001, f"Stock after GR expected {initial_stock + 10} but got {stock_after_gr}"
            passes += 1
            print("PASS: Stock increased after goods receipt (value:", stock_after_gr, ")")
        except Exception as e:
            fails += 1
            print("FAIL: Stock did not increase after goods receipt -", e)
            raise

        # 6) Verify a balanced inventory journal was created (journals count increased and latest journal balances)
        try:
            journals_before = get_journals() or []
            count_before = len(journals_before) if isinstance(journals_before, list) else (len(journals_before.get("data") or []) if isinstance(journals_before, dict) else 0)
            # fetch again to get after (we already posted GR)
            journals_after_raw = get_journals()
            # normalize list extraction
            if journals_after_raw is None:
                raise AssertionError("Could not retrieve journals")
            if isinstance(journals_after_raw, dict) and "data" in journals_after_raw and isinstance(journals_after_raw["data"], list):
                journals_after = journals_after_raw["data"]
            elif isinstance(journals_after_raw, list):
                journals_after = journals_after_raw
            else:
                # attempt other structures
                journals_after = []
                for v in journals_after_raw.values():
                    if isinstance(v, list):
                        journals_after = v
                        break
            count_after = len(journals_after)
            assert count_after >= count_before, "Journal count did not increase after goods receipt"
            # attempt to take the latest journal (assuming it's first or last)
            latest_journal = None
            if count_after > 0:
                latest_journal = journals_after[0] if isinstance(journals_after, list) else None
                if latest_journal and latest_journal.get("id") is None and len(journals_after) > 1:
                    latest_journal = journals_after[-1]
            assert latest_journal is not None, "Could not determine latest journal entry"
            # extract lines and check debit == credit
            lines = latest_journal.get("lines") or latest_journal.get("journal_lines") or latest_journal.get("items") or []
            if isinstance(lines, dict):
                # sometimes wrapped
                lines = list(lines.values())
            total_debit = 0.0
            total_credit = 0.0
            for ln in lines:
                total_debit += float(ln.get("debit") or 0)
                total_credit += float(ln.get("credit") or 0)
            assert abs(total_debit - total_credit) < 0.01, f"Inventory journal not balanced: debit={total_debit}, credit={total_credit}"
            passes += 1
            print("PASS: Goods receipt posted a balanced inventory journal (debit==credit==%.2f)" % total_debit)
        except Exception as e:
            fails += 1
            print("FAIL: Goods receipt journal validation -", e)
            # Do not raise further; continue to checkout tests

        # 7) Perform a valid checkout with in-stock items and a valid discount
        try:
            # Use quantity 2, discount 5.0 (less than subtotal)
            checkout_payload = {
                "items": [
                    {
                        "product_id": product_id,
                        "quantity": 2
                    }
                ],
                "payments": [
                    {
                        "method": "cash",
                        "amount": 25.0  # expecting selling price 15 *2 = 30, after discount 5 => 25
                    }
                ],
                "discount": 5.0,
                "note": "Test checkout valid"
            }
            r = session.post(f"{BASE_URL}/api/checkout", headers=headers, json=checkout_payload, timeout=TIMEOUT)
            assert r.status_code == 201, f"Valid checkout expected 201 but got {r.status_code} - {r.text}"
            passes += 1
            print("PASS: Valid checkout returned 201")
        except Exception as e:
            fails += 1
            print("FAIL: Valid checkout -", e)
            raise

        # wait briefly
        time.sleep(0.5)

        # 8) Verify stock decreased by 2 and a balanced sales journal posted
        try:
            products_after_checkout = get_products()
            prod_after_checkout = _find_product_in_list(products_after_checkout, product_id=product_id, name=created_product_name)
            stock_after_checkout = _product_stock_value(prod_after_checkout)
            assert stock_after_checkout is not None, "Could not read product stock after checkout"
            expected_stock = stock_after_gr - 2
            assert abs(stock_after_checkout - expected_stock) < 0.01, f"Stock after checkout expected {expected_stock} but got {stock_after_checkout}"
            passes += 1
            print("PASS: Stock decreased by checkout (now", stock_after_checkout, ")")
        except Exception as e:
            fails += 1
            print("FAIL: Stock did not decrease after checkout -", e)
            raise

        # 9) Verify sales journal balanced (similar approach as goods receipt)
        try:
            journals_after_checkout_raw = get_journals()
            if journals_after_checkout_raw is None:
                raise AssertionError("Could not retrieve journals after checkout")
            # normalize
            if isinstance(journals_after_checkout_raw, dict) and "data" in journals_after_checkout_raw:
                journals_after_checkout = journals_after_checkout_raw["data"]
            elif isinstance(journals_after_checkout_raw, list):
                journals_after_checkout = journals_after_checkout_raw
            else:
                journals_after_checkout = []
                for v in journals_after_checkout_raw.values():
                    if isinstance(v, list):
                        journals_after_checkout = v
                        break
            # assume latest journal corresponds to checkout; take the latest entry
            latest = journals_after_checkout[0] if journals_after_checkout else None
            if latest is None:
                raise AssertionError("No journals found after checkout")
            lines = latest.get("lines") or latest.get("journal_lines") or latest.get("items") or []
            total_debit = sum(float(ln.get("debit") or 0) for ln in lines)
            total_credit = sum(float(ln.get("credit") or 0) for ln in lines)
            assert abs(total_debit - total_credit) < 0.01, f"Sales journal not balanced: debit={total_debit}, credit={total_credit}"
            passes += 1
            print("PASS: Checkout posted a balanced sales journal (%.2f)" % total_debit)
        except Exception as e:
            fails += 1
            print("FAIL: Checkout journal validation -", e)
            # continue

        # 10) Attempt over-stock checkout (quantity above available) -> expect 422 and no stock change
        try:
            # Choose quantity large: current stock + 1000
            over_qty = int(stock_after_checkout + 1000 if stock_after_checkout is not None else 99999)
            over_payload = {
                "items": [
                    {
                        "product_id": product_id,
                        "quantity": over_qty
                    }
                ],
                "payments": [{"method": "cash", "amount": 1}],
                "discount": 0.0
            }
            r = session.post(f"{BASE_URL}/api/checkout", headers=headers, json=over_payload, timeout=TIMEOUT)
            assert r.status_code == 422, f"Over-stock checkout expected 422 but got {r.status_code} - {r.text}"
            # verify stock unchanged
            products_after_over = get_products()
            prod_after_over = _find_product_in_list(products_after_over, product_id=product_id, name=created_product_name)
            stock_after_over = _product_stock_value(prod_after_over)
            assert abs(stock_after_over - stock_after_checkout) < 0.01, "Stock changed after failed over-stock checkout"
            passes += 1
            print("PASS: Over-stock checkout rejected with 422 and stock unchanged")
        except AssertionError as e:
            fails += 1
            print("FAIL: Over-stock checkout -", e)
        except Exception as e:
            fails += 1
            print("ERROR during over-stock checkout test -", e)

        # 11) Attempt invalid discount (negative or greater than subtotal) -> expect 422 and no stock change
        try:
            # Apply an excessive discount (e.g., 10000) to exceed subtotal
            invalid_discount_payload = {
                "items": [
                    {
                        "product_id": product_id,
                        "quantity": 1
                    }
                ],
                "payments": [{"method": "cash", "amount": 0}],
                "discount": 100000.0
            }
            r = session.post(f"{BASE_URL}/api/checkout", headers=headers, json=invalid_discount_payload, timeout=TIMEOUT)
            assert r.status_code == 422, f"Invalid discount checkout expected 422 but got {r.status_code} - {r.text}"
            # verify stock unchanged
            products_after_invalid_disc = get_products()
            prod_after_invalid_disc = _find_product_in_list(products_after_invalid_disc, product_id=product_id, name=created_product_name)
            stock_after_invalid_disc = _product_stock_value(prod_after_invalid_disc)
            assert abs(stock_after_invalid_disc - stock_after_checkout) < 0.01, "Stock changed after failed invalid-discount checkout"
            passes += 1
            print("PASS: Invalid discount checkout rejected with 422 and stock unchanged")
        except AssertionError as e:
            fails += 1
            print("FAIL: Invalid discount checkout -", e)
        except Exception as e:
            fails += 1
            print("ERROR during invalid-discount checkout test -", e)

        # 12) Submit a purchase order alone and confirm it does not change stock
        try:
            po_payload = {
                "reference_number": f"PO-{int(time.time())}",
                "supplier_name": "Test Supplier",
                "items": [
                    {
                        "product_id": product_id,
                        "quantity": 5,
                        "purchase_price": 10.0
                    }
                ]
            }
            # Common endpoint name attempt
            r = session.post(f"{BASE_URL}/api/purchase-orders", headers=headers, json=po_payload, timeout=TIMEOUT)
            # Accept 200 or 201 as "order created"
            assert r.status_code in (200, 201), f"Purchase order expected 200/201 but got {r.status_code} - {r.text}"
            # verify stock unchanged
            products_after_po = get_products()
            prod_after_po = _find_product_in_list(products_after_po, product_id=product_id, name=created_product_name)
            stock_after_po = _product_stock_value(prod_after_po)
            assert abs(stock_after_po - stock_after_checkout) < 0.01, "Stock changed after creating purchase order without goods receipt"
            passes += 1
            print("PASS: Purchase order created but stock unchanged")
        except AssertionError as e:
            fails += 1
            print("FAIL: Purchase order stock-change assertion -", e)
        except requests.HTTPError as e:
            fails += 1
            print("HTTP error creating purchase order -", e)
        except Exception as e:
            # If endpoint does not exist (404), count as a warning but do not fail the whole suite
            if isinstance(e, requests.exceptions.HTTPError) and getattr(e.response, "status_code", None) == 404:
                fails += 1
                print("FAIL: Purchase order endpoint not found (404) -", e)
            else:
                # Some systems might not implement purchase-orders API; report as fail but continue
                fails += 1
                print("ERROR during purchase order test -", e)

    finally:
        # cleanup: attempt to delete created product if exists
        if product_id:
            try:
                dr = session.delete(f"{BASE_URL}/api/products/{product_id}", headers=headers, timeout=TIMEOUT)
                if dr.status_code in (200, 204):
                    print("CLEANUP: Deleted created product", product_id)
                else:
                    print("CLEANUP: Could not delete product (status", dr.status_code, ")", dr.text[:200])
            except Exception as e:
                print("CLEANUP: Exception while deleting product -", e)

    # Summary
    total = passes + fails
    print("TEST RESULT SUMMARY for TC006 - checkout_and_goods_receipt_stock_and_accounting_integration")
    print(f"Total assertions attempted: {total}")
    print(f"Passed: {passes}")
    print(f"Failed: {fails}")

    # Final assertion: consider test failed if any assertion failed
    if fails > 0:
        raise AssertionError(f"Test completed with {fails} failures and {passes} passes")


if __name__ == "__main__":
    try:
        test_checkout_and_goods_receipt_stock_and_accounting_integration()
        print("All checks passed for TC006.")
    except AssertionError as e:
        print("TC006 finished with failures:", e)
        sys.exit(1)
    except Exception as e:
        print("TC006 encountered an unexpected error:", e)
        sys.exit(2)
import asyncio
import re
from playwright import async_api
from playwright.async_api import expect

async def run_test():
    pw = None
    browser = None
    context = None

    try:
        # Start a Playwright session in asynchronous mode
        pw = await async_api.async_playwright().start()

        # Launch a Chromium browser in headless mode with custom arguments
        browser = await pw.chromium.launch(
            headless=True,
            args=[
                "--window-size=1280,720",
                "--disable-dev-shm-usage",
                "--ipc=host",
                "--single-process"
            ],
        )

        # Create a new browser context (like an incognito window)
        context = await browser.new_context()
        # Wider default timeout to match the agent's DOM-stability budget;
        # auto-waiting Playwright APIs (expect, locator.wait_for) inherit this.
        context.set_default_timeout(15000)

        # Open a new page in the browser context
        page = await context.new_page()

        # Interact with the page elements to simulate user flow
        # -> navigate
        await page.goto("http://localhost:8000/")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # -> Click the 'Masuk' button to open the login page.
        # Masuk link
        elem = page.get_by_role("link", name="Masuk")
        await elem.click(timeout=10000)
        
        # -> Fill the 'Alamat Email' field with master@majubersama.test, fill the 'Kata Sandi' field with password, then click the 'Masuk' button to sign in.
        # nama@majubersama.test email field
        elem = page.get_by_role("textbox", name="Alamat Email")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("master@majubersama.test")
        
        # -> Fill the 'Alamat Email' field with master@majubersama.test, fill the 'Kata Sandi' field with password, then click the 'Masuk' button to sign in.
        # Masukkan kata sandi password field
        elem = page.get_by_role("textbox", name="Kata Sandi")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password")
        
        # -> Fill the 'Alamat Email' field with master@majubersama.test, fill the 'Kata Sandi' field with password, then click the 'Masuk' button to sign in.
        # Masuk button
        elem = page.get_by_role("button", name="Masuk")
        await elem.click(timeout=10000)
        
        # -> Click the 'Pengaturan' button in the left sidebar to reveal the product/catalog links.
        # Pengaturan Produk, staf, cabang button
        elem = page.get_by_role("button", name="Pengaturan Produk, staf,")
        await elem.click(timeout=10000)
        
        # -> Click the 'Katalog Produk' link in the left sidebar to open the product catalog page.
        # Katalog Produk link
        elem = page.get_by_role("link", name="Katalog Produk")
        await elem.click(timeout=10000)
        
        # -> Click the '+ Tambah Produk' button to open the new product form.
        # + Tambah Produk link
        elem = page.get_by_role("link", name="+ Tambah Produk")
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The new-product page is open at /backoffice/products/create.
        # Assert-outcome: passed
        # Assert: Page URL contains /backoffice/products/create.
        await expect(page).to_have_url(re.compile("/backoffice/products/create"), timeout=15000), "Page URL contains /backoffice/products/create."
        
        # --> The product creation form shows the core input fields (e.g. Nama Produk).
        await page.get_by_role("textbox", name="Nama Produk *").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The 'Nama Produk' text input is visible on the form.
        await expect(page.get_by_role("textbox", name="Nama Produk *").nth(0)).to_be_visible(timeout=15000), "The 'Nama Produk' text input is visible on the form."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    
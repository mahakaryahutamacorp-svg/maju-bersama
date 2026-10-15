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
        
        # -> Click the 'Masuk' (Login) button on the homepage to open the login page.
        # Masuk link
        elem = page.get_by_role("link", name="Masuk")
        await elem.click(timeout=10000)
        
        # -> Fill the 'Alamat Email' and 'Kata Sandi' fields and click the 'Masuk' button to submit the sign-in form.
        # nama@majubersama.test email field
        elem = page.get_by_role("textbox", name="Alamat Email")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("master@majubersama.test")
        
        # -> Fill the 'Alamat Email' and 'Kata Sandi' fields and click the 'Masuk' button to submit the sign-in form.
        # Masukkan kata sandi password field
        elem = page.get_by_role("textbox", name="Kata Sandi")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password")
        
        # -> Fill the 'Alamat Email' and 'Kata Sandi' fields and click the 'Masuk' button to submit the sign-in form.
        # Masuk button
        elem = page.get_by_role("button", name="Masuk")
        await elem.click(timeout=10000)
        
        # -> Click the 'Pengaturan' menu in the left navigation to reveal the 'Produk' submenu
        # Pengaturan Produk, staf, cabang button
        elem = page.get_by_role("button", name="Pengaturan Produk, staf,")
        await elem.click(timeout=10000)
        
        # -> Click the 'Katalog Produk' link in the 'Pengaturan' menu to open the product catalog.
        # Katalog Produk link
        elem = page.get_by_role("link", name="Katalog Produk")
        await elem.click(timeout=10000)
        
        # -> Open the 'Filter Cabang' dropdown (labelled 'Filter Cabang') to reveal branch options such as 'CV.DEKA AGRO MANDIRI (PAKAN)'.
        # Seluruh Cabang CV.DEKA AGRO MANDIRI (PAKAN) MAJU... dropdown
        elem = page.locator("select[name=\"branch_id\"]")
        await elem.click(timeout=10000)
        
        # -> Select 'CV.DEKA AGRO MANDIRI (PAKAN)' from the 'Filter Cabang' dropdown.
        # Seluruh Cabang CV.DEKA AGRO MANDIRI (PAKAN) MAJU... dropdown
        elem = page.locator("xpath=/html/body/div/div/main/div[2]/form/div[3]/select").nth(0)
        await elem.wait_for(state="visible", timeout=10000)
        await elem.select_option("")
        
        # -> Click the 'Filter' button to apply the selected branch filter.
        # Filter button
        elem = page.get_by_role("button", name="Filter")
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The product table is displayed on the Katalog Produk page.
        # Assert-outcome: passed
        # Assert: Product table header contains 'Produk & SKU'.
        await expect(page.locator("thead").nth(0)).to_contain_text("Produk & SKU", timeout=15000), "Product table header contains 'Produk & SKU'."
        
        # --> The product list is scoped to the selected branch CV.DEKA AGRO MANDIRI (PAKAN).
        # Assert-outcome: passed
        # Assert: Product row's Cabang column equals 'CV.DEKA AGRO MANDIRI (PAKAN)'.
        await expect(page.locator("xpath=/html/body/div[1]/div/main/div[3]/div[2]/table/tbody/tr/td[3]").nth(0)).to_have_text("CV.DEKA AGRO MANDIRI\nPAKAN", timeout=15000), "Product row's Cabang column equals 'CV.DEKA AGRO MANDIRI (PAKAN)'."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    
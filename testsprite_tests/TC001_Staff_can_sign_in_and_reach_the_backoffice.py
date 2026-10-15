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
        
        # -> Click the 'Masuk' button in the page header to open the login page.
        # Masuk link
        elem = page.get_by_role("link", name="Masuk")
        await elem.click(timeout=10000)
        
        # -> Fill 'master@majubersama.test' into the 'Alamat Email' field, fill 'password' into the 'Kata Sandi' field, then click the 'Masuk' button to submit.
        # nama@majubersama.test email field
        elem = page.get_by_role("textbox", name="Alamat Email")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("master@majubersama.test")
        
        # -> Fill 'master@majubersama.test' into the 'Alamat Email' field, fill 'password' into the 'Kata Sandi' field, then click the 'Masuk' button to submit.
        # Masukkan kata sandi password field
        elem = page.get_by_role("textbox", name="Kata Sandi")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("password")
        
        # -> Fill 'master@majubersama.test' into the 'Alamat Email' field, fill 'password' into the 'Kata Sandi' field, then click the 'Masuk' button to submit.
        # Masuk button
        elem = page.get_by_role("button", name="Masuk")
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> The browser is on the backoffice page (URL contains /backoffice).
        # Assert-outcome: passed
        # Assert: Verifies the current URL contains '/backoffice'.
        await expect(page).to_have_url(re.compile("/backoffice"), timeout=15000), "Verifies the current URL contains '/backoffice'.\u0010"
        
        # --> Authenticated staff username 'master' is shown in the sidebar.
        # Assert-outcome: passed
        # Assert: Verifies the sidebar username text equals 'master'.
        await expect(page.locator("xpath=/html/body/div[1]/aside/div[2]/div/p[2]/span").nth(0)).to_have_text("master", timeout=15000), "Verifies the sidebar username text equals 'master'."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    
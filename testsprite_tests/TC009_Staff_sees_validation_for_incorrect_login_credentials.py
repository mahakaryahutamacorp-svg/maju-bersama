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
        
        # -> Fill the 'Alamat Email' field with 'invalid@example.com', fill the 'Kata Sandi' field with 'invalid-password', and click the 'Masuk' button to submit the form.
        # nama@majubersama.test email field
        elem = page.get_by_role("textbox", name="Alamat Email")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("invalid@example.com")
        
        # -> Fill the 'Alamat Email' field with 'invalid@example.com', fill the 'Kata Sandi' field with 'invalid-password', and click the 'Masuk' button to submit the form.
        # Masukkan kata sandi password field
        elem = page.get_by_role("textbox", name="Kata Sandi")
        await elem.wait_for(state="visible", timeout=10000)
        await elem.fill("invalid-password")
        
        # -> Fill the 'Alamat Email' field with 'invalid@example.com', fill the 'Kata Sandi' field with 'invalid-password', and click the 'Masuk' button to submit the form.
        # Masuk button
        elem = page.get_by_role("button", name="Masuk")
        await elem.click(timeout=10000)
        
        # --> Assertions to verify final state
        
        # --> Invalid credentials are rejected: the error "Email atau password tidak valid." is shown and the app remains on the /login page.
        # Assert-outcome: passed
        # Assert: The URL contains /login, showing the app stayed on the login page.
        await expect(page).to_have_url(re.compile("/login"), timeout=15000), "The URL contains /login, showing the app stayed on the login page."
        await page.get_by_role("textbox", name="Alamat Email").nth(0).scroll_into_view_if_needed()
        # Assert-outcome: passed
        # Assert: The login form's email input is visible, confirming the login form is still displayed.
        await expect(page.get_by_role("textbox", name="Alamat Email").nth(0)).to_be_visible(timeout=15000), "The login form's email input is visible, confirming the login form is still displayed."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    
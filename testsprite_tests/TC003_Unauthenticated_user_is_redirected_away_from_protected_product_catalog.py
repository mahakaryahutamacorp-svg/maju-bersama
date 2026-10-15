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
        
        # -> Open the '/backoffice/products' page and confirm whether it redirects to a login page and that the protected product catalog content is not displayed.
        await page.goto("http://localhost:8000/backoffice/products")
        try:
            await page.wait_for_load_state("domcontentloaded", timeout=5000)
        except Exception:
            pass
        
        # --> Assertions to verify final state
        
        # --> User is redirected to the login page.
        # Assert-outcome: passed
        # Assert: Page URL contains '/login', indicating redirect to the login page.
        await expect(page).to_have_url(re.compile("/login"), timeout=15000), "Page URL contains '/login', indicating redirect to the login page."
        
        # --> Protected product catalog content is not shown and the login form is displayed.
        # Assert-outcome: passed
        # Assert: Login submit button with text 'Masuk' is visible, showing the login form instead of product catalog content.
        await expect(page.get_by_role("button").nth(0)).to_have_text("Masuk", timeout=15000), "Login submit button with text 'Masuk' is visible, showing the login form instead of product catalog content."
        await asyncio.sleep(5)

    finally:
        if context:
            await context.close()
        if browser:
            await browser.close()
        if pw:
            await pw.stop()

asyncio.run(run_test())
    
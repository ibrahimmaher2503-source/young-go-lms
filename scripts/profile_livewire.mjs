import { chromium } from 'playwright';

const BASE_URL = 'http://127.0.0.1:8080';

async function run() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log('Logging in as instructor via /login...');
  await page.goto(`${BASE_URL}/login`);
  await page.waitForLoadState('networkidle');
  await page.fill('input#login', 'instructor@younggo.test');
  await page.fill('input#password', 'password123');
  await page.locator('form[data-ajax] button[type="submit"]').click();
  await page.waitForTimeout(1000);
  console.log('Instructor logged in, current URL:', page.url());

  // Navigate to /instructor/courses
  console.log('Navigating to /instructor/courses...');
  await page.goto(`${BASE_URL}/instructor/courses`);
  await page.waitForLoadState('networkidle');
  console.log('Opened /instructor/courses, page title:', await page.title());

  // Find table search input
  const searchInput = page.locator('input[placeholder*="بحث"], input[placeholder*="Search"], input[type="search"]').first();
  if (await searchInput.isVisible()) {
    console.log('Found search input, typing search query...');
    const [livewireResponse] = await Promise.all([
      page.waitForResponse((res) => res.url().includes('/update') && res.status() === 200),
      searchInput.fill('رسم'),
    ]);
    console.log('Livewire search request completed with status:', livewireResponse.status());
  } else {
    console.log('Search input not visible directly on table.');
  }

  await browser.close();
}

run().catch((err) => {
  console.error('Error:', err);
  process.exit(1);
});

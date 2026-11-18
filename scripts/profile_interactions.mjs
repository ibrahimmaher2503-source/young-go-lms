import { chromium } from 'playwright';
import { execSync } from 'child_process';

const BASE_URL = 'http://127.0.0.1:8080';

// Ensure demo student is ready
execSync('php artisan cache:clear', { stdio: 'pipe' });
execSync('php artisan db:seed --class=DemoStudentSeeder', { stdio: 'pipe' });

async function run() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  // 1. Login as demo student
  console.log('Logging in as student...');
  await page.goto(`${BASE_URL}/login`);
  await page.waitForLoadState('networkidle');
  await page.fill('input#login', 'student@younggo.test');
  await page.fill('input#password', 'password123');
  await Promise.all([
    page.waitForURL('**/my', { timeout: 15000 }),
    page.locator('form[data-ajax] button[type="submit"]').click(),
  ]);
  console.log('Logged in successfully!');

  // 2. Open course player /learn/1/1
  console.log('Navigating to /learn/1/1...');
  await page.goto(`${BASE_URL}/learn/1/1`, { waitUntil: 'networkidle' });

  // 3. Send progress heartbeat via browser fetch (authenticated)
  console.log('Sending heartbeat via browser fetch to /learn/1/1/progress...');
  const heartbeatRes = await page.evaluate(async () => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    const res = await fetch('/learn/1/1/progress', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': token,
      },
      body: JSON.stringify({ position: 45 }),
    });
    return { status: res.status, body: await res.json() };
  });
  console.log('Heartbeat response:', heartbeatRes);

  // 4. Click complete button on player
  console.log('Clicking complete button on player...');
  const completeBtn = page.locator('[data-player-complete]');
  if (await completeBtn.isVisible()) {
    await Promise.all([
      page.waitForResponse((res) => res.url().includes('/complete') && res.status() === 200),
      completeBtn.click(),
    ]);
    console.log('Completed lesson successfully!');
  } else {
    console.log('Complete button not visible or already completed.');
  }

  await browser.close();
}

run().catch((err) => {
  console.error('Error running interactions:', err);
  process.exit(1);
});

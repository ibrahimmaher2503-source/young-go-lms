import { chromium } from 'playwright';

const BASE_URL = 'http://127.0.0.1:8080';

async function run() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log('Logging in as student...');
  await page.goto(`${BASE_URL}/login`);
  await page.waitForLoadState('networkidle');
  await page.fill('input#login', 'student@younggo.test');
  await page.fill('input#password', 'password123');
  await page.locator('form[data-ajax] button[type="submit"]').click();
  await page.waitForTimeout(1000);

  console.log('Navigating to quiz attempt /learn/1/100/quiz/1...');
  await page.goto(`${BASE_URL}/learn/1/100/quiz/1`);
  await page.waitForLoadState('networkidle');

  // Check if open or already completed
  const submitBtn = page.locator('button[data-submit]');
  const finishBtn = page.locator('button[data-finish]');
  
  if (await finishBtn.isVisible()) {
    console.log('Attempt is OPEN. Selecting answer and submitting...');
    const radio = page.locator('input[type="radio"]').first();
    if (await radio.isVisible()) {
      await radio.check();
      console.log('Answer radio checked.');
    }

    console.log('Clicking finish button...');
    await finishBtn.click();
    await page.waitForTimeout(300);

    console.log('Clicking confirm submit button...');
    await Promise.all([
      page.waitForURL('**/quiz/**', { timeout: 15000 }),
      submitBtn.click(),
    ]);
    await page.waitForLoadState('networkidle');
    console.log('Submitted successfully! Final URL:', page.url());
  } else {
    console.log('Attempt is already graded or closed.');
  }

  // Verify result section
  const score = await page.locator('[data-score]').textContent().catch(() => null);
  const percent = await page.locator('[data-percent]').textContent().catch(() => null);
  const outcome = await page.locator('[data-outcome]').textContent().catch(() => null);
  console.log(`Quiz Result: Score = ${score?.trim()}, Percent = ${percent?.trim()}, Outcome = ${outcome?.trim()}`);

  await browser.close();
}

run().catch((err) => {
  console.error('Error:', err);
  process.exit(1);
});

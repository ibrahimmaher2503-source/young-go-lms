import { chromium } from 'playwright';

const BASE_URL = 'http://127.0.0.1:8080';

async function run() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  console.log('1. Logging in as student...');
  await page.goto(`${BASE_URL}/login`);
  await page.waitForLoadState('networkidle');
  await page.fill('input#login', 'student@younggo.test');
  await page.fill('input#password', 'password123');
  await Promise.all([
    page.waitForURL('**/my', { timeout: 15000 }),
    page.locator('form[data-ajax] button[type="submit"]').click(),
  ]);
  console.log('Logged in, URL:', page.url());

  // 2. Open /learn/1/100 (Quiz item)
  console.log('2. Opening /learn/1/100 (Quiz Overview)...');
  await page.goto(`${BASE_URL}/learn/1/100`, { waitUntil: 'networkidle' });
  console.log('Page title:', await page.title());

  // Check start quiz form
  const startQuizBtn = page.locator('form[action*="/quiz/start"] button[type="submit"], button:has-text("ابدأ الاختبار"), button:has-text("Start Quiz")').first();
  if (await startQuizBtn.isVisible()) {
    console.log('Found Start Quiz button, clicking...');
    await Promise.all([
      page.waitForURL('**/quiz/**', { timeout: 15000 }),
      startQuizBtn.click(),
    ]);
    console.log('Started Quiz, redirected to:', page.url());

    // 3. View quiz attempt form
    await page.waitForLoadState('networkidle');
    console.log('Quiz attempt page loaded, checking question options...');

    // Select the first radio option
    const firstRadio = page.locator('input[type="radio"]').first();
    if (await firstRadio.isVisible()) {
      await firstRadio.check();
      console.log('Selected question answer radio button.');
    }

    // 4. Submit Quiz
    const submitBtn = page.locator('form button[type="submit"]:has-text("تسليم"), form button[type="submit"]:has-text("Submit"), button[type="submit"]').first();
    if (await submitBtn.isVisible()) {
      console.log('Clicking Submit Quiz button...');
      await Promise.all([
        page.waitForResponse((res) => res.url().includes('/submit') || res.url().includes('/quiz')),
        submitBtn.click(),
      ]);
      await page.waitForLoadState('networkidle');
      console.log('Quiz submitted! Final URL:', page.url());
      
      const bodyText = await page.innerText('body');
      const hasResult = bodyText.includes('النتيجة') || bodyText.includes('Score') || bodyText.includes('Result') || bodyText.includes('10');
      console.log('Result/Score visible on page:', hasResult ? 'YES' : 'NO');
    }
  } else {
    console.log('Start Quiz button not visible. Printing body preview:');
    const text = await page.innerText('body');
    console.log(text.substring(0, 300));
  }

  await browser.close();
}

run().catch((err) => {
  console.error('Error:', err);
  process.exit(1);
});

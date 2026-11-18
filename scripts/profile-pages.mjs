import { chromium } from '@playwright/test';
import { execSync } from 'child_process';

async function profile() {
  // Ensure fresh seed
  execSync('php artisan cache:clear', { stdio: 'pipe' });
  execSync('php artisan db:seed --class=DemoStudentSeeder', { stdio: 'pipe' });

  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  // Attach CDP Session for Chrome DevTools Performance & Metrics
  const cdp = await page.context().newCDPSession(page);
  await cdp.send('Performance.enable');
  await cdp.send('Network.enable');

  console.log('--- Logging in ---');
  await page.goto('http://127.0.0.1:8080/login');
  await page.waitForLoadState('networkidle');
  await page.fill('input#login', 'student@younggo.test');
  await page.fill('input#password', 'password123');
  await page.locator('form[data-ajax] button[type="submit"]').click();
  await page.waitForURL('**/my');

  async function profileUrl(url, label) {
    console.log(`\n================ Profiling ${label} (${url}) ================`);
    const requests = [];
    const urlCounts = new Map();

    const requestListener = (req) => {
      const u = req.url();
      urlCounts.set(u, (urlCounts.get(u) || 0) + 1);
    };

    const responseListener = (res) => {
      const req = res.request();
      const timing = req.timing();
      const duration = timing && timing.responseEnd >= 0 ? timing.responseEnd : null;
      requests.push({
        url: req.url(),
        method: req.method(),
        type: req.resourceType(),
        status: res.status(),
        duration,
      });
    };

    page.on('request', requestListener);
    page.on('response', responseListener);

    const startTime = Date.now();
    await page.goto(url);
    await page.waitForLoadState('networkidle');
    const totalTime = Date.now() - startTime;

    page.off('request', requestListener);
    page.off('response', responseListener);

    // Get Chrome DevTools Metrics
    const cdpMetrics = await cdp.send('Performance.getMetrics');
    const metricsMap = {};
    for (const m of cdpMetrics.metrics) {
      metricsMap[m.name] = m.value;
    }

    // Performance Timing from browser Resource Timing API
    const resourceTimings = await page.evaluate(() => {
      return performance.getEntriesByType('resource').map((r) => ({
        name: r.name,
        initiatorType: r.initiatorType,
        duration: Math.round(r.duration * 100) / 100,
        transferSize: r.transferSize,
      }));
    });

    const apiRequests = requests.filter((r) => ['fetch', 'xhr'].includes(r.type));
    const duplicateRequests = Array.from(urlCounts.entries()).filter(([_, count]) => count > 1);

    // Sort by duration descending
    const sortedByDuration = [...requests].filter((r) => r.duration !== null).sort((a, b) => b.duration - a.duration);
    const slowest = sortedByDuration[0] || (resourceTimings.sort((a, b) => b.duration - a.duration)[0] ? {
      url: resourceTimings[0].name,
      duration: resourceTimings[0].duration,
      type: resourceTimings[0].initiatorType,
    } : null);

    console.log(`Total Requests: ${requests.length}`);
    console.log(`API / XHR Requests: ${apiRequests.length}`);
    console.log(`Duplicate Requests: ${duplicateRequests.length > 0 ? duplicateRequests.map(([u, c]) => `${u} (${c}x)`).join(', ') : 'None'}`);
    if (slowest) {
      console.log(`Slowest Request: ${slowest.url} [${slowest.type}] -> ${Math.round(slowest.duration)} ms`);
    }
    console.log(`Page Load Time (networkidle): ${totalTime} ms`);
    console.log(`DevTools JS Heap Used: ${Math.round((metricsMap.JSHeapUsedSize || 0) / 1024 / 1024 * 100) / 100} MB`);
    console.log(`DevTools Layout Count: ${metricsMap.LayoutCount || 0}`);
    console.log(`DevTools Recalc Style Count: ${metricsMap.RecalcStyleCount || 0}`);

    console.log('\nAll Requests Breakdown:');
    requests.forEach((r, idx) => {
      console.log(`  ${idx + 1}. [${r.type}] ${r.status} ${r.method} ${r.url} (${Math.round(r.duration || 0)} ms)`);
    });

    return {
      label,
      url,
      totalRequests: requests.length,
      apiRequests: apiRequests.length,
      duplicateRequests,
      slowest,
      totalTime,
      metricsMap,
      requests,
    };
  }

  // 1. Profile /my
  const myProfile = await profileUrl('http://127.0.0.1:8080/my', 'Student Dashboard (/my)');

  // 2. Find a lesson link to profile /learn/...
  const learnLink = await page.locator('a[href*="/learn/"]').first().getAttribute('href');
  const fullLearnUrl = learnLink.startsWith('http') ? learnLink : `http://127.0.0.1:8080${learnLink}`;

  // Profile /learn/...
  const learnProfile = await profileUrl(fullLearnUrl, `Course Player / Lesson Page (${fullLearnUrl})`);

  await browser.close();
}

profile().catch(console.error);

import { chromium } from 'playwright';
import { execSync } from 'child_process';

const BASE_URL = 'http://127.0.0.1:8080';

// Reset cache and seed demo data
execSync('php artisan cache:clear', { stdio: 'pipe' });
execSync('php artisan db:seed --class=DemoStudentSeeder', { stdio: 'pipe' });

async function run() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext();
  const page = await context.newPage();

  async function profileUrl(name, url, loginUser = null) {
    if (loginUser) {
      await page.goto(`${BASE_URL}/login`);
      await page.waitForLoadState('networkidle');
      await page.fill('input#login', loginUser.email);
      await page.fill('input#password', loginUser.password);
      await Promise.all([
        page.waitForURL('**/my', { timeout: 15000 }),
        page.locator('form[data-ajax] button[type="submit"]').click(),
      ]);
    }

    const requests = [];
    const onReq = (req) => {
      requests.push({
        url: req.url(),
        resourceType: req.resourceType(),
        method: req.method(),
        timestamp: Date.now(),
      });
    };
    page.on('request', onReq);

    const start = Date.now();
    await page.goto(url, { waitUntil: 'networkidle' });
    const loadTime = Date.now() - start;

    page.off('request', onReq);

    const totalRequests = requests.length;
    const apiRequests = requests.filter(r => ['fetch', 'xhr'].includes(r.resourceType)).length;
    
    const urlCounts = {};
    for (const r of requests) {
      urlCounts[r.url] = (urlCounts[r.url] || 0) + 1;
    }
    const duplicateRequests = Object.entries(urlCounts).filter(([u, c]) => c > 1);

    // DevTools Heap metrics via CDP
    const cdp = await context.newCDPSession(page);
    await cdp.send('Performance.enable');
    const perfMetrics = await cdp.send('Performance.getMetrics');
    const jsHeapMetric = perfMetrics.metrics.find(m => m.name === 'JSHeapUsedSize');
    const heapUsedMb = jsHeapMetric ? (jsHeapMetric.value / 1024 / 1024).toFixed(2) : 'N/A';

    return {
      name,
      url,
      totalRequests,
      apiRequests,
      duplicateRequestsCount: duplicateRequests.length,
      duplicateRequests,
      loadTimeMs: loadTime,
      heapUsedMb,
      allRequests: requests.map(r => `${r.method} [${r.resourceType}] ${r.url.replace(BASE_URL, '')}`),
    };
  }

  const results = {};

  // 1. Public Catalog
  console.log('Profiling Public Catalog...');
  results['Public Catalog'] = await profileUrl('Public Catalog', `${BASE_URL}/courses`);

  // 2. Public Catalog Search
  console.log('Profiling Public Catalog Search...');
  results['Public Catalog Search'] = await profileUrl('Public Catalog Search', `${BASE_URL}/courses?q=رسم`);

  // 3. Student Dashboard
  console.log('Profiling Student Dashboard...');
  results['Student Dashboard'] = await profileUrl('Student Dashboard', `${BASE_URL}/my`, {
    email: 'student@younggo.test',
    password: 'password123',
  });

  // 4. Student My Courses
  console.log('Profiling Student My Courses...');
  results['Student My Courses'] = await profileUrl('Student My Courses', `${BASE_URL}/my/courses`);

  // 5. Course Player
  console.log('Profiling Course Player...');
  results['Course Player'] = await profileUrl('Course Player', `${BASE_URL}/learn/1/1`);

  console.log('--- NETWORK_PROFILE_JSON_START ---');
  console.log(JSON.stringify(results, null, 2));
  console.log('--- NETWORK_PROFILE_JSON_END ---');

  await browser.close();
}

run().catch((err) => {
  console.error('Error running network profiler:', err);
  process.exit(1);
});

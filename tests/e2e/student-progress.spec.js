import { test, expect } from '@playwright/test';
import { execSync } from 'child_process';

test.describe('Student Course Progress Flow', () => {
  test.beforeEach(async () => {
    // Reset rate limiter and ensure demo student, course, and enrollment are in a clean state
    execSync('php artisan cache:clear', { stdio: 'pipe' });
    execSync('php artisan db:seed --class=DemoStudentSeeder', { stdio: 'pipe' });
  });

  test('تسجيل الدخول → فتح كورس الطالب مسجّل فيه → إكمال درس → إعادة تحميل الصفحة والتأكد إن التقدّم اتحفظ', async ({ page }) => {
    await test.step('1. تسجيل الدخول بحساب طالب تجريبي', async () => {
      await page.goto('/login');
      await page.waitForLoadState('networkidle');

      await page.fill('input#login', 'student@younggo.test');
      await page.fill('input#password', 'password123');

      // Click submit on login form
      const submitButton = page.locator('form[data-ajax] button[type="submit"]');
      await submitButton.click();

      // Wait for redirect to /home -> /my
      await page.waitForURL('**/my', { timeout: 15000 });
      await expect(page).toHaveURL(/.*\/my/);
      await expect(page.locator('h1')).toBeVisible();
    });

    await test.step('2. فتح كورس الطالب مسجّل فيه من لوحة التحكم', async () => {
      const courseLink = page.locator('a[href*="/learn/"]').first();
      await expect(courseLink).toBeVisible({ timeout: 10000 });
      await courseLink.click();

      // Verify we arrived at the course player
      await page.waitForURL(/\/learn\/\d+\/\d+/, { timeout: 15000 });
      await expect(page.locator('[data-player]')).toBeVisible();
      await expect(page.locator('[data-lesson-title]')).toBeVisible();
    });

    await test.step('3. إكمال الدرس', async () => {
      const completeButton = page.locator('[data-player-complete]');
      await expect(completeButton).toBeVisible();

      // Click complete and wait for the response
      await Promise.all([
        page.waitForResponse((res) => res.url().includes('/complete') && res.status() === 200),
        completeButton.click(),
      ]);

      // The player.js reloads the page upon completion; wait for completed badge to appear
      await expect(page.locator('[data-lesson-completed]')).toBeVisible({ timeout: 15000 });
    });

    await test.step('4. إعادة تحميل الصفحة والتأكد من حفظ التقدّم', async () => {
      // Reload the page explicitly to verify progress was saved to the server
      await page.reload({ waitUntil: 'networkidle' });

      // Ensure the completed badge is displayed
      const completedBadge = page.locator('[data-lesson-completed]');
      await expect(completedBadge).toBeVisible();

      // Ensure the mark-complete button is removed since lesson is done
      const completeButton = page.locator('[data-player-complete]');
      await expect(completeButton).toHaveCount(0);
    });
  });
});

import { test, expect } from './fixtures/wp-fixture';

test('export formats download correctly', async ({ page }) => {
  await page.goto('/wp-login.php');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');

  await page.goto('/wp-admin/admin.php?page=pit_import_export');
  const pitApp = await page.evaluate(() => (window as any).pitApp);
  expect(pitApp).toBeTruthy();

  for (const format of ['csv', 'pdf', 'excel']) {
    const response = await page.request.get(`${pitApp.restUrl}export?format=${format}`, {
      headers: { 'X-WP-Nonce': pitApp.nonce },
    });
    expect(response.ok()).toBeTruthy();
    const body = await response.body();
    expect(body.length).toBeGreaterThan(0);
    const ct = response.headers()['content-type'];
    if (format === 'csv') expect(ct).toContain('text/csv');
    if (format === 'pdf') expect(ct).toContain('application/pdf');
    if (format === 'excel') expect(ct).toContain('application/vnd.ms-excel');
  }
});

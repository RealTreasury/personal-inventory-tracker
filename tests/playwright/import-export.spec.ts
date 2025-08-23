import { test, expect } from './fixtures/wp-fixture';
import path from 'path';

test('CSV import/export flow', async ({ page }) => {
  // Import sample CSV
  await page.goto('/wp-admin/admin.php?page=pit_import_export');
  const fileInput = page.locator('input[type="file"]');
  await fileInput.setInputFiles(path.join('docs', 'sample.csv'));
  await page.waitForSelector('button:has-text("Import Data")');
  await page.click('button:has-text("Import Data")');
  await expect(page.locator('text=Import Completed')).toBeVisible();

  // Verify items exist
  await page.goto('/wp-admin/admin.php?page=pit_items');
  await expect(page.locator('table.wp-list-table')).toContainText('Apples');

  // Export CSV and confirm content
  await page.goto('/wp-admin/admin.php?page=pit_import_export');
  await page.click('button:has-text("Export Data")');
  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.click('button:has-text("Export CSV File")'),
  ]);
  const csv = await download.text();
  expect(csv).toContain('Apples');
});

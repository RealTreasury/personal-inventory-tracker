import { test, expect } from './fixtures/wp-fixture';

test('inventory CRUD flow', async ({ page }) => {
  const itemName = `Test Item ${Date.now()}`;

  // Create
  await page.goto('/wp-admin/admin.php?page=pit_add_item');
  await page.fill('#pit_item_name', itemName);
  await page.fill('#pit_qty', '5');
  await page.click('input[type="submit"]');

  // Read
  await page.waitForURL(/page=pit_items/);
  const row = page.locator(`table.wp-list-table tbody tr:has-text("${itemName}")`);
  await expect(row).toHaveCount(1);

  // Update
  await row.locator('a:has-text("Edit")').click();
  await page.fill('#pit_qty', '10');
  await page.click('input[type="submit"]');
  await page.waitForURL(/pit_message=saved/);
  const updatedRow = page.locator(`table.wp-list-table tbody tr:has-text("${itemName}")`);
  await expect(updatedRow.locator('td.column-qty')).toContainText('10');

  // Delete
  await updatedRow.locator('input[type="checkbox"]').check();
  await page.selectOption('select[name="action"]', 'delete');
  await page.click('#doaction');
  await page.waitForLoadState('networkidle');
  await expect(page.locator(`table.wp-list-table tbody tr:has-text("${itemName}")`)).toHaveCount(0);
});

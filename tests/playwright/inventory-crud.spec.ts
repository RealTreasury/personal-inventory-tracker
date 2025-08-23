import { test, expect } from './fixtures/wp-fixture';

test('inventory CRUD flow', async ({ page }) => {
  await page.goto('http://localhost:8888/wp-admin/admin.php?page=pit_add_item');

  await page.fill('#pit_item_name', 'Playwright Item');
  await page.fill('#pit_qty', '5');

  await Promise.all([
    page.waitForNavigation(),
    page.click('text=Add Item'),
  ]);

  await expect(page.locator('.notice')).toContainText('Item saved');
  await expect(page.locator('table')).toContainText('Playwright Item');

  const row = page.locator('table tbody tr').filter({ hasText: 'Playwright Item' });
  await row.locator('a', { hasText: 'Edit' }).click();

  await page.fill('#pit_item_name', 'Updated Item');
  await Promise.all([
    page.waitForNavigation(),
    page.click('text=Update Item'),
  ]);

  await expect(page.locator('.notice')).toContainText('Item saved');
  await expect(page.locator('table')).toContainText('Updated Item');

  const updatedRow = page.locator('table tbody tr').filter({ hasText: 'Updated Item' });
  await updatedRow.locator('input[type="checkbox"]').check();
  await page.selectOption('select[name="action"]', 'delete');
  await page.click('#doaction');

  await expect(page.locator('table')).not.toContainText('Updated Item');
});

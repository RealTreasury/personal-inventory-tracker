import { test, expect } from './fixtures/wp-fixture';
import { execSync } from 'child_process';

test('inventory CRUD flow', async ({ adminPage }) => {
  const page = adminPage;
  const itemName = `Test Item ${Date.now()}`;

  // Create
  await page.goto('http://localhost:8888/wp-admin/admin.php?page=pit_add_item');
  await page.fill('#pit_item_name', itemName);
  await page.fill('#pit_qty', '3');
  await page.click('text=Add Item');
  await expect(page).toHaveURL(/page=pit_items/);
  await expect(page.locator('table.wp-list-table')).toContainText(itemName);

  // Update
  await page.click(`text=${itemName}`);
  await page.fill('#pit_qty', '5');
  await page.click('text=Update Item');
  await expect(page).toHaveURL(/page=pit_items/);

  const qty = execSync(
    `npx wp-env run cli "wp post meta get $(wp post list --post_type=pit_item --title='${itemName}' --format=ids) pit_qty"`,
    { encoding: 'utf8' }
  ).trim();
  expect(qty).toBe('5');

  // Delete
  await page.goto('http://localhost:8888/wp-admin/admin.php?page=pit_items');
  await page.locator(`tr:has-text("${itemName}") input[type='checkbox']`).check();
  await page.selectOption('select[name="action"]', 'delete');
  await page.click('#doaction');
  await expect(page.locator('table.wp-list-table')).not.toContainText(itemName);
});


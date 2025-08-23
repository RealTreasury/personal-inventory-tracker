import { test, expect } from './fixtures/wp-fixture';
import { execSync } from 'child_process';
import fs from 'fs';

test('CSV export streams thousands of items', async ({ page }) => {
  execSync(
    'npx wp-env run cli "wp post generate --post_type=pit_item --count=1500"',
    { stdio: 'inherit' }
  );

  await page.goto('http://localhost:8888/wp-admin/admin.php?page=pit_import_export');
  await page.getByRole('button', { name: 'Export Data' }).click();

  const [download] = await Promise.all([
    page.waitForEvent('download'),
    page.getByRole('button', { name: 'Export CSV File' }).click(),
  ]);

  const path = await download.path();
  const csv = fs.readFileSync(String(path), 'utf8');
  const lines = csv.trim().split(/\n/);
  expect(lines.length).toBe(1501);
});

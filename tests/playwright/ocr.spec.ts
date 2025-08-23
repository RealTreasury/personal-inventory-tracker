import { test, expect } from './fixtures/wp-fixture';

test('OCR receipt scanning flow', async ({ page }) => {
  await page.route('https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.esm.min.js', route => {
    route.fulfill({
      contentType: 'application/javascript',
      body: `export async function createWorker(){return{loadLanguage:async()=>{},initialize:async()=>{},setParameters:async()=>{},recognize:async()=>({data:{lines:[{text:'Orange Juice',confidence:99}]}}),terminate:async()=>{}};}`
    });
  });

  await page.goto('/wp-admin/admin.php?page=pit_ocr_receipt');
  const image = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8HwQACfsD/VM6rQAAAABJRU5ErkJggg==', 'base64');
  await page.setInputFiles('input[type="file"]', { name: 'receipt.png', mimeType: 'image/png', buffer: image });

  const result = page.locator('text=Orange Juice');
  await expect(result).toBeVisible();
  await result.locator('..').locator('button:has-text("Add")').click();
  await expect(result).toHaveCount(0);

  await page.goto('/wp-admin/admin.php?page=pit_items');
  await expect(page.locator('table.wp-list-table')).toContainText('Orange Juice');
});

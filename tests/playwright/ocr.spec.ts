import { test, expect } from './fixtures/wp-fixture';

test('OCR receipt scanning flow', async ({ page }) => {
  await page.route('**/tesseract.esm.min.js', (route) => {
    route.fulfill({
      contentType: 'application/javascript',
      body: `export const createWorker = async () => ({
        recognize: async () => ({ data: { lines: [{ text: 'Ocr Milk', confidence: 90, bbox: {}, words: [] }] } }),
        loadLanguage: async () => {},
        initialize: async () => {},
        setParameters: async () => {},
        terminate: async () => {}
      });`,
    });
  });

  await page.goto('http://localhost:8888/wp-admin/admin.php?page=pit_ocr_receipt');

  const pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8Xw8AAgMBgI2n6hUAAAAASUVORK5CYII=';
  const buffer = Buffer.from(pngBase64, 'base64');
  await page.setInputFiles('input[type="file"]', {
    name: 'receipt.png',
    mimeType: 'image/png',
    buffer,
  });

  await expect(page.getByText('Scanned Items (1)')).toBeVisible();
  await expect(page.getByText('Ocr Milk')).toBeVisible();

  await page.getByRole('button', { name: /^Add$/ }).click();

  await page.goto('http://localhost:8888/wp-admin/admin.php?page=pit_items');
  await expect(page.locator('table')).toContainText('Ocr Milk');
});

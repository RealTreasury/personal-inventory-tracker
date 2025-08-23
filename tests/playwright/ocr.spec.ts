import { test, expect } from './fixtures/wp-fixture';
import fs from 'fs';
import os from 'os';
import path from 'path';

const RECEIPT_PNG_BASE64 = `
iVBORw0KGgoAAAANSUhEUgAAAMgAAABkCAIAAABM5OhcAAADZklEQVR4nO3dv0tqYQDG8de4FBJkRP4DDSFI0hAk9EMH5QQVtPQLgoIiGtoa25sa2hoqaYsiEBoCwzUi
iCAyHIwKmhQagrMU2nsH4XBJqYb7cPHy/Uynxxdx+HKOuOSz1hrgb2v61x8A/yfCggRhQYKwIEFYkCAsSBAWJAgLEoQFCcKCBGFBgrAgQViQICxIEBYkCAsShAUJwoIE
YUGCsCBBWJAgLEgQFiQICxKEBQnCggRhQYKwIEFYkCAsSBAWJAgLEoQFCcKCBGFBgrAgQViQICxIEBYkCAsShAWJ78Py+/1TU1Pen3Nzc36/3xhzd3e3vb1dHdvb2z9d
fJJKpYaGhnp7e8/Ozrzx8fHRcZx4PD42NlYsFusuaFT2O4FAIBKJlMtla+3Hx0c0Gg0EArVnPl38qVQqDQ8PVyqVfD4fCoW8PZFIZLNZa202m11ZWam7oEH9KKyFhYWL
iwtr7fX19fLycm1Gny6KxWJPT08ul6uO+Xz+6OjIWuu6bjAY9N65s7Oz2mu5XO7u7q67oEH96DuW4ziZTMYYk8lkHMf5+vD7+/vMzMzW1lY4HK4uoVBocnLSGHN8fDw+
Pu6djEQiJycnxph0Ol198NUuaFTfphcIBF5eXgYHB621yWTy9fX16zvW0tLS7u5u7fvc39+Hw+FSqeQtDw8PExMT8Xh8c3OzeierXdCgfv0kvo6OjqampufnZ2NMW1vb
Fyff3t5yuZwxZnFx8c/ddd3p6em9vb1gMOiNBwcHh4eHzc3NhUIhnU7XXdCgfvpzw8jIyPr6eiKR+PpYS0vL+fn509PTzs6ON1pr5+fn19bW+vv7q4vrusaYq6ur09NT
Y8z+/v7s7GzdBY3q23ta9TF3c3Pj8/lub29tvSdgX1/fxsaGt5RKpa6ursvLy+qrqVSqtbU1FovFYrHR0VFrbTKZtNYWCoWBgYFoNLq6ulqpVOouaFA+y39YhQC/vEOC
sCBBWJAgLEgQFiQICxKEBQnCggRhQYKwIEFYkCAsSBAWJAgLEoQFCcKCBGFBgrAgQViQICxIEBYkCAsShAUJwoIEYUGCsCBBWJAgLEgQFiQICxKEBQnCggRhQYKwIEFY
kCAsSBAWJAgLEoQFCcKCBGFBgrAgQViQICxIEBYkCAsShAUJwoIEYUHiNwHAOlGwEa8HAAAAAElFTkSuQmCC
`;

test('OCR receipt scanning flow', async ({ adminPage }) => {
  const page = adminPage;
  const receipt = path.join(os.tmpdir(), 'receipt-sample.png');
  fs.writeFileSync(
    receipt,
    Buffer.from(RECEIPT_PNG_BASE64.replace(/\s+/g, ''), 'base64'),
  );

  await page.goto('http://localhost:8888/wp-admin/admin.php?page=pit_ocr_receipt');
  await page.setInputFiles('input[type="file"]', receipt);
  await expect(page.locator('text=Milk')).toBeVisible();
});


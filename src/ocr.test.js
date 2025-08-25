import * as ocr from './ocr.js';

describe('OCR failure handling', () => {
  test('extractItemSuggestions handles module not found error gracefully', async () => {
    // Test the actual error that occurs during testing when tesseract module is not found
    const result = await ocr.extractItemSuggestions(new Blob());
    
    expect(result).toHaveProperty('error', true);
    expect(result).toHaveProperty('items', []);
    expect(result.message).toContain('tesseract.esm.min.js');
  });
});

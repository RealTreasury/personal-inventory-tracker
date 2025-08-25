import * as ocr from './ocr.js';

describe('OCR failure handling', () => {
  test('extractItemSuggestions returns empty array when Tesseract fails to load', async () => {
    jest.spyOn(ocr, 'loadTesseract').mockRejectedValue(new Error('fail'));
    const items = await ocr.extractItemSuggestions(new Blob());
    expect(items).toEqual([]);
  });
});

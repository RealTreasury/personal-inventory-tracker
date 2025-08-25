/**
 * Integration tests for OCR error handling improvements.
 */

import * as ocr from '../src/ocr.js';

// Mock fetch for testing
global.fetch = jest.fn();

// Mock window object
Object.defineProperty(window, 'pitApp', {
  writable: true,
  value: {
    assetUrl: '/test/assets/',
  },
});

describe('OCR Error Handling Integration', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    // Reset console methods
    jest.spyOn(console, 'error').mockImplementation(() => {});
  });

  afterEach(() => {
    console.error.mockRestore();
  });

  test('extractItemSuggestions returns structured error for module loading failure', async () => {
    const result = await ocr.extractItemSuggestions(new Blob());
    
    expect(result).toHaveProperty('error', true);
    expect(result).toHaveProperty('message');
    expect(result).toHaveProperty('items', []);
    expect(typeof result.message).toBe('string');
    expect(result.message.length).toBeGreaterThan(0);
  });

  test('bindOcrToInput handles missing elements gracefully', () => {
    // Should not throw when input is null
    expect(() => {
      ocr.bindOcrToInput(null, () => {});
    }).not.toThrow();
  });

  test('loadTesseract handles invalid asset URL', async () => {
    // Clear the window.pitApp to test fallback
    const originalPitApp = window.pitApp;
    delete window.pitApp;

    let errorThrown = false;
    try {
      await ocr.loadTesseract();
    } catch (error) {
      errorThrown = true;
      // Should handle the error gracefully - any error type is acceptable
      expect(error).toBeDefined();
      expect(error.message).toContain('tesseract.esm.min.js');
    }

    expect(errorThrown).toBe(true);

    // Restore original state
    window.pitApp = originalPitApp;
  });

  test('extractItemSuggestions logs errors properly', async () => {
    await ocr.extractItemSuggestions(new Blob());
    
    expect(console.error).toHaveBeenCalledWith(
      'OCR processing failed:',
      expect.stringContaining('tesseract.esm.min.js')
    );
  });

  test('error structure is consistent and usable by components', async () => {
    const result = await ocr.extractItemSuggestions(new Blob());
    
    // Verify the error structure matches what components expect
    if (result.error) {
      expect(result).toEqual(
        expect.objectContaining({
          error: true,
          message: expect.any(String),
          items: expect.any(Array),
        })
      );
      
      // Items array should be empty on error
      expect(result.items).toHaveLength(0);
      
      // Message should be descriptive
      expect(result.message.length).toBeGreaterThan(10);
    }
  });
});
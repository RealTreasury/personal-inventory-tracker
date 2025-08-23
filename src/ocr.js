import Tesseract from 'tesseract.js';

/**
 * Run OCR on a receipt image and return item suggestions.
 * Only executes in browser environments.
 * @param {File|Blob|string} image - Image blob or URL.
 * @param {number} [minConfidence=60] - Minimum confidence for lines.
 * @returns {Promise<Array<{text: string, confidence: number}>>}
 */
export async function extractItemSuggestions(image, minConfidence = 60) {
  if (typeof window === 'undefined') {
    console.warn('OCR is only supported in browser environments.');
    return [];
  }
  const { data } = await Tesseract.recognize(image, 'eng');
  return data.lines
    .filter(line => line.confidence >= minConfidence)
    .map(line => ({
      text: line.text.trim(),
      confidence: line.confidence,
    }));
}

/**
 * Attach OCR processing to a file input and invoke callback with suggestions.
 * @param {HTMLInputElement} input - File input element.
 * @param {(items: Array<{text: string, confidence: number}>) => void} callback - Called with suggestions.
 * @param {number} [minConfidence=60] - Minimum confidence.
 */
export function bindOcrToInput(input, callback, minConfidence = 60) {
  if (typeof window === 'undefined') {
    console.warn('OCR binding skipped: not in a browser');
    return;
  }
  input.addEventListener('change', async () => {
    const [file] = input.files;
    if (file) {
      const items = await extractItemSuggestions(file, minConfidence);
      callback(items);
    }
  });
}

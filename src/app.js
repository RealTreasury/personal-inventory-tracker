import { extractItemSuggestions, bindOcrToInput } from './ocr.js';

// expose functions for application use
if (typeof window !== 'undefined') {
  window.extractItemSuggestions = extractItemSuggestions;
  window.bindOcrToInput = bindOcrToInput;
}

export { extractItemSuggestions, bindOcrToInput };

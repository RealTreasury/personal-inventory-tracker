import { extractItemSuggestions, bindOcrToInput } from './ocr.js';

// expose functions for admin interface
if (typeof window !== 'undefined') {
  window.adminExtractItemSuggestions = extractItemSuggestions;
  window.adminBindOcrToInput = bindOcrToInput;
}

export { extractItemSuggestions, bindOcrToInput };

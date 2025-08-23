import test from 'node:test';
import assert from 'node:assert/strict';
import * as ocr from '../src/ocr.js';

test('extractItemSuggestions returns empty array outside browser', async () => {
  const result = await ocr.extractItemSuggestions('dummy');
  assert.deepEqual(result, []);
});

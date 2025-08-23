# personal-inventory-tracker

This project bundles client-side OCR using [Tesseract.js](https://github.com/naptha/tesseract.js).

## Building assets

Run `npm run build` to produce the bundled files in `assets/`.

## Usage

The bundled scripts expose helpers on `window` for running OCR on receipt images:

- `extractItemSuggestions(image, minConfidence)`
- `bindOcrToInput(inputElement, callback, minConfidence)`

Both functions operate entirely on the client and filter out low-confidence lines.


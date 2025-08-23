# Coding Guidelines

- Each PHP file in this directory defines exactly one class named `PIT_*` and should be stored in a file named `class-pit-*.php`.
- Follow WordPress coding standards:
  - Use 4-space indentation.
  - Write function names in `snake_case`.
  - Escape all output using functions like `esc_html`, `esc_attr`, etc.
  - Include nonces for all form submissions.
- The file `includes/js/ocr.js` is auto-generated from `src/ocr.js`. Do **not** edit it directly. Modify `src/ocr.js` and run `npm run build` instead.

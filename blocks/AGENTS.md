# Block Development Guidelines

- Each block resides in its own subfolder within `blocks/`. Every block should include:
  - `block.json`
  - `edit.js`
  - `index.js`
  - `style.css` (optional)

## Build

Run `npm run build` whenever block scripts or styles are modified to regenerate build assets.

## Coding Guidelines

- Use WordPress internationalization functions for any translatable text.
- Keep block code free of PHP logic; restrict PHP to server-side files.


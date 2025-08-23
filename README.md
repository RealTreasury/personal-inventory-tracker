# Personal Inventory Tracker

Personal Inventory Tracker is a WordPress plugin that helps you manage household items with OCR-powered receipt scanning and CSV import/export so you always know what you have on hand.

## Features
- Custom post type for inventory items.
- REST API for integrations.
- Client-side OCR (Tesseract.js) to extract items from receipts.
- Import and export inventory via CSV.
- Cron-based reorder reminders.

## Requirements
- WordPress 5.0 or higher.
- PHP 7.4 or higher.
- Modern browser with JavaScript enabled for OCR utilities.
- Node & npm (optional) to rebuild assets.

## Installation (WordPress.com)
1. Download the plugin as a ZIP file from this repository.
2. In your WordPress.com dashboard, go to **Plugins → Upload Plugin**.
3. Upload the ZIP and click **Install Now**.
4. Activate the plugin after the upload completes.
5. Configure settings under **Settings → Personal Inventory**.

## Quick Start
1. Prepare a CSV file with your items in the format below.
2. In the WordPress admin, navigate to Personal Inventory and import the CSV or add items manually.
3. Place one of the following shortcodes on a page to display the inventory interface:
   `[personal_inventory]`, `[pit_enhanced]`, `[pit_dashboard]`, or `[pit_app]` (deprecated).
4. Choose a front-end mode:
   - **Read-only:** visitors can view inventory but cannot modify data.
   - **Write:** authenticated users can update quantities or add items via the REST API.
5. Use the OCR widget to scan receipts and pre-fill item fields.

## Shortcodes
The plugin registers several shortcodes that render the same inventory interface:

- `[personal_inventory]`
- `[pit_enhanced]`
- `[pit_dashboard]`
- `[pit_app]` *(deprecated)*

## CSV Format
Each row represents an inventory item with the following columns:

- `post_title` – item name.
- `qty` – quantity on hand.
- `reorder_threshold` – quantity at which a reorder is triggered.
- `reorder_interval` – number of days between automatic reorders.
- `last_reordered` – Unix timestamp of the most recent reorder.

```csv
post_title,qty,reorder_threshold,reorder_interval,last_reordered
Apples,10,2,30,1700000000
Bananas,5,1,7,1700000000
Milk,1,2,14,1700000000
```

A copy of this sample CSV is available at [`docs/sample.csv`](docs/sample.csv).
This parser supports quoted fields, embedded commas, and both LF and CRLF line endings.

## CLI Commands
Use WP-CLI to manage inventory from the command line.

- Export items to CSV:
  `wp pit inventory export --file=items.csv`
- Import items from CSV:
  `wp pit inventory import items.csv`
- Clear cached data:
  `wp pit cache clear`

## OCR Tips
- Use well-lit, high-contrast photos.
- Crop images to the receipt area before scanning.
- Smaller images process faster but must remain legible.
- Adjust the `minConfidence` parameter in JS helpers for accuracy vs. noise.

## Privacy & Security
- OCR runs entirely in the browser; receipt images are never uploaded to the server.
- Inventory data resides in your WordPress database—apply regular WordPress security best practices.
- Use SSL and limit write mode to trusted users to protect inventory data.

## Building Assets
Run `npm run build` to compile the JavaScript for both the front-end (`assets/app.js`) and admin (`assets/admin.js`) using esbuild.

## FAQ
**Does this plugin require a specific WordPress.com plan?**  
Yes. Uploading custom plugins is available on Business or higher plans.

**Can I use this on self-hosted WordPress?**  
Absolutely. Install it like any other plugin through **Plugins → Add New**.

## License
GPL-2.0+

## Release Notes
### 1.0.0
- Initial release.

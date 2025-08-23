=== Personal Inventory Tracker ===
Contributors: openai
Tags: inventory, ocr, csv
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Manage personal inventory with OCR-powered receipt scanning and CSV import/export.

== Description ==
Personal Inventory Tracker helps households keep track of items using a custom post type and REST API endpoints. The plugin bundles Tesseract.js for client-side OCR so receipts can be scanned in the browser and item lines suggested automatically.

== Features ==
* Custom post type for inventory items.
* REST API for integrations or front-end updates.
* Client-side OCR for extracting items from receipts.
* CSV import and export of inventory data.
* Cron-based reorder reminders.

== Requirements ==
* WordPress 5.0 or higher.
* PHP 7.4 or higher.
* Modern browser with JavaScript enabled for OCR.
* Node & npm (optional) to rebuild assets.

== Installation ==
1. Upload `personal-inventory-tracker` to the `/wp-content/plugins/` directory or install via the Plugins screen.
2. Run `npm run build` if you need to regenerate front-end assets.
3. Activate the plugin through the Plugins screen in WordPress.
4. Configure settings under **Settings → Personal Inventory**.

== Quick Start ==
1. Prepare a CSV file with your inventory (see format below or `docs/sample.csv`).
2. Import the CSV via the Personal Inventory admin page or add items manually.
3. Add the `[personal_inventory]` shortcode to a page.
4. Choose your front-end mode:
   * **Read-only:** inventory is displayed but cannot be changed.
   * **Write:** authenticated users can modify inventory via the REST API.
5. Use the OCR tool to scan receipts and pre-fill item fields.

== CSV Format ==
Each row represents one inventory item. Columns include `post_title`, `qty`, `reorder_threshold`, `reorder_interval`, and `last_reordered` (Unix timestamp).

```
post_title,qty,reorder_threshold,reorder_interval,last_reordered
Apples,10,2,30,1700000000
Bananas,5,1,7,1700000000
Milk,1,2,14,1700000000
```

== OCR Tips ==
* Capture clear, high-contrast images.
* Crop photos to the receipt area.
* Smaller images process faster while staying readable.
* Adjust the `minConfidence` setting for accuracy.

== Privacy and Security ==
* OCR processing happens entirely in the browser; images are not uploaded.
* Inventory data remains in your WordPress database.
* Use SSL and restrict write mode to trusted users.

== Changelog ==
= 1.0.0 =
* Initial release.

# OCR Scanner

Learn how to digitize receipt data using the plugin's OCR scanner.

## Camera capture
1. Open the **OCR Receipt** page in the dashboard.
2. Choose **Use Camera** and allow browser access to your camera.
3. Align the receipt in the frame and press **Capture**. The image is processed locally and suggested items appear below.

## Image upload
1. Open the **OCR Receipt** page.
2. Select **Upload Image** to choose a photo of a receipt from your device.
3. Confirm the selection and let the scanner extract items.

## Settings
- **Language:** Select the language used on the receipt for better recognition.
- **Minimum Confidence:** Adjust the confidence threshold for keeping detected items.
- **Crop and rotate:** Use the preview tools to crop or rotate the image before scanning.

Save your settings and rerun the scan if the results are not accurate.

## Shortcode usage

Use the `[pit_ocr_scanner]` shortcode to add the scanner to any page:

1. In the WordPress admin, go to **Pages → Add New**.
2. Give the page a title like "Receipt OCR Test".
3. Add `[pit_ocr_scanner]` in the content area and publish the page.
4. Visit the page to try scanning receipts from your camera or by uploading an image.

The shortcode automatically loads existing inventory items to help match scanned text.

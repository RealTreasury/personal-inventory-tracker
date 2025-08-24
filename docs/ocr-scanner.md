# OCR Scanner

Learn how to digitize receipt data using the plugin's OCR scanner.

## Front-end shortcode

Display the scanner on any public page with the `[pit_ocr_scanner]` shortcode.

### Create a test page
1. In the WordPress dashboard, go to **Pages → Add New**.
2. Enter a title such as "Receipt Scanner Test".
3. Add the shortcode `[pit_ocr_scanner]` to the content area.
4. Publish the page and open it on the front end to try scanning receipts.

## Camera capture
1. Visit the page where you placed the `[pit_ocr_scanner]` shortcode.
2. Choose **Open Camera** and allow browser access to your camera.
3. Align the receipt in the frame and press **Capture**. The image is processed locally and suggested items appear below.

## Image upload
1. Visit the same page on the front end.
2. Select **Upload Image** to choose a photo of a receipt from your device or take a new photo.
3. Confirm the selection and let the scanner extract items.

## Settings
- **Language:** Select the language used on the receipt for better recognition.
- **Minimum Confidence:** Adjust the confidence threshold for keeping detected items.
- **Crop and rotate:** Use the preview tools to crop or rotate the image before scanning.

Save your settings and rerun the scan if the results are not accurate.

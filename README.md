# Personal Inventory Tracker

Personal Inventory Tracker is a WordPress plugin that helps you manage household items with OCR-powered receipt scanning so you always know what you have on hand.

## Features
- **Inventory Management**: Custom post type for inventory items with categories and quantities
- **OCR Receipt Scanning**: Client-side OCR (Tesseract.js) to extract items from receipts
- **REST API**: Complete API for integrations and front-end applications
- **Analytics Dashboard**: Track usage trends and shopping patterns
- **Reorder Reminders**: Cron-based notifications when items run low
- **Bulk Operations**: Import/export via CSV, batch updates
- **Mobile-Friendly**: Responsive design with camera integration for receipt scanning
- **Customizable**: Multiple shortcodes, configurable settings, extensible architecture

## Requirements
- **WordPress**: 5.0 or higher
- **PHP**: 7.4 or higher (8.0+ recommended)
- **Browser**: Modern browser with JavaScript enabled for OCR functionality
- **Development**: Node.js 16+ and npm 8+ (for building assets)

## Quick Installation

### WordPress.com (Business Plan or Higher)
1. Download the plugin as a ZIP file from this repository
2. In your WordPress.com dashboard, go to **Plugins → Upload Plugin**
3. Upload the ZIP and click **Install Now**
4. Activate the plugin after upload completes
5. Configure settings under **Settings → Personal Inventory**

### Self-Hosted WordPress
1. Download or clone this repository
2. Upload to your `/wp-content/plugins/` directory
3. Activate through **Plugins → Installed Plugins**
4. Configure settings under **Settings → Personal Inventory**

## Developer Setup

### Prerequisites
```bash
# Ensure you have the required versions
node --version  # Should be 16.0.0 or higher
npm --version   # Should be 8.0.0 or higher
php --version   # Should be 7.4 or higher
```

### Development Installation
1. **Clone the repository**:
   ```bash
   git clone https://github.com/RealTreasury/personal-inventory-tracker.git
   cd personal-inventory-tracker
   ```

2. **Install dependencies**:
   ```bash
   npm install
   ```

3. **Build assets**:
   ```bash
   # Development build with source maps
   npm run build:dev
   
   # Production build (minified)
   npm run build
   
   # Watch mode for development
   npm run watch
   ```

4. **Install in WordPress**:
   - Copy the entire directory to `/wp-content/plugins/`
   - Or symlink for development: `ln -s /path/to/repo /wp-content/plugins/personal-inventory-tracker`

### Build Commands
- `npm run build` - Production build (minified, optimized)
- `npm run build:dev` - Development build (source maps, not minified)
- `npm run watch` - Watch mode for active development
- `npm run lint` - Check JavaScript code quality
- `npm run test` - Run Jest unit tests
- `npm run test:e2e` - Run Playwright end-to-end tests

### Architecture Overview

The plugin follows a modern WordPress development approach:

```
personal-inventory-tracker/
├── src/                    # Source files (ES modules, React components)
│   ├── REST/              # REST API endpoints
│   ├── Admin/             # WordPress admin interface
│   ├── components/        # Reusable React components
│   └── *.js,*.jsx        # Frontend modules
├── assets/                # Compiled JavaScript/CSS (auto-generated)
├── includes/              # WordPress-specific PHP classes
├── templates/             # PHP template files
├── docs/                  # Documentation
└── tests/                # Test suites
```

**Key Technologies:**
- **Frontend**: React 18, modern JavaScript (ES2018+)
- **Build System**: esbuild for fast compilation
- **OCR**: Tesseract.js for client-side image processing
- **Charts**: Chart.js for analytics visualization
- **Testing**: Jest (unit), Playwright (e2e)

## Installation (WordPress.com)
1. Download the plugin as a ZIP file from this repository.
2. In your WordPress.com dashboard, go to **Plugins → Upload Plugin**.
3. Upload the ZIP and click **Install Now**.
4. Activate the plugin after the upload completes.
5. Configure settings under **Settings → Personal Inventory**.

## Configuration

### Plugin Settings
Access plugin settings via **Settings → Personal Inventory** in WordPress admin:

| Setting | Description | Default |
|---------|-------------|---------|
| **Default Unit List** | Comma-separated list of measurement units | `piece, box, bottle, bag` |
| **Default Interval** | Days between reorder notifications | `30` |
| **Frontend Read-only** | Disable editing on front-end interfaces | `false` |
| **Public Access** | Allow non-logged-in users to view inventory | `false` |
| **OCR Language** | Language for Tesseract OCR processing | `eng` |
| **OCR Min Confidence** | Minimum confidence threshold for OCR results | `60` |
| **GPT API Key** | OpenAI API key for auto-categorization | *(empty)* |
| **Currency Symbol** | Symbol displayed for price fields | `$` |

### Environment Configuration
You can override settings via WordPress constants in `wp-config.php`:

```php
// Disable public access regardless of admin setting
define('PIT_FORCE_PRIVATE', true);

// Set default OCR confidence
define('PIT_OCR_MIN_CONFIDENCE', 70);

// Enable debug mode
define('PIT_DEBUG', true);
```

## REST API Overview

The plugin provides two API versions for maximum compatibility:

### API v1 (Legacy)
**Base URL**: `/wp-json/pit/v1/`
- `GET /items` - List all inventory items
- `POST /items` - Create new item
- `PUT /items/{id}` - Update existing item
- `DELETE /items/{id}` - Delete item

### API v2 (Enhanced)
**Base URL**: `/wp-json/pit/v2/`

#### Items
- `GET /items` - List items with pagination and filtering
- `POST /items` - Create new item
- `PUT /items/{id}` - Update item
- `DELETE /items/{id}` - Delete item
- `POST /items/batch` - Bulk operations

#### Analytics
- `GET /analytics` - Usage statistics and trends
- `GET /analytics/trends` - Historical trend data

#### Additional Features
- `GET /categories` - List all item categories
- `GET /shopping-list` - Generate shopping list based on low stock
- `POST /ocr/process` - Process OCR results from receipts

**Authentication**: Uses WordPress nonces for security. Include `X-WP-Nonce` header with requests.

### Example API Usage

```javascript
// Fetch all items
const response = await fetch('/wp-json/pit/v2/items', {
    headers: {
        'X-WP-Nonce': pitData.nonce
    }
});
const items = await response.json();

// Create new item
const newItem = await fetch('/wp-json/pit/v2/items', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': pitData.nonce
    },
    body: JSON.stringify({
        title: 'Milk',
        qty: 2,
        category: 'Dairy',
        notes: 'Organic whole milk'
    })
});
```

*For complete API documentation, see [docs/rest-api.md](docs/rest-api.md)*

## Quick Start
1. In the WordPress admin, navigate to Personal Inventory and add items manually.
2. Place one of the following shortcodes on a page to display the inventory interface:
   `[personal_inventory]`, `[pit_enhanced]`, `[pit_dashboard]`, or `[pit_app]` (deprecated).
3. Choose a front-end mode:
   - **Read-only:** visitors can view inventory but cannot modify data.
   - **Write:** authenticated users can update quantities or add items via the REST API.
4. Use the OCR widget to scan receipts and pre-fill item fields.

## Shortcodes & Frontend Display

### Available Shortcodes
The plugin provides several shortcodes for displaying inventory interfaces:

| Shortcode | Description | Attributes |
|-----------|-------------|------------|
| `[personal_inventory]` | Main inventory interface | `view`, `readonly`, `public` |
| `[pit_enhanced]` | Enhanced dashboard with analytics | `view`, `readonly`, `public` |
| `[pit_dashboard]` | Dashboard view with charts | `view`, `readonly`, `public` |
| `[pit_ocr_scanner]` | OCR receipt scanner only | *(none)* |
| `[pit_app]` | Legacy shortcode (deprecated) | `view`, `readonly`, `public` |

### Shortcode Examples

```html
<!-- Basic inventory list -->
[personal_inventory]

<!-- Read-only public inventory -->
[personal_inventory readonly="true" public="true"]

<!-- Enhanced dashboard with analytics -->
[pit_enhanced view="dashboard"]

<!-- OCR scanner for receipt processing -->
[pit_ocr_scanner]
```

### Customization Examples

#### Custom CSS Styling
```css
/* Customize inventory items */
.pit-item {
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 1rem;
    margin: 0.5rem 0;
}

.pit-item--low-stock {
    border-color: #e74c3c;
    background-color: #fdf2f2;
}

/* Style the OCR scanner */
.pit-ocr-scanner {
    max-width: 600px;
    margin: 0 auto;
}
```

#### JavaScript Integration
```javascript
// Listen for inventory updates
document.addEventListener('pit:item-updated', function(event) {
    console.log('Item updated:', event.detail);
    // Refresh your custom UI
});

// Access OCR functionality
if (window.PITOcr) {
    PITOcr.extractItemSuggestions(imageData).then(suggestions => {
        console.log('OCR suggestions:', suggestions);
    });
}
```

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

## OCR Receipt Scanning

### Best Practices
- **Lighting**: Use well-lit, high-contrast photos for better recognition
- **Cropping**: Crop images to focus on the receipt area before scanning
- **Size**: Smaller images process faster but must remain legible
- **Quality**: Higher resolution images provide better OCR accuracy

### Configuration
- **Language**: Set the OCR language in plugin settings (default: English)
- **Confidence**: Adjust `minConfidence` parameter for accuracy vs. noise (default: 60)
- **Processing**: All OCR runs client-side; no data is sent to external servers

### Supported Formats
- **Image Types**: JPEG, PNG, WebP
- **Languages**: Supports all Tesseract.js languages (eng, fra, deu, spa, etc.)

## Privacy & Security

### Data Protection
- **OCR Processing**: Runs entirely in the browser; receipt images are never uploaded to servers
- **Local Storage**: Inventory data resides in your WordPress database
- **No External APIs**: Unless GPT integration is enabled, no data leaves your server

### Security Best Practices
- **SSL/HTTPS**: Always use SSL certificates for production sites
- **User Permissions**: Limit write access to trusted users only
- **Regular Backups**: Back up your WordPress database regularly
- **Updates**: Keep WordPress, PHP, and the plugin updated

### Access Control
- **Read Access**: Configure public access or restrict to logged-in users
- **Write Access**: Requires specific user capabilities (`manage_inventory_items`)
- **API Security**: All REST API endpoints require proper nonces and permissions

## Building & Development

### Asset Compilation
The plugin uses esbuild for fast JavaScript compilation:

```bash
# Development build with source maps
npm run build:dev

# Production build (minified)
npm run build

# Watch mode for active development
npm run watch
```

### Generated Files
**Do not edit these files directly** - they are auto-generated:
- `assets/*.js` - Compiled JavaScript bundles
- `assets/*.css` - Compiled stylesheets
- `includes/js/ocr.js` - Compiled OCR module

### Code Structure
- **Source Files**: Edit files in `src/` directory
- **React Components**: Located in `src/components/`
- **PHP Classes**: Located in `src/` with proper namespacing
- **Templates**: PHP templates in `templates/` directory

## Testing

### Running Tests
```bash
# Unit tests (Jest)
npm run test

# Watch mode for test development
npm run test:watch

# Coverage report
npm run test:coverage

# End-to-end tests (Playwright)
npm run test:e2e
```

### Test Environment
End-to-end tests use WordPress environment via `wp-env`:

```bash
# Start WordPress test environment
npx wp-env start

# Run Playwright tests
npx playwright test

# Stop test environment
npx wp-env stop
```

## Troubleshooting

### Common Issues

#### OCR Not Working
**Problem**: OCR scanner shows errors or doesn't process images

**Solutions**:
1. Check browser console for JavaScript errors
2. Ensure Tesseract.js files are properly loaded (`assets/tesseract/`)
3. Try different image formats (PNG often works better than JPEG)
4. Reduce image size if browser runs out of memory
5. Check OCR language setting matches receipt language

#### Plugin Assets Not Loading
**Problem**: Styles or JavaScript not working correctly

**Solutions**:
1. Run `npm run build` to regenerate assets
2. Check file permissions on `assets/` directory
3. Clear WordPress cache (if using caching plugins)
4. Check browser developer tools for 404 errors

#### REST API Errors
**Problem**: API endpoints returning 403 or 500 errors

**Solutions**:
1. Verify user has proper permissions (`manage_inventory_items`)
2. Check nonce is included in `X-WP-Nonce` header
3. Ensure pretty permalinks are enabled in WordPress
4. Check PHP error logs for detailed error messages

#### Build Failures
**Problem**: `npm run build` fails with errors

**Solutions**:
1. Delete `node_modules/` and run `npm install` again
2. Ensure Node.js version is 16.0.0 or higher
3. Check for syntax errors in source files
4. Clear npm cache: `npm cache clean --force`

### Getting Help
1. **Check the logs**: WordPress debug logs and browser console
2. **Search issues**: Check existing GitHub issues for similar problems
3. **Create an issue**: Provide detailed error messages and system info
4. **Documentation**: Review [docs/](docs/) folder for detailed guides

## CLI Commands

Use WP-CLI to manage inventory from the command line:

```bash
# Clear cached data
wp pit cache clear

# Import items from CSV
wp pit import --file=inventory.csv

# Export current inventory
wp pit export --format=csv > inventory-backup.csv

# Generate sample data for testing
wp pit generate --count=50

# Check plugin status
wp pit status
```

## FAQ

### Installation & Setup

**Q: Does this plugin require a specific WordPress.com plan?**  
A: Yes. Uploading custom plugins requires a Business plan or higher on WordPress.com.

**Q: Can I use this on self-hosted WordPress?**  
A: Absolutely. Install it like any other plugin through **Plugins → Add New** or upload manually.

**Q: What PHP version do I need?**  
A: PHP 7.4 minimum, but PHP 8.0+ is recommended for better performance.

### Functionality

**Q: How does OCR work? Is my data sent anywhere?**  
A: OCR processing happens entirely in your browser using Tesseract.js. No images or data are sent to external servers.

**Q: Can I customize the inventory fields?**  
A: The plugin provides standard fields (title, quantity, category, notes). Custom fields can be added via hooks and filters.

**Q: How do I backup my inventory data?**  
A: Use the CSV export feature, or backup your WordPress database. The plugin stores data in custom post types.

### Development

**Q: How do I modify the front-end interface?**  
A: Edit files in `src/components/`, then run `npm run build`. The interface uses React components.

**Q: Can I add custom API endpoints?**  
A: Yes, use WordPress's `register_rest_route()` function and follow the plugin's namespace conventions.

**Q: How do I contribute to the plugin?**  
A: Fork the repository, make your changes, add tests, and submit a pull request.

### Troubleshooting

**Q: OCR isn't working on mobile devices**  
A: Ensure the device has sufficient memory and try reducing image size. Some older devices may struggle with large images.

**Q: The plugin breaks my theme**  
A: Check for JavaScript conflicts in browser console. The plugin uses modern JavaScript that may conflict with older themes.

**Q: Import/export isn't working**  
A: Verify file permissions and that your CSV follows the correct format (see CSV Format section above).

## Contributing

We welcome contributions! Here's how to get started:

1. **Fork** the repository
2. **Create** a feature branch: `git checkout -b feature/amazing-feature`
3. **Install** dependencies: `npm install`
4. **Make** your changes in `src/` directory
5. **Build** assets: `npm run build`
6. **Test** your changes: `npm run test`
7. **Commit** your changes: `git commit -m 'Add amazing feature'`
8. **Push** to your branch: `git push origin feature/amazing-feature`
9. **Submit** a pull request

### Development Guidelines
- Follow WordPress coding standards for PHP
- Use ESLint configuration for JavaScript
- Add tests for new functionality
- Update documentation for new features
- Ensure backward compatibility

## Support

- **Documentation**: Check the [docs/](docs/) directory
- **Issues**: Report bugs on [GitHub Issues](https://github.com/RealTreasury/personal-inventory-tracker/issues)
- **Discussions**: Use [GitHub Discussions](https://github.com/RealTreasury/personal-inventory-tracker/discussions) for questions

## License
GPL-2.0+

## Release Notes
### 1.0.0
- Initial release.

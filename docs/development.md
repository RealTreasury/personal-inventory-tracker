# Development Guide

This guide covers advanced development topics for Personal Inventory Tracker, including customization, extending functionality, and contributing to the project.

## Development Environment Setup

### Prerequisites

Ensure you have the following installed:

```bash
# Check versions
node --version   # >= 16.0.0
npm --version    # >= 8.0.0
php --version    # >= 7.4 (8.0+ recommended)
composer --version  # Latest version

# WordPress development (optional)
wp --version     # WP-CLI for command line operations
```

### Local WordPress Setup

#### Option 1: Using @wordpress/env (Recommended)

```bash
# Install WordPress environment
npm install -g @wordpress/env

# Start WordPress instance
npx wp-env start

# Access your site
# Frontend: http://localhost:8888
# Admin: http://localhost:8888/wp-admin (admin/password)
```

#### Option 2: Local Development Stack

Set up LAMP/XAMPP/MAMP or use Docker:

```bash
# Docker Compose example
version: '3.8'
services:
  wordpress:
    image: wordpress:latest
    ports:
      - "8080:80"
    environment:
      WORDPRESS_DB_HOST: db
      WORDPRESS_DB_USER: wordpress
      WORDPRESS_DB_PASSWORD: password
      WORDPRESS_DB_NAME: wordpress
    volumes:
      - ./:/var/www/html/wp-content/plugins/personal-inventory-tracker

  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: wordpress
      MYSQL_USER: wordpress
      MYSQL_PASSWORD: password
      MYSQL_ROOT_PASSWORD: password
```

### Plugin Development Setup

1. **Clone and setup the plugin**:
   ```bash
   git clone https://github.com/RealTreasury/personal-inventory-tracker.git
   cd personal-inventory-tracker
   npm install
   composer install
   ```

2. **Development workflow**:
   ```bash
   # Watch mode for active development
   npm run watch
   
   # In another terminal, run tests
   npm run test:watch
   ```

3. **Link to WordPress**:
   ```bash
   # Symlink for development
   ln -s $(pwd) /path/to/wordpress/wp-content/plugins/personal-inventory-tracker
   ```

## Architecture Overview

### Directory Structure

```
personal-inventory-tracker/
├── src/                        # Source files (edit these)
│   ├── components/            # React components
│   │   ├── InventoryList.jsx
│   │   ├── ItemForm.jsx
│   │   └── OCRScanner.jsx
│   ├── REST/                  # REST API classes
│   │   └── Rest_Api.php
│   ├── Admin/                 # WordPress admin classes
│   ├── Services/              # Business logic
│   ├── admin.js              # Admin JavaScript
│   ├── frontend-app.jsx      # Frontend React app
│   └── ocr.js               # OCR functionality
├── assets/                    # Compiled files (auto-generated)
│   ├── app.js               # Main frontend bundle
│   ├── admin.js             # Admin interface
│   └── *.css               # Compiled stylesheets
├── includes/                  # WordPress integration
│   ├── class-pit-*.php      # WordPress-specific classes
│   └── js/                  # Legacy JavaScript
├── templates/                 # PHP template files
├── tests/                     # Test suites
├── docs/                      # Documentation
└── build.js                  # Build configuration
```

### Build System

The plugin uses **esbuild** for fast compilation:

```javascript
// build.js configuration highlights
const builds = [
  {
    entryPoints: { app: 'src/frontend-app.jsx' },
    outdir: 'assets',
    format: 'esm',          // ES modules
    splitting: true,        # Code splitting
    loader: { '.jsx': 'jsx' }
  },
  {
    entryPoints: ['src/admin.js'],
    outfile: 'assets/admin.js',
    globalName: 'PITAdmin'  // Global variable
  }
];
```

### Data Flow

```
Frontend (React) → REST API → PHP Backend → WordPress Database
     ↓                 ↓           ↓              ↓
   User Actions    Validation   Business     Post Meta &
   OCR Processing  Permissions   Logic       Custom Tables
```

## Customization Guide

### Adding Custom Fields

#### 1. Extend the Database Schema

```php
// In your theme or custom plugin
add_action('pit_extend_schema', function() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'pit_items_meta';
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        item_id int(11) NOT NULL,
        meta_key varchar(255) NOT NULL,
        meta_value longtext,
        PRIMARY KEY (id),
        KEY item_id (item_id),
        KEY meta_key (meta_key)
    ) {$wpdb->get_charset_collate()};";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});
```

#### 2. Add REST API Fields

```php
// Add custom fields to REST API
add_action('rest_api_init', function() {
    register_rest_field('pit_item', 'expiry_date', [
        'get_callback' => function($post) {
            return get_post_meta($post['id'], 'expiry_date', true);
        },
        'update_callback' => function($value, $post) {
            return update_post_meta($post->ID, 'expiry_date', $value);
        },
        'schema' => [
            'type' => 'string',
            'format' => 'date',
            'description' => 'Item expiry date'
        ]
    ]);
});
```

#### 3. Update Frontend Components

```jsx
// src/components/ItemForm.jsx
const ItemForm = ({ item, onSave }) => {
    const [formData, setFormData] = useState({
        title: item?.title || '',
        qty: item?.qty || 1,
        expiry_date: item?.expiry_date || '',  // New field
        ...
    });

    return (
        <form onSubmit={handleSubmit}>
            {/* Existing fields */}
            
            <div className="field-group">
                <label htmlFor="expiry_date">Expiry Date</label>
                <input
                    type="date"
                    id="expiry_date"
                    value={formData.expiry_date}
                    onChange={(e) => setFormData(prev => ({
                        ...prev,
                        expiry_date: e.target.value
                    }))}
                />
            </div>
            
            <button type="submit">Save Item</button>
        </form>
    );
};
```

### Custom React Components

#### Creating a Custom Component

```jsx
// src/components/ExpiryTracker.jsx
import React, { useState, useEffect } from 'react';

const ExpiryTracker = () => {
    const [expiringItems, setExpiringItems] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        fetchExpiringItems();
    }, []);

    const fetchExpiringItems = async () => {
        try {
            const response = await fetch('/wp-json/pit/v2/items?expiring_soon=true', {
                headers: {
                    'X-WP-Nonce': pitData.nonce
                }
            });
            const data = await response.json();
            setExpiringItems(data.items);
        } catch (error) {
            console.error('Failed to fetch expiring items:', error);
        } finally {
            setLoading(false);
        }
    };

    if (loading) {
        return <div className="pit-loading">Loading expiring items...</div>;
    }

    return (
        <div className="expiry-tracker">
            <h3>Items Expiring Soon</h3>
            {expiringItems.length === 0 ? (
                <p>No items expiring soon!</p>
            ) : (
                <ul className="expiry-list">
                    {expiringItems.map(item => (
                        <li key={item.id} className="expiry-item">
                            <span className="item-name">{item.title}</span>
                            <span className="expiry-date">
                                Expires: {new Date(item.expiry_date).toLocaleDateString()}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
};

export default ExpiryTracker;
```

#### Register the Component

```jsx
// src/frontend-app.jsx
import ExpiryTracker from './components/ExpiryTracker.jsx';

// Add to your main app component
const InventoryApp = () => {
    return (
        <div className="pit-app">
            <ExpiryTracker />
            {/* Other components */}
        </div>
    );
};
```

### Custom Shortcodes

```php
// Add custom shortcode
add_shortcode('pit_expiry_tracker', function($atts) {
    $atts = shortcode_atts([
        'days_ahead' => 7,
        'show_expired' => false
    ], $atts);
    
    // Enqueue the component
    wp_enqueue_script('pit-expiry-tracker', 
        PIT_PLUGIN_URL . 'assets/expiry-tracker.js',
        ['pit-frontend'], PIT_VERSION, true
    );
    
    wp_localize_script('pit-expiry-tracker', 'expiryTrackerData', [
        'daysAhead' => (int) $atts['days_ahead'],
        'showExpired' => (bool) $atts['show_expired'],
        'nonce' => wp_create_nonce('wp_rest')
    ]);
    
    return '<div id="pit-expiry-tracker"></div>';
});
```

### Extending the REST API

#### Custom Endpoints

```php
// Add custom REST endpoint
add_action('rest_api_init', function() {
    register_rest_route('pit/v2', '/analytics/expiry', [
        'methods' => 'GET',
        'callback' => 'pit_get_expiry_analytics',
        'permission_callback' => function() {
            return current_user_can('view_inventory');
        }
    ]);
});

function pit_get_expiry_analytics($request) {
    $days_ahead = $request->get_param('days') ?: 30;
    
    $items = get_posts([
        'post_type' => 'pit_item',
        'posts_per_page' => -1,
        'meta_query' => [
            [
                'key' => 'expiry_date',
                'value' => date('Y-m-d', strtotime("+{$days_ahead} days")),
                'compare' => '<='
            ]
        ]
    ]);
    
    $analytics = [
        'total_expiring' => count($items),
        'expired_count' => 0,
        'expiring_soon' => 0,
        'categories' => []
    ];
    
    foreach ($items as $item) {
        $expiry_date = get_post_meta($item->ID, 'expiry_date', true);
        $category = get_post_meta($item->ID, 'category', true) ?: 'Uncategorized';
        
        if (strtotime($expiry_date) < time()) {
            $analytics['expired_count']++;
        } else {
            $analytics['expiring_soon']++;
        }
        
        if (!isset($analytics['categories'][$category])) {
            $analytics['categories'][$category] = 0;
        }
        $analytics['categories'][$category]++;
    }
    
    return rest_ensure_response($analytics);
}
```

### Hooks and Filters

#### Available Hooks

```php
// Item lifecycle hooks
do_action('pit_item_created', $item_id, $item_data);
do_action('pit_item_updated', $item_id, $old_data, $new_data);
do_action('pit_item_deleted', $item_id, $item_data);

// OCR processing hooks
apply_filters('pit_ocr_suggestions', $suggestions, $image_data);
apply_filters('pit_ocr_confidence_threshold', 60);

// API response filters
apply_filters('pit_api_item_response', $item_data, $item_id);
apply_filters('pit_api_items_query', $query_args, $request);
```

#### Example Usage

```php
// Automatically categorize items using AI
add_filter('pit_item_created', function($item_id, $item_data) {
    if (empty($item_data['category'])) {
        $category = ai_suggest_category($item_data['title']);
        if ($category) {
            update_post_meta($item_id, 'category', $category);
        }
    }
}, 10, 2);

// Enhance OCR suggestions
add_filter('pit_ocr_suggestions', function($suggestions, $image_data) {
    foreach ($suggestions as &$suggestion) {
        // Add price extraction
        if (preg_match('/\$(\d+\.\d{2})/', $suggestion['text'], $matches)) {
            $suggestion['price'] = floatval($matches[1]);
        }
        
        // Improve quantity detection
        if (preg_match('/(\d+)x?\s*(.+)/', $suggestion['text'], $matches)) {
            $suggestion['qty'] = intval($matches[1]);
            $suggestion['title'] = trim($matches[2]);
        }
    }
    
    return $suggestions;
}, 10, 2);
```

## Testing

### Unit Tests (Jest)

```javascript
// tests/components/ItemForm.test.js
import React from 'react';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';
import ItemForm from '../../src/components/ItemForm.jsx';

describe('ItemForm', () => {
    const mockOnSave = jest.fn();

    beforeEach(() => {
        mockOnSave.mockClear();
    });

    test('renders form fields correctly', () => {
        render(<ItemForm onSave={mockOnSave} />);
        
        expect(screen.getByLabelText(/item name/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/quantity/i)).toBeInTheDocument();
        expect(screen.getByLabelText(/category/i)).toBeInTheDocument();
    });

    test('submits form with correct data', async () => {
        render(<ItemForm onSave={mockOnSave} />);
        
        fireEvent.change(screen.getByLabelText(/item name/i), {
            target: { value: 'Test Item' }
        });
        fireEvent.change(screen.getByLabelText(/quantity/i), {
            target: { value: '5' }
        });
        
        fireEvent.click(screen.getByRole('button', { name: /save/i }));
        
        await waitFor(() => {
            expect(mockOnSave).toHaveBeenCalledWith({
                title: 'Test Item',
                qty: 5,
                category: '',
                notes: ''
            });
        });
    });
});
```

### PHP Unit Tests (PHPUnit)

```php
// tests/RestApiTest.php
<?php
class RestApiTest extends WP_UnitTestCase {

    private $api;
    private $user_id;

    public function setUp(): void {
        parent::setUp();
        
        $this->api = new \RealTreasury\Inventory\REST\Rest_Api();
        $this->user_id = $this->factory->user->create([
            'role' => 'administrator'
        ]);
        wp_set_current_user($this->user_id);
    }

    public function test_create_item() {
        $request = new WP_REST_Request('POST', '/pit/v2/items');
        $request->set_body_params([
            'title' => 'Test Item',
            'qty' => 5,
            'category' => 'Test Category'
        ]);

        $response = $this->api->create_item($request);
        
        $this->assertInstanceOf('WP_REST_Response', $response);
        $this->assertEquals(201, $response->get_status());
        
        $data = $response->get_data();
        $this->assertEquals('Test Item', $data['title']);
        $this->assertEquals(5, $data['qty']);
    }

    public function test_unauthorized_access() {
        wp_set_current_user(0); // Not logged in
        
        $request = new WP_REST_Request('POST', '/pit/v2/items');
        $request->set_body_params(['title' => 'Test']);

        $response = $this->api->create_item($request);
        
        $this->assertWPError($response);
        $this->assertEquals('rest_forbidden', $response->get_error_code());
    }
}
```

### End-to-End Tests (Playwright)

```javascript
// tests/e2e/inventory-management.spec.js
const { test, expect } = require('@playwright/test');

test.describe('Inventory Management', () => {
    test.beforeEach(async ({ page }) => {
        // Login as admin
        await page.goto('/wp-admin');
        await page.fill('#user_login', 'admin');
        await page.fill('#user_pass', 'password');
        await page.click('#wp-submit');
    });

    test('should create new inventory item', async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=personal-inventory');
        
        await page.click('text=Add New Item');
        await page.fill('[name="title"]', 'Test Item');
        await page.fill('[name="qty"]', '10');
        await page.selectOption('[name="category"]', 'Test Category');
        
        await page.click('text=Save Item');
        
        await expect(page.locator('text=Item saved successfully')).toBeVisible();
        await expect(page.locator('text=Test Item')).toBeVisible();
    });

    test('should scan receipt with OCR', async ({ page }) => {
        await page.goto('/test-page-with-ocr-shortcode');
        
        // Upload test receipt image
        const fileInput = page.locator('input[type="file"]');
        await fileInput.setInputFiles('./tests/fixtures/test-receipt.png');
        
        await page.click('text=Process Receipt');
        
        // Wait for OCR processing
        await expect(page.locator('text=Processing...')).toBeVisible();
        await expect(page.locator('text=Processing...')).not.toBeVisible({
            timeout: 30000
        });
        
        // Check that items were detected
        await expect(page.locator('.ocr-suggestion')).toHaveCount.greaterThan(0);
    });
});
```

## Performance Optimization

### Frontend Optimization

1. **Code Splitting**: The build system automatically splits code
2. **Lazy Loading**: Use React.lazy for heavy components
3. **Memoization**: Use React.memo and useMemo appropriately

```jsx
// Lazy load heavy components
const AnalyticsChart = React.lazy(() => import('./AnalyticsChart.jsx'));

const Dashboard = () => {
    return (
        <Suspense fallback={<div>Loading chart...</div>}>
            <AnalyticsChart />
        </Suspense>
    );
};
```

### Backend Optimization

1. **Database Queries**: Use proper indexing and efficient queries
2. **Caching**: Implement transient caching for expensive operations
3. **Pagination**: Always paginate large result sets

```php
// Efficient query with caching
function get_low_stock_items($limit = 20) {
    $cache_key = 'pit_low_stock_' . $limit;
    $items = get_transient($cache_key);
    
    if (false === $items) {
        global $wpdb;
        
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, 
                   CAST(qty.meta_value AS UNSIGNED) as qty,
                   CAST(threshold.meta_value AS UNSIGNED) as threshold
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} qty ON p.ID = qty.post_id AND qty.meta_key = 'qty'
            LEFT JOIN {$wpdb->postmeta} threshold ON p.ID = threshold.post_id AND threshold.meta_key = 'reorder_threshold'
            WHERE p.post_type = 'pit_item'
            AND p.post_status = 'publish'
            AND CAST(qty.meta_value AS UNSIGNED) <= CAST(threshold.meta_value AS UNSIGNED)
            ORDER BY qty.meta_value ASC
            LIMIT %d
        ", $limit));
        
        set_transient($cache_key, $items, HOUR_IN_SECONDS);
    }
    
    return $items;
}

// Clear cache when items are updated
add_action('pit_item_updated', function() {
    delete_transient('pit_low_stock_20');
});
```

## Security Considerations

### Input Validation

```php
// Always validate and sanitize input
function validate_item_data($data) {
    $errors = [];
    
    // Required fields
    if (empty($data['title'])) {
        $errors[] = 'Item title is required';
    }
    
    // Validate quantity
    if (isset($data['qty']) && (!is_numeric($data['qty']) || $data['qty'] < 0)) {
        $errors[] = 'Quantity must be a positive number';
    }
    
    // Sanitize strings
    $data['title'] = sanitize_text_field($data['title']);
    $data['category'] = sanitize_text_field($data['category']);
    $data['notes'] = sanitize_textarea_field($data['notes']);
    
    return empty($errors) ? $data : new WP_Error('validation_failed', 'Validation failed', $errors);
}
```

### Permission Checks

```php
// Always check permissions
function check_item_permissions($action, $item_id = null) {
    switch ($action) {
        case 'read':
            return current_user_can('view_inventory') || 
                   !empty(get_option('pit_public_access'));
                   
        case 'create':
        case 'update':
        case 'delete':
            return current_user_can('manage_inventory_items');
            
        default:
            return false;
    }
}
```

### Nonce Verification

```javascript
// Always include nonces in API requests
const apiRequest = async (endpoint, options = {}) => {
    const headers = {
        'X-WP-Nonce': pitData.nonce,
        'Content-Type': 'application/json',
        ...options.headers
    };
    
    const response = await fetch(`/wp-json/pit/v2${endpoint}`, {
        ...options,
        headers
    });
    
    if (!response.ok) {
        throw new Error(`API Error: ${response.status}`);
    }
    
    return response.json();
};
```

## Deployment

### Production Build

```bash
# Clean previous builds
npm run clean

# Install production dependencies
npm ci --production

# Build for production
npm run build

# Validate build
npm run validate
```

### WordPress Plugin Deployment

1. **Version Update**: Update version in plugin header and package.json
2. **Build Assets**: Run production build
3. **Test**: Run full test suite
4. **Package**: Create distribution ZIP excluding development files

```bash
# Create distribution package
zip -r personal-inventory-tracker.zip . \
    -x "node_modules/*" "tests/*" "*.git*" \
    "src/*" "*.log" "composer.lock"
```

## Contributing

### Code Standards

- **PHP**: Follow WordPress Coding Standards
- **JavaScript**: Use ESLint configuration provided
- **React**: Follow React best practices
- **CSS**: Use BEM methodology for class naming

### Pull Request Process

1. Fork the repository
2. Create feature branch: `git checkout -b feature/amazing-feature`
3. Make changes in `src/` directory
4. Add tests for new functionality
5. Run test suite: `npm run test`
6. Build assets: `npm run build`
7. Commit changes: `git commit -m 'Add amazing feature'`
8. Push to branch: `git push origin feature/amazing-feature`
9. Submit pull request

### Development Guidelines

- Keep functions small and focused
- Write comprehensive tests
- Document complex logic
- Use semantic commit messages
- Ensure backward compatibility

For questions or support, check the [main documentation](../README.md) or open an issue on GitHub.
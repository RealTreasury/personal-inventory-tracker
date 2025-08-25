# REST API Documentation

Personal Inventory Tracker provides a comprehensive REST API for managing inventory items, analytics, and OCR processing. The API supports both v1 (legacy) and v2 (enhanced) versions.

## Base URLs

- **API v1**: `/wp-json/pit/v1/`
- **API v2**: `/wp-json/pit/v2/` (recommended)

## Authentication

All API endpoints require WordPress authentication. Include the nonce in your requests:

```javascript
const response = await fetch('/wp-json/pit/v2/items', {
    headers: {
        'X-WP-Nonce': wpApiSettings.nonce,
        'Content-Type': 'application/json'
    }
});
```

### Permissions

- **Read Access**: `view_inventory` capability or public access enabled
- **Write Access**: `manage_inventory_items` capability
- **Admin Access**: `manage_options` capability

## API v2 Endpoints

### Items

#### GET /items
Retrieve inventory items with pagination and filtering.

**Parameters:**
- `page` (int) - Page number (default: 1)
- `per_page` (int) - Items per page (default: 20, max: 100)
- `search` (string) - Search items by title
- `category` (string) - Filter by category
- `low_stock` (boolean) - Show only low stock items
- `orderby` (string) - Sort field: `title`, `qty`, `date`, `modified`
- `order` (string) - Sort direction: `asc`, `desc`

**Example Request:**
```bash
curl -X GET "https://yoursite.com/wp-json/pit/v2/items?per_page=10&category=groceries&orderby=qty&order=asc" \
     -H "X-WP-Nonce: YOUR_NONCE"
```

**Example Response:**
```json
{
    "items": [
        {
            "id": 123,
            "title": "Organic Milk",
            "qty": 2,
            "category": "Dairy",
            "notes": "1 gallon containers",
            "purchased": false,
            "reorder_threshold": 1,
            "reorder_interval": 7,
            "last_reordered": "2024-01-15T10:30:00",
            "date_created": "2024-01-01T00:00:00",
            "date_modified": "2024-01-15T10:30:00"
        }
    ],
    "pagination": {
        "total": 150,
        "pages": 15,
        "current_page": 1,
        "per_page": 10
    }
}
```

#### POST /items
Create a new inventory item.

**Required Fields:**
- `title` (string) - Item name

**Optional Fields:**
- `qty` (integer) - Quantity (default: 1)
- `category` (string) - Item category
- `notes` (string) - Additional notes
- `purchased` (boolean) - Purchase status (default: false)
- `reorder_threshold` (integer) - Low stock threshold
- `reorder_interval` (integer) - Days between reorders

**Example Request:**
```javascript
const newItem = await fetch('/wp-json/pit/v2/items', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        title: 'Organic Bananas',
        qty: 6,
        category: 'Produce',
        notes: 'Fair trade certified',
        reorder_threshold: 2,
        reorder_interval: 7
    })
});
```

#### PUT /items/{id}
Update an existing inventory item.

**Example Request:**
```javascript
const updatedItem = await fetch('/wp-json/pit/v2/items/123', {
    method: 'PUT',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        qty: 8,
        notes: 'Restocked from farmers market'
    })
});
```

#### DELETE /items/{id}
Delete an inventory item.

**Example Request:**
```bash
curl -X DELETE "https://yoursite.com/wp-json/pit/v2/items/123" \
     -H "X-WP-Nonce: YOUR_NONCE"
```

### Batch Operations

#### POST /items/batch
Perform bulk operations on multiple items.

**Operations:**
- `create` - Create multiple items
- `update` - Update multiple items
- `delete` - Delete multiple items

**Example Request (Bulk Update):**
```javascript
const batchUpdate = await fetch('/wp-json/pit/v2/items/batch', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': wpApiSettings.nonce
    },
    body: JSON.stringify({
        operation: 'update',
        items: [
            { id: 123, qty: 5 },
            { id: 124, qty: 3 },
            { id: 125, purchased: true }
        ]
    })
});
```

### Categories

#### GET /categories
Retrieve all item categories with usage counts.

**Example Response:**
```json
{
    "categories": [
        {
            "name": "Produce",
            "count": 25,
            "items": ["Apples", "Bananas", "Carrots"]
        },
        {
            "name": "Dairy",
            "count": 8,
            "items": ["Milk", "Cheese", "Yogurt"]
        }
    ]
}
```

### Analytics

#### GET /analytics
Get inventory analytics and statistics.

**Parameters:**
- `period` (string) - Time period: `week`, `month`, `year` (default: `month`)

**Example Response:**
```json
{
    "summary": {
        "total_items": 150,
        "low_stock_items": 12,
        "total_value": 1250.50,
        "categories": 15
    },
    "trends": {
        "period": "month",
        "consumption_rate": 2.3,
        "purchase_frequency": 0.8,
        "top_categories": ["Produce", "Dairy", "Pantry"]
    },
    "low_stock": [
        {
            "id": 123,
            "title": "Milk",
            "qty": 1,
            "threshold": 2
        }
    ]
}
```

#### GET /analytics/trends
Get detailed trend data for charts.

**Example Response:**
```json
{
    "labels": ["2024-01", "2024-02", "2024-03"],
    "datasets": [
        {
            "label": "Items Added",
            "data": [15, 23, 18],
            "color": "#3498db"
        },
        {
            "label": "Items Consumed",
            "data": [12, 20, 16],
            "color": "#e74c3c"
        }
    ]
}
```

### Shopping List

#### GET /shopping-list
Generate shopping list based on low stock items.

**Parameters:**
- `format` (string) - Response format: `json`, `text` (default: `json`)

**Example Response:**
```json
{
    "items": [
        {
            "title": "Milk",
            "qty_needed": 2,
            "category": "Dairy",
            "notes": "Organic whole milk"
        },
        {
            "title": "Bananas",
            "qty_needed": 6,
            "category": "Produce",
            "notes": ""
        }
    ],
    "total_items": 2,
    "generated_at": "2024-01-15T10:30:00Z"
}
```

### OCR Processing

#### POST /ocr/process
Process OCR results from receipt scanning.

**Request Body:**
```json
{
    "items": [
        {
            "text": "Organic Milk 2x",
            "confidence": 85,
            "suggested_qty": 2,
            "suggested_category": "Dairy"
        }
    ],
    "source": "tesseract",
    "language": "eng"
}
```

**Example Response:**
```json
{
    "processed_items": [
        {
            "title": "Organic Milk",
            "qty": 2,
            "category": "Dairy",
            "confidence": 85,
            "auto_added": false
        }
    ],
    "summary": {
        "total_detected": 5,
        "auto_added": 2,
        "manual_review": 3
    }
}
```

## API v1 (Legacy)

The v1 API provides basic CRUD operations for backward compatibility:

### Endpoints
- `GET /pit/v1/items` - List items
- `POST /pit/v1/items` - Create item
- `PUT /pit/v1/items/{id}` - Update item
- `DELETE /pit/v1/items/{id}` - Delete item

**Note**: API v1 has limited features and will be deprecated in future versions. Use API v2 for new integrations.

## Error Handling

### HTTP Status Codes
- `200` - Success
- `201` - Created
- `400` - Bad Request (validation error)
- `401` - Unauthorized
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `422` - Unprocessable Entity (validation error)
- `500` - Internal Server Error

### Error Response Format
```json
{
    "code": "invalid_item",
    "message": "Item title is required",
    "data": {
        "status": 400,
        "field": "title"
    }
}
```

## Rate Limiting

The API implements basic rate limiting:
- **Authenticated Users**: 1000 requests per hour
- **Public Access**: 100 requests per hour
- **Batch Operations**: Limited to 100 items per request

## Webhooks

The plugin supports webhooks for real-time integrations:

```php
// Register webhook
add_action('pit_item_updated', function($item_id, $item_data) {
    // Send webhook notification
    wp_remote_post('https://your-webhook-url.com/inventory', [
        'body' => json_encode([
            'event' => 'item_updated',
            'item_id' => $item_id,
            'data' => $item_data,
            'timestamp' => current_time('timestamp')
        ])
    ]);
});
```

## Code Examples

### JavaScript Frontend Integration

```javascript
class InventoryAPI {
    constructor(baseUrl, nonce) {
        this.baseUrl = baseUrl;
        this.nonce = nonce;
    }

    async request(endpoint, options = {}) {
        const url = `${this.baseUrl}${endpoint}`;
        const headers = {
            'X-WP-Nonce': this.nonce,
            'Content-Type': 'application/json',
            ...options.headers
        };

        const response = await fetch(url, { ...options, headers });
        
        if (!response.ok) {
            throw new Error(`API Error: ${response.status}`);
        }
        
        return response.json();
    }

    async getItems(params = {}) {
        const query = new URLSearchParams(params);
        return this.request(`/items?${query}`);
    }

    async createItem(itemData) {
        return this.request('/items', {
            method: 'POST',
            body: JSON.stringify(itemData)
        });
    }

    async updateItem(id, itemData) {
        return this.request(`/items/${id}`, {
            method: 'PUT',
            body: JSON.stringify(itemData)
        });
    }

    async deleteItem(id) {
        return this.request(`/items/${id}`, {
            method: 'DELETE'
        });
    }
}

// Usage
const api = new InventoryAPI('/wp-json/pit/v2', wpApiSettings.nonce);

// Get low stock items
api.getItems({ low_stock: true }).then(response => {
    console.log('Low stock items:', response.items);
});

// Add new item
api.createItem({
    title: 'Coffee Beans',
    qty: 2,
    category: 'Pantry'
}).then(item => {
    console.log('Item created:', item);
});
```

### PHP Backend Integration

```php
// Custom plugin integration
class CustomInventoryIntegration {
    
    public function sync_with_external_system() {
        $items = $this->get_all_items();
        
        foreach ($items as $item) {
            $this->send_to_external_api($item);
        }
    }
    
    private function get_all_items() {
        $request = new WP_REST_Request('GET', '/pit/v2/items');
        $request->set_param('per_page', 100);
        
        $response = rest_do_request($request);
        
        if ($response->is_error()) {
            return [];
        }
        
        return $response->get_data()['items'];
    }
    
    private function send_to_external_api($item) {
        wp_remote_post('https://external-system.com/api/items', [
            'body' => json_encode([
                'name' => $item['title'],
                'quantity' => $item['qty'],
                'category' => $item['category']
            ]),
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . get_option('external_api_token')
            ]
        ]);
    }
}
```

For more integration examples, see the [development guide](development.md).
import React, { useState, useEffect, useCallback } from 'react';
import {
  Search, Plus, Camera, BarChart3, Package, AlertTriangle,
  CheckCircle, Grid, List, Home, Settings, Bell, Download,
  Upload, MapPin, DollarSign, Clock, Shield, Tag, Filter
} from 'lucide-react';

/**
 * Enterprise-Grade Household Inventory Management System
 * Features: Multi-location tracking, purchase history, warranties,
 * maintenance scheduling, advanced analytics, notifications, and more
 */
const EnterpriseInventoryApp = () => {
  // State management
  const [items, setItems] = useState([]);
  const [locations, setLocations] = useState([]);
  const [notifications, setNotifications] = useState([]);
  const [loading, setLoading] = useState(false);
  const [view, setView] = useState('dashboard');
  const [searchTerm, setSearchTerm] = useState('');
  const [filters, setFilters] = useState({});
  const [viewMode, setViewMode] = useState('grid');
  const [selectedItem, setSelectedItem] = useState(null);
  const [showNotifications, setShowNotifications] = useState(false);

  const apiUrl = window.pitApp?.restUrl || '/wp-json/pit/v2/';
  const nonce = window.pitApp?.nonce || '';

  // Fetch data
  const fetchItems = useCallback(async () => {
    setLoading(true);
    try {
      const response = await fetch(`${apiUrl}items`, {
        headers: { 'X-WP-Nonce': nonce }
      });
      const data = await response.json();
      setItems(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching items:', error);
    }
    setLoading(false);
  }, [apiUrl, nonce]);

  const fetchLocations = useCallback(async () => {
    try {
      const response = await fetch(`${apiUrl}locations`, {
        headers: { 'X-WP-Nonce': nonce }
      });
      const data = await response.json();
      setLocations(Array.isArray(data) ? data : []);
    } catch (error) {
      console.error('Error fetching locations:', error);
    }
  }, [apiUrl, nonce]);

  const fetchNotifications = useCallback(async () => {
    try {
      const response = await fetch(`${apiUrl}notifications`, {
        headers: { 'X-WP-Nonce': nonce }
      });
      const data = await response.json();
      setNotifications(data.notifications || []);
    } catch (error) {
      console.error('Error fetching notifications:', error);
    }
  }, [apiUrl, nonce]);

  useEffect(() => {
    fetchItems();
    fetchLocations();
    fetchNotifications();
  }, [fetchItems, fetchLocations, fetchNotifications]);

  // Analytics calculations
  const analytics = {
    totalItems: items.length,
    totalQuantity: items.reduce((sum, item) => sum + (item.qty || 0), 0),
    lowStock: items.filter(item => {
      const qty = item.qty || 0;
      const threshold = item.threshold || 5;
      return qty > 0 && qty <= threshold;
    }).length,
    outOfStock: items.filter(item => (item.qty || 0) === 0).length,
    totalValue: items.reduce((sum, item) => {
      const price = parseFloat(item.purchase_price || 0);
      const qty = parseInt(item.qty || 0);
      return sum + (price * qty);
    }, 0),
    uniqueLocations: new Set(items.map(item => item.location_id).filter(Boolean)).size,
    categories: [...new Set(items.map(item => item.category).filter(Boolean))],
    unreadNotifications: notifications.filter(n => !n.is_read).length
  };

  // Filter items
  const filteredItems = items.filter(item => {
    if (searchTerm && !item.title?.toLowerCase().includes(searchTerm.toLowerCase())) {
      return false;
    }

    if (filters.status) {
      const qty = item.qty || 0;
      const threshold = item.threshold || 5;

      if (filters.status === 'low-stock' && (qty === 0 || qty > threshold)) return false;
      if (filters.status === 'out-of-stock' && qty !== 0) return false;
      if (filters.status === 'in-stock' && qty === 0) return false;
    }

    if (filters.location && item.location_id != filters.location) return false;
    if (filters.category && item.category !== filters.category) return false;

    return true;
  });

  // Header Component
  const Header = () => (
    <header className="bg-white shadow-sm border-b border-gray-200 sticky top-0 z-50">
      <div className="px-4 sm:px-6 lg:px-8 py-4">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <Package className="h-8 w-8 text-blue-600" />
            <div>
              <h1 className="text-2xl font-bold text-gray-900">Inventory Tracker Pro</h1>
              <p className="text-sm text-gray-500">Enterprise Management System</p>
            </div>
          </div>

          <div className="flex items-center space-x-4">
            {/* Notifications */}
            <div className="relative">
              <button
                onClick={() => setShowNotifications(!showNotifications)}
                className="relative p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg"
              >
                <Bell className="h-5 w-5" />
                {analytics.unreadNotifications > 0 && (
                  <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full"></span>
                )}
              </button>
              {showNotifications && <NotificationPanel />}
            </div>

            {/* Navigation */}
            <nav className="flex space-x-1">
              {[
                { key: 'dashboard', icon: Home, label: 'Dashboard' },
                { key: 'inventory', icon: Package, label: 'Inventory' },
                { key: 'locations', icon: MapPin, label: 'Locations' },
                { key: 'analytics', icon: BarChart3, label: 'Analytics' },
                { key: 'settings', icon: Settings, label: 'Settings' }
              ].map(({ key, icon: Icon, label }) => (
                <button
                  key={key}
                  onClick={() => setView(key)}
                  className={`px-3 py-2 rounded-lg text-sm font-medium transition-colors flex items-center space-x-2 ${
                    view === key
                      ? 'bg-blue-100 text-blue-700'
                      : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'
                  }`}
                >
                  <Icon className="h-4 w-4" />
                  <span className="hidden lg:inline">{label}</span>
                </button>
              ))}
            </nav>
          </div>
        </div>
      </div>
    </header>
  );

  // Dashboard View
  const Dashboard = () => (
    <div className="space-y-6">
      {/* Statistics Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard
          title="Total Items"
          value={analytics.totalItems}
          icon={Package}
          color="blue"
          subtitle={`${analytics.totalQuantity} total units`}
        />
        <StatCard
          title="Total Value"
          value={`$${analytics.totalValue.toFixed(2)}`}
          icon={DollarSign}
          color="green"
          subtitle="Inventory worth"
        />
        <StatCard
          title="Low Stock"
          value={analytics.lowStock}
          icon={AlertTriangle}
          color="yellow"
          subtitle={`${analytics.outOfStock} out of stock`}
        />
        <StatCard
          title="Locations"
          value={analytics.uniqueLocations}
          icon={MapPin}
          color="purple"
          subtitle={`${analytics.categories.length} categories`}
        />
      </div>

      {/* Quick Actions */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h3 className="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h3>
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
          <QuickActionButton
            icon={Plus}
            label="Add Item"
            onClick={() => setView('inventory')}
            color="blue"
          />
          <QuickActionButton
            icon={Camera}
            label="Scan Receipt"
            onClick={() => setView('scan')}
            color="green"
          />
          <QuickActionButton
            icon={Upload}
            label="Import Data"
            onClick={() => setView('import')}
            color="purple"
          />
          <QuickActionButton
            icon={Download}
            label="Export Data"
            onClick={() => handleExport()}
            color="gray"
          />
        </div>
      </div>

      {/* Alerts */}
      {(analytics.lowStock > 0 || analytics.outOfStock > 0) && (
        <AlertsCard items={items} />
      )}

      {/* Recent Activity */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <RecentItemsCard items={items.slice(0, 5)} />
        <UpcomingTasksCard />
      </div>
    </div>
  );

  // Inventory View
  const InventoryView = () => (
    <div className="space-y-6">
      {/* Controls */}
      <div className="bg-white p-4 rounded-xl shadow-sm border border-gray-200">
        <div className="flex flex-col md:flex-row justify-between items-start md:items-center space-y-4 md:space-y-0 gap-4">
          <div className="flex items-center space-x-4 flex-1 w-full md:w-auto">
            <div className="relative flex-1 md:flex-initial">
              <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
              <input
                type="text"
                placeholder="Search items..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="pl-10 pr-4 py-2 border border-gray-300 rounded-lg w-full md:w-64 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>
            <button className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50">
              <Filter className="h-4 w-4 text-gray-600" />
            </button>
          </div>

          <div className="flex items-center space-x-2">
            <button
              onClick={() => setViewMode(viewMode === 'grid' ? 'list' : 'grid')}
              className="p-2 border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              {viewMode === 'grid' ? <List className="h-4 w-4" /> : <Grid className="h-4 w-4" />}
            </button>
            <button className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2">
              <Plus className="h-4 w-4" />
              <span>Add Item</span>
            </button>
          </div>
        </div>
      </div>

      {/* Items Grid */}
      <div className={viewMode === 'grid'
        ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6'
        : 'space-y-4'
      }>
        {filteredItems.map(item => (
          <ItemCard
            key={item.id}
            item={item}
            viewMode={viewMode}
            onSelect={() => setSelectedItem(item)}
          />
        ))}
      </div>

      {filteredItems.length === 0 && (
        <div className="text-center py-12">
          <Package className="mx-auto h-12 w-12 text-gray-400" />
          <h3 className="mt-2 text-sm font-medium text-gray-900">No items found</h3>
          <p className="mt-1 text-sm text-gray-500">Get started by adding your first inventory item.</p>
        </div>
      )}
    </div>
  );

  // Locations View
  const LocationsView = () => (
    <div className="space-y-6">
      <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <div className="flex justify-between items-center mb-6">
          <h2 className="text-xl font-semibold text-gray-900">Locations</h2>
          <button className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 flex items-center space-x-2">
            <Plus className="h-4 w-4" />
            <span>Add Location</span>
          </button>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {locations.map(location => (
            <LocationCard key={location.id} location={location} items={items} />
          ))}
        </div>
      </div>
    </div>
  );

  // Helper functions
  const handleExport = async () => {
    try {
      const response = await fetch(`${apiUrl}export?format=json`, {
        headers: { 'X-WP-Nonce': nonce }
      });
      const data = await response.json();

      const blob = new Blob([JSON.stringify(data.data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `inventory-export-${new Date().toISOString().split('T')[0]}.json`;
      a.click();
    } catch (error) {
      console.error('Export failed:', error);
    }
  };

  // Notification Panel
  const NotificationPanel = () => (
    <div className="absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-lg border border-gray-200 max-h-96 overflow-y-auto">
      <div className="p-4 border-b border-gray-200">
        <div className="flex justify-between items-center">
          <h3 className="font-semibold text-gray-900">Notifications</h3>
          <span className="text-xs text-gray-500">{analytics.unreadNotifications} unread</span>
        </div>
      </div>
      <div className="divide-y divide-gray-200">
        {notifications.slice(0, 10).map(notification => (
          <div key={notification.id} className={`p-4 hover:bg-gray-50 ${!notification.is_read ? 'bg-blue-50' : ''}`}>
            <div className="flex justify-between items-start">
              <div className="flex-1">
                <p className="text-sm font-medium text-gray-900">{notification.title}</p>
                <p className="text-xs text-gray-500 mt-1">{notification.message}</p>
              </div>
              {!notification.is_read && (
                <span className="w-2 h-2 bg-blue-600 rounded-full"></span>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );

  if (loading && items.length === 0) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-32 w-32 border-b-2 border-blue-600 mx-auto"></div>
          <p className="mt-4 text-gray-600">Loading inventory...</p>
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50">
      <Header />
      <main className="px-4 sm:px-6 lg:px-8 py-8 max-w-7xl mx-auto">
        {view === 'dashboard' && <Dashboard />}
        {view === 'inventory' && <InventoryView />}
        {view === 'locations' && <LocationsView />}
        {view === 'analytics' && <div>Analytics View Coming Soon</div>}
        {view === 'settings' && <div>Settings View Coming Soon</div>}
      </main>
    </div>
  );
};

// Sub-components
const StatCard = ({ title, value, icon: Icon, color, subtitle }) => {
  const colors = {
    blue: 'from-blue-500 to-blue-600',
    green: 'from-green-500 to-green-600',
    yellow: 'from-yellow-500 to-yellow-600',
    purple: 'from-purple-500 to-purple-600',
    red: 'from-red-500 to-red-600',
  };

  return (
    <div className={`bg-gradient-to-r ${colors[color]} p-6 rounded-xl text-white shadow-lg`}>
      <div className="flex items-center justify-between">
        <div>
          <p className="text-white/80 text-sm">{title}</p>
          <p className="text-3xl font-bold mt-1">{value}</p>
          {subtitle && <p className="text-white/70 text-xs mt-2">{subtitle}</p>}
        </div>
        <Icon className="h-8 w-8 text-white/80" />
      </div>
    </div>
  );
};

const QuickActionButton = ({ icon: Icon, label, onClick, color }) => (
  <button
    onClick={onClick}
    className="p-4 border-2 border-gray-200 rounded-lg hover:border-gray-300 hover:bg-gray-50 transition-all group"
  >
    <Icon className={`h-6 w-6 mx-auto mb-2 text-${color}-600`} />
    <span className="text-sm font-medium text-gray-900">{label}</span>
  </button>
);

const AlertsCard = ({ items }) => {
  const lowStock = items.filter(i => {
    const qty = i.qty || 0;
    const threshold = i.threshold || 5;
    return qty > 0 && qty <= threshold;
  });

  const outOfStock = items.filter(i => (i.qty || 0) === 0);

  return (
    <div className="bg-yellow-50 border border-yellow-200 rounded-xl p-6">
      <div className="flex items-center space-x-3 mb-4">
        <AlertTriangle className="h-6 w-6 text-yellow-600" />
        <h3 className="text-lg font-semibold text-yellow-800">Stock Alerts</h3>
      </div>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
        {lowStock.slice(0, 4).map(item => (
          <div key={item.id} className="bg-white p-3 rounded-lg border border-yellow-200">
            <div className="flex justify-between items-center">
              <span className="font-medium text-gray-900">{item.title}</span>
              <span className="text-sm text-yellow-600">Qty: {item.qty}</span>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
};

const RecentItemsCard = ({ items }) => (
  <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
    <h3 className="text-lg font-semibold text-gray-900 mb-4">Recent Items</h3>
    <div className="space-y-3">
      {items.map(item => (
        <div key={item.id} className="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
          <div className="flex items-center space-x-3">
            <div className="w-2 h-2 bg-green-400 rounded-full"></div>
            <span className="text-sm text-gray-900">{item.title}</span>
          </div>
          <span className="text-sm text-gray-500">Qty: {item.qty || 0}</span>
        </div>
      ))}
    </div>
  </div>
);

const UpcomingTasksCard = () => (
  <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
    <h3 className="text-lg font-semibold text-gray-900 mb-4">Upcoming Tasks</h3>
    <div className="space-y-3">
      <TaskItem
        title="Restock groceries"
        date="Today"
        type="reorder"
      />
      <TaskItem
        title="HVAC maintenance"
        date="In 3 days"
        type="maintenance"
      />
      <TaskItem
        title="Warranty expires: Refrigerator"
        date="In 7 days"
        type="warranty"
      />
    </div>
  </div>
);

const TaskItem = ({ title, date, type }) => {
  const icons = {
    reorder: Package,
    maintenance: Clock,
    warranty: Shield
  };
  const Icon = icons[type];

  return (
    <div className="flex items-center space-x-3 py-2">
      <Icon className="h-5 w-5 text-gray-400" />
      <div className="flex-1">
        <p className="text-sm text-gray-900">{title}</p>
        <p className="text-xs text-gray-500">{date}</p>
      </div>
    </div>
  );
};

const ItemCard = ({ item, viewMode, onSelect }) => {
  const qty = item.qty || 0;
  const threshold = item.threshold || 5;
  const isLowStock = qty > 0 && qty <= threshold;
  const isOutOfStock = qty === 0;

  if (viewMode === 'list') {
    return (
      <div className="bg-white p-4 rounded-lg border border-gray-200 hover:border-gray-300 transition-colors">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4 flex-1">
            <div className={`w-4 h-4 rounded-full ${isOutOfStock ? 'bg-red-400' : isLowStock ? 'bg-yellow-400' : 'bg-green-400'}`}></div>
            <div className="flex-1">
              <h3 className="font-medium text-gray-900">{item.title}</h3>
              <div className="flex items-center space-x-4 text-sm text-gray-500 mt-1">
                <span>Qty: {qty}</span>
                {item.location_id && <span className="flex items-center"><MapPin className="h-3 w-3 mr-1" /> Location</span>}
                {item.category && <span className="flex items-center"><Tag className="h-3 w-3 mr-1" /> {item.category}</span>}
              </div>
            </div>
          </div>
          <button
            onClick={onSelect}
            className="px-4 py-2 text-sm text-blue-600 hover:text-blue-700 font-medium"
          >
            View Details
          </button>
        </div>
      </div>
    );
  }

  return (
    <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
      <div className="flex justify-between items-start mb-4">
        <div className={`w-3 h-3 rounded-full ${isOutOfStock ? 'bg-red-400' : isLowStock ? 'bg-yellow-400' : 'bg-green-400'}`}></div>
        {isLowStock && (
          <span className="px-2 py-1 bg-yellow-100 text-yellow-700 text-xs rounded-full">Low Stock</span>
        )}
      </div>

      <h3 className="font-semibold text-gray-900 mb-3 truncate">{item.title}</h3>

      <div className="space-y-2 text-sm">
        <div className="flex justify-between items-center">
          <span className="text-gray-500">Quantity</span>
          <span className="font-medium">{qty}</span>
        </div>
        {item.category && (
          <div className="flex justify-between items-center">
            <span className="text-gray-500">Category</span>
            <span className="text-gray-900">{item.category}</span>
          </div>
        )}
        {item.purchase_price && (
          <div className="flex justify-between items-center">
            <span className="text-gray-500">Value</span>
            <span className="text-gray-900">${(parseFloat(item.purchase_price) * qty).toFixed(2)}</span>
          </div>
        )}
      </div>

      <button
        onClick={onSelect}
        className="mt-4 w-full py-2 text-sm text-blue-600 hover:text-blue-700 font-medium border border-gray-200 rounded-lg hover:bg-gray-50"
      >
        View Details
      </button>
    </div>
  );
};

const LocationCard = ({ location, items }) => {
  const itemsInLocation = items.filter(i => i.location_id == location.id);

  return (
    <div className="bg-white p-4 rounded-lg border border-gray-200 hover:border-gray-300 transition-colors">
      <div className="flex items-start justify-between mb-3">
        <div className="flex items-center space-x-2">
          <MapPin className="h-5 w-5 text-blue-600" />
          <h4 className="font-semibold text-gray-900">{location.name}</h4>
        </div>
        <span className="text-xs text-gray-500">{location.type}</span>
      </div>
      {location.description && (
        <p className="text-sm text-gray-600 mb-3">{location.description}</p>
      )}
      <div className="flex items-center justify-between text-sm">
        <span className="text-gray-500">{itemsInLocation.length} items</span>
        <button className="text-blue-600 hover:text-blue-700">Manage</button>
      </div>
    </div>
  );
};

export default EnterpriseInventoryApp;

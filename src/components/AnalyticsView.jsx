import React, { useEffect, useMemo, useState } from 'react';
import { CheckCircle, AlertTriangle, XCircle } from 'lucide-react';

const AnalyticsView = () => {
  const [items, setItems] = useState([]);
  const [selectedMetric, setSelectedMetric] = useState('purchases');

  useEffect(() => {
    const fetchItems = async () => {
      try {
        const response = await fetch('/wp-json/pit/v2/items');
        if (response.ok) {
          const json = await response.json();
          setItems(json);
        }
      } catch (err) {
        // errors silently ignored; handle in production as needed
      }
    };

    fetchItems();
  }, []);

  const cutoffDate = useMemo(() => new Date(Date.now() - 30 * 24 * 60 * 60 * 1000), []);

  const metrics = useMemo(
    () => ({
      purchases: {
        label: 'Recent Purchases',
        icon: CheckCircle,
        filter: item => new Date(item.last_purchased) >= cutoffDate,
      },
      lowStock: {
        label: 'Low Stock',
        icon: AlertTriangle,
        filter: item => item.current_qty < item.threshold,
      },
      expired: {
        label: 'Expired',
        icon: XCircle,
        filter: item => item.expiry && new Date(item.expiry) < new Date(),
      },
    }),
    [cutoffDate]
  );

  const filteredItems = useMemo(() => {
    const metric = metrics[selectedMetric];
    return items.filter(item => metric.filter(item));
  }, [items, metrics, selectedMetric]);

  return (
    <div className="space-y-6">
      <div className="flex space-x-4">
        {Object.entries(metrics).map(([key, metric]) => {
          const Icon = metric.icon;
          const active = selectedMetric === key;
          return (
            <button
              key={key}
              onClick={() => setSelectedMetric(key)}
              className={`flex items-center px-4 py-2 rounded-lg border transition-colors ${
                active ? 'bg-blue-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'
              }`}
            >
              <Icon className="h-5 w-5 mr-2" />
              <span>{metric.label}</span>
              <span className="ml-2 text-sm text-gray-500">{items.filter(metric.filter).length}</span>
            </button>
          );
        })}
      </div>
      <div>
        <ul className="divide-y divide-gray-200">
          {filteredItems.map(item => (
            <li key={item.id} className="py-2 flex justify-between">
              <span className="font-medium">{item.title}</span>
              {selectedMetric === 'purchases' && (
                <span className="text-sm text-gray-500">
                  {new Date(item.last_purchased).toLocaleDateString()}
                </span>
              )}
              {selectedMetric === 'lowStock' && (
                <span className="text-sm text-gray-500">
                  {item.current_qty}/{item.threshold}
                </span>
              )}
              {selectedMetric === 'expired' && (
                <span className="text-sm text-gray-500">
                  {item.expiry ? new Date(item.expiry).toLocaleDateString() : 'N/A'}
                </span>
              )}
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
};

export default AnalyticsView;


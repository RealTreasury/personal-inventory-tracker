import React, { useMemo, useState } from 'react';
import { CheckCircle, AlertTriangle, XCircle } from 'lucide-react';

const AnalyticsView = ({ items = [], purchaseTrends = [], timeRange = 30 }) => {
  const [selectedMetric, setSelectedMetric] = useState('purchases');

  // Calculate cutoff date based on selected time range
  const cutoffDate = useMemo(
    () => new Date(Date.now() - timeRange * 24 * 60 * 60 * 1000),
    [timeRange]
  );

  // Filter items and purchase trends to the selected range
  const recentItems = useMemo(
    () => items.filter(item => new Date(item.last_purchased) >= cutoffDate),
    [items, cutoffDate]
  );

  const recentTrends = useMemo(
    () => purchaseTrends.filter(trend => new Date(trend.date) >= cutoffDate),
    [purchaseTrends, cutoffDate]
  );

  const metrics = useMemo(
    () => ({
      purchases: {
        label: 'Recent Purchases',
        icon: CheckCircle,
        filter: () => true,
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
    []
  );

  const filteredItems = useMemo(() => {
    const metric = metrics[selectedMetric];
    return recentItems.filter(item => metric.filter(item));
  }, [recentItems, metrics, selectedMetric]);

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
              <span className="ml-2 text-sm text-gray-500">{recentItems.filter(metric.filter).length}</span>
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


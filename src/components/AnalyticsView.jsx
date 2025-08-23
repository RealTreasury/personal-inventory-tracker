import React, { useEffect, useMemo, useState, useRef } from 'react';
import { CheckCircle, AlertTriangle, Package } from 'lucide-react';
import Chart from 'chart.js/auto';

const AnalyticsView = ({ timeRange = 30 }) => {
  const [selectedMetric, setSelectedMetric] = useState('purchases');
  const [items, setItems] = useState([]);
  const chartRef = useRef(null);

  useEffect(() => {
    const fetchData = async () => {
      try {
        const response = await fetch(
          `${window.pitApp?.restUrl}analytics?range=${timeRange}`,
          { headers: { 'X-WP-Nonce': window.pitApp?.nonce } }
        );
        if (response.ok) {
          const data = await response.json();
          setItems(data.items);
        }
      } catch (err) {
        // silently ignore errors
      }
    };
    fetchData();
  }, [timeRange]);

  // Calculate cutoff date based on selected time range
  const cutoffDate = useMemo(
    () => new Date(Date.now() - timeRange * 24 * 60 * 60 * 1000),
    [timeRange]
  );

  // Filter items to the selected range
  const recentItems = useMemo(
    () => items.filter(item => new Date(item.last_purchased) >= cutoffDate),
    [items, cutoffDate]
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
        icon: Package,
        filter: item => item.expiry && new Date(item.expiry) < new Date(),
      },
    }),
    []
  );

  const filteredItems = useMemo(() => {
    const metric = metrics[selectedMetric];
    return recentItems.filter(item => metric.filter(item));
  }, [recentItems, metrics, selectedMetric]);

  useEffect(() => {
    let chartInstance;
    if (chartRef.current) {
      chartInstance = new Chart(chartRef.current, {
        type: 'bar',
        data: {
          labels: ['Purchases', 'Low Stock', 'Expired'],
          datasets: [
            {
              data: [
                recentItems.length,
                recentItems.filter(metrics.lowStock.filter).length,
                recentItems.filter(metrics.expired.filter).length,
              ],
              backgroundColor: ['#3b82f6', '#f59e0b', '#ef4444'],
            },
          ],
        },
        options: { responsive: true, plugins: { legend: { display: false } } },
      });
    }
    return () => chartInstance?.destroy();
  }, [recentItems, metrics]);

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
      <canvas ref={chartRef} className="max-w-md" />
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


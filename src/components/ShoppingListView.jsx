import React, { useEffect, useState } from 'react';
import { AlertCircle } from 'lucide-react';

const ShoppingListView = () => {
  const [data, setData] = useState({ items: [], total_items: 0, estimated_total: 0 });
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const fetchList = async () => {
      try {
        const response = await fetch('/wp-json/pit/v2/shopping-list');
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        const json = await response.json();
        setData(json);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    };

    fetchList();
  }, []);

  if (loading) {
    return <div>Loading shopping list...</div>;
  }

  if (error) {
    return (
      <div className="flex items-center text-red-600">
        <AlertCircle className="h-5 w-5 mr-2" />
        <span>{error}</span>
      </div>
    );
  }

  if (data.items.length === 0) {
    return <div>No items need to be purchased.</div>;
  }

  return (
    <div className="space-y-4">
      <div className="flex justify-between items-center">
        <h2 className="text-xl font-semibold">Shopping List</h2>
        <div className="text-sm text-gray-600">
          {data.total_items} items • Estimated total: $
          {Number.parseFloat(data.estimated_total).toFixed(2)}
        </div>
      </div>
      <ul className="divide-y divide-gray-200">
        {data.items.map((item) => (
          <li key={item.id} className="py-2 flex justify-between">
            <div>
              <span className="font-medium">{item.title}</span>
              <span className="ml-2 text-sm text-gray-500">({item.category})</span>
            </div>
            <div className="text-sm text-gray-700">
              Qty: {item.current_qty}/{item.threshold} • $
              {Number.parseFloat(item.estimated_cost).toFixed(2)}
            </div>
          </li>
        ))}
      </ul>
    </div>
  );
};

export default ShoppingListView;


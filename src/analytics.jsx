import React from 'react';
import { createRoot } from 'react-dom/client';
import AnalyticsView from './components/AnalyticsView.jsx';

const container = document.getElementById('pit-analytics');

if (container) {
  const root = createRoot(container);
  const fetchData = async () => {
    try {
      const response = await fetch(`${window.pitApp?.restUrl}analytics`, {
        headers: { 'X-WP-Nonce': window.pitApp?.nonce },
      });
      if (response.ok) {
        const data = await response.json();
        root.render(<AnalyticsView items={data.items} />);
      }
    } catch (err) {
      // silently ignore errors
    }
  };
  fetchData();
}

import React from 'react';
import { createRoot } from 'react-dom/client';
import AnalyticsView from './components/AnalyticsView.jsx';

const container = document.getElementById('pit-analytics');

if (container) {
  const root = createRoot(container);
  root.render(<AnalyticsView timeRange={30} />);
}

import React from 'react';
import { createRoot } from 'react-dom/client';
import EnterpriseInventoryApp from './components/EnterpriseInventoryApp';

const container = document.getElementById('pit-enhanced-app');

if (container) {
  const root = createRoot(container);
  root.render(<EnterpriseInventoryApp />);
}

export default EnterpriseInventoryApp;


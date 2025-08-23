import React from 'react';
import { createRoot } from 'react-dom/client';
import ImportExportView from './components/ImportExportView.jsx';

const container = document.getElementById('pit-import-export');

if (container) {
  const root = createRoot(container);
  const onItemsUpdated = () => {
    if (window.pitApp?.onItemsUpdated) {
      window.pitApp.onItemsUpdated();
    }
  };
  root.render(<ImportExportView onItemsUpdated={onItemsUpdated} />);
}

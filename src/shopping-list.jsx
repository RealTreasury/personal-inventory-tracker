import React from 'react';
import { createRoot } from 'react-dom/client';
import ShoppingListView from './components/ShoppingListView.jsx';

const container = document.getElementById('pit-shopping-list');

if (container) {
  const root = createRoot(container);
  root.render(<ShoppingListView />);
}


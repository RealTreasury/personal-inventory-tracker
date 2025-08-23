import React from 'react';
import { createRoot } from 'react-dom/client';
import OCRScannerView from './components/OCRScannerView.jsx';

const container = document.getElementById('pit-ocr-scanner');

if (container) {
  const root = createRoot(container);
  const items = window.pitApp?.items || [];
  root.render(<OCRScannerView items={items} />);
}


import Papa from 'papaparse';

export function parseCSV(file, defaultMappings) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        // Remove UTF-8 BOM if present to ensure clean parsing
        const text = e.target.result.replace(/^\uFEFF/, '');
        const results = Papa.parse(text, { header: true, skipEmptyLines: true });
        if (results.errors.length) {
          throw new Error('Failed to parse CSV file');
        }
        const data = results.data;
        const headers = results.meta.fields || [];
        const mapping = {};
        headers.forEach((header) => {
          const lowerHeader = header.toLowerCase();
          Object.entries(defaultMappings.csv).forEach(([field, patterns]) => {
            if (patterns.some((pattern) => lowerHeader.includes(pattern))) {
              mapping[field] = header;
            }
          });
          if (lowerHeader.startsWith('meta_')) {
            mapping[header] = header;
          }
        });
        resolve({ data, mapping });
      } catch (err) {
        reject(new Error('Failed to parse CSV file'));
      }
    };
    reader.onerror = () => reject(new Error('Failed to read file'));
    reader.readAsText(file);
  });
}

export function parseJSON(file) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = (e) => {
      try {
        const data = JSON.parse(e.target.result);
        if (!Array.isArray(data)) {
          reject(new Error('JSON must contain an array of items'));
          return;
        }
        const mapping = {};
        if (data.length > 0) {
          const first = data[0];
          Object.keys(first).forEach((key) => {
            const lower = key.toLowerCase();
            if (lower.includes('name') || lower.includes('title')) {
              mapping.title = key;
            } else if (lower.includes('qty') || lower.includes('quantity')) {
              mapping.qty = key;
            } else if (lower.includes('category')) {
              mapping.category = key;
            }
          });
        }
        resolve({ data, mapping });
      } catch (err) {
        reject(new Error('Failed to parse JSON file'));
      }
    };
    reader.onerror = () => reject(new Error('Failed to read file'));
    reader.readAsText(file);
  });
}

export function parseExcel() {
  return Promise.reject(
    new Error('Excel import coming soon! Please use CSV for now.')
  );
}

export function downloadFile(content, filename, mimeType) {
  const blob = new Blob([content], { type: mimeType });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

import React, { useState, useRef, useCallback } from 'react';
import {
  Upload,
  Download,
  FileText,
  Image,
  Database,
  CheckCircle,
  AlertCircle,
  Info,
  RefreshCw,
  Eye,
  EyeOff,
  Trash2,
  Edit,
  Settings,
  FileJson,
  FileSpreadsheet,
  Camera,
  Link,
  X
} from 'lucide-react';
import {
  parseCSV,
  parseJSON,
  parseExcel,
  downloadFile
} from '../utils/importExportHelpers.js';

const ImportExportView = ({ onItemsUpdated }) => {
  const [activeTab, setActiveTab] = useState('import');
  const [importFormat, setImportFormat] = useState('csv');
  const [exportFormat, setExportFormat] = useState('csv');
  const [dragOver, setDragOver] = useState(false);
  const [importing, setImporting] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [importProgress, setImportProgress] = useState(0);
  const [importResults, setImportResults] = useState(null);
  const [previewData, setPreviewData] = useState([]);
  const [showPreview, setShowPreview] = useState(false);
  const [fieldMapping, setFieldMapping] = useState({});
  const [importSettings, setImportSettings] = useState({
    updateExisting: true,
    createNew: true,
    skipErrors: true,
    validateData: true
  });

  const fileInputRef = useRef(null);
  const apiUrlRef = useRef(null);

  const formats = {
    import: {
      csv: { label: 'CSV File', icon: FileSpreadsheet, accept: '.csv', description: 'Comma-separated values' },
      json: { label: 'JSON File', icon: FileJson, accept: '.json', description: 'JavaScript Object Notation' },
      excel: { label: 'Excel File', icon: FileSpreadsheet, accept: '.xlsx,.xls', description: 'Microsoft Excel' },
      api: { label: 'API Import', icon: Link, accept: '', description: 'Import from external API' }
    },
    export: {
      csv: { label: 'CSV File', icon: FileSpreadsheet, description: 'Comma-separated values' },
      json: { label: 'JSON File', icon: FileJson, description: 'JavaScript Object Notation' },
      excel: { label: 'Excel File', icon: FileSpreadsheet, description: 'Microsoft Excel (coming soon)' },
      pdf: { label: 'PDF Report', icon: FileText, description: 'Printable inventory report' }
    }
  };

  const defaultMappings = {
    csv: {
      title: ['title', 'name', 'item_name', 'product_name'],
      qty: ['qty', 'quantity', 'stock', 'amount'],
      category: ['category', 'type', 'group'],
      unit: ['unit', 'measure', 'measurement'],
      threshold: ['threshold', 'min_stock', 'reorder_level'],
      notes: ['notes', 'description', 'memo']
    }
  };

  const handleFileSelect = (event) => {
    const file = event.target.files[0];
    if (file) {
      processFile(file);
    }
  };

  const handleDrop = useCallback((event) => {
    event.preventDefault();
    setDragOver(false);
    const files = Array.from(event.dataTransfer.files);
    if (files.length > 0) {
      processFile(files[0]);
    }
  }, []);

  const handleDragOver = useCallback((event) => {
    event.preventDefault();
    setDragOver(true);
  }, []);

  const handleDragLeave = useCallback((event) => {
    event.preventDefault();
    setDragOver(false);
  }, []);

  const processFile = async (file) => {
    setImporting(true);
    setImportProgress(10);
    try {
      let parsed;
      if (importFormat === 'csv') {
        parsed = await parseCSV(file, defaultMappings);
      } else if (importFormat === 'json') {
        parsed = await parseJSON(file);
      } else if (importFormat === 'excel') {
        parsed = await parseExcel(file);
      }
      setImportProgress(50);
      if (parsed) {
        setFieldMapping(parsed.mapping || {});
        setPreviewData(parsed.data.slice(0, 10));
        setShowPreview(true);
      }
      setImportProgress(100);
    } catch (error) {
      console.error('File processing error:', error);
      setImportResults({
        success: false,
        error: error.message,
        imported: 0,
        skipped: 0,
        errors: []
      });
    } finally {
      setImporting(false);
    }
  };

  const executeImport = async () => {
    setImporting(true);
    setImportProgress(0);
    try {
      const mappedData = previewData.map((row) => {
        const item = {};
        Object.entries(fieldMapping).forEach(([field, sourceField]) => {
          if (sourceField && row[sourceField] !== undefined) {
            item[field] = row[sourceField];
          }
        });
        return item;
      });
      const batchSize = 10;
      let imported = 0;
      let skipped = 0;
      const errors = [];
      for (let i = 0; i < mappedData.length; i += batchSize) {
        const batch = mappedData.slice(i, i + batchSize);
        try {
          const response = await fetch(`${window.pitApp?.restUrl}items/import`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-WP-Nonce': window.pitApp?.nonce
            },
            body: JSON.stringify({
              items: batch,
              settings: importSettings
            })
          });
          if (response.ok) {
            const result = await response.json();
            imported += result.imported || 0;
            skipped += result.skipped || 0;
          } else {
            throw new Error(`HTTP ${response.status}`);
          }
        } catch (error) {
          errors.push(`Batch ${Math.floor(i / batchSize) + 1}: ${error.message}`);
          if (!importSettings.skipErrors) {
            throw error;
          }
        }
        setImportProgress(Math.round(((i + batchSize) / mappedData.length) * 100));
      }
      setImportResults({
        success: true,
        imported,
        skipped,
        errors,
        total: mappedData.length
      });
      if (onItemsUpdated) {
        onItemsUpdated();
      }
    } catch (error) {
      setImportResults({
        success: false,
        error: error.message,
        imported: 0,
        skipped: 0,
        errors: []
      });
    } finally {
      setImporting(false);
      setShowPreview(false);
    }
  };

  const executeExport = async () => {
    setExporting(true);
    try {
      const response = await fetch(`${window.pitApp?.restUrl}export?format=${exportFormat}`, {
        headers: { 'X-WP-Nonce': window.pitApp?.nonce }
      });
      if (!response.ok) {
        throw new Error(`Export failed: ${response.status}`);
      }
      if (exportFormat === 'json') {
        const data = await response.json();
        downloadFile(JSON.stringify(data, null, 2), `inventory-${new Date().toISOString().split('T')[0]}.json`, 'application/json');
      } else if (exportFormat === 'csv') {
        const data = await response.text();
        downloadFile(data, `inventory-${new Date().toISOString().split('T')[0]}.csv`, 'text/csv');
      } else if (exportFormat === 'pdf') {
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `inventory-report-${new Date().toISOString().split('T')[0]}.pdf`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
      }
    } catch (error) {
      console.error('Export error:', error);
      alert('Export failed: ' + error.message);
    } finally {
      setExporting(false);
    }
  };

  const importFromAPI = async () => {
    const url = apiUrlRef.current?.value;
    if (!url) {
      alert('Please enter an API URL');
      return;
    }
    setImporting(true);
    try {
      const response = await fetch(url);
      const data = await response.json();
      if (Array.isArray(data)) {
        setPreviewData(data.slice(0, 10));
        setShowPreview(true);
      } else {
        throw new Error('API must return an array of items');
      }
    } catch (error) {
      alert('Failed to import from API: ' + error.message);
    } finally {
      setImporting(false);
    }
  };
  return (
    <div className="space-y-6">
      <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <h2 className="text-xl font-semibold text-gray-900 mb-2">Import & Export</h2>
        <p className="text-gray-600">Manage your inventory data with bulk import and export operations</p>
      </div>
      <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div className="flex border-b border-gray-200">
          <button
            onClick={() => setActiveTab('import')}
            className={`flex-1 px-6 py-4 text-sm font-medium ${activeTab === 'import' ? 'bg-blue-50 text-blue-700 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'}`}
          >
            <Upload className="h-4 w-4 inline mr-2" />
            Import Data
          </button>
          <button
            onClick={() => setActiveTab('export')}
            className={`flex-1 px-6 py-4 text-sm font-medium ${activeTab === 'export' ? 'bg-blue-50 text-blue-700 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'}`}
          >
            <Download className="h-4 w-4 inline mr-2" />
            Export Data
          </button>
        </div>
        <div className="p-6">
          {activeTab === 'import' ? (
            <div className="space-y-6">
              <div>
                <h3 className="font-semibold text-gray-900 mb-3">Select Import Format</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  {Object.entries(formats.import).map(([key, format]) => (
                    <div
                      key={key}
                      onClick={() => setImportFormat(key)}
                      className={`p-4 border-2 rounded-lg cursor-pointer transition-all ${importFormat === key ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-gray-300'}`}
                    >
                      <div className="flex items-center space-x-3">
                        <format.icon className={`h-6 w-6 ${importFormat === key ? 'text-blue-600' : 'text-gray-400'}`} />
                        <div>
                          <div className="font-medium text-gray-900">{format.label}</div>
                          <div className="text-sm text-gray-500">{format.description}</div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
              {importFormat === 'api' ? (
                <div className="space-y-4">
                  <div>
                    <label className="block text-sm font-medium text-gray-700 mb-2">API Endpoint URL</label>
                    <div className="flex space-x-3">
                      <input
                        ref={apiUrlRef}
                        type="url"
                        placeholder="https://api.example.com/inventory"
                        className="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                      />
                      <button
                        onClick={importFromAPI}
                        disabled={importing}
                        className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 transition-colors"
                      >
                        {importing ? <RefreshCw className="h-4 w-4 animate-spin" /> : 'Import'}
                      </button>
                    </div>
                  </div>
                </div>
              ) : (
                <div
                  onDrop={handleDrop}
                  onDragOver={handleDragOver}
                  onDragLeave={handleDragLeave}
                  className={`border-2 border-dashed rounded-xl p-8 text-center transition-colors ${dragOver ? 'border-blue-400 bg-blue-50' : 'border-gray-300 hover:border-gray-400'}`}
                >
                  <Upload className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                  <h3 className="text-lg font-medium text-gray-900 mb-2">
                    Drop your {formats.import[importFormat].label} here
                  </h3>
                  <p className="text-gray-500 mb-4">Or click to browse files</p>
                  <input
                    ref={fileInputRef}
                    type="file"
                    accept={formats.import[importFormat].accept}
                    onChange={handleFileSelect}
                    className="hidden"
                  />
                  <button
                    onClick={() => fileInputRef.current?.click()}
                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                  >
                    Choose File
                  </button>
                </div>
              )}
              <div className="bg-gray-50 p-4 rounded-lg">
                <h4 className="font-medium text-gray-900 mb-3">Import Settings</h4>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <label className="flex items-center space-x-3">
                    <input
                      type="checkbox"
                      checked={importSettings.updateExisting}
                      onChange={(e) =>
                        setImportSettings((prev) => ({
                          ...prev,
                          updateExisting: e.target.checked
                        }))
                      }
                      className="rounded border-gray-300"
                    />
                    <span className="text-sm">Update existing items</span>
                  </label>
                  <label className="flex items-center space-x-3">
                    <input
                      type="checkbox"
                      checked={importSettings.createNew}
                      onChange={(e) =>
                        setImportSettings((prev) => ({
                          ...prev,
                          createNew: e.target.checked
                        }))
                      }
                      className="rounded border-gray-300"
                    />
                    <span className="text-sm">Create new items</span>
                  </label>
                  <label className="flex items-center space-x-3">
                    <input
                      type="checkbox"
                      checked={importSettings.skipErrors}
                      onChange={(e) =>
                        setImportSettings((prev) => ({
                          ...prev,
                          skipErrors: e.target.checked
                        }))
                      }
                      className="rounded border-gray-300"
                    />
                    <span className="text-sm">Skip errors and continue</span>
                  </label>
                  <label className="flex items-center space-x-3">
                    <input
                      type="checkbox"
                      checked={importSettings.validateData}
                      onChange={(e) =>
                        setImportSettings((prev) => ({
                          ...prev,
                          validateData: e.target.checked
                        }))
                      }
                      className="rounded border-gray-300"
                    />
                    <span className="text-sm">Validate data before import</span>
                  </label>
                </div>
              </div>
              {importing && (
                <div className="bg-blue-50 p-4 rounded-lg">
                  <div className="flex items-center space-x-3 mb-2">
                    <RefreshCw className="h-5 w-5 text-blue-600 animate-spin" />
                    <span className="text-blue-800 font-medium">
                      Processing import... {importProgress}%
                    </span>
                  </div>
                  <div className="w-full bg-blue-200 rounded-full h-2">
                    <div
                      className="bg-blue-600 h-2 rounded-full transition-all duration-300"
                      style={{ width: `${importProgress}%` }}
                    ></div>
                  </div>
                </div>
              )}
              {importResults && (
                <div
                  className={`p-4 rounded-lg ${
                    importResults.success
                      ? 'bg-green-50 border border-green-200'
                      : 'bg-red-50 border border-red-200'
                  }`}
                >
                  <div className="flex items-center space-x-3 mb-2">
                    {importResults.success ? (
                      <CheckCircle className="h-5 w-5 text-green-600" />
                    ) : (
                      <AlertCircle className="h-5 w-5 text-red-600" />
                    )}
                    <span
                      className={`font-medium ${
                        importResults.success
                          ? 'text-green-800'
                          : 'text-red-800'
                      }`}
                    >
                      {importResults.success ? 'Import Completed' : 'Import Failed'}
                    </span>
                  </div>
                  {importResults.success ? (
                    <div className="text-sm text-green-700">
                      <p>Successfully imported {importResults.imported} items</p>
                      {importResults.skipped > 0 && (
                        <p>Skipped {importResults.skipped} items</p>
                      )}
                      {importResults.errors.length > 0 && (
                        <details className="mt-2">
                          <summary className="cursor-pointer">
                            View errors ({importResults.errors.length})
                          </summary>
                          <ul className="mt-1 ml-4 list-disc">
                            {importResults.errors.map((error, index) => (
                              <li key={index}>{error}</li>
                            ))}
                          </ul>
                        </details>
                      )}
                    </div>
                  ) : (
                    <div className="text-sm text-red-700">
                      {importResults.error}
                    </div>
                  )}
                </div>
              )}
            </div>
          ) : (
            <div className="space-y-6">
              <div>
                <h3 className="font-semibold text-gray-900 mb-3">Select Export Format</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                  {Object.entries(formats.export).map(([key, format]) => (
                    <div
                      key={key}
                      onClick={() => setExportFormat(key)}
                      className={`p-4 border-2 rounded-lg cursor-pointer transition-all ${
                        exportFormat === key
                          ? 'border-blue-500 bg-blue-50'
                          : 'border-gray-200 hover:border-gray-300'
                      } ${key === 'excel' ? 'opacity-50 cursor-not-allowed' : ''}`}
                    >
                      <div className="flex items-center space-x-3">
                        <format.icon className={`h-6 w-6 ${exportFormat === key ? 'text-blue-600' : 'text-gray-400'}`} />
                        <div>
                          <div className="font-medium text-gray-900">{format.label}</div>
                          <div className="text-sm text-gray-500">{format.description}</div>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
              <div className="bg-gray-50 p-4 rounded-lg">
                <h4 className="font-medium text-gray-900 mb-3">Export Options</h4>
                <div className="space-y-3">
                  <label className="flex items-center space-x-3">
                    <input type="checkbox" defaultChecked className="rounded border-gray-300" />
                    <span className="text-sm">Include item metadata (dates, notes)</span>
                  </label>
                  <label className="flex items-center space-x-3">
                    <input type="checkbox" defaultChecked className="rounded border-gray-300" />
                    <span className="text-sm">Include category information</span>
                  </label>
                  <label className="flex items-center space-x-3">
                    <input type="checkbox" className="rounded border-gray-300" />
                    <span className="text-sm">Include purchase history</span>
                  </label>
                  <label className="flex items-center space-x-3">
                    <input type="checkbox" className="rounded border-gray-300" />
                    <span className="text-sm">Export only low-stock items</span>
                  </label>
                </div>
              </div>
              <div className="flex justify-center">
                <button
                  onClick={executeExport}
                  disabled={exporting}
                  className="px-8 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 disabled:opacity-50 transition-colors flex items-center space-x-2"
                >
                  {exporting ? (
                    <>
                      <RefreshCw className="h-5 w-5 animate-spin" />
                      <span>Exporting...</span>
                    </>
                  ) : (
                    <>
                      <Download className="h-5 w-5" />
                      <span>Export {formats.export[exportFormat].label}</span>
                    </>
                  )}
                </button>
              </div>
            </div>
          )}
        </div>
      </div>
      {showPreview && (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 max-w-6xl w-full mx-4 max-h-[80vh] overflow-y-auto">
            <div className="flex justify-between items-center mb-6">
              <h3 className="text-lg font-semibold">Preview Import Data</h3>
              <button
                onClick={() => setShowPreview(false)}
                className="p-1 hover:bg-gray-100 rounded"
              >
                <X className="h-5 w-5" />
              </button>
            </div>
            <div className="mb-6">
              <h4 className="font-medium text-gray-900 mb-3">Field Mapping</h4>
              <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {['title', 'qty', 'category', 'unit', 'threshold', 'notes'].map((field) => (
                  <div key={field}>
                    <label className="block text-sm font-medium text-gray-700 mb-1">
                      {field.charAt(0).toUpperCase() + field.slice(1)}
                    </label>
                    <select
                      value={fieldMapping[field] || ''}
                      onChange={(e) =>
                        setFieldMapping((prev) => ({ ...prev, [field]: e.target.value }))
                      }
                      className="w-full px-3 py-2 border border-gray-300 rounded-md text-sm"
                    >
                      <option value="">Not mapped</option>
                      {previewData.length > 0 &&
                        Object.keys(previewData[0]).map((key) => (
                          <option key={key} value={key}>
                            {key}
                          </option>
                        ))}
                    </select>
                  </div>
                ))}
              </div>
            </div>
            <div className="mb-6">
              <h4 className="font-medium text-gray-900 mb-3">Data Preview (First 10 rows)</h4>
              <div className="overflow-x-auto border border-gray-200 rounded-lg">
                <table className="min-w-full divide-y divide-gray-200">
                  <thead className="bg-gray-50">
                    <tr>
                      {previewData.length > 0 &&
                        Object.keys(previewData[0]).map((key) => (
                          <th
                            key={key}
                            className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                          >
                            {key}
                          </th>
                        ))}
                    </tr>
                  </thead>
                  <tbody className="bg-white divide-y divide-gray-200">
                    {previewData.map((row, index) => (
                      <tr key={index}>
                        {Object.values(row).map((value, cellIndex) => (
                          <td key={cellIndex} className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            {String(value)}
                          </td>
                        ))}
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </div>
            <div className="flex justify-end space-x-3">
              <button
                onClick={() => setShowPreview(false)}
                className="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={executeImport}
                className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                Import Data
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default ImportExportView;

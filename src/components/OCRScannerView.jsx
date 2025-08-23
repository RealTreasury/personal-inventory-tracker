import React, { useState, useRef, useCallback } from 'react';
import {
  Camera, Upload, X, Check, AlertCircle,
  Loader2, Image, FileText, Zap, Settings,
  Plus, Minus, Eye, EyeOff, RotateCw
} from 'lucide-react';
import { loadTesseract } from '../ocr';

const OCRScannerView = ({ onItemsExtracted, items = [] }) => {
  const [isScanning, setIsScanning] = useState(false);
  const [scanResults, setScanResults] = useState([]);
  const [selectedImage, setSelectedImage] = useState(null);
  const [imagePreview, setImagePreview] = useState(null);
  const [confidence, setConfidence] = useState(60);
  const [processingStage, setProcessingStage] = useState('');
  const [scanSettings, setScanSettings] = useState({
    language: 'eng',
    psm: '6', // Page segmentation mode
    oem: '1', // OCR Engine Mode
    whitelist: '', // Character whitelist
    blacklist: '' // Character blacklist
  });

  const fileInputRef = useRef(null);
  const videoRef = useRef(null);
  const canvasRef = useRef(null);

  // Initialize camera
  const startCamera = async () => {
    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: {
          facingMode: 'environment', // Use back camera on mobile
          width: { ideal: 1920 },
          height: { ideal: 1080 }
        }
      });
      if (videoRef.current) {
        videoRef.current.srcObject = stream;
      }
    } catch (error) {
      console.error('Camera access failed:', error);
      alert('Camera access failed. Please use file upload instead.');
    }
  };

  // Stop camera
  const stopCamera = () => {
    if (videoRef.current?.srcObject) {
      const stream = videoRef.current.srcObject;
      const tracks = stream.getTracks();
      tracks.forEach(track => track.stop());
      videoRef.current.srcObject = null;
    }
  };

  // Capture photo from camera
  const capturePhoto = () => {
    const canvas = canvasRef.current;
    const video = videoRef.current;

    if (canvas && video) {
      const context = canvas.getContext('2d');
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      context.drawImage(video, 0, 0);

      canvas.toBlob((blob) => {
        processImage(blob);
        stopCamera();
      }, 'image/jpeg', 0.9);
    }
  };

  // Handle file upload
  const handleFileUpload = (event) => {
    const file = event.target.files[0];
    if (file && file.type.startsWith('image/')) {
      setSelectedImage(file);

      // Create preview
      const reader = new FileReader();
      reader.onload = (e) => setImagePreview(e.target.result);
      reader.readAsDataURL(file);

      processImage(file);
    }
  };

  // Process image with OCR
  const processImage = async (imageFile) => {
    setIsScanning(true);
    setScanResults([]);
    setProcessingStage('Initializing...');

    try {
      const Tesseract = await loadTesseract();
      setProcessingStage('Loading OCR engine...');

      const worker = await Tesseract.createWorker({
        logger: ({ status, progress }) => {
          if (status === 'recognizing text') {
            setProcessingStage(`Recognizing text... ${Math.round(progress * 100)}%`);
          } else {
            setProcessingStage(status);
          }
        }
      });

      setProcessingStage('Configuring OCR settings...');

      // Configure OCR settings
      await worker.loadLanguage(scanSettings.language);
      await worker.initialize(scanSettings.language);

      // Set OCR parameters
      await worker.setParameters({
        tessedit_pageseg_mode: scanSettings.psm,
        tessedit_ocr_engine_mode: scanSettings.oem,
        tessedit_char_whitelist: scanSettings.whitelist,
        tessedit_char_blacklist: scanSettings.blacklist,
      });

      setProcessingStage('Scanning image...');

      const result = await worker.recognize(imageFile);

      // Process results
      const lines = result.data.lines
        .filter(line => line.confidence >= confidence)
        .map(line => ({
          text: line.text.trim(),
          confidence: Math.round(line.confidence),
          bbox: line.bbox,
          words: line.words
        }))
        .filter(line => line.text.length > 2); // Filter out very short text

      setScanResults(lines);
      setProcessingStage('');
      await worker.terminate();

      if (onItemsExtracted) {
        onItemsExtracted(lines);
      }

    } catch (error) {
      console.error('OCR processing failed:', error);
      setProcessingStage('');
      alert(error.message || 'OCR processing failed. Please try again.');
    } finally {
      setIsScanning(false);
    }
  };

  // Smart matching with existing items
  const findMatchingItem = (text) => {
    const normalizedText = text.toLowerCase().trim();
    return items.find(item =>
      item.title.toLowerCase().includes(normalizedText) ||
      normalizedText.includes(item.title.toLowerCase())
    );
  };

  // Add scanned item to inventory
  const addScannedItem = async (scanResult, quantity = 1) => {
    try {
      const response = await fetch(`${window.pitApp?.restUrl}items`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': window.pitApp?.nonce
        },
        body: JSON.stringify({
          title: scanResult.text,
          qty: quantity,
          purchased: true
        })
      });

      if (response.ok) {
        // Remove from scan results
        setScanResults(prev => prev.filter(item => item !== scanResult));
      }
    } catch (error) {
      console.error('Failed to add item:', error);
    }
  };

  // Settings Panel
  const SettingsPanel = ({ isOpen, onClose }) => {
    if (!isOpen) return null;

    return (
      <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div className="bg-white rounded-xl p-6 max-w-md w-full mx-4">
          <div className="flex justify-between items-center mb-6">
            <h3 className="text-lg font-semibold">OCR Settings</h3>
            <button onClick={onClose} className="p-1 hover:bg-gray-100 rounded">
              <X className="h-5 w-5" />
            </button>
          </div>

          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Confidence Threshold: {confidence}%
              </label>
              <input
                type="range"
                min="30"
                max="95"
                value={confidence}
                onChange={(e) => setConfidence(parseInt(e.target.value))}
                className="w-full"
              />
              <div className="flex justify-between text-xs text-gray-500 mt-1">
                <span>Less Strict</span>
                <span>More Strict</span>
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Language
              </label>
              <select
                value={scanSettings.language}
                onChange={(e) => setScanSettings(prev => ({ ...prev, language: e.target.value }))}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              >
                <option value="eng">English</option>
                <option value="spa">Spanish</option>
                <option value="fra">French</option>
                <option value="deu">German</option>
                <option value="ita">Italian</option>
              </select>
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-1">
                Page Segmentation Mode
              </label>
              <select
                value={scanSettings.psm}
                onChange={(e) => setScanSettings(prev => ({ ...prev, psm: e.target.value }))}
                className="w-full px-3 py-2 border border-gray-300 rounded-md"
              >
                <option value="3">Fully automatic</option>
                <option value="6">Single block (default)</option>
                <option value="7">Single text line</option>
                <option value="8">Single word</option>
                <option value="11">Sparse text</option>
                <option value="13">Raw line</option>
              </select>
            </div>
          </div>

          <div className="flex justify-end space-x-3 mt-6">
            <button
              onClick={onClose}
              className="px-4 py-2 text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50"
            >
              Cancel
            </button>
            <button
              onClick={onClose}
              className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700"
            >
              Save Settings
            </button>
          </div>
        </div>
      </div>
    );
  };

  const [showSettings, setShowSettings] = useState(false);
  const [showCamera, setShowCamera] = useState(false);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
        <div className="flex justify-between items-center">
          <div>
            <h2 className="text-xl font-semibold text-gray-900">Receipt Scanner</h2>
            <p className="text-gray-600 mt-1">Scan receipts to automatically add items to your inventory</p>
          </div>
          <button
            onClick={() => setShowSettings(true)}
            className="p-2 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-lg"
          >
            <Settings className="h-5 w-5" />
          </button>
        </div>
      </div>

      {/* Upload Options */}
      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        {/* Camera Capture */}
        <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
          <div className="text-center">
            <Camera className="h-12 w-12 text-blue-500 mx-auto mb-4" />
            <h3 className="font-semibold text-gray-900 mb-2">Take Photo</h3>
            <p className="text-gray-600 text-sm mb-4">Use your camera to capture receipts directly</p>
            <button
              onClick={() => {
                setShowCamera(true);
                startCamera();
              }}
              className="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
            >
              Open Camera
            </button>
          </div>
        </div>

        {/* File Upload */}
        <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
          <div className="text-center">
            <Upload className="h-12 w-12 text-green-500 mx-auto mb-4" />
            <h3 className="font-semibold text-gray-900 mb-2">Upload Image</h3>
            <p className="text-gray-600 text-sm mb-4">Select an image file from your device</p>
            <input
              ref={fileInputRef}
              type="file"
              accept="image/*"
              onChange={handleFileUpload}
              className="hidden"
            />
            <button
              onClick={() => fileInputRef.current?.click()}
              className="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
            >
              Choose File
            </button>
          </div>
        </div>
      </div>

      {/* Camera Modal */}
      {showCamera && (
        <div className="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50">
          <div className="bg-white rounded-xl p-6 max-w-2xl w-full mx-4">
            <div className="flex justify-between items-center mb-4">
              <h3 className="text-lg font-semibold">Camera</h3>
              <button
                onClick={() => {
                  setShowCamera(false);
                  stopCamera();
                }}
                className="p-1 hover:bg-gray-100 rounded"
              >
                <X className="h-5 w-5" />
              </button>
            </div>

            <div className="relative">
              <video
                ref={videoRef}
                autoPlay
                playsInline
                className="w-full rounded-lg"
              />
              <div className="absolute bottom-4 left-1/2 transform -translate-x-1/2">
                <button
                  onClick={capturePhoto}
                  className="w-16 h-16 bg-white rounded-full shadow-lg hover:shadow-xl transition-shadow flex items-center justify-center"
                >
                  <div className="w-12 h-12 bg-blue-600 rounded-full"></div>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Image Preview */}
      {imagePreview && (
        <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
          <h3 className="font-semibold text-gray-900 mb-4">Image Preview</h3>
          <div className="flex justify-center">
            <img
              src={imagePreview}
              alt="Receipt preview"
              className="max-w-full max-h-96 object-contain rounded-lg border border-gray-300"
            />
          </div>
        </div>
      )}

      {/* Processing Status */}
      {isScanning && (
        <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
          <div className="flex items-center justify-center space-x-3">
            <Loader2 className="h-6 w-6 animate-spin text-blue-600" />
            <div>
              <p className="font-medium text-gray-900">Processing Image...</p>
              <p className="text-sm text-gray-600">{processingStage}</p>
            </div>
          </div>
          <div className="mt-4 bg-gray-200 rounded-full h-2">
            <div className="bg-blue-600 h-2 rounded-full animate-pulse" style={{ width: '60%' }}></div>
          </div>
        </div>
      )}

      {/* Scan Results */}
      {scanResults.length > 0 && (
        <div className="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
          <div className="flex justify-between items-center mb-6">
            <h3 className="font-semibold text-gray-900">
              Scanned Items ({scanResults.length})
            </h3>
            <div className="flex items-center space-x-2 text-sm text-gray-600">
              <Zap className="h-4 w-4" />
              <span>Min confidence: {confidence}%</span>
            </div>
          </div>

          <div className="space-y-3">
            {scanResults.map((result, index) => {
              const matchingItem = findMatchingItem(result.text);

              return (
                <div
                  key={index}
                  className="flex items-center justify-between p-4 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors"
                >
                  <div className="flex-1">
                    <div className="flex items-center space-x-3">
                      <div className="flex-1">
                        <p className="font-medium text-gray-900">{result.text}</p>
                        <div className="flex items-center space-x-4 mt-1">
                          <span className={`text-xs px-2 py-1 rounded-full ${
                            result.confidence >= 80
                              ? 'bg-green-100 text-green-700'
                              : result.confidence >= 60
                              ? 'bg-yellow-100 text-yellow-700'
                              : 'bg-red-100 text-red-700'
                          }`}>
                            {result.confidence}% confident
                          </span>
                          {matchingItem && (
                            <span className="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded-full">
                              Matches: {matchingItem.title}
                            </span>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="flex items-center space-x-2 ml-4">
                    <input
                      type="number"
                      defaultValue="1"
                      min="1"
                      className="w-16 px-2 py-1 text-sm border border-gray-300 rounded"
                      id={`qty-${index}`}
                    />
                    <button
                      onClick={() => {
                        const qty = document.getElementById(`qty-${index}`).value;
                        addScannedItem(result, parseInt(qty) || 1);
                      }}
                      className="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700 transition-colors flex items-center space-x-1"
                    >
                      <Plus className="h-3 w-3" />
                      <span>Add</span>
                    </button>
                    <button
                      onClick={() => setScanResults(prev => prev.filter((_, i) => i !== index))}
                      className="p-1 text-gray-400 hover:text-red-500 transition-colors"
                    >
                      <X className="h-4 w-4" />
                    </button>
                  </div>
                </div>
              );
            })}
          </div>

          {scanResults.length > 0 && (
            <div className="flex justify-center mt-6">
              <button
                onClick={() => {
                  scanResults.forEach((result, index) => {
                    const qtyInput = document.getElementById(`qty-${index}`);
                    const qty = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
                    addScannedItem(result, qty);
                  });
                }}
                className="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center space-x-2"
              >
                <Check className="h-4 w-4" />
                <span>Add All Items</span>
              </button>
            </div>
          )}
        </div>
      )}

      {/* Tips */}
      <div className="bg-blue-50 border border-blue-200 rounded-xl p-6">
        <div className="flex items-start space-x-3">
          <AlertCircle className="h-5 w-5 text-blue-600 mt-0.5" />
          <div>
            <h4 className="font-medium text-blue-900">OCR Tips for Best Results</h4>
            <ul className="text-sm text-blue-800 mt-2 space-y-1">
              <li>• Ensure good lighting and avoid shadows</li>
              <li>• Keep the camera steady and receipt flat</li>
              <li>• Make sure text is clearly visible and in focus</li>
              <li>• Crop images to show only the receipt area</li>
              <li>• Use high resolution images when possible</li>
            </ul>
          </div>
        </div>
      </div>

      {/* Hidden canvas for photo capture */}
      <canvas ref={canvasRef} style={{ display: 'none' }} />

      {/* Settings Modal */}
      <SettingsPanel
        isOpen={showSettings}
        onClose={() => setShowSettings(false)}
      />
    </div>
  );
};

export default OCRScannerView;


# Personal Inventory Tracker Plugin

## Purpose
The Personal Inventory Tracker plugin lets you catalog household or office items in a single searchable database. It is designed for self‑hosted use and keeps all inventory data under your control.

## Features
- CSV import and export
- OCR support for parsing receipts and labels into inventory entries
- Search, sort, and filter tools
- Front‑end read‑only mode for safe browsing
- Optional write mode when you need to add or edit items

## Requirements
- Node.js 18+
- Python 3.10+
- [Tesseract OCR](https://github.com/tesseract-ocr/tesseract)
- A modern browser

## Installation
1. Clone this repository.
2. Install JavaScript dependencies with `npm install`.
3. Create a Python virtual environment and `pip install -r requirements.txt`.
4. Make sure `tesseract` is installed and available on your PATH.
5. Configure environment variables as needed. To enable front‑end write mode, set `WRITE_MODE=1` on the server.

## Quick Start
1. Start the back end with `npm start`.
2. Visit `http://localhost:3000` in your browser.
3. Drag in a CSV file or upload receipt photos to populate your inventory.
4. Switch to write mode when you are ready to make changes.

## CSV Format
The application reads and writes standard comma‑separated values. The columns are:
`item_name,quantity,category,location,notes`

### Sample CSV
```csv
item_name,quantity,category,location,notes
"AA batteries",4,Electronics,Desk drawer,
"Paper towels",6,Household,Pantry,
"Flour",2,Grocery,Pantry,Expires 2025-06-01
```

## Front‑End Modes
The user interface launches in **read‑only mode**, which allows browsing and searching without changing data. Enabling **write mode** allows adding, editing, or deleting items and is protected by the `WRITE_MODE` environment flag.

## OCR Tips
- Use sharp, well‑lit images.
- Crop photos so only the relevant text remains.
- Check results for accuracy before saving.

## Privacy & Security
All data stays on the host running this plugin. Avoid exposing the service to untrusted networks and keep backups of your CSV files.

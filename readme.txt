Personal Inventory Tracker Plugin
=================================

Purpose
-------
The Personal Inventory Tracker plugin lets you catalog household or office items in a single searchable database. It is designed for self-hosted use and keeps all inventory data under your control.

Features
--------
- CSV import and export
- OCR support for parsing receipts and labels into inventory entries
- Search, sort, and filter tools
- Front-end read-only mode for safe browsing
- Optional write mode when you need to add or edit items

Requirements
------------
- Node.js 18+
- Python 3.10+
- Tesseract OCR
- A modern browser

Installation
------------
1. Clone this repository.
2. Install JavaScript dependencies with `npm install`.
3. Create a Python virtual environment and `pip install -r requirements.txt`.
4. Ensure `tesseract` is installed and available on your PATH.
5. To allow front-end write access, set the environment variable WRITE_MODE=1 when starting the server.

Quick Start
-----------
1. Start the back end with `npm start`.
2. Open http://localhost:3000 in your browser.
3. Drag in a CSV file or upload receipt photos to populate your inventory.
4. Switch to write mode when you are ready to make changes.

CSV Format
----------
The application reads and writes standard comma-separated values with the following columns:
item_name,quantity,category,location,notes

Sample CSV:
item_name,quantity,category,location,notes
"AA batteries",4,Electronics,Desk drawer,
"Paper towels",6,Household,Pantry,
"Flour",2,Grocery,Pantry,Expires 2025-06-01

Front-End Modes
---------------
Read-only mode lets you browse and search without modifying data. Write mode allows adding, editing, or deleting items and is enabled only when the WRITE_MODE environment variable is set.

OCR Tips
--------
- Use sharp, well-lit images.
- Crop photos so only the relevant text remains.
- Check results for accuracy before saving.

Privacy and Security
--------------------
All data stays on the host running this plugin. Avoid exposing the service to untrusted networks and keep backups of your CSV files.

# AskDialog PrestaShop Module

AskDialog integrates conversational AI into PrestaShop stores, enabling intelligent product recommendations and customer support through an AI-powered chat interface.

## Features

- Export product catalog and CMS pages to Dialog AI platform
- Real-time product data API for AI queries
- Frontend SDK with customizable appearance
- Export status monitoring
- Multi-language and multi-shop support

## Installation

1. Upload the module to `/modules/askdialog/`
2. Install via PrestaShop back office
3. Configure API keys in module settings

## Configuration

The module requires two API keys:

- **Private API Key**: Used for catalog exports and status monitoring
- **Public API Key**: Used for public data access by Dialog AI

These keys are provided by Dialog when setting up your organization.

## API Documentation

All endpoints require authentication via the `Authorization` header:

```
Authorization: Token YOUR_API_KEY
```

### Public API Endpoints

Base URL: `https://your-shop.com/module/askdialog/api`

#### Get Catalog Data

Returns the complete product catalog with categories, prices, and availability.

```bash
GET /module/askdialog/api?action=getCatalogData
Authorization: Token YOUR_PUBLIC_API_KEY
```

#### Get Language Data

Returns available languages configured in the shop.

```bash
GET /module/askdialog/api?action=getLanguageData
Authorization: Token YOUR_PUBLIC_API_KEY
```

#### Get Single Product

Returns detailed information for a specific product.

```bash
GET /module/askdialog/api?action=getProductData&id_product=123
Authorization: Token YOUR_PUBLIC_API_KEY
```

Parameters:
- `id_product` (required): Product ID
- `locale` (optional): Language locale
- `country_code` (optional): Country code for tax calculation

#### Get Category Data

Returns the category tree structure.

```bash
GET /module/askdialog/api?action=getCategoryData
Authorization: Token YOUR_PUBLIC_API_KEY
```

### Export API Endpoints

Base URL: `https://your-shop.com/module/askdialog/feed`

#### Trigger Catalog Export

Initiates export of catalog and CMS data to S3. Returns immediately with HTTP 202, processing continues in background.

```bash
POST /module/askdialog/feed?action=sendCatalogData
Authorization: Token YOUR_PRIVATE_API_KEY
```

Response:
```json
{
  "status": "accepted",
  "message": "Export started"
}
```

### Export Status API Endpoints

Base URL: `https://your-shop.com/module/askdialog/exportstatus`

#### Get Latest Export Status

Returns the most recent export status for the shop.

```bash
GET /module/askdialog/exportstatus?action=getLatestStatus&export_type=catalog
Authorization: Token YOUR_PRIVATE_API_KEY
```

Parameters:
- `export_type` (optional): `catalog` or `cms` (default: `catalog`)

Response:
```json
{
  "id": 123,
  "id_shop": 1,
  "export_type": "catalog",
  "status": "success",
  "file_name": "catalog_20250109_143022.json",
  "s3_url": "https://s3.amazonaws.com/...",
  "started_at": "2025-01-09 14:30:22",
  "completed_at": "2025-01-09 14:30:45",
  "metadata": {
    "id_lang": 1,
    "country_code": "fr"
  }
}
```

Status values:
- `init`: Export log created
- `pending`: File generation in progress
- `success`: Upload completed successfully
- `error`: Export failed (see `error_message` field)

#### Get Export History

Returns recent export history with optional filtering.

```bash
GET /module/askdialog/exportstatus?action=getExportHistory&limit=20&export_type=catalog
Authorization: Token YOUR_PRIVATE_API_KEY
```

Parameters:
- `limit` (optional): Number of results (default: 10, max: 100)
- `export_type` (optional): Filter by type

Response:
```json
{
  "status": "success",
  "count": 20,
  "exports": [...]
}
```

#### Get Export By ID

Returns a specific export log by ID.

```bash
GET /module/askdialog/exportstatus?action=getExportById&id=123
Authorization: Token YOUR_PRIVATE_API_KEY
```

Parameters:
- `id` (required): Export log ID

#### Get Status Summary

Returns count of exports grouped by status.

```bash
GET /module/askdialog/exportstatus?action=getStatusSummary
Authorization: Token YOUR_PRIVATE_API_KEY
```

Response:
```json
{
  "status": "success",
  "id_shop": 1,
  "counts": {
    "init": 0,
    "pending": 1,
    "success": 145,
    "error": 3
  }
}
```

#### Cleanup Old Logs

Deletes export logs older than specified number of days. Intended for cron jobs.

```bash
GET /module/askdialog/exportstatus?action=cleanupOldLogs&days=90
Authorization: Token YOUR_PRIVATE_API_KEY
```

Parameters:
- `days` (optional): Days to keep (default: 90, min: 7, max: 365)

Response:
```json
{
  "status": "success",
  "message": "Export logs older than 90 days have been deleted",
  "days_kept": 90
}
```

## Development

### Composer dependencies

```bash
cd askdialog 
composer install
```

### Build Frontend Assets

```bash
cd views/js/_dev
npm install
npm run build
```

### Code Standards

- PHP: PSR-12, PrestaShop coding standards
- Use FQCN with leading backslash for core PrestaShop classes

## Support

For issues and feature requests, contact Dialog support.

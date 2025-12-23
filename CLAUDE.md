# AskDialog PrestaShop Module - Development Guide

## Communication Guidelines

- **Response language**: Always respond in French
- **Code & Comments**: 100% in English (code, variables, functions, comments, commit messages)

## Module Overview

AskDialog is a PrestaShop module that integrates conversational AI into e-commerce stores. It consists of two main components:

### 1. Data Export System (Backend → Dialog AI)
- Exports product catalog and CMS pages to Dialog AI platform
- Uses batch processing for large catalogs
- Stores data in JSON format and uploads to S3 via signed URLs
- Manages export queue via `askdialog_product` table

**Key Files:**
- `src/Service/DataGenerator.php`: Orchestrator - delegates to export services
- `src/Service/Export/ProductExportService.php`: Product catalog export logic
- `src/Service/Export/CmsExportService.php`: CMS pages export logic
- `src/Service/Export/CategoryExportService.php`: Category tree export logic
- `src/Service/AskDialogClient.php`: Handles API communication with Dialog platform
- `controllers/front/feed.php`: Export endpoint (private API key protected)

### 2. Frontend SDK Integration
- Loads Dialog conversational AI widget on frontend
- Configurable appearance (colors, fonts, borders)
- Can be enabled on product pages with instant questions
- Analytics tracking with PostHog

**Key Files:**
- `views/js/askdialog.js`: Main SDK
- `views/js/ai-input.js`: AI input for general pages
- `views/js/instant.js`: Instant questions on product pages
- `views/js/setupModal.js`: Modal configuration

### 3. Public API
- Provides product and catalog data to Dialog AI
- Supports multilingual and multi-country (with tax calculations)
- Protected by public API key

**Key Files:**
- `controllers/front/api.php`: Public API endpoints

## Current Architecture

### Namespace Structure
- Current: `LouisAuthie\Askdialog`
- Target: `Dialog\AskDialog`

### Data Flow
1. **Export**: PrestaShop → DataGenerator → JSON files → S3 (via AskDialogClient)
2. **Frontend**: User interaction → SDK JS → Dialog AI API
3. **API**: Dialog AI → Public API → Product data

### Database Tables
- `askdialog_product`: Queue for batch export processing

### Configuration (Configuration::get)
- `ASKDIALOG_API_KEY`: Private API key
- `ASKDIALOG_API_KEY_PUBLIC`: Public API key
- `ASKDIALOG_API_URL`: Api URL
- `ASKDIALOG_ENABLE_PRODUCT_HOOK`: Enable on product pages
- `ASKDIALOG_COLOR_PRIMARY`: Primary color
- `ASKDIALOG_COLOR_BACKGROUND`: Background color
- `ASKDIALOG_COLOR_CTA_TEXT`: CTA text color
- `ASKDIALOG_CTA_BORDER_TYPE`: Border type (solid, dashed, etc.)
- `ASKDIALOG_CAPITALIZE_CTAS`: Capitalize CTAs
- `ASKDIALOG_FONT_FAMILY`: Font family
- `ASKDIALOG_HIGHLIGHT_PRODUCT_NAME`: Highlight product name
- `ASKDIALOG_BATCH_SIZE`: Batch size for export (default: 1000000)

### Hooks Used
- `displayHeader`: Load CSS/JS files
- `displayFooterAfter`: Inject Dialog SDK with configuration
- `displayProductAdditionalInfo`: Display assistant on product pages
- `actionFrontControllerInitBefore`: Handle CORS (currently commented)
- `displayOrderConfirmation`: PostHog analytics on order confirmation

## Refactoring History

### Category Integration into Catalog (2025-12-23)

**Changes:**
- ✅ Merged categories into catalog JSON (single file export)
- ✅ Products now reference categories by name (`category_names`, `default_category_name`)
- ✅ Removed separate `categoryUploadUrl` (client request)
- ✅ CategoryExportService kept intact for potential future use
- ✅ Added `id_category_default` to ProductRepository

**Structure:**
```json
{
  "categories": [
    {
      "id_category": 5,
      "name": "Electronics",
      "description": "...",
      "children": [...]
    }
  ],
  "products": [
    {
      "id": 123,
      "name": "Product",
      "category_names": ["Electronics", "Gadgets"],
      "default_category_name": "Electronics",
      ...
    }
  ]
}
```

**Benefits:** 
- No duplication of category descriptions across products
- LLM-friendly structure with references by name
- Single catalog file (< 100 MB target)
- Flexibility for future separate category export

### Category Export + DataGenerator Refactoring (2025-12-22)

**Changes:**
- ✅ Added category tree export (nested JSON structure for LLM)
- ✅ Split DataGenerator into specialized export services (SRP pattern)
- ✅ Created `src/Service/Export/` directory with 3 services
- ✅ DataGenerator reduced from 526 → 150 lines (orchestrator pattern)
- ✅ Fixed `getProductData` API endpoint (id_product parameter)

**Architecture:**
```
src/Service/
├── DataGenerator.php (orchestrator - ~180 lines)
└── Export/
    ├── ProductExportService.php (470 lines)
    ├── CmsExportService.php (100 lines)
    └── CategoryExportService.php (140 lines)
```

**Benefits:** Better maintainability, testability, and single responsibility per service

### DataGenerator Optimization (2025-12-19)
**Pre-refactoring version:** `d61c6f8e6e360181616bfdae8aa09a809384cfdf`

**Changes:**
- ✅ Implemented Repository pattern for all database queries
- ✅ Fixed N+1 query problem: ~24,000 queries → 11 queries for 1,000 products
- ✅ Added bulk data loading with indexed arrays (O(1) lookup)
- ✅ Created 9 repositories: Product, Combination, Image, Stock, Category, Tag, Feature, Language, Cms
- ✅ Removed duplicate code (getProductIdsForShop, filename generation)
- ✅ Removed unused methods (getCatalogDataForBatch, getNumCatalogRemaining)
- ✅ Optimized JSON encoding for LLM (JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
- ✅ Added automatic cleanup: tmp/ (24h) and sent/ (keep 20 most recent)
- ✅ PathHelper: Added generateUniqueFilename() and generateTmpFilePath()
- ✅ PathHelper: Added cleanSentFiles() and cleanSentFilesKeepRecent()
- ✅ Async HTTP response: Dialog API receives 202 Accepted in <1s (with PHP-FPM), export continues in background
- ✅ JsonResponseTrait: Added sendJsonResponseAsync() for non-blocking responses

**Performance:** 
- 2,182x fewer queries, ~4x faster export (35s → ~10s on 25k products)
- Dialog API response time: 15s → <1s (with PHP-FPM)

## TODO: Current Sprint

### 1. Testing & Validation
- [ ] Test refactored DataGenerator with real catalog data
- [ ] Validate JSON output format matches Dialog AI requirements
- [ ] Performance benchmarks on large catalogs (10k+ products) 

## Development Workflow

1. **Setup:**
   ```bash
   composer install
   cd views/js/_dev && npm install
   ```

2. **Build assets:**
   ```bash
   cd views/js/_dev && npm run build
   ```

## Code Standards

- Follow PrestaShop coding standards
- Use PSR-12 for PHP code
- Use ESLint for JavaScript
- All classes must have proper PHPDoc
- Use type hints where possible (PHP 7.1+)
- **For all Core PrestaShop classes** (e.g., `Product`, `Context`, `Validation`, `Configuration`, etc.):
  - Always use FQCN (Fully Qualified Class Name) with leading backslash: `\Product`, `\Context`, `\Validation`
  - Never import them with `use` keyword at the top of the file
  - This ensures compatibility across PrestaShop versions and avoids namespace conflicts

## Git Workflow

- Branch naming: `feature/{feature-name}` or `fix/{issue-name}`
- Commit messages: Conventional Commits format (in English)
  - `feature: add new feature`
  - `fix: resolve bug`
  - `refactor: restructure code`
  - `docs: update documentation`
  - `chore: update dependencies`

## Notes

- Module is compatible with PrestaShop 1.6 to 8.x (check `ps_versions_compliancy`)
- Uses Guzzle HTTP client for API calls
- Symfony YAML component for configuration
- Current branch: `feature/remove_1_6_legacy`

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
- `controllers/front/feed.php`: Export endpoint (private API key protected). **Only the compressed files are archived**, under `var/modules/askdialog/sent/shop_<id>/`, and only the newest export per shop is kept (`SENT_FILES_PER_EXPORT`). The uncompressed JSON is deleted after upload: nothing reads it, and it is an order of magnitude larger than the `.gz` (868 MB vs ~130 MB on a large catalogue) — keeping several days of both filled merchants' disks with gigabytes. **Archives are per shop on purpose**: the tree is shared across a multistore install while `downloadlatestexport.php` resolves the latest successful export *per shop*, so a shared directory lets one shop's retention delete another's archive — including that of a shop whose exports are currently failing. Archives written by earlier versions sit flat at the root; the download endpoint falls back to them, and they are cleared **only once every shop owns an archive** — removing them on any single shop's export would strip that fallback from a shop with a slower cadence or failing exports, which is the very cross-shop deletion the per-shop layout prevents.

### 2. Frontend SDK Integration
- Loads Dialog through the storefront tag only, `https://cdn.askdialog.com/assets/dialog.js` (1.5.0, DEC-3531): the tag loads the widget (`index.js`) and the product block (`dialog-product-block.js`) from the same CDN, so a merchant's Content-Security-Policy lists no Shopify or CloudFront host. Before 1.5.0 the module loaded the Shopify theme extension's `setupModal.js`/`instant.js` from `cdn.shopify.com` and `index.js` from `d2zm7i5bmzo6ze.cloudfront.net`
- The module hands the tag a `dialogLayer` `config`: API key, the assistant's country (bound to the shop in multistore), language and display currency, and on product pages the product, its combination and the product block's slot and mode. The fields are documented in dialog-ecom, `docs/reference/DEC-3531_storefront-tag-store-modules.md`
- The config declares no `producer`: the tag sends no storefront event for the module, so a shop also running the GTM template does not count its page views twice (the reason DAT-1632 was reverted)
- Appearance (colors, font, borders) comes from the Dialog dashboard; the module declares none (removed in 1.3.0)
- Can be enabled on product pages with instant questions (`ASKDIALOG_ENABLE_PRODUCT_HOOK`, AI button mode with `ASKDIALOG_AI_BUTTON_MODE`)
- Add to cart: the widget calls PrestaShop's CartController itself; `cart-integration.js` still exposes `window.askDialogAddToCart` and `window.askDialogAddCurrentProductToCart`

**Key Files:**
- `askdialog.php`: `hookActionFrontControllerSetMedia` defines `askDialogConfig` and `DIALOG_VARIABLES` (`Media::addJsDef`, no inline script of the module's own) and registers the scripts
- `views/js/dialog-layer.js`: pushes the config, again on the theme's `updatedProduct`, which re-renders the product block's slot
- `views/templates/hook/displayproduct.tpl`: the product block's slot, `#dialog-product-block`, with the product and selected combination

### 3. Public API
- Provides product and catalog data to Dialog AI
- Supports multilingual and multi-country (with tax calculations)
- Protected by public API key

**Key Files:**
- `controllers/front/api.php`: Public API endpoints

### 4. Export Status Monitoring
- Tracks S3 upload status in database
- Allows Dialog admin to monitor export progress
- Four states: init, pending, success, error
- Protected by private API key

**Key Files:**
- `controllers/front/exportstatus.php`: Export status API endpoints
- `src/Repository/ExportLogRepository.php`: Database operations for export logs

## Current Architecture

### Namespace Structure
- Current: `LouisAuthie\Askdialog`
- Target: `Dialog\AskDialog`

### Data Flow
1. **Export**: PrestaShop → DataGenerator → JSON files → S3 (via AskDialogClient)
2. **Frontend**: User interaction → SDK JS → Dialog AI API
3. **API**: Dialog AI → Public API → Product data

### Database Tables
- `askdialog_export_log`: Tracks export status (init, pending, success, error) for S3 uploads
- `askdialog_cart_visitor`: The Dialog visitor of each cart (`id_cart` key, visitor id, consent level `a`/`am`); `date_upd` moves only when the visitor or the level changes
- `askdialog_cart_origin`: The handoff links each cart received (`(id_cart, handoff_ref)` key, origin, date); see Cart handoff

### Configuration (Configuration::get)
- `ASKDIALOG_API_KEY`: Private API key
- `ASKDIALOG_API_KEY_PUBLIC`: Public API key
- `ASKDIALOG_API_URL`: API URL (default: defined in `AskDialog::DIALOG_API_URL`)
- `ASKDIALOG_ENABLE_PRODUCT_HOOK`: Enable on product pages

### Hooks Used
Declared once in `AskDialog::HOOKS`: `install()` registers them, and an upgrade script that changes hooks calls `registerHooks()` (1.0.5, 1.5.0, 1.5.2), which registers them and unregisters `AskDialog::RETIRED_HOOKS` in every shop, so a fresh install and an upgraded one hold the same hooks. A new hook goes in `HOOKS` plus an upgrade script calling `registerHooks()`.
- `actionFrontControllerSetMedia`: Load CSS/JS files and define the storefront tag's config
- `displayProductAdditionalInfo`: Render the product block's slot on product pages
- `displayOrderConfirmation`: Track the order (PostHog PHP, `user_submitted_checkout` with `order_reference`) and render the confirmation template
- `actionCartUpdateQuantityBefore`: Track add to cart events (PostHog PHP)
- `actionCartSave`: Link the saved cart to the Dialog visitor kept in the cookie (`askdialog_cart_visitor`, see Shopper identification)
- Retired: `displayFooterAfter` (1.5.0, the tag now creates the widget container) and `actionValidateOrder` (1.5.2: no method handled it; only installs upgraded through 1.0.5 had it)

### Shopper identification (1.6.0)
Feeds the Shoppers page in Dialog. After the shopper consents, the widget posts `userId`, `consentLevel` (`a` or `am`) and `token` (`prestashop.static_token`) to `controllers/front/identity.php`, which answers 204 (403 on a wrong token, 400 on invalid input). `CartVisitorLink` keeps the visitor in the PrestaShop cookie, links the current cart at once, and `actionCartSave` links every cart saved afterwards. The link is kept per cart, never read from the cookie at order time: `actionValidateOrder` can run from a payment webhook, with no browser. The hook catches `Throwable` and only logs, since it runs on every cart write. `askdialog_cart_origin` (Cart handoff) is a separate table on purpose: it records the links a cart received, while this one follows the shopper's consent on every save.

### Shoppers read (DEC-3924, DEC-3928)
`controllers/front/shoppers.php`, private key: what Dialog reads to build its Shoppers page.

- `getModuleInfo` returns `version` and `actions`: Dialog checks it before turning identification on. `actions` also lists `handoff` (`OTHER_CAPABILITIES`, not dispatched here): Dialog signs cart links only for a module that lists it.
- `getCarts`, `getOrders` and `getCreditSlips` take a cursor, `updatedAfter=<ISO 8601 with offset>&afterId=<id>&limit=<1-500, default 100>`, and return their rows after it in `(date_upd, id)` order, with `nextCursor` to send back as is. PrestaShop dates are local and to the second, so a page never returns the current second, which can still receive writes.
- `getCarts` returns the carts that have a customer and a linked visitor. A cart changes when PrestaShop saves it or when its link changes (the later of the two `date_upd`). Pricing runs in the cart's currency, and a page closes after 60 s (less under a low `max_execution_time`) on the carts priced so far. `total` leaves out shipping, which would call the carrier modules.
- `getOrders` and `getCreditSlips` cover the customers with a linked cart only. A state change updates `ps_orders.date_upd`, so the order comes back on the next read. Each order carries its state id, the state's `paid` flag and whether it is `PS_OS_CANCELED`, `PS_OS_ERROR` or `PS_OS_REFUND`. A credit slip's `amount` is products plus shipping, tax included.
- `getCustomerOrders&idCustomer=<id>` returns that customer's 200 most recent orders, in the `getOrders` shape, and nothing for a customer without a linked cart. Dialog reads it once, when it first links the customer.
- `ps_orders` and `order_slip` have no index on `date_upd`: the order reads start from the linked customers and their `id_customer` index.
- Amounts are decimal strings, tax included. `ChangedCartsReader` and `ShopperOrdersReader` build the pages; `ShopperFormat` writes their dates and amounts.

### Cart handoff (1.7.0, DEC-4029)
Dialog (the ChatGPT plugin) hands a cart over in a signed link to `controllers/front/handoff.php`, `index.php?fc=module&module=askdialog&controller=handoff&p=<p>&s=<s>`, a form that works with friendly URLs on or off. The contract is shared with dialog-ecom and dialog-mcp-server (payload v1); `tests/fixtures/handoff-v1-vectors.json` holds its vectors, copied byte for byte.

- `HandoffPayload::verify()` (`src/Service/CartHandoff/`) checks `s = hex(HMAC-SHA256(p))` under the key `hash_hmac('sha256', 'dialog-cart-handoff-v1', <private key>, true)`, on the received `p`, never a re-encoded JSON. It refuses `v` other than 1, `now > exp + 60`, `exp > now + 960`, and malformed lines: 1 to 50, `id`/`variant` digit strings (`variant` absent = 0, no combination), `qty` 1 to 99. Only `utm_source|medium|campaign|content|term` with values `^[A-Za-z0-9._-]{1,100}$` go onto the redirect.
- A refused link shows "This cart link is invalid or has expired." and creates no cart. Otherwise the controller claims `(id_cart, ref)` with `INSERT IGNORE` before adding lines: a claimed pair adds nothing (reload, double click), a claim that added no line is released, several links into one cart are all kept. Lines that cannot go in as asked get a notice each (unavailable, option missing, personalisation required, minimal quantity raised, stock lowered, out of stock), domain `Modules.Askdialog.Shop`, translated in `translations/` (9.0.3 reads them; 1.7.6.7 shows them in English, as it does the module's admin strings).
- Always redirects to the cart page (`cart`, `action=show`), never the checkout (DEC-3659), with `Cache-Control: no-store`.
- `handoff_ref` is base64url, stored `ascii_bin` to compare case-sensitively. Upgrade 1.7.0 drops and recreates the table: the unreleased DEC-3660 spike (#70) made one keyed on the cart alone. Crediting orders to a handoff is later work; the spike's `actionValidateOrder` stays retired.
- Unit tests: `composer install` then `composer test` (PHPUnit, PHP 8.1+; dev-only, the module ships without vendor/).

### API authentication
Front controllers called by Dialog run as AJAX from their constructor, so PrestaShop skips the storefront header and its `actionFrontControllerSetMedia` hook. `ApiKeyAuthTrait::requireApiKey()` reads the key from `X-Api-Key`, then `Authorization: Token <key>`, then the same in `$_SERVER` (`HTTP_X_API_KEY`, `HTTP_AUTHORIZATION`, `REDIRECT_HTTP_AUTHORIZATION`) for hosts that strip headers, and compares it in constant time: 401 when missing, 403 when wrong. `feed`, `exportstatus`, `logs` and `shoppers` take the private key; `api` takes the public key, which is printed in every storefront page and so never gates customer data.

## Refactoring History

### Appearance settings removed (1.3.0, DEC-3065)

The module used to store colours, border type, capitalization, highlight and font per shop
(`askdialog_appearance` table, "Appearance" tab) and pass them to the widget as `data-*` and
`DIALOG_VARIABLES`. The widget ignores them since the dashboard became the only source of the
assistant's look, so the tab, its forms, the repository, `views/css/all-pages/variables.css` and
the table are gone; `upgrade/upgrade-1.3.0.php` drops the table on existing installs.

### PostHog Analytics Migration to PHP

**Problem:** JavaScript PostHog tracking was unreliable and blocked by ad blockers (uBlock Origin, etc.)

**Solution:** Migrated to server-side PHP tracking using PostHog HTTP API

**Changes:**
- ✅ Created `src/Service/PostHogService.php`: Server-side analytics service
- ✅ Uses Symfony HttpClient for HTTP requests (consistent with AskDialogClient)
- ✅ Registered hooks: `actionCartUpdateQuantityBefore`, `displayOrderConfirmation`
- ✅ Removed old JavaScript files: `posthog.js`, `posthog_order_confirmation.js`
- ✅ Sets `$process_person_profile: false` for GDPR compliance

**Tracked Events:**
1. **user_added_to_cart** (via `actionCartUpdateQuantityBefore`)
   - `productId`: Product ID
   - `variantId`: Combination ID (if applicable)
   - `quantity`: Quantity added
   - `currency`: ISO currency code

2. **user_submitted_checkout** (via `displayOrderConfirmation`)
   - `items`: product, combination, title, quantity, unit price
   - `orderValue`: Total paid
   - `currency`: ISO currency code
   - `order_reference`: Order reference

**Key Features:**
- **Ad-blocker proof**: Server-side tracking cannot be blocked
- **Stable distinct_id**: Priority order - PostHog frontend cookie > customer ID > cart ID > session ID
- **Frontend/Backend sync**: Reads `distinct_id` from PostHog cookie to link backend events with frontend analytics
- **Cookie persistence override**: `posthog-cookie-override.js` forces PostHog to use cookies instead of localStorage
- **Error handling**: Failures logged but don't break user experience
- **GDPR compliant**: No person profiles created

**API Configuration:**
- Endpoint: `https://eu.i.posthog.com/batch/`
- API Key: `phc_EKMR6Jt4OTMEYmoUlz0v58KPwqcFxI7aZCLckpSD8Tv` (the storefront project, shared with the widget)

**Sending (1.5.1):** `capture()` only buffers. One shutdown function sends the request's events as one batch, after the response where the SAPI allows it:
- FPM (`fastcgi_finish_request`) and LiteSpeed (`litespeed_finish_request`, which has no `fastcgi_` alias) end the response first; the batch then has 3 seconds.
- mod_php and CLI cannot: the client still waits on the single batch, capped at 0.5 second.
- `Cookie::write()` must run before the response ends. PrestaShop otherwise writes the cookie from `Cookie::__destruct`, after shutdown functions, and a first add-to-cart loses the shopper's cart.
- The hook fires on every `Cart::updateQty()` with `up`, whoever calls it: `Cart::duplicate()` and third-party checkouts rebuild a cart line by line in one request, so a blocking send there costs one round trip per product.

**Benefits:**
- 100% reliable tracking (no client-side blocking)
- Better data quality (direct from database)
- Simpler codebase (no JavaScript event listeners)

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

## Export Status API

### Overview
The Export Status API allows Dialog admin server to monitor S3 upload progress. All endpoints require authentication with private API key.

### Authentication
```bash
X-Api-Key: {ASKDIALOG_API_KEY}
# or
Authorization: Token {ASKDIALOG_API_KEY}
```

### Base URL
```
https://your-shop.com/module/askdialog/exportstatus
```

### Available Endpoints

#### 1. Get Latest Status
Returns the most recent export status for a specific type.

**Request:**
```bash
GET /module/askdialog/exportstatus?action=getLatestStatus&export_type=catalog
```

**Parameters:**
- `export_type` (optional): catalog or cms (default: catalog)

**Response:**
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

#### 2. Get Export History
Returns recent export history with optional filtering.

**Request:**
```bash
GET /module/askdialog/exportstatus?action=getExportHistory&limit=20&export_type=catalog
```

**Parameters:**
- `limit` (optional): Number of results (default: 10, max: 100)
- `export_type` (optional): Filter by type (catalog, cms)

**Response:**
```json
{
  "status": "success",
  "count": 20,
  "exports": [...]
}
```

#### 3. Get Export by ID
Returns a specific export log by ID.

**Request:**
```bash
GET /module/askdialog/exportstatus?action=getExportById&id=123
```

**Parameters:**
- `id` (required): Export log ID

#### 4. Get Status Summary
Returns count of exports by status.

**Request:**
```bash
GET /module/askdialog/exportstatus?action=getStatusSummary
```

**Response:**
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

#### 5. Cleanup Old Logs (Cron-style)
Deletes export logs older than specified days.

**Request:**
```bash
GET /module/askdialog/exportstatus?action=cleanupOldLogs&days=90
```

**Parameters:**
- `days` (optional): Days to keep (default: 90, min: 7, max: 365)

**Response:**
```json
{
  "status": "success",
  "message": "Export logs older than 90 days have been deleted",
  "days_kept": 90,
  "deleted_count": 15
}
```

### Export Status Flow
1. **init**: Export log created when Dialog API triggers export
2. **pending**: File generation started
3. **success**: Files uploaded to S3 successfully
4. **error**: Export failed (error_message field contains details)

### Multistore Catalog Evolution (2026-05-22)

**Context:** Built on top of `fix/multistore_stock_0`. Branch: `feature/multistore-catalog-export`.

**Changes:**
- ✅ Single unified `catalog.json` generated instead of one per shop
- ✅ Each product now has a `shops` field listing active shops where it's available
- ✅ Condition: `shops` field is only added when `\Shop::isFeatureActive()` returns true — no-op for single-shop installs
- ✅ Stock behavior unchanged (shared stock logic from fix/multistore_stock_0 still applies)

**New methods in ProductRepository:**
- `getAllProductIds()`: returns all active product IDs across all active shops (multistore)
- `getActiveShopsByProductIds(array $productIds)`: returns active shops per product, grouped by id_product
- `findByIdsWithLangAllShops(array $productIds, $idLang)`: loads product data without shop restriction on ps_product_shop

**Changes in ProductExportService:**
- New property `$productShopsData` preloaded per batch
- All product ID queries use `getAllProductIds()` when multistore active
- Product data loading uses `findByIdsWithLangAllShops()` when multistore active (so products from all shops are included, not just the triggering shop's)
- `getProductData()` adds `shops` field only when multistore active

**JSON output (multistore active):**
```json
{
  "id": 123,
  "title": "Product Name",
  "shops": [
    {"id": 1, "name": "Boutique FR"},
    {"id": 2, "name": "Boutique B2B"}
  ],
  ...
}
```

**Design decisions:**
- Price/stock at root level remain from triggering shop context (acceptable, stock is mutualized)
- `shops[]` contains only `id` + `name` — sufficient for Dialog AI to filter by shop without per-shop stock complexity
- Language/content data uses the triggering shop's ID as source for `product_lang`

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
- Use type hints where possible (PHP 7.4+)
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

- Module is compatible with PrestaShop 1.7.6 to 8.x (check `ps_versions_compliancy`); it must boot WITHOUT vendor/ (source checkouts are vendorless — askdialog.php registers its own PSR-4 autoloader)
- **HTTP calls must go through `Service\Http\HttpTransport`, never Symfony directly.** `Symfony\Component\HttpClient` and `Symfony\Component\Mime` are Symfony 4.3+ components: PrestaShop only ships them from 8.0, and 1.7.6–1.7.8 (Symfony 3.4) do not have them. Referencing them there raises `Class not found` — a fatal, not an exception, which escapes `catch (Exception)` and takes the front-office page down (it did, on an add-to-cart hook). HttpTransport uses Symfony when available and falls back to cURL otherwise, so the module works on every supported version. Front-office hooks additionally catch `\Throwable`, not `\Exception`, so analytics can never break a page. In the cURL path, `timeout` is an IDLE timeout (CONNECTTIMEOUT + LOW_SPEED_*), never CURLOPT_TIMEOUT — Symfony's `max_duration` is unlimited by default, and a total cap would abort a large catalogue upload that is still progressing; a caller that wants a cap sets `max_duration` itself, as the PostHog batch does. TLS verification is always on and cannot be turned off.
- Uses HttpClient for API calls
- Symfony YAML component for configuration

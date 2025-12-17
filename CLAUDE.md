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
- `src/Service/DataGenerator.php`: Generates JSON data for products and CMS pages
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

## TODO: Cleanup Legacy Elements from 1.6

### 1. Rename Namespace: LouisAuthie → Dialog\AskDialog
**Files to modify:**
- `composer.json`: Update autoload PSR-4
- `askdialog.php`: Update use statements
- `src/Service/AskDialogClient.php`: Update namespace declaration
- `src/Service/DataGenerator.php`: Update namespace declaration
- `controllers/front/feed.php`: Update use statements
- `controllers/front/api.php`: Update use statements

After changes: Run `composer dump-autoload`

### 2. Configure Composer Autoload
- Ensure PSR-4 autoloading is properly configured
- Verify vendor directory structure
- Add `.gitignore` rules for vendor if missing

### 3. Remove Deprecated Hooks
**Audit hooks in `install()` method:**
- Check PrestaShop 8.x compatibility
- Remove hooks specific to PrestaShop 1.6
- Verify `actionFrontControllerInitBefore` usage (CORS handling is commented)

### 4. Rename Translation Domain
**Current:** `Modules.AskDialog.Admin`
**Target:** To be defined (standardize across module)

**Files to check:**
- All `$this->trans()` calls in `askdialog.php`
- Template files if they use translations

### 5. Remove Vendor from Git Versioning
- Add `/vendor/` to `.gitignore`
- Remove vendor directory from git tracking: `git rm -r --cached vendor`
- Commit the `.gitignore` update
- Document composer install requirement in README

### 7. Create/Update README.md
- Installation instructions
- Configuration guide
- Development setup (composer install, npm install, build)
- API documentation
- Hook descriptions

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

## Git Workflow

- Branch naming: `feature/{feature-name}` or `fix/{issue-name}`
- Commit messages: Conventional Commits format (in English)
  - `feat: add new feature`
  - `fix: resolve bug`
  - `refactor: restructure code`
  - `docs: update documentation`
  - `chore: update dependencies`

## Notes

- Module is compatible with PrestaShop 1.6 to 8.x (check `ps_versions_compliancy`)
- Uses Guzzle HTTP client for API calls
- Symfony YAML component for configuration
- Current branch: `feature/remove_1_6_legacy`

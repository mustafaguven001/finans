# Güven Hijyen -- B2B Product Catalog

WordPress/WooCommerce-based B2B corporate website for Güven Hijyen, a professional cleaning and hygiene products distributor. The site serves as a digital product catalog with structured Request for Quote (RFQ) workflows -- no public pricing, no cart, no checkout.

## Technology Stack

- **CMS**: WordPress 6.0+
- **E-commerce Framework**: WooCommerce 7.0+ (product data model only -- cart/checkout disabled)
- **PHP**: 7.4+
- **Custom Plugin**: `guvenhijyen-core` -- all business logic
- **Database**: MySQL 5.7+ / MariaDB 10.3+

## Directory Structure

```
finans/
  docs/
    ARCHITECTURE.md           Technical architecture document
    DEPLOYMENT-RUNBOOK.md     Production deployment runbook
    IMPORT-GUIDE.md           Master XLSX Import System guide
    QA-CHECKLIST.md           Comprehensive QA checklist
  import/
    images/
      products/               Product images (featured + gallery)
      categories/             Category banner images
      brands/                 Brand logos
      sectors/                Sector header images
      blog/                   Blog post featured images
      corporate/              Company-level images
    documents/
      technical/              Technical Data Sheets
      safety/                 Safety Data Sheets (SDS/MSDS)
      certificates/           ISO, HACCP, halal certificates
      catalogs/               Product catalogs, brochures
  migration/
    migration-config.example.php   Example migration configuration
    run-migration.php              CLI migration runner
  scripts/
    backup.sh                 Database and file backup script
    post-deploy.sh            Post-deployment verification script
  tests/                      Test files
  wp-content/
    plugins/
      guvenhijyen-core/       Custom plugin -- all business logic
        guvenhijyen-core.php  Plugin bootstrap
        includes/
          class-company-settings.php    Company info management
          class-procurement.php         Procurement status (active/unavailable/discontinued)
          class-publication-rules.php   Frontend visibility gating
          class-brand-manager.php       Brand taxonomy with readiness rules
          class-sector-manager.php      Sector taxonomy with readiness rules
          class-compatibility.php       Product relationship management
          class-document-system.php     Document CPT and relations
          class-seo-hooks.php           Schema.org and meta tag output
          class-sales-unit.php          Sales unit definitions
          class-redirect-manager.php    URL redirect management (301/302/410)
          class-blog-manager.php        Blog quality gating and relationships
        import/
          class-import-error-report.php Import audit trail and error tracking
```

## Setup Instructions

### Prerequisites

1. WordPress 6.0+ installed and configured
2. WooCommerce 7.0+ installed and activated
3. PHP 7.4+ with `mbstring`, `xml`, and `zip` extensions
4. MySQL 5.7+ or MariaDB 10.3+
5. WP-CLI installed (for migration and maintenance scripts)

### Installation

1. Clone this repository:
   ```bash
   git clone <repository-url> finans
   ```

2. Copy the plugin to your WordPress installation:
   ```bash
   cp -r wp-content/plugins/guvenhijyen-core /path/to/wordpress/wp-content/plugins/
   ```

3. Activate the plugin in WP Admin (Plugins page) or via WP-CLI:
   ```bash
   wp plugin activate guvenhijyen-core
   ```

4. Configure company settings in WP Admin under Güven Hijyen > Company Settings.

### Theme Setup

The site requires a compatible theme with WooCommerce template overrides. The theme should provide:

- Product archive grid with filter sidebar
- Single product template with RFQ button (no price/cart)
- Sector landing page template
- RFQ form templates (single product, quote list, general)
- Quote list page template
- 410 Gone error template (`410.php`)

## Development Workflow

1. Work on a feature branch
2. Test changes on staging environment
3. Run the QA checklist (`docs/QA-CHECKLIST.md`) on staging
4. Merge to main after approval
5. Deploy using the deployment runbook (`docs/DEPLOYMENT-RUNBOOK.md`)
6. Run post-deployment verification (`scripts/post-deploy.sh`)

## Import System

The Master XLSX Import System is the primary method for populating the product catalog. It uses a structured workbook with 15 sheets covering products, categories, brands, attributes, sectors, documents, blog posts, and redirects.

### Quick Start

1. Prepare the import workbook following `docs/IMPORT-GUIDE.md`
2. Place image and document files in the `import/` directory structure
3. Copy and configure the migration config:
   ```bash
   cp migration/migration-config.example.php migration/migration-config.php
   # Edit migration-config.php with your paths
   ```
4. Run a dry run to validate:
   ```bash
   wp eval-file migration/run-migration.php --config=migration/migration-config.php --mode=dry_run
   ```
5. Fix any errors reported, then run the import:
   ```bash
   wp eval-file migration/run-migration.php --config=migration/migration-config.php --mode=import
   ```

See `docs/IMPORT-GUIDE.md` for complete field definitions, validation rules, and troubleshooting.

## Deployment

See `docs/DEPLOYMENT-RUNBOOK.md` for:

- Pre-deployment checklist
- Backup procedures
- Step-by-step deployment instructions
- Post-deployment verification
- Rollback procedures

Quick commands:

```bash
# Backup
bash scripts/backup.sh --wp-path=/var/www/html

# Post-deployment verification
bash scripts/post-deploy.sh --site-url=https://guvenhijyen.com
```

## Documentation

| Document | Description |
|----------|-------------|
| `docs/ARCHITECTURE.md` | System architecture, data model, plugin design |
| `docs/IMPORT-GUIDE.md` | Import system guide with field definitions and error codes |
| `docs/DEPLOYMENT-RUNBOOK.md` | Production deployment and rollback procedures |
| `docs/QA-CHECKLIST.md` | Comprehensive QA testing checklist |

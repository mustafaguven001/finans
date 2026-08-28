# Güven Hijyen -- Technical Architecture

## System Overview

Güven Hijyen is a B2B corporate product catalog website built on WordPress + WooCommerce. It serves as a digital catalog for professional cleaning and hygiene products, enabling business customers to browse products, review technical documentation, and submit Request for Quote (RFQ) inquiries. There is no e-commerce checkout flow -- the site operates as a catalog with structured RFQ workflows.

### Key Design Decisions

- **No public pricing**: Products do not display prices. Pricing is handled offline through the RFQ process.
- **No cart/checkout**: WooCommerce cart and checkout are disabled. The quote list replaces the cart concept.
- **Publication gating**: Products must pass multiple readiness checks before appearing on the frontend.
- **Structured data over free-form**: Product relationships, sector assignments, and document links are stored in structured meta rather than free-text fields.
- **Import-first content**: The primary content pipeline is the Master XLSX Import System, not manual WordPress entry.

---

## Technology Stack

| Layer | Technology | Version | Purpose |
|-------|-----------|---------|---------|
| CMS | WordPress | 6.0+ | Content management, user roles, admin interface |
| E-commerce Framework | WooCommerce | 7.0+ | Product data model, taxonomies, product templates |
| PHP | PHP | 7.4+ | Server-side logic |
| Database | MySQL / MariaDB | 5.7+ / 10.3+ | Data storage |
| Custom Plugin | guvenhijyen-core | 1.0.0 | All custom business logic |
| Frontend | Custom Theme | -- | Tailored theme (child theme or custom) |
| SEO | Yoast SEO or Rank Math | -- | Meta tags, sitemaps, schema defaults |
| Forms | Custom RFQ system | -- | Built into guvenhijyen-core |
| Search | WordPress native or SearchWP/Relevanssi | -- | Product search with Turkish support |
| Email | WP Mail SMTP or similar | -- | Transactional email delivery |
| Caching | Object cache + page cache | -- | Performance optimization |

---

## Plugin Architecture: guvenhijyen-core

The `guvenhijyen-core` plugin encapsulates all custom business logic. It is structured as a set of manager classes, each responsible for a specific domain.

### Module Map

```
wp-content/plugins/guvenhijyen-core/
  guvenhijyen-core.php              Main plugin file, bootstrap
  includes/
    class-company-settings.php      Company info management (address, phone, social)
    class-procurement.php           Procurement status (active/unavailable/discontinued)
    class-publication-rules.php     Frontend visibility gating logic
    class-brand-manager.php         Brand taxonomy meta, readiness rules
    class-sector-manager.php        Sector taxonomy meta, readiness rules
    class-compatibility.php         Product relationship management
    class-document-system.php       Document CPT and relations
    class-seo-hooks.php             Schema.org output, meta tag hooks
    class-sales-unit.php            Sales unit definitions and quantity rules
    class-redirect-manager.php      URL redirect management
    class-blog-manager.php          Blog quality gating and relationship meta
  import/
    class-import-error-report.php   Import audit trail and error tracking
```

### Initialization Flow

1. WordPress fires `plugins_loaded`.
2. Plugin checks WooCommerce is active. If not, shows admin notice and stops.
3. Loads all class files via `require_once`.
4. Calls `::init()` on each manager class, which hooks into WordPress actions/filters.
5. Separately, `init` action registers custom taxonomies and post types.

### Class Pattern

All manager classes follow a consistent pattern:

- Static class with `public static function init(): void` entry point
- `init()` registers WordPress hooks (actions and filters)
- No constructor, no instances -- pure static to align with WordPress hook system
- Nonce verification on all form saves
- Capability checks before data modifications
- Sanitization of all input data

---

## Data Model

### Products (WooCommerce)

Products use the WooCommerce `product` post type with custom meta:

| Meta Key | Type | Description |
|----------|------|-------------|
| `_sku` | string | WooCommerce native SKU |
| `_gh_procurement_status` | enum | `active`, `temporarily_unavailable`, `discontinued` |
| `_gh_product_relationships` | array | Compatibility/relationship links to other products |
| `_gh_sales_unit` | string | Unit of sale (adet, koli, bidon, etc.) |
| `_gh_minimum_quantity` | int | Minimum order quantity |
| `_gh_quantity_step` | int | Quantity increment step |
| `_gh_migration_key` | string | Import traceability key (e.g., `PROD-001`) |
| `_gh_source_product_id` | string | ID from source system |

### Variations (WooCommerce)

Product variations use `product_variation` post type (WooCommerce native). Custom meta mirrors parent product meta with inheritance fallback.

### Categories (WooCommerce)

Uses `product_cat` taxonomy (WooCommerce native). Additional meta:

| Meta Key | Type | Description |
|----------|------|-------------|
| `display_order` | int | Sort order within parent level |

Standard WooCommerce features: hierarchical, image support, description.

### Brands

Custom taxonomy: `product_brand` (registered by WooCommerce or theme).

| Meta Key | Type | Description |
|----------|------|-------------|
| `gh_brand_logo` | int | Attachment ID for brand logo |
| `gh_brand_description` | text | Brand description |
| `gh_brand_website` | url | Brand website URL |
| `gh_brand_verified` | bool | Whether brand is verified |
| `gh_brand_ready` | bool | Computed: verified + logo + description |

**Frontend visibility rule**: Only brands with `gh_brand_ready=1` appear in frontend queries.

### Sectors

Custom taxonomy: `product_sector` (registered by guvenhijyen-core).

| Meta Key | Type | Description |
|----------|------|-------------|
| `gh_sector_description` | text | Sector description |
| `gh_sector_image` | int | Attachment ID for sector image |
| `gh_sector_icon` | string | CSS class or SVG for sector icon |
| `gh_sector_ready` | bool | Computed: description + (image or icon) |

**Rewrite slug**: `/sektor/`

**Frontend visibility rule**: Only sectors with `gh_sector_ready=1` appear in frontend queries.

### Documents

Custom post type: `gh_document` (registered by guvenhijyen-core).

| Meta Key | Type | Description |
|----------|------|-------------|
| `_gh_doc_type` | enum | `technical_data_sheet`, `safety_data_sheet`, `certificate`, `catalog`, `user_manual`, `brochure` |
| `_gh_doc_file` | int | Attachment ID for the document file |
| `_gh_doc_version` | string | Document version |
| `_gh_doc_date` | date | Document issue date |
| `_gh_doc_code` | string | Internal document reference |
| `_gh_doc_relations` | array | Linked entities (products by SKU, categories, brands, sectors) |

**Visibility**: Documents are not public post types. They are accessed through their related product/category pages as download links.

### Product Relationships (Compatibility)

Stored as serialized array in `_gh_product_relationships` post meta on each product.

```php
[
    ['product_id' => 456, 'type' => 'compatible_consumable'],
    ['product_id' => 789, 'type' => 'alternative'],
]
```

**Relationship types:**

| Type | Direction | Description |
|------|-----------|-------------|
| `compatible_consumable` | Directional | Source is consumable for target device |
| `compatible_device` | Directional | Source works with target device |
| `accessory` | Directional | Source is accessory for target |
| `alternative` | Symmetric | Both products stored as alternatives of each other |
| `complementary` | Symmetric | Both products stored as complements of each other |

Symmetric types are stored in both directions automatically.

---

## RFQ Domain Design

### RFQ Entry Points

1. **Single Product RFQ**: Button on product detail page. Pre-populates with product name, SKU, and quantity.
2. **Quote List RFQ**: User builds a list of products (stored in browser session), then submits RFQ for the entire list.
3. **General RFQ**: Form at `/teklif-talebi/` for open-ended inquiries not tied to specific products.

### RFQ Data Structure

| Field | Source | Description |
|-------|--------|-------------|
| `company_name` | Form input | Customer's company name |
| `contact_name` | Form input | Contact person |
| `email` | Form input | Contact email |
| `phone` | Form input | Contact phone |
| `products` | Pre-populated or quote list | Array of {sku, name, quantity} |
| `sector` | Form input (optional) | Customer's industry |
| `message` | Form input (optional) | Additional notes |
| `source` | System | `single_product`, `quote_list`, or `general` |
| `page_url` | System | URL where the RFQ was initiated |
| `submitted_at` | System | Timestamp |

### Quote List Architecture

The quote list is a client-side feature:

- Products are stored in `localStorage` as an array of `{sku, name, quantity}`.
- A JavaScript module manages add/remove/update operations.
- The header displays a quote list counter badge.
- The quote list page renders the stored items and provides the RFQ form.
- No server-side state is required for the quote list itself.

### RFQ Processing

1. Form submitted via AJAX to a custom REST endpoint or `admin-post.php` handler.
2. Server validates: CSRF nonce, required fields, email format, product SKU existence.
3. RFQ data stored in a custom database table or custom post type.
4. Admin notification email sent with full RFQ details.
5. Customer confirmation email sent (if configured).
6. Success response returned to frontend.

---

## Import Pipeline Design

### Architecture

The import pipeline is a PHP-based ETL (Extract-Transform-Load) system designed to run via WP-CLI.

```
                    +-----------------------+
                    |   XLSX Source File     |
                    +-----------+-----------+
                                |
                    +-----------v-----------+
                    |   Sheet Parser        |
                    |   (PhpSpreadsheet)    |
                    +-----------+-----------+
                                |
                    +-----------v-----------+
                    |   Schema Validator    |
                    |   per-sheet rules     |
                    +-----------+-----------+
                                |
                    +-----------v-----------+
                    |   Dependency Resolver |
                    |   process ordering    |
                    +-----------+-----------+
                                |
               +-------+-------+-------+
               |               |               |
    +----------v--+  +---------v---+  +--------v----+
    | Category    |  | Brand       |  | Attribute   |
    | Processor   |  | Processor   |  | Processor   |
    +----------+--+  +---------+---+  +--------+----+
               |               |               |
               +-------+-------+-------+
                                |
                    +-----------v-----------+
                    |   Product Processor   |
                    +-----------+-----------+
                                |
               +-------+-------+-------+-------+
               |       |       |       |       |
            Variations Attrs Compat Sectors Documents
               |       |       |       |       |
               +---+---+---+---+---+---+---+---+
                                |
                    +-----------v-----------+
                    |   Media Processor     |
                    |   (images, documents) |
                    +-----------+-----------+
                                |
                    +-----------v-----------+
                    |   Reconciliation      |
                    |   Report Generator    |
                    +-----------------------+
```

### Error Tracking

All import operations log to two custom database tables:

**`{prefix}gh_import_audit`**: One row per import run.
- `import_id`: Unique identifier
- `source_file`, `file_hash`: Source traceability
- `total_rows`, `created`, `updated`, `skipped`, `manual_review`, `failed`: Counters
- `mode`: `dry_run` or `import`
- `status`: `running`, `completed`, `failed`

**`{prefix}gh_import_errors`**: One row per error/warning/info.
- `import_id`: Links to audit row
- `sheet_name`, `row_number`: Location of the issue
- `error_code`, `message`, `recommended_action`: Error details
- `severity`: `error`, `warning`, `info`

---

## Frontend Architecture

### Template Hierarchy

The theme uses WordPress template hierarchy with WooCommerce template overrides:

```
theme/
  woocommerce/
    archive-product.php          Product archive (grid with filters)
    single-product.php           Product detail page
    single-product/
      related.php                Related products section
      tabs/                      Product tabs (description, attributes, documents)
  template-parts/
    rfq/
      form-single.php            Single product RFQ form
      form-quote-list.php        Quote list RFQ form
      form-general.php           General RFQ form
    product/
      card.php                   Product card for grids
      compatibility.php          Compatible products section
    sector/
      landing.php                Sector landing page template
    blog/
      card.php                   Blog post card
  page-templates/
    page-rfq.php                 RFQ page template
    page-contact.php             Contact page template
    page-quote-list.php          Quote list page template
```

### JavaScript Architecture

Client-side JavaScript is minimal and focused:

- **Quote List Manager**: `localStorage`-based product list management
- **RFQ Form Handler**: AJAX form submission with validation
- **WhatsApp Integration**: Dynamic WhatsApp link with page-context pre-fill
- **Search Enhancement**: Autocomplete/suggestions (if search plugin supports it)
- **Product Image Gallery**: Lightbox/zoom on product images

No SPA framework. Server-rendered pages with progressive enhancement.

### CSS Architecture

- Utility-first or component-based CSS (depending on theme choice)
- Mobile-first responsive design
- CSS custom properties for brand colors and spacing
- No CSS frameworks in production output (compiled/purged if using Tailwind during development)

---

## SEO Strategy

### Schema.org Markup

| Page Type | Schema Type | Key Properties |
|-----------|------------|----------------|
| Homepage | Organization + LocalBusiness | name, telephone, email, address, sameAs |
| Product Detail | Product | name, sku, description, image, brand, category (no price/offers) |
| Product Archive | CollectionPage | name, description |
| Category Page | CollectionPage | name, description |
| Brand Page | Brand | name, description, logo, url |
| Blog Post | Article | headline, datePublished, author, image |
| Contact Page | ContactPage + Organization | -- |

### URL Structure

| Content Type | URL Pattern | Example |
|-------------|------------|---------|
| Product | `/urunler/{product-slug}/` | `/urunler/endustriyel-bulasik-deterjani-20l/` |
| Category | `/urun-kategorisi/{category-slug}/` | `/urun-kategorisi/bulasik-makinesi-kimyasallari/` |
| Brand | `/marka/{brand-slug}/` | `/marka/kiehl/` |
| Sector | `/sektor/{sector-slug}/` | `/sektor/otel-ve-konaklama/` |
| Blog Post | `/bilgi-merkezi/{post-slug}/` | `/bilgi-merkezi/mutfak-hijyen-rehberi/` |
| RFQ | `/teklif-talebi/` | `/teklif-talebi/` |

### Meta Tags

- Every public page has a unique `<title>` and `<meta name="description">`.
- Product pages: `{Product Name} - {Brand} | Güven Hijyen`
- Category pages: `{Category Name} | Güven Hijyen Ürün Kataloğu`
- Auto-generated from content if not manually set.

### Redirect Strategy

- Custom redirect manager table (`gh_redirects`) handles 301/302/410 redirects.
- Hooks into `template_redirect` early (priority 1) for performance.
- Legacy WooCommerce shop pages (`/magaza/`, `/sepetim/`, `/checkout/`, `/hesabim/`) handled specifically.
- Import system bulk-loads redirects from the `14_REDIRECTS` sheet.
- Admin UI for viewing, editing, and monitoring redirect hit counts.

---

## Security Architecture

### Authentication and Authorization

- WordPress native authentication system
- Admin area restricted to authorized users
- No public user registration (B2B -- accounts created by administrators)
- RFQ forms do not require login (public submission)
- Nonce verification on all form submissions (admin and public)

### Input Handling

- All user input sanitized using WordPress sanitization functions
- Database queries use `$wpdb->prepare()` for parameterized queries
- File uploads validated by MIME type and extension
- Import system sanitizes all XLSX cell values before database insertion

### File Security

- `wp-config.php` permissions: 600
- `DISALLOW_FILE_EDIT` = true
- Directory listing disabled via `.htaccess`
- Direct PHP execution blocked in `wp-content/uploads/`
- `.git/` directory blocked from web access

### Headers

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- `Referrer-Policy: strict-origin-when-cross-origin`

### API Security

- XML-RPC disabled
- REST API user enumeration blocked for unauthenticated requests
- Application passwords disabled unless explicitly needed

---

## Performance Strategy

### Caching Layers

1. **Object Cache**: Redis or Memcached for `wp_cache` API
2. **Transient Cache**: Database or object cache for computed values (brand readiness, sector readiness)
3. **Page Cache**: Full-page HTML cache for anonymous visitors
4. **CDN**: Static assets (CSS, JS, images) served via CDN
5. **OPcache**: PHP bytecode caching

### Database Optimization

- Indexes on all meta keys used in `meta_query` (procurement status, readiness flags)
- Custom tables for high-write operations (import audit, import errors, redirects)
- Minimal autoloaded options
- Transient-based caching for expensive taxonomy queries

### Frontend Optimization

- Images lazy-loaded below the fold
- WebP format served where browser supports it
- Critical CSS inlined, remaining CSS deferred
- JavaScript deferred or loaded at end of body
- Font subsetting for Turkish character support
- No unnecessary third-party scripts

### Targets

| Metric | Target |
|--------|--------|
| TTFB | < 800ms |
| LCP | < 2.5s |
| FID/INP | < 200ms |
| CLS | < 0.1 |
| Total page weight | < 3MB |

---

## Integration Points

| System | Integration Method | Purpose |
|--------|-------------------|---------|
| Email (SMTP) | WP Mail SMTP plugin | RFQ notifications, system emails |
| WhatsApp | Client-side deep link | Quick customer communication |
| Google Search Console | Sitemap submission, verification meta tag | SEO monitoring |
| Google Analytics / Tag Manager | Script injection via theme or plugin | Traffic analytics |
| Import Pipeline (XLSX) | WP-CLI command | Bulk data import |
| Backup System | Shell scripts + cron | Database and file backups |
| Monitoring | Uptime service (external) | Availability monitoring |

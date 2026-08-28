# Master XLSX Import System Guide

## Overview

The Güven Hijyen Master XLSX Import System is a structured pipeline for migrating product catalog data, media assets, documents, blog content, and SEO redirects into the WordPress/WooCommerce installation. It uses a single `.xlsx` workbook with 15 purpose-specific sheets that feed into a validation, review, and publication pipeline.

The system supports two modes:

- **Dry Run** (`dry_run`): Validates all data without writing to the database. Produces an error report identifying issues that must be resolved before a live import.
- **Import** (`import`): Validates and writes data to WordPress. Records created/updated/skipped/failed counts per sheet.

Every import run is assigned a unique `import_id` (e.g., `imp_a1b2c3d4-...`) and tracked in the `{prefix}gh_import_audit` table with full error details in `{prefix}gh_import_errors`.

---

## Workbook Structure

The workbook contains 15 sheets, processed in dependency order:

| Sheet # | Sheet Name | Purpose | Dependency |
|---------|-----------|---------|------------|
| 01 | `01_PRODUCTS` | Simple and parent product definitions | Categories, Brands |
| 02 | `02_VARIATIONS` | Product variations (children of parent SKUs) | Products |
| 03 | `03_CATEGORIES` | Product category hierarchy | None |
| 04 | `04_BRANDS` | Brand/manufacturer definitions | None |
| 05 | `05_ATTRIBUTES` | Global product attributes and their values | None |
| 06 | `06_PRODUCT_ATTRIBUTES` | Per-product attribute assignments | Products, Attributes |
| 07 | `07_COMPATIBILITY` | Product relationship mappings | Products |
| 08 | `08_SECTORS` | Industry sector definitions | None |
| 09 | `09_PRODUCT_SECTORS` | Product-to-sector assignments | Products, Sectors |
| 10 | `10_DOCUMENTS` | Technical documents, certificates, catalogs | None |
| 11 | `11_DOCUMENT_RELATIONS` | Link documents to products/categories/brands/sectors | Documents, target entities |
| 12 | `12_IMAGES` | Image file references and metadata | Target entities |
| 13 | `13_BLOG` | Blog/knowledge center posts | Categories (optional) |
| 14 | `14_REDIRECTS` | URL redirect rules (301/302/410) | None |
| 15 | `15_IMPORT_ERRORS` | Output only -- auto-populated during import | N/A |

**Processing order**: 03 -> 04 -> 05 -> 08 -> 01 -> 02 -> 06 -> 07 -> 09 -> 10 -> 11 -> 12 -> 13 -> 14

---

## Sheet Field Definitions

### 01_PRODUCTS

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `migration_key` | Required | `PROD-{number}` | Unique across workbook. Used for cross-referencing. | `PROD-001` |
| `source_product_id` | Optional | Integer or string | ID from the source system, stored as meta for traceability. | `4521` |
| `existing_wp_post_id` | Optional | Integer | If updating an existing WP product, provide its post ID. Leave blank for new products. | `1234` |
| `product_name` | Required | Text (max 200 chars) | Must not be empty. No HTML. Turkish characters allowed. | `Endüstriyel Bulaşık Makinesi Deterjanı 20L` |
| `sku` | Required | Alphanumeric + hyphens (max 64 chars) | Unique across all products. Case-insensitive uniqueness check. | `GH-DET-BM-020` |
| `slug` | Optional | Lowercase, hyphens only | Auto-generated from `product_name` if blank. Must be unique. | `endustriyel-bulasik-makinesi-deterjani-20l` |
| `product_type` | Required | `simple` or `variable` | Must be one of the two values. | `simple` |
| `parent_sku` | Conditional | Valid SKU reference | Required if this row is a grouped child. Must reference an existing SKU in this sheet. | `GH-DET-BM` |
| `short_description` | Required | Text (max 500 chars) | Plain text or limited HTML (`<strong>`, `<em>`, `<br>`). No full HTML documents. | `Profesyonel bulaşık makineleri için konsantre deterjan.` |
| `long_description` | Required | Text | HTML allowed. Must contain substantive product information. | Full product description with features, usage instructions. |
| `category` | Required | Text | Must match a `category_name` in `03_CATEGORIES` or an existing WP category. | `Bulaşık Makinesi Kimyasalları` |
| `subcategory` | Optional | Text | Must match a `category_name` that has `parent_category` set. If provided, `category` must be its parent. | `Deterjanlar` |
| `brand` | Required | Text | Must match a `brand_name` in `04_BRANDS` or an existing WP brand term. | `Güven Hijyen` |
| `sales_unit` | Required | Enum | One of: `adet`, `koli`, `paket`, `palet`, `kg`, `litre`, `metre`, `rulo`, `kutu`, `galon`, `bidon`, `set`, `çift` | `bidon` |
| `minimum_quantity` | Optional | Positive integer | Defaults to 1 if blank. Must be >= 1. | `1` |
| `quantity_step` | Optional | Positive integer | Defaults to 1 if blank. Must be >= 1. Minimum quantity must be divisible by step. | `1` |
| `procurement_status` | Optional | Enum | One of: `active`, `temporarily_unavailable`, `discontinued`. Blank means needs review. | `active` |
| `featured_image` | Required | Filename | Must reference a file in `import/images/products/`. Extension must be `.jpg`, `.jpeg`, `.png`, or `.webp`. | `GH-DET-BM-020-main.jpg` |
| `gallery_images` | Optional | Pipe-separated filenames | Each must exist in `import/images/products/`. Maximum 8 images. | `GH-DET-BM-020-2.jpg\|GH-DET-BM-020-3.jpg` |
| `publication_status` | Optional | Enum | One of: `draft`, `pending`, `publish`. Defaults to `draft`. | `draft` |
| `seo_title` | Optional | Text (max 60 chars) | If blank, generated from product name + brand. | `Endüstriyel Bulaşık Deterjanı 20L - Güven Hijyen` |
| `meta_description` | Optional | Text (max 160 chars) | If blank, generated from short description. | `Profesyonel bulaşık makineleri için 20L konsantre deterjan. Güven Hijyen kalitesiyle.` |

### 02_VARIATIONS

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `parent_sku` | Required | Valid SKU | Must reference a product with `product_type=variable` in `01_PRODUCTS`. | `GH-DET-BM` |
| `variation_sku` | Required | Alphanumeric + hyphens | Unique across all SKUs (products and variations). | `GH-DET-BM-005` |
| `variation_name` | Required | Text (max 200 chars) | Descriptive name for this variation. | `Bulaşık Makinesi Deterjanı 5L` |
| `attribute_1_name` | Required | Text | Must reference an attribute defined in `05_ATTRIBUTES`. | `Hacim` |
| `attribute_1_value` | Required | Text | Must be a valid value for the named attribute. | `5L` |
| `attribute_2_name` | Optional | Text | If provided, must reference a valid attribute. | `Konsantrasyon` |
| `attribute_2_value` | Conditional | Text | Required if `attribute_2_name` is provided. | `Standart` |
| `sales_unit` | Optional | Enum | Inherits from parent if blank. Same enum as products. | `bidon` |
| `minimum_quantity` | Optional | Positive integer | Inherits from parent if blank. | `1` |
| `quantity_step` | Optional | Positive integer | Inherits from parent if blank. | `1` |
| `procurement_status` | Optional | Enum | Inherits from parent if blank. Same values as products. | `active` |
| `featured_image` | Optional | Filename | File in `import/images/products/`. Inherits parent image if blank. | `GH-DET-BM-005-main.jpg` |

### 03_CATEGORIES

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `category_name` | Required | Text (max 200 chars) | Unique within its parent level. | `Bulaşık Makinesi Kimyasalları` |
| `parent_category` | Optional | Text | Must reference another `category_name` in this sheet (or existing WP category). Blank = top-level. | `Endüstriyel Temizlik` |
| `slug` | Optional | Lowercase, hyphens | Auto-generated from `category_name` if blank. | `bulasik-makinesi-kimyasallari` |
| `description` | Required | Text | Category description displayed on archive pages. | `Profesyonel bulaşık makineleri için deterjan, parlatıcı ve kireç çözücü ürünler.` |
| `image` | Optional | Filename | File in `import/images/categories/`. | `cat-bulasik-kimyasallari.jpg` |
| `display_order` | Optional | Integer (0-999) | Sort order within parent. Lower numbers display first. Defaults to 0. | `10` |
| `seo_title` | Optional | Text (max 60 chars) | Auto-generated from category name if blank. | `Bulaşık Makinesi Kimyasalları - Güven Hijyen` |
| `meta_description` | Optional | Text (max 160 chars) | Auto-generated from description if blank. | `Endüstriyel bulaşık makineleri için profesyonel temizlik kimyasalları.` |

### 04_BRANDS

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `brand_name` | Required | Text (max 100 chars) | Unique. | `Kiehl` |
| `slug` | Optional | Lowercase, hyphens | Auto-generated from `brand_name` if blank. | `kiehl` |
| `description` | Required | Text | Brand description for brand archive pages. | `Kiehl, Almanya merkezli profesyonel temizlik ve hijyen ürünleri üreticisi.` |
| `logo` | Required | Filename | File in `import/images/brands/`. PNG or SVG recommended. | `brand-kiehl-logo.png` |
| `website` | Optional | URL | Must be a valid URL if provided. | `https://www.kiehl-group.com` |
| `verified` | Required | `yes` or `no` | Brands marked `no` will not appear on frontend. | `yes` |
| `ready` | Auto | Computed | System-computed. Brand is ready when: verified=yes, logo exists, description is not empty. | (auto-computed) |

### 05_ATTRIBUTES

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `attribute_name` | Required | Text (max 100 chars) | Unique. Maps to WooCommerce global attribute. | `Hacim` |
| `attribute_slug` | Optional | Lowercase, hyphens | Auto-generated from `attribute_name` if blank. Max 28 chars (WooCommerce limit). | `hacim` |
| `type` | Required | `select` or `text` | `select`: predefined values; `text`: free-form input. | `select` |
| `values` | Conditional | Pipe-separated text | Required if `type=select`. Each value max 100 chars. | `1L\|5L\|10L\|20L\|30L` |
| `display_order` | Optional | Integer (0-999) | Sort order on product pages. Defaults to 0. | `5` |

### 06_PRODUCT_ATTRIBUTES

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `sku` | Required | Valid SKU | Must match a product SKU in `01_PRODUCTS`. | `GH-DET-BM-020` |
| `attribute_name` | Required | Text | Must match an `attribute_name` in `05_ATTRIBUTES`. | `Hacim` |
| `attribute_value` | Required | Text | If attribute `type=select`, must be one of its defined values. | `20L` |
| `visible` | Optional | `yes` or `no` | Whether this attribute displays on the product page. Defaults to `yes`. | `yes` |
| `filterable` | Optional | `yes` or `no` | Whether this attribute appears in layered navigation filters. Defaults to `no`. | `yes` |

### 07_COMPATIBILITY

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `source_sku` | Required | Valid SKU | Must match a product SKU. | `GH-DET-BM-020` |
| `target_sku` | Required | Valid SKU | Must match a different product SKU. Must not equal `source_sku`. | `GH-MAK-BM-001` |
| `relationship_type` | Required | Enum | One of: `compatible_consumable`, `compatible_device`, `accessory`, `alternative`, `complementary`. | `compatible_device` |

**Relationship type definitions:**

- `compatible_consumable`: Source is a consumable used by target device (e.g., deterjan -> bulaşık makinesi)
- `compatible_device`: Source works with target device
- `accessory`: Source is an accessory for target
- `alternative`: Source and target are alternatives (symmetric -- stored in both directions)
- `complementary`: Source and target complement each other (symmetric -- stored in both directions)

### 08_SECTORS

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `sector_name` | Required | Text (max 100 chars) | Unique. | `Otel ve Konaklama` |
| `slug` | Optional | Lowercase, hyphens | Auto-generated from `sector_name` if blank. | `otel-ve-konaklama` |
| `description` | Required | Text | Sector description displayed on sector landing pages. | `Otel, tatil köyü ve konaklama tesisleri için profesyonel temizlik ve hijyen çözümleri.` |
| `image` | Optional | Filename | File in `import/images/sectors/`. | `sector-otel-konaklama.jpg` |
| `icon` | Optional | CSS class or inline SVG | Icon displayed in sector navigation. | `gh-icon-hotel` |
| `ready` | Auto | Computed | Sector is ready when description is not empty AND (image or icon exists). | (auto-computed) |

### 09_PRODUCT_SECTORS

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `sku` | Required | Valid SKU | Must match a product SKU in `01_PRODUCTS`. | `GH-DET-BM-020` |
| `sector_name` | Required | Text | Must match a `sector_name` in `08_SECTORS` or existing taxonomy term. | `Otel ve Konaklama` |

### 10_DOCUMENTS

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `document_key` | Required | `DOC-{number}` | Unique across workbook. | `DOC-001` |
| `title` | Required | Text (max 200 chars) | Document title. | `Endüstriyel Bulaşık Deterjanı - Teknik Veri Sayfası` |
| `type` | Required | Enum | One of: `technical_data_sheet`, `safety_data_sheet`, `certificate`, `catalog`, `user_manual`, `brochure`. | `technical_data_sheet` |
| `description` | Optional | Text (max 500 chars) | Brief description of the document content. | `Ürün özellikleri, kullanım dozajı ve depolama koşulları.` |
| `file_path` | Required | Relative path | File in `import/documents/` subdirectory. Must be `.pdf`, `.doc`, `.docx`, or `.xlsx`. | `technical/GH-DET-BM-020-tds.pdf` |
| `version` | Optional | Text (max 20 chars) | Document version identifier. | `2.1` |
| `document_date` | Optional | `YYYY-MM-DD` | Date the document was issued. | `2024-06-15` |
| `document_code` | Optional | Text (max 64 chars) | Internal document reference number. | `TDS-BM-020-TR` |

### 11_DOCUMENT_RELATIONS

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `document_key` | Required | `DOC-{number}` | Must match a `document_key` in `10_DOCUMENTS`. | `DOC-001` |
| `relation_type` | Required | Enum | One of: `product`, `category`, `brand`, `sector`. | `product` |
| `relation_identifier` | Required | Text | For `product`: a valid SKU. For `category`: category name. For `brand`: brand name. For `sector`: sector name. | `GH-DET-BM-020` |

### 12_IMAGES

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `sku_or_identifier` | Required | Text | SKU for products, name for categories/sectors/brands, slug for blog. | `GH-DET-BM-020` |
| `image_type` | Required | Enum | One of: `featured`, `gallery`, `category`, `sector`, `brand`, `blog`. | `gallery` |
| `filename` | Required | Filename | Must exist in the appropriate `import/images/` subdirectory based on `image_type`. | `GH-DET-BM-020-angle.jpg` |
| `display_order` | Optional | Integer (0-99) | Sort order for gallery images. Defaults to 0. | `2` |
| `alt_text` | Required | Text (max 200 chars) | Descriptive alt text for accessibility and SEO. Must describe the image content. | `Endüstriyel bulaşık deterjanı 20 litre bidon yan görünüm` |

### 13_BLOG

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `post_title` | Required | Text (max 200 chars) | Must not be empty. | `Endüstriyel Mutfaklarda Hijyen Standartları Rehberi` |
| `slug` | Optional | Lowercase, hyphens | Auto-generated from `post_title` if blank. | `endustriyel-mutfaklarda-hijyen-standartlari-rehberi` |
| `content` | Required | HTML | Full post content. Must contain genuine, substantive content. | Full HTML article body. |
| `excerpt` | Required | Text (max 300 chars) | Plain text summary. | `Endüstriyel mutfaklarda uyulması gereken hijyen standartları ve denetim süreçleri hakkında kapsamlı rehber.` |
| `category` | Required | Text | WordPress post category name. Created if it does not exist. | `Hijyen Rehberleri` |
| `featured_image` | Required | Filename | File in `import/images/blog/`. | `blog-mutfak-hijyen-rehberi.jpg` |
| `author` | Optional | WordPress username | Must be a valid WordPress user. Defaults to the import user. | `editor` |
| `publication_status` | Optional | Enum | `draft` or `publish`. Defaults to `draft`. | `draft` |
| `seo_title` | Optional | Text (max 60 chars) | Auto-generated from post title if blank. | `Endüstriyel Mutfak Hijyen Standartları - Güven Hijyen` |
| `meta_description` | Optional | Text (max 160 chars) | Auto-generated from excerpt if blank. | `Endüstriyel mutfaklarda hijyen standartları, HACCP gereklilikleri ve temizlik protokolleri.` |
| `related_products` | Optional | Pipe-separated SKUs | Each must be a valid product SKU. | `GH-DET-BM-020\|GH-DET-EL-005` |
| `related_categories` | Optional | Pipe-separated names | Each must be a valid category name. | `Mutfak Hijyeni\|Bulaşık Makinesi Kimyasalları` |

### 14_REDIRECTS

| Field | Required | Format | Validation Rules | Example |
|-------|----------|--------|-----------------|---------|
| `source_url` | Required | Relative URL path | Must start with `/`. Must not conflict with existing WordPress URLs. | `/magaza/eski-urun-sayfasi/` |
| `target_url` | Conditional | Relative URL path or full URL | Required for 301/302. Must start with `/` or `https://`. Not required for 410 (Gone). | `/urunler/yeni-urun-sayfasi/` |
| `redirect_type` | Required | `301`, `302`, or `410` | 301 = permanent, 302 = temporary, 410 = gone (no target). | `301` |
| `notes` | Optional | Text (max 500 chars) | Reason for the redirect. | `Eski ürün URL yapısından yeni yapıya yönlendirme.` |

**Redirect validation rules:**

- No redirect chains: target_url must not itself be a redirect source
- No redirect loops: following redirects must not circle back to source
- No redirect to 404: target_url must resolve to an existing page or a known-good URL pattern
- No redirect to noindex pages
- Collision detection: source_url must not match an existing live WordPress URL
- Special handling for legacy WooCommerce URLs:
  - `/magaza/` -> product archive (301)
  - `/sepetim/`, `/checkout/`, `/hesabim/` -> contextual 404/410 (not blanket homepage redirect)

### 15_IMPORT_ERRORS (Output Only)

This sheet is auto-populated during import. Do not enter data here.

| Field | Description |
|-------|------------|
| `sheet_name` | Which sheet the error occurred in |
| `row_number` | Row number in that sheet |
| `migration_key` | The migration key or document key of the failing row |
| `sku` | The SKU of the failing row (if applicable) |
| `field` | The field that caused the error |
| `error_code` | Machine-readable error code (see Error Codes Reference) |
| `message` | Human-readable error description |
| `recommended_action` | Suggested fix |
| `severity` | `error`, `warning`, or `info` |

---

## Import Pipeline

### Pipeline Stages

```
XLSX File
  |
  v
[1. Parse & Normalize]
  - Read each sheet
  - Trim whitespace from all cells
  - Normalize Turkish characters in slugs
  - Validate column headers match expected schema
  |
  v
[2. Dependency Resolution]
  - Build dependency graph
  - Determine processing order
  - Verify cross-sheet references are satisfiable
  |
  v
[3. Per-Row Validation]
  - Required field checks
  - Format validation (dates, URLs, enums)
  - Uniqueness checks (SKU, migration_key, slug)
  - Cross-reference validation (category exists, brand exists, etc.)
  - File existence checks (images, documents)
  |
  v
[4. Dry Run Report] (if mode=dry_run)
  - Generate validation report
  - List all errors, warnings, info messages
  - Provide row-level fix recommendations
  - STOP -- no data is written
  |
  v
[5. Import Execution] (if mode=import)
  - Process sheets in dependency order
  - Create/update WordPress entities
  - Upload and attach media files
  - Set taxonomy terms
  - Store relationships and meta
  - Log every action
  |
  v
[6. Reconciliation Report]
  - Summary: created / updated / skipped / failed per sheet
  - Error details for any failures
  - Orphan detection (products without categories, etc.)
  - Import ID for audit trail
```

### File Hash Tracking

Each import records the SHA-256 hash of the source file. Re-importing the same file triggers a warning. This prevents accidental duplicate imports.

---

## Publication Workflow

Products move through the following states after import:

```
[Imported] -> [Validated] -> [Reviewed] -> [Publish Ready] -> [Published]
```

### State Definitions

| State | Meaning | Who Acts |
|-------|---------|----------|
| **Imported** | Data written to WP as `draft`. No procurement status set (blank). | Import system |
| **Validated** | Passed automated checks: SKU present, category assigned, image attached. | Import system |
| **Reviewed** | Human has verified product data, descriptions, images. Procurement status set to `active`. | Content editor |
| **Publish Ready** | All publication rules satisfied: SKU + category + image + procurement=active. `GH_Publication_Rules::is_publish_ready()` returns true. | System (automatic) |
| **Published** | `post_status` changed to `publish`. Product visible on frontend. | Content editor / bulk action |

### Publication Rules Checklist

A product is "publish ready" when ALL of these are true:

1. `post_status` = `publish`
2. Product has a SKU
3. Product has at least one category assigned
4. Product has a featured image
5. Procurement status = `active`

If any rule fails, the product is blocked from frontend display even if `post_status=publish`. The admin product list shows a red X with tooltip listing the blockers.

---

## Image File Naming Convention

### Directory Structure

```
import/
  images/
    products/      # Product featured and gallery images
    categories/    # Category header/banner images
    brands/        # Brand logos
    sectors/       # Sector header images
    blog/          # Blog post featured images
    corporate/     # Company-level images (about page, team, etc.)
```

### Naming Rules

**Products:**
```
{SKU}-main.{ext}           Featured image
{SKU}-{n}.{ext}            Gallery image (n = 2, 3, 4, ...)
{SKU}-detail-{n}.{ext}     Detail/close-up shots
```

Examples:
```
GH-DET-BM-020-main.jpg
GH-DET-BM-020-2.jpg
GH-DET-BM-020-3.jpg
GH-DET-BM-020-detail-1.jpg
```

**Categories:**
```
cat-{category-slug}.{ext}
cat-{category-slug}-banner.{ext}
```

**Brands:**
```
brand-{brand-slug}-logo.{ext}
```

**Sectors:**
```
sector-{sector-slug}.{ext}
sector-{sector-slug}-icon.{ext}
```

**Blog:**
```
blog-{post-slug}.{ext}
```

### Image Requirements

- Formats: `.jpg`, `.jpeg`, `.png`, `.webp`
- Product images: minimum 800x800px, recommended 1200x1200px
- Category banners: minimum 1200x400px
- Brand logos: PNG with transparent background, minimum 400x200px
- Maximum file size: 2MB per image
- Color space: sRGB

---

## Document File Organization

```
import/
  documents/
    technical/       # Technical Data Sheets (TDS)
    safety/          # Safety Data Sheets (SDS/MSDS)
    certificates/    # ISO, HACCP, halal certificates
    catalogs/        # Product catalogs, brochures
```

### Naming Convention

```
{SKU}-tds.pdf              Technical data sheet
{SKU}-sds.pdf              Safety data sheet
{brand-slug}-catalog.pdf   Brand catalog
cert-{type}-{year}.pdf     Certificate
```

---

## Step-by-Step Import Instructions

### 1. Prepare the Workbook

1. Download the template workbook from the admin panel or use a blank workbook with the 15 sheets named exactly as listed above.
2. Fill in sheets starting with independent data: `03_CATEGORIES`, `04_BRANDS`, `05_ATTRIBUTES`, `08_SECTORS`.
3. Then fill product data: `01_PRODUCTS`, `02_VARIATIONS`.
4. Fill relationship sheets: `06_PRODUCT_ATTRIBUTES`, `07_COMPATIBILITY`, `09_PRODUCT_SECTORS`.
5. Fill document and image sheets: `10_DOCUMENTS`, `11_DOCUMENT_RELATIONS`, `12_IMAGES`.
6. Fill content: `13_BLOG`.
7. Fill redirects: `14_REDIRECTS`.
8. Leave `15_IMPORT_ERRORS` empty.

### 2. Prepare Media Files

1. Place image files in the appropriate `import/images/` subdirectory.
2. Place document files in the appropriate `import/documents/` subdirectory.
3. Verify every filename referenced in the workbook has a matching file on disk.

### 3. Configure the Migration

1. Copy `migration/migration-config.example.php` to `migration/migration-config.php`.
2. Update the `source.file` path to point to your workbook.
3. Set `options.image_base_path` and `options.document_base_path`.
4. Set `options.mode` to `dry_run`.

### 4. Run Dry Run

```bash
wp eval-file migration/run-migration.php --config=migration/migration-config.php --mode=dry_run
```

Review the output. Fix all errors in the workbook and re-run until the dry run passes cleanly.

### 5. Run Import

```bash
wp eval-file migration/run-migration.php --config=migration/migration-config.php --mode=import
```

### 6. Review Results

1. Check the reconciliation report output.
2. Review any entries in the `15_IMPORT_ERRORS` sheet (or the `gh_import_errors` DB table).
3. In WP Admin, verify products appear in the product list with correct categories, brands, and images.
4. Check the Publication Rules column -- products imported as draft will show blockers until reviewed.

### 7. Post-Import Review

1. Set procurement status on each product (`active`, `temporarily_unavailable`, or `discontinued`).
2. Review and approve blog posts (set `content_quality_status` to `approved`).
3. Change product `post_status` to `publish` when ready.
4. Verify frontend display of published products.

---

## Troubleshooting Common Errors

### "Required field missing"
- **Cause**: A required column is empty for one or more rows.
- **Fix**: Open the XLSX, navigate to the sheet and row indicated, and fill in the missing value.

### "Duplicate SKU"
- **Cause**: Two rows in `01_PRODUCTS` or across products/variations share the same SKU.
- **Fix**: Ensure every SKU is unique across both `01_PRODUCTS` and `02_VARIATIONS`.

### "Referenced category not found"
- **Cause**: A product references a category name that does not exist in `03_CATEGORIES` and does not already exist in WordPress.
- **Fix**: Add the category to `03_CATEGORIES` or correct the spelling. Category names are case-sensitive.

### "Image file not found"
- **Cause**: The filename in the XLSX does not match any file in the expected directory.
- **Fix**: Verify the file exists at the exact path. Check for case-sensitivity in filenames. Ensure the file extension matches.

### "Redirect chain detected"
- **Cause**: A redirect's target URL is itself a source URL of another redirect.
- **Fix**: Update the first redirect to point directly to the final destination.

### "Redirect loop detected"
- **Cause**: Following the redirect chain circles back to the original source URL.
- **Fix**: Break the loop by correcting one of the target URLs.

### "Parent SKU not found for variation"
- **Cause**: A variation references a `parent_sku` that does not exist in `01_PRODUCTS`.
- **Fix**: Ensure the parent product is defined in `01_PRODUCTS` with `product_type=variable`.

### "Attribute value not in defined values"
- **Cause**: A product attribute assignment uses a value not listed in the attribute's `values` field.
- **Fix**: Either add the value to `05_ATTRIBUTES` or correct the value in `06_PRODUCT_ATTRIBUTES`.

### "Duplicate import detected (same file hash)"
- **Cause**: The same XLSX file was imported previously.
- **Fix**: If this is intentional (re-import with corrections), the system will proceed but log a warning. Existing products will be updated, not duplicated.

---

## Error Codes Reference

| Code | Severity | Description |
|------|----------|-------------|
| `E_REQUIRED_FIELD` | error | A required field is empty |
| `E_INVALID_FORMAT` | error | Field value does not match expected format |
| `E_DUPLICATE_SKU` | error | SKU already exists in workbook or database |
| `E_DUPLICATE_KEY` | error | Migration key or document key is duplicated |
| `E_DUPLICATE_SLUG` | error | Slug conflicts with existing slug |
| `E_REF_NOT_FOUND` | error | Cross-reference target does not exist |
| `E_FILE_NOT_FOUND` | error | Referenced file does not exist on disk |
| `E_INVALID_ENUM` | error | Value is not one of the allowed enum values |
| `E_PARENT_NOT_VARIABLE` | error | Variation references a parent that is not `product_type=variable` |
| `E_SELF_REFERENCE` | error | A relationship row references the same SKU as both source and target |
| `E_REDIRECT_CHAIN` | error | Redirect target is itself a redirect source |
| `E_REDIRECT_LOOP` | error | Following redirects creates a circular loop |
| `E_REDIRECT_COLLISION` | error | Redirect source URL matches an existing live WordPress URL |
| `E_ATTR_VALUE_INVALID` | error | Attribute value not in the defined select values |
| `E_IMAGE_TOO_SMALL` | warning | Image dimensions below minimum recommended size |
| `E_IMAGE_TOO_LARGE` | warning | Image file size exceeds 2MB |
| `E_MISSING_SEO` | warning | SEO title or meta description is blank (will be auto-generated) |
| `E_MISSING_ALT_TEXT` | warning | Image alt text is empty |
| `E_MISSING_DESCRIPTION` | warning | Long description is empty or very short |
| `E_DUPLICATE_IMPORT` | warning | Same file hash was imported previously |
| `E_ORPHAN_DOCUMENT` | warning | Document has no relations defined in `11_DOCUMENT_RELATIONS` |
| `E_CATEGORY_NO_PRODUCTS` | info | Category has no products assigned to it |
| `E_BRAND_NOT_READY` | info | Brand does not meet readiness criteria |
| `E_SECTOR_NOT_READY` | info | Sector does not meet readiness criteria |
| `E_PROCUREMENT_BLANK` | info | Product has no procurement status set (needs manual review) |
| `E_VARIATION_INHERITS` | info | Variation field left blank; inheriting from parent |

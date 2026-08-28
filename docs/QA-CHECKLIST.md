# Güven Hijyen -- QA Checklist

## Frontend / UI Testing

### General UI

- [ ] All pages load without JavaScript errors (check browser console)
- [ ] All pages render without broken layout
- [ ] Navigation menu displays correct structure (categories, sectors, brands)
- [ ] Footer contains company info, contact details, social links
- [ ] Logo links to homepage
- [ ] Breadcrumbs display correctly and link to correct pages
- [ ] No broken images across all page types
- [ ] Favicon displays in browser tab
- [ ] 404 page displays custom design (not default WordPress)
- [ ] Turkish characters (ş, ç, ğ, ı, ö, ü, İ, Ş, Ç, Ğ, Ö, Ü) render correctly throughout
- [ ] No "Lorem ipsum" or placeholder text anywhere on the site

### Page-Specific UI

- [ ] **Homepage**: Hero section, featured categories, sector highlights load correctly
- [ ] **Product Archive** (`/urunler/`): Product grid displays, filters work, pagination works
- [ ] **Product Detail**: Product name, SKU, images, description, attributes, related products all display
- [ ] **Category Pages**: Category description, product grid, subcategory navigation
- [ ] **Brand Pages**: Brand logo, description, product listing
- [ ] **Sector Pages**: Sector description, related products by sector
- [ ] **Contact Page**: Company info, map, contact form
- [ ] **Blog/Knowledge Center**: Post listing, individual post pages, related content
- [ ] **RFQ Page**: Form fields render, validation messages display

---

## Responsive Testing

### Mobile (320px - 480px width)

- [ ] Navigation collapses to hamburger menu
- [ ] Hamburger menu opens/closes correctly
- [ ] Product grid switches to single column
- [ ] Product images scale properly
- [ ] RFQ forms are usable (fields not cut off, submit button accessible)
- [ ] Quote list is accessible and functional
- [ ] WhatsApp floating button does not obscure content
- [ ] Footer stacks vertically
- [ ] No horizontal scrollbar on any page
- [ ] Touch targets are at least 44x44px
- [ ] Text is readable without zooming (minimum 14px body text)

### Tablet (768px - 1024px width)

- [ ] Navigation adapts appropriately
- [ ] Product grid shows 2-3 columns
- [ ] Sidebar filters collapse or move above/below content
- [ ] Forms are usable at this width
- [ ] Images scale correctly

### Desktop (1200px+)

- [ ] Full navigation menu displays
- [ ] Product grid shows 3-4 columns
- [ ] Sidebar filters display alongside content
- [ ] Content area has reasonable max-width (not stretched to full screen on ultrawide)

---

## Cross-Browser Testing

Test the following pages in each browser: Homepage, Product Archive, Product Detail, RFQ Form, Contact Page.

### Chrome (latest)
- [ ] All pages render correctly
- [ ] JavaScript functionality works
- [ ] Forms submit successfully

### Firefox (latest)
- [ ] All pages render correctly
- [ ] JavaScript functionality works
- [ ] Forms submit successfully

### Safari (latest, macOS/iOS)
- [ ] All pages render correctly
- [ ] JavaScript functionality works
- [ ] Forms submit successfully
- [ ] Position:sticky works for any sticky headers/navigation

### Edge (latest)
- [ ] All pages render correctly
- [ ] JavaScript functionality works
- [ ] Forms submit successfully

---

## Accessibility Testing

### Keyboard Navigation

- [ ] All interactive elements (links, buttons, form fields) are reachable via Tab key
- [ ] Tab order follows visual layout (logical reading order)
- [ ] Skip-to-content link is present and functional
- [ ] Dropdown menus are operable with keyboard (Enter to open, Escape to close)
- [ ] Modal dialogs trap focus when open and return focus when closed
- [ ] No keyboard traps anywhere on the site

### Focus Indicators

- [ ] All focusable elements have a visible focus indicator
- [ ] Focus indicator has sufficient contrast against background
- [ ] Focus indicator is not suppressed with `outline: none` without replacement

### Screen Reader

- [ ] Pages have a single `<h1>` heading
- [ ] Heading hierarchy is logical (no skipped levels)
- [ ] Images have descriptive `alt` attributes
- [ ] Decorative images have `alt=""`
- [ ] Form fields have associated `<label>` elements
- [ ] Error messages are associated with their form fields via `aria-describedby`
- [ ] Page landmarks (`<nav>`, `<main>`, `<footer>`, etc.) are used correctly
- [ ] ARIA labels are present where visual context is insufficient

### Contrast and Readability

- [ ] Text contrast ratio meets WCAG AA (4.5:1 for normal text, 3:1 for large text)
- [ ] UI component contrast meets 3:1 against adjacent colors
- [ ] Information is not conveyed by color alone

### Text Resizing

- [ ] Page remains usable when browser text is resized to 200%
- [ ] No content is clipped or overlapping at 200% zoom
- [ ] Horizontal scrolling does not appear at 200% zoom (up to 1280px viewport)

---

## RFQ Flow Testing

### Single Product RFQ

- [ ] "Teklif İste" button appears on product detail page
- [ ] Clicking opens RFQ form with product pre-selected
- [ ] Product name and SKU display in the form
- [ ] Company name field: required, validation works
- [ ] Contact name field: required, validation works
- [ ] Email field: required, email format validated
- [ ] Phone field: required, format validated
- [ ] Quantity field: respects minimum quantity and quantity step
- [ ] Message/notes field: optional, works
- [ ] Submit with valid data: success message displayed
- [ ] Submit with empty required fields: error messages displayed per field
- [ ] Submit with invalid email: error message displayed
- [ ] Admin receives notification email with product details and customer info
- [ ] Submitting again after success works (form is not stuck)

### Quote List (Multi-Product) RFQ

- [ ] "Teklif Listesine Ekle" button appears on product cards and product detail pages
- [ ] Clicking adds product to quote list
- [ ] Quote list counter updates in header/navigation
- [ ] Quote list page (`/teklif-listesi/`) displays all added products
- [ ] Each item shows: product name, SKU, quantity input
- [ ] Quantity input respects minimum and step values
- [ ] Remove button works for individual items
- [ ] "Clear all" button works
- [ ] "Teklif İste" button opens RFQ form with all products listed
- [ ] Submitting RFQ includes all products with quantities in the email
- [ ] Quote list persists across page navigation (session storage or cookies)
- [ ] Quote list works for non-logged-in users

### General RFQ

- [ ] General RFQ form at `/teklif-talebi/` loads correctly
- [ ] Form does not pre-select any product
- [ ] Free-text field for describing needs is present
- [ ] Sector/industry dropdown or field is present
- [ ] All validations work as in single product RFQ
- [ ] Submission generates correct admin notification

---

## WhatsApp Testing

- [ ] WhatsApp floating button appears on all pages
- [ ] Button links to correct WhatsApp number
- [ ] Pre-filled message includes page context (product name and SKU if on product page)
- [ ] Link opens WhatsApp app on mobile
- [ ] Link opens WhatsApp Web on desktop
- [ ] Button does not overlap critical content or navigation
- [ ] Button position is consistent across pages

---

## Search Testing

### Search Functionality

- [ ] Search input is accessible from all pages (header search)
- [ ] **Exact SKU search**: Searching `GH-DET-BM-020` returns the exact product
- [ ] **Partial SKU search**: Searching `DET-BM` returns relevant products
- [ ] **Product name search**: Searching `bulaşık deterjanı` returns relevant products
- [ ] **Turkish character handling**: `ş`, `ç`, `ğ`, `ı`, `ö`, `ü` work correctly in search
- [ ] **Case insensitivity**: `deterjan` and `Deterjan` return same results
- [ ] **Typo tolerance**: Minor misspellings return reasonable results (if search plugin supports it)
- [ ] **No results**: Searching gibberish shows "no results" message, not an error
- [ ] **Empty search**: Submitting empty search is handled gracefully
- [ ] Search results show: product name, SKU, category, featured image
- [ ] Search results link to correct product pages
- [ ] Search only returns published, active products (not drafts, not discontinued)

---

## SEO Testing

### Schema Markup

- [ ] **Product schema**: Present on all product detail pages (validate via Google Rich Results Test)
  - [ ] Contains: name, sku, description, image, brand, category
  - [ ] Does NOT contain: price, availability, offers (B2B catalog, no public pricing)
- [ ] **Organization schema**: Present on homepage
  - [ ] Contains: name, telephone, email, address, sameAs (social profiles)
- [ ] **BreadcrumbList schema**: Present on product and category pages
- [ ] **Blog posts**: Article schema present with headline, datePublished, author

### Canonical URLs

- [ ] Every page has a `<link rel="canonical">` tag
- [ ] Canonical URL uses HTTPS
- [ ] Canonical URL uses consistent trailing slash convention
- [ ] No self-referencing canonical issues on paginated pages
- [ ] Product variations do not have separate canonical URLs (point to parent)

### Sitemap

- [ ] `/sitemap_index.xml` is accessible
- [ ] Product sitemap contains all published products
- [ ] Category sitemap contains active categories
- [ ] Blog sitemap contains published, approved posts
- [ ] No draft, private, or noindex pages in sitemap
- [ ] Sitemap URLs use HTTPS
- [ ] Sitemap is referenced in `robots.txt`

### Robots.txt

- [ ] `/robots.txt` is accessible
- [ ] Allows crawling of product pages, categories, blog
- [ ] Blocks `/wp-admin/` (except `admin-ajax.php`)
- [ ] Blocks `/sepetim/`, `/checkout/`, `/hesabim/` (deprecated WooCommerce pages)
- [ ] Contains `Sitemap:` directive pointing to sitemap index
- [ ] Does not block CSS/JS files needed for rendering

### Redirects

- [ ] All 301 redirects in the redirect table are functional
- [ ] No redirect chains (each redirect goes directly to final destination)
- [ ] No redirect loops
- [ ] HTTP -> HTTPS redirect works for all URLs
- [ ] www -> non-www redirect works (or vice versa, whichever is canonical)
- [ ] `/magaza/` redirects to product archive with 301
- [ ] `/sepetim/`, `/checkout/`, `/hesabim/` return 410 (Gone)
- [ ] Legacy product URLs redirect to new product URLs

---

## Performance Testing

### Core Web Vitals

Test with Google PageSpeed Insights and/or Lighthouse on:
- Homepage
- Product archive (with many products)
- Product detail page
- Blog post

| Metric | Target | Page |
|--------|--------|------|
| LCP (Largest Contentful Paint) | < 2.5s | All pages |
| FID / INP (Interaction to Next Paint) | < 200ms | All pages |
| CLS (Cumulative Layout Shift) | < 0.1 | All pages |
| TTFB (Time to First Byte) | < 800ms | All pages |

### Resource Loading

- [ ] Total page weight under 3MB (including images)
- [ ] No uncompressed resources (Gzip/Brotli enabled)
- [ ] Images are appropriately sized (not serving 4000px images in 400px containers)
- [ ] Images use modern formats where supported (WebP)
- [ ] CSS and JS are minified in production
- [ ] No unused CSS/JS blocking render
- [ ] Fonts are preloaded or use `font-display: swap`
- [ ] Third-party scripts are deferred or async

### Database Performance

- [ ] Product archive with 500+ products loads in under 3 seconds
- [ ] Category filter queries do not cause slow queries
- [ ] Search queries do not cause slow queries
- [ ] No excessive `wp_options` autoload (total autoloaded < 1MB)
- [ ] Transient cleanup is running

---

## Security Testing

- [ ] `wp-config.php` not accessible via browser
- [ ] Directory listing disabled (visiting `/wp-content/uploads/` does not list files)
- [ ] `readme.html` and `license.txt` removed or blocked
- [ ] WordPress version not exposed in HTML source or headers
- [ ] Login page (`/wp-login.php`) is accessible only via HTTPS
- [ ] XML-RPC is disabled (return 403 or 405)
- [ ] REST API user enumeration is disabled for unauthenticated requests
- [ ] File uploads are restricted to allowed MIME types
- [ ] RFQ form has CSRF protection (nonce validation)
- [ ] RFQ form has rate limiting or honeypot anti-spam
- [ ] No SQL injection vectors in search or form inputs
- [ ] No XSS vectors in search results or form error messages
- [ ] Admin area enforces strong passwords
- [ ] `DISALLOW_FILE_EDIT` is `true` in `wp-config.php`
- [ ] Security headers present:
  - [ ] `X-Content-Type-Options: nosniff`
  - [ ] `X-Frame-Options: SAMEORIGIN`
  - [ ] `Strict-Transport-Security: max-age=31536000`
  - [ ] `Referrer-Policy: strict-origin-when-cross-origin`

---

## Email Testing

- [ ] SMTP connection is active (not using PHP `mail()`)
- [ ] Test email from WP Admin arrives in inbox
- [ ] RFQ notification to admin: arrives, correct content, Turkish characters intact
- [ ] RFQ confirmation to customer (if enabled): arrives, correct content
- [ ] Contact form submission: admin notification arrives
- [ ] Emails are not landing in spam (check SPF, DKIM, DMARC)
- [ ] Reply-To address is set correctly on all outgoing emails
- [ ] From name displays as company name, not "WordPress"

---

## Import System Testing

### Dry Run

- [ ] Dry run with valid workbook: completes with zero errors
- [ ] Dry run with missing required fields: reports correct errors with row numbers
- [ ] Dry run with duplicate SKUs: reports `E_DUPLICATE_SKU` error
- [ ] Dry run with invalid cross-references: reports `E_REF_NOT_FOUND` error
- [ ] Dry run with missing image files: reports `E_FILE_NOT_FOUND` error
- [ ] Dry run with redirect chains: reports `E_REDIRECT_CHAIN` error
- [ ] Dry run with redirect loops: reports `E_REDIRECT_LOOP` error
- [ ] Dry run does not write any data to the database

### Import

- [ ] Import creates products with correct: name, SKU, slug, descriptions, category, brand
- [ ] Import sets procurement status when provided
- [ ] Import defaults to `draft` publication status
- [ ] Import uploads and attaches featured images
- [ ] Import uploads and attaches gallery images in correct order
- [ ] Import creates categories with correct hierarchy
- [ ] Import creates brands with logo, description, verified status
- [ ] Import creates product variations linked to parent
- [ ] Import assigns product attributes correctly
- [ ] Import creates compatibility relationships (including symmetric)
- [ ] Import assigns products to sectors
- [ ] Import uploads documents and creates relations
- [ ] Import creates blog posts as drafts with `content_quality_status=unreviewed`
- [ ] Import creates redirects in the redirect manager table
- [ ] Import reconciliation report is accurate
- [ ] Import audit trail is recorded in `gh_import_audit` table

### Re-Import / Update

- [ ] Re-importing same file triggers duplicate warning
- [ ] Re-importing with updated product data updates existing products (matched by SKU)
- [ ] Re-importing does not duplicate products, categories, or brands
- [ ] Existing images are not re-uploaded if unchanged

---

## Migration Testing

### Data Integrity

- [ ] Product count matches between source data and WordPress
- [ ] Category hierarchy matches source data
- [ ] Brand count and assignments match source data
- [ ] Sector assignments match source data
- [ ] Variation-to-parent relationships are correct
- [ ] Product attribute values match source data
- [ ] Compatibility relationships match source data (including symmetric pairs)
- [ ] Document count and relations match source data
- [ ] Redirect count matches source data
- [ ] No orphan products (products without categories)
- [ ] No orphan variations (variations without valid parent)
- [ ] No orphan documents (documents without relations)

### Frontend Verification After Migration

- [ ] Published products appear in correct categories on frontend
- [ ] Products display correct brand
- [ ] Products display correct sector assignments
- [ ] Products display correct attributes
- [ ] Products display correct related/compatible products
- [ ] Products display attached documents for download
- [ ] Category pages show correct product count
- [ ] Brand pages show correct product count
- [ ] Sector pages show correct product count
- [ ] Unpublished/draft products do NOT appear on frontend
- [ ] Products without active procurement status do NOT appear on frontend

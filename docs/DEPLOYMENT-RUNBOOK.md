# Güven Hijyen -- Production Deployment Runbook

## Pre-Deployment Checklist

Before any deployment, confirm every item:

- [ ] All code changes are merged to the deployment branch
- [ ] Import system dry run passes with zero errors on production data copy
- [ ] All product images and documents are uploaded to `import/` directories
- [ ] Plugin version in `guvenhijyen-core.php` is updated
- [ ] `wp-config.php` production values reviewed (DB credentials, salts, debug off)
- [ ] `WP_DEBUG` is `false`, `WP_DEBUG_LOG` is `false`, `WP_DEBUG_DISPLAY` is `false`
- [ ] SMTP/email credentials configured and tested
- [ ] SSL certificate is valid and not expiring within 30 days
- [ ] DNS records are configured (A/AAAA, CNAME for www, MX for email)
- [ ] CDN/caching layer configured (if applicable)
- [ ] Hosting provider resource limits reviewed (PHP memory, max execution time, upload size)
- [ ] `.htaccess` or Nginx config reviewed for rewrite rules
- [ ] Staging deployment completed and verified
- [ ] Stakeholder sign-off received

---

## Backup Procedures

### Full Backup Before Deployment

Run the backup script:

```bash
bash scripts/backup.sh
```

This creates timestamped backups in `~/backups/guvenhijyen/`:

### Manual Backup Steps

**Database:**
```bash
mysqldump -u DB_USER -p DB_NAME \
  --single-transaction \
  --routines \
  --triggers \
  --default-character-set=utf8mb4 \
  > backup-db-$(date +%Y%m%d-%H%M%S).sql
```

**wp-content:**
```bash
tar czf backup-wp-content-$(date +%Y%m%d-%H%M%S).tar.gz \
  -C /path/to/wordpress wp-content
```

**Configuration:**
```bash
cp wp-config.php backup-wp-config-$(date +%Y%m%d-%H%M%S).php
cp .htaccess backup-htaccess-$(date +%Y%m%d-%H%M%S)
```

### Verify Backups

- Confirm backup file sizes are non-zero
- Verify database dump contains `CREATE TABLE` statements
- Check wp-content archive contains `plugins/`, `themes/`, `uploads/`
- Store a copy off-server (cloud storage, separate host)

---

## Staging Deployment Steps

1. **Sync staging database from production** (if staging exists)
   ```bash
   wp db import staging-snapshot.sql --path=/path/to/staging
   wp search-replace 'production-domain.com' 'staging-domain.com' --path=/path/to/staging
   ```

2. **Deploy code to staging**
   ```bash
   git pull origin main
   ```

3. **Activate/update plugins**
   ```bash
   wp plugin activate guvenhijyen-core --path=/path/to/staging
   wp plugin activate woocommerce --path=/path/to/staging
   ```

4. **Flush rewrite rules**
   ```bash
   wp rewrite flush --path=/path/to/staging
   ```

5. **Run import on staging** (if importing new data)
   ```bash
   wp eval-file migration/run-migration.php \
     --config=migration/migration-config.php \
     --mode=import \
     --path=/path/to/staging
   ```

6. **Run full QA checklist on staging** (see `docs/QA-CHECKLIST.md`)

7. **Get stakeholder approval on staging**

---

## Production Deployment Steps

### Phase 1: Preparation (30 minutes before)

1. **Notify stakeholders** that deployment is starting
2. **Run backup** (see Backup Procedures above)
3. **Verify backup integrity**
4. **Note current state**:
   ```bash
   wp core version
   wp plugin list --status=active --format=table
   wp post list --post_type=product --post_status=publish --format=count
   ```

### Phase 2: Deployment

1. **Enable maintenance mode**
   ```bash
   wp maintenance-mode activate
   ```

2. **Deploy code**
   ```bash
   git pull origin main
   ```

3. **Update file permissions**
   ```bash
   find /path/to/wordpress -type d -exec chmod 755 {} \;
   find /path/to/wordpress -type f -exec chmod 644 {} \;
   chmod 600 wp-config.php
   ```

4. **Run database migrations** (if plugin has pending DB changes)
   ```bash
   wp plugin deactivate guvenhijyen-core
   wp plugin activate guvenhijyen-core
   ```

5. **Run import** (if deploying with new catalog data)
   ```bash
   wp eval-file migration/run-migration.php \
     --config=migration/migration-config.php \
     --mode=import
   ```

6. **Flush rewrite rules**
   ```bash
   wp rewrite flush
   ```

7. **Regenerate sitemap**
   ```bash
   wp yoast index --reindex  # If using Yoast SEO
   # Or for Rank Math:
   # wp rankmath sitemap generate
   ```

8. **Clear all caches**
   ```bash
   wp cache flush
   wp transient delete --all
   # If using object cache:
   wp cache flush
   # If using a page cache plugin:
   wp w3-total-cache flush all  # or wp super-cache flush, etc.
   ```

9. **Disable maintenance mode**
   ```bash
   wp maintenance-mode deactivate
   ```

### Phase 3: Post-Deployment Verification

Run the post-deployment script:

```bash
bash scripts/post-deploy.sh
```

Or verify manually (see Post-Deployment Verification section below).

---

## Post-Deployment Verification

### Critical URL Checks

Verify these URLs return HTTP 200:

| URL | Expected Content |
|-----|-----------------|
| `/` | Homepage with product categories |
| `/urunler/` | Product archive page |
| `/sektor/otel-ve-konaklama/` | Sector landing page |
| `/marka/` | Brand archive page |
| `/teklif-talebi/` | RFQ form page |
| `/iletisim/` | Contact page |
| `/bilgi-merkezi/` | Blog/knowledge center |
| `/wp-admin/` | Admin login page |
| `/robots.txt` | Robots directives |
| `/sitemap_index.xml` | Sitemap index |

### DNS/SSL Verification

```bash
# Verify DNS resolves correctly
dig +short guvenhijyen.com A
dig +short www.guvenhijyen.com CNAME

# Verify SSL certificate
openssl s_client -connect guvenhijyen.com:443 -servername guvenhijyen.com </dev/null 2>/dev/null | openssl x509 -noout -dates -subject

# Verify HTTPS redirect
curl -sI http://guvenhijyen.com | grep -i location
# Expected: Location: https://guvenhijyen.com/

# Verify www redirect
curl -sI https://www.guvenhijyen.com | grep -i location
# Expected: Location: https://guvenhijyen.com/
```

### Cache Invalidation

1. **Object cache**: `wp cache flush`
2. **Transients**: `wp transient delete --all`
3. **Page cache**: Flush via plugin admin or CLI
4. **CDN cache**: Purge all from CDN dashboard
5. **OPcache**: Restart PHP-FPM or call `opcache_reset()` via admin tool
6. **Browser**: Verify with `Ctrl+Shift+R` (hard refresh)

### Search Index Rebuild

If using a search plugin (SearchWP, Relevanssi, ElasticPress):

```bash
# Relevanssi
wp relevanssi index

# SearchWP
wp searchwp reindex

# ElasticPress
wp elasticpress index --setup
```

### Sitemap Regeneration

1. Visit `/sitemap_index.xml` -- verify it loads
2. Check product sitemap contains published products
3. Check category sitemap contains active categories
4. Submit updated sitemap to Google Search Console
5. Submit updated sitemap to Bing Webmaster Tools

---

## SEO Smoke Test Checklist

- [ ] Homepage has unique `<title>` tag
- [ ] Homepage has `<meta name="description">` tag
- [ ] Product pages have schema.org Product markup (validate with Google Rich Results Test)
- [ ] Organization schema is present on homepage
- [ ] Canonical URLs are correct (no trailing slash inconsistencies)
- [ ] `robots.txt` allows Googlebot access to product pages
- [ ] `robots.txt` blocks `/wp-admin/` (except `admin-ajax.php`)
- [ ] `sitemap_index.xml` is accessible
- [ ] No `noindex` on pages that should be indexed
- [ ] 301 redirects from old URLs are working
- [ ] Hreflang tags present if applicable
- [ ] Open Graph meta tags present on product and blog pages
- [ ] Images have `alt` attributes

---

## RFQ Smoke Test Checklist

Test all three RFQ entry points:

- [ ] **Single Product RFQ**: Go to a product page, click "Teklif İste", fill form, submit. Verify confirmation message. Verify admin receives email notification.
- [ ] **Quote List RFQ**: Add multiple products to quote list, go to quote list page, fill RFQ form, submit. Verify all products listed in the email.
- [ ] **General RFQ**: Go to `/teklif-talebi/`, fill general inquiry form, submit. Verify confirmation.

For each:
- [ ] Required field validation works (empty fields rejected)
- [ ] Email format validation works
- [ ] Phone number format is reasonable
- [ ] Turkish characters in form fields preserved in email
- [ ] Admin notification email received within 2 minutes
- [ ] Customer confirmation email received (if configured)
- [ ] RFQ stored in database/admin panel

---

## Performance Smoke Test

- [ ] Homepage loads in under 3 seconds (TTFB < 800ms)
- [ ] Product archive page loads in under 3 seconds
- [ ] Individual product page loads in under 2.5 seconds
- [ ] No render-blocking resources in critical path
- [ ] Images are lazy-loaded below the fold
- [ ] Gzip/Brotli compression enabled (check response headers)
- [ ] Browser caching headers set for static assets
- [ ] No 404s for CSS, JS, or image resources (check browser console)

Core Web Vitals targets:
- LCP (Largest Contentful Paint): < 2.5s
- FID (First Input Delay) / INP (Interaction to Next Paint): < 200ms
- CLS (Cumulative Layout Shift): < 0.1

---

## Security Smoke Test

- [ ] `wp-config.php` is not accessible via browser (403 or 404)
- [ ] Directory listing is disabled
- [ ] `.git/` directory is not accessible via browser
- [ ] `xmlrpc.php` is blocked or disabled
- [ ] Admin area requires HTTPS
- [ ] WordPress admin username is not `admin`
- [ ] File editing is disabled in admin (`DISALLOW_FILE_EDIT` = true)
- [ ] Database table prefix is not `wp_`
- [ ] Security headers present: `X-Content-Type-Options`, `X-Frame-Options`, `Strict-Transport-Security`
- [ ] No PHP error messages visible on frontend

---

## Email/SMTP Verification

- [ ] SMTP plugin is active and configured
- [ ] Send test email from WP Admin (Tools > Site Health or SMTP plugin test)
- [ ] Verify email arrives in inbox (not spam)
- [ ] RFQ notification emails use correct From address
- [ ] Reply-To address is correct
- [ ] Email contains proper Turkish character encoding (UTF-8)
- [ ] SPF, DKIM, and DMARC records configured for sending domain

---

## Rollback Procedures

### When to Roll Back

Roll back if any of these occur after deployment:

- Site returns 500 errors
- White screen of death
- Critical plugin activation failure
- Database corruption
- Data loss detected
- Security vulnerability discovered

### Rollback Steps

1. **Enable maintenance mode**
   ```bash
   wp maintenance-mode activate
   ```

2. **Restore database**
   ```bash
   wp db import /path/to/backup-db-TIMESTAMP.sql
   ```

3. **Restore wp-content** (if files were changed)
   ```bash
   tar xzf /path/to/backup-wp-content-TIMESTAMP.tar.gz -C /path/to/wordpress/
   ```

4. **Restore configuration**
   ```bash
   cp /path/to/backup-wp-config-TIMESTAMP.php /path/to/wordpress/wp-config.php
   cp /path/to/backup-htaccess-TIMESTAMP /path/to/wordpress/.htaccess
   ```

5. **Flush caches**
   ```bash
   wp cache flush
   wp transient delete --all
   wp rewrite flush
   ```

6. **Disable maintenance mode**
   ```bash
   wp maintenance-mode deactivate
   ```

7. **Verify site is functional**

8. **Notify stakeholders** of rollback and reason

9. **Document the failure** for post-mortem analysis

---

## Post-Launch Monitoring Plan

### First 48-72 Hours (Critical Period)

**Frequency**: Check every 2-4 hours during business hours.

- [ ] **Uptime**: Site is accessible (set up uptime monitoring if not already active)
- [ ] **Error logs**: Check `wp-content/debug.log` (if enabled) and server error logs for new errors
- [ ] **PHP errors**: No fatal errors, warnings, or notices in production logs
- [ ] **404 monitoring**: Track 404 errors -- look for missing redirects from old URLs
- [ ] **Search Console**: Check Google Search Console for crawl errors, coverage issues
- [ ] **RFQ submissions**: Verify RFQ emails continue arriving
- [ ] **Performance**: Page load times remain within targets
- [ ] **Database**: No unusual growth in `wp_options` autoloaded data
- [ ] **Disk space**: Adequate free space on server
- [ ] **SSL**: Certificate status still valid

### 2-4 Week Monitoring Plan

**Weekly checks:**

- [ ] **Google Search Console**: Review indexing status, any manual actions, coverage report
- [ ] **404 log review**: Aggregate 404s, create redirects for legitimate old URLs being requested
- [ ] **Performance trends**: Compare Core Web Vitals week over week
- [ ] **SEO ranking changes**: Monitor key product category rankings
- [ ] **Sitemap health**: Verify sitemap is current, no stale URLs
- [ ] **Backup verification**: Confirm automated backups are running
- [ ] **Plugin updates**: Review available updates (do not auto-update in production)
- [ ] **Security scan**: Run Wordfence or Sucuri scan
- [ ] **User feedback**: Collect and address any reported issues
- [ ] **RFQ volume**: Compare RFQ submission rates to baseline expectations
- [ ] **Redirect performance**: Review redirect hit counts, identify any high-traffic legacy URLs that are missing redirects
- [ ] **Content review**: Verify no draft/unreviewed products leaked to frontend
- [ ] **Publication rules**: Spot-check that `GH_Publication_Rules` is correctly filtering unpublished products

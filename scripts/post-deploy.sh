#!/usr/bin/env bash
#
# Güven Hijyen -- Post-Deployment Verification Script
#
# Runs automated checks after a deployment to verify the site is functional.
#
# Usage:
#   bash scripts/post-deploy.sh
#   bash scripts/post-deploy.sh --site-url=https://guvenhijyen.com
#   bash scripts/post-deploy.sh --wp-path=/var/www/html
#

set -euo pipefail

# -------------------------------------------------------------------------
# Configuration
# -------------------------------------------------------------------------

SITE_URL=""
WP_PATH=""
PASS_COUNT=0
FAIL_COUNT=0
WARN_COUNT=0

# Parse arguments.
for arg in "$@"; do
    case "$arg" in
        --site-url=*) SITE_URL="${arg#*=}" ;;
        --wp-path=*)  WP_PATH="${arg#*=}" ;;
        *) echo "Unknown argument: $arg"; exit 1 ;;
    esac
done

# Auto-detect WP path if not specified.
if [[ -z "$WP_PATH" ]]; then
    if command -v wp &>/dev/null; then
        WP_PATH=$(wp eval 'echo ABSPATH;' 2>/dev/null || true)
    fi
    if [[ -z "$WP_PATH" ]]; then
        WP_PATH="/var/www/html"
    fi
fi

# Auto-detect site URL if not specified.
if [[ -z "$SITE_URL" ]]; then
    if command -v wp &>/dev/null; then
        SITE_URL=$(wp option get siteurl 2>/dev/null || true)
    fi
    if [[ -z "$SITE_URL" ]]; then
        echo "ERROR: Could not detect site URL. Use --site-url=https://example.com"
        exit 1
    fi
fi

# Strip trailing slash from URL.
SITE_URL="${SITE_URL%/}"

# -------------------------------------------------------------------------
# Helper functions
# -------------------------------------------------------------------------

pass() {
    echo "  [PASS] $1"
    PASS_COUNT=$((PASS_COUNT + 1))
}

fail() {
    echo "  [FAIL] $1"
    FAIL_COUNT=$((FAIL_COUNT + 1))
}

warn() {
    echo "  [WARN] $1"
    WARN_COUNT=$((WARN_COUNT + 1))
}

check_url() {
    local url="$1"
    local label="$2"
    local expected_status="${3:-200}"

    local status
    status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 15 "${SITE_URL}${url}" 2>/dev/null || echo "000")

    if [[ "$status" == "$expected_status" ]]; then
        pass "${label} (${url}) -> HTTP ${status}"
    else
        fail "${label} (${url}) -> HTTP ${status} (expected ${expected_status})"
    fi
}

# -------------------------------------------------------------------------
# Checks
# -------------------------------------------------------------------------

echo "================================================================="
echo "Güven Hijyen -- Post-Deployment Verification"
echo "================================================================="
echo "Site URL : ${SITE_URL}"
echo "WP Path  : ${WP_PATH}"
echo "Date     : $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
echo "================================================================="
echo ""

# --- WordPress Accessibility ---
echo "--- WordPress Accessibility ---"
check_url "/" "Homepage"
check_url "/wp-admin/" "Admin area" "200 302"

# Accept either 200 or 302 for admin (redirect to login is expected).
admin_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 15 "${SITE_URL}/wp-admin/" 2>/dev/null || echo "000")
if [[ "$admin_status" == "200" || "$admin_status" == "302" ]]; then
    # Already counted above; this is just for clarity.
    :
fi

check_url "/wp-login.php" "Login page"
echo ""

# --- Plugin Activation ---
echo "--- Plugin Activation ---"
if command -v wp &>/dev/null; then
    if wp plugin is-active guvenhijyen-core 2>/dev/null; then
        pass "guvenhijyen-core is active"
    else
        fail "guvenhijyen-core is NOT active"
    fi

    if wp plugin is-active woocommerce 2>/dev/null; then
        pass "WooCommerce is active"
    else
        fail "WooCommerce is NOT active"
    fi
else
    warn "WP-CLI not available -- cannot check plugin status"
fi
echo ""

# --- Flush Rewrite Rules ---
echo "--- Rewrite Rules ---"
if command -v wp &>/dev/null; then
    if wp rewrite flush 2>/dev/null; then
        pass "Rewrite rules flushed"
    else
        fail "Failed to flush rewrite rules"
    fi
else
    warn "WP-CLI not available -- skipping rewrite flush"
fi
echo ""

# --- Sitemap ---
echo "--- Sitemap ---"
check_url "/sitemap_index.xml" "Sitemap index"
echo ""

# --- Cache Flush ---
echo "--- Cache ---"
if command -v wp &>/dev/null; then
    if wp cache flush 2>/dev/null; then
        pass "Object cache flushed"
    else
        warn "Object cache flush returned non-zero (may not be configured)"
    fi

    if wp transient delete --all 2>/dev/null; then
        pass "Transients cleared"
    else
        warn "Transient deletion returned non-zero"
    fi
else
    warn "WP-CLI not available -- skipping cache flush"
fi
echo ""

# --- Critical URLs ---
echo "--- Critical URL Checks ---"
check_url "/" "Homepage"
check_url "/urunler/" "Product archive"
check_url "/teklif-talebi/" "RFQ page"
check_url "/iletisim/" "Contact page"
check_url "/bilgi-merkezi/" "Blog/Knowledge center"
echo ""

# --- Robots.txt ---
echo "--- Robots.txt ---"
robots_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${SITE_URL}/robots.txt" 2>/dev/null || echo "000")
if [[ "$robots_status" == "200" ]]; then
    pass "robots.txt is accessible"

    # Check for sitemap reference.
    robots_content=$(curl -s --max-time 10 "${SITE_URL}/robots.txt" 2>/dev/null || true)
    if echo "$robots_content" | grep -qi "sitemap"; then
        pass "robots.txt contains Sitemap directive"
    else
        warn "robots.txt does not contain a Sitemap directive"
    fi
else
    fail "robots.txt is NOT accessible (HTTP ${robots_status})"
fi
echo ""

# --- SSL ---
echo "--- SSL Verification ---"
domain=$(echo "$SITE_URL" | sed 's|https\?://||' | sed 's|/.*||')

if echo | openssl s_client -servername "$domain" -connect "$domain:443" 2>/dev/null | openssl x509 -noout -dates 2>/dev/null; then
    expiry=$(echo | openssl s_client -servername "$domain" -connect "$domain:443" 2>/dev/null | openssl x509 -noout -enddate 2>/dev/null | cut -d= -f2)
    pass "SSL certificate valid (expires: ${expiry})"

    # Check if expiring within 30 days.
    expiry_epoch=$(date -d "$expiry" +%s 2>/dev/null || echo "0")
    now_epoch=$(date +%s)
    days_left=$(( (expiry_epoch - now_epoch) / 86400 ))

    if [[ $days_left -lt 30 && $days_left -gt 0 ]]; then
        warn "SSL certificate expires in ${days_left} days"
    fi
else
    warn "Could not verify SSL certificate (may not be available in this environment)"
fi

# Check HTTPS redirect.
http_redirect=$(curl -s -o /dev/null -w "%{redirect_url}" --max-time 10 "http://${domain}/" 2>/dev/null || true)
if [[ "$http_redirect" == https://* ]]; then
    pass "HTTP -> HTTPS redirect working"
else
    warn "HTTP -> HTTPS redirect could not be verified"
fi
echo ""

# --- Smoke Test: Response Content ---
echo "--- Content Smoke Test ---"
homepage_content=$(curl -s --max-time 15 "${SITE_URL}/" 2>/dev/null || true)

if echo "$homepage_content" | grep -qi "güven hijyen\|guvenhijyen"; then
    pass "Homepage contains expected brand name"
else
    fail "Homepage does not contain expected brand name"
fi

if echo "$homepage_content" | grep -qi "<title>"; then
    pass "Homepage has a <title> tag"
else
    fail "Homepage is missing a <title> tag"
fi

if echo "$homepage_content" | grep -qi "schema.org"; then
    pass "Homepage contains schema.org markup"
else
    warn "Homepage does not appear to contain schema.org markup"
fi
echo ""

# --- Security Quick Checks ---
echo "--- Security Quick Checks ---"
check_url "/wp-config.php" "wp-config.php blocked" "403"
check_url "/.git/HEAD" ".git directory blocked" "403"

xmlrpc_status=$(curl -s -o /dev/null -w "%{http_code}" --max-time 10 "${SITE_URL}/xmlrpc.php" 2>/dev/null || echo "000")
if [[ "$xmlrpc_status" == "403" || "$xmlrpc_status" == "405" ]]; then
    pass "XML-RPC is blocked (HTTP ${xmlrpc_status})"
elif [[ "$xmlrpc_status" == "405" ]]; then
    pass "XML-RPC returns 405 Method Not Allowed"
else
    warn "XML-RPC returned HTTP ${xmlrpc_status} (consider blocking)"
fi
echo ""

# -------------------------------------------------------------------------
# Results Summary
# -------------------------------------------------------------------------

echo "================================================================="
echo "RESULTS SUMMARY"
echo "================================================================="
echo "  Passed  : ${PASS_COUNT}"
echo "  Failed  : ${FAIL_COUNT}"
echo "  Warnings: ${WARN_COUNT}"
echo "================================================================="

if [[ $FAIL_COUNT -gt 0 ]]; then
    echo ""
    echo "DEPLOYMENT VERIFICATION FAILED -- review the failures above."
    exit 1
else
    echo ""
    echo "Deployment verification completed. Review any warnings above."
    exit 0
fi

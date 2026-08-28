#!/usr/bin/env bash
#
# Güven Hijyen -- Backup Script
#
# Creates timestamped backups of the WordPress database, wp-content directory,
# and configuration files.
#
# Usage:
#   bash scripts/backup.sh
#   bash scripts/backup.sh --wp-path=/var/www/html --backup-dir=/backups/guvenhijyen
#   bash scripts/backup.sh --retention=10
#
# Options:
#   --wp-path=<path>      WordPress installation path (default: /var/www/html)
#   --backup-dir=<path>   Backup storage directory (default: ~/backups/guvenhijyen)
#   --retention=<n>       Number of backups to keep (default: 7)
#   --skip-db             Skip database backup
#   --skip-files          Skip wp-content backup
#   --db-name=<name>      Database name (auto-detected from wp-config.php if omitted)
#   --db-user=<user>      Database user (auto-detected from wp-config.php if omitted)
#   --db-pass=<pass>      Database password (auto-detected from wp-config.php if omitted)
#   --db-host=<host>      Database host (auto-detected from wp-config.php if omitted)
#

set -euo pipefail

# -------------------------------------------------------------------------
# Configuration defaults
# -------------------------------------------------------------------------

WP_PATH="/var/www/html"
BACKUP_DIR="${HOME}/backups/guvenhijyen"
RETENTION=7
SKIP_DB=false
SKIP_FILES=false
DB_NAME=""
DB_USER=""
DB_PASS=""
DB_HOST=""
TIMESTAMP=$(date +%Y%m%d-%H%M%S)

# Parse arguments.
for arg in "$@"; do
    case "$arg" in
        --wp-path=*)    WP_PATH="${arg#*=}" ;;
        --backup-dir=*) BACKUP_DIR="${arg#*=}" ;;
        --retention=*)  RETENTION="${arg#*=}" ;;
        --skip-db)      SKIP_DB=true ;;
        --skip-files)   SKIP_FILES=true ;;
        --db-name=*)    DB_NAME="${arg#*=}" ;;
        --db-user=*)    DB_USER="${arg#*=}" ;;
        --db-pass=*)    DB_PASS="${arg#*=}" ;;
        --db-host=*)    DB_HOST="${arg#*=}" ;;
        *) echo "Unknown argument: $arg"; exit 1 ;;
    esac
done

# -------------------------------------------------------------------------
# Validate environment
# -------------------------------------------------------------------------

if [[ ! -d "$WP_PATH" ]]; then
    echo "ERROR: WordPress path does not exist: ${WP_PATH}"
    exit 1
fi

if [[ ! -f "${WP_PATH}/wp-config.php" ]]; then
    echo "ERROR: wp-config.php not found in ${WP_PATH}"
    exit 1
fi

# Create backup directory.
mkdir -p "${BACKUP_DIR}"

echo "================================================================="
echo "Güven Hijyen -- Backup"
echo "================================================================="
echo "WordPress path : ${WP_PATH}"
echo "Backup dir     : ${BACKUP_DIR}"
echo "Timestamp      : ${TIMESTAMP}"
echo "Retention      : Keep last ${RETENTION} backups"
echo "================================================================="
echo ""

# -------------------------------------------------------------------------
# Auto-detect database credentials from wp-config.php
# -------------------------------------------------------------------------

extract_wp_config_value() {
    local key="$1"
    grep "define.*['\"]${key}['\"]" "${WP_PATH}/wp-config.php" \
        | head -1 \
        | sed "s/.*['\"]${key}['\"].*['\"]//;s/['\"].*//" \
        || true
}

if [[ -z "$DB_NAME" ]]; then
    DB_NAME=$(extract_wp_config_value "DB_NAME")
fi
if [[ -z "$DB_USER" ]]; then
    DB_USER=$(extract_wp_config_value "DB_USER")
fi
if [[ -z "$DB_PASS" ]]; then
    DB_PASS=$(extract_wp_config_value "DB_PASSWORD")
fi
if [[ -z "$DB_HOST" ]]; then
    DB_HOST=$(extract_wp_config_value "DB_HOST")
    if [[ -z "$DB_HOST" ]]; then
        DB_HOST="localhost"
    fi
fi

# -------------------------------------------------------------------------
# 1. Database Backup
# -------------------------------------------------------------------------

if [[ "$SKIP_DB" == false ]]; then
    echo "--- Database Backup ---"

    if [[ -z "$DB_NAME" || -z "$DB_USER" ]]; then
        echo "  ERROR: Could not determine database credentials."
        echo "  Use --db-name, --db-user, --db-pass, --db-host to specify manually."
        exit 1
    fi

    DB_BACKUP_FILE="${BACKUP_DIR}/db-${TIMESTAMP}.sql.gz"

    echo "  Database : ${DB_NAME}@${DB_HOST}"
    echo "  Output   : ${DB_BACKUP_FILE}"

    mysqldump \
        -u "${DB_USER}" \
        -p"${DB_PASS}" \
        -h "${DB_HOST}" \
        "${DB_NAME}" \
        --single-transaction \
        --routines \
        --triggers \
        --default-character-set=utf8mb4 \
        2>/dev/null \
        | gzip > "${DB_BACKUP_FILE}"

    DB_SIZE=$(du -sh "${DB_BACKUP_FILE}" | cut -f1)
    echo "  Size     : ${DB_SIZE}"

    # Verify the dump is not empty.
    if [[ $(stat -c%s "${DB_BACKUP_FILE}" 2>/dev/null || stat -f%z "${DB_BACKUP_FILE}" 2>/dev/null) -lt 100 ]]; then
        echo "  WARNING: Database backup file is suspiciously small. Verify manually."
    else
        echo "  Status   : OK"
    fi

    echo ""
else
    echo "--- Database Backup: SKIPPED ---"
    echo ""
fi

# -------------------------------------------------------------------------
# 2. wp-content Backup
# -------------------------------------------------------------------------

if [[ "$SKIP_FILES" == false ]]; then
    echo "--- wp-content Backup ---"

    FILES_BACKUP_FILE="${BACKUP_DIR}/wp-content-${TIMESTAMP}.tar.gz"

    echo "  Source : ${WP_PATH}/wp-content/"
    echo "  Output : ${FILES_BACKUP_FILE}"

    tar czf "${FILES_BACKUP_FILE}" \
        -C "${WP_PATH}" \
        wp-content \
        --exclude='wp-content/cache' \
        --exclude='wp-content/upgrade' \
        --exclude='wp-content/debug.log' \
        2>/dev/null || true

    FILES_SIZE=$(du -sh "${FILES_BACKUP_FILE}" | cut -f1)
    echo "  Size   : ${FILES_SIZE}"

    # Verify the archive is valid.
    if tar tzf "${FILES_BACKUP_FILE}" &>/dev/null; then
        echo "  Status : OK (archive verified)"
    else
        echo "  WARNING: Archive verification failed. Backup may be corrupt."
    fi

    echo ""
else
    echo "--- wp-content Backup: SKIPPED ---"
    echo ""
fi

# -------------------------------------------------------------------------
# 3. Configuration Backup
# -------------------------------------------------------------------------

echo "--- Configuration Backup ---"

CONFIG_BACKUP_DIR="${BACKUP_DIR}/config-${TIMESTAMP}"
mkdir -p "${CONFIG_BACKUP_DIR}"

# wp-config.php
if [[ -f "${WP_PATH}/wp-config.php" ]]; then
    cp "${WP_PATH}/wp-config.php" "${CONFIG_BACKUP_DIR}/wp-config.php"
    echo "  Backed up: wp-config.php"
fi

# .htaccess
if [[ -f "${WP_PATH}/.htaccess" ]]; then
    cp "${WP_PATH}/.htaccess" "${CONFIG_BACKUP_DIR}/.htaccess"
    echo "  Backed up: .htaccess"
fi

# nginx.conf (common locations)
for nginx_conf in "${WP_PATH}/nginx.conf" "/etc/nginx/sites-available/guvenhijyen" "/etc/nginx/conf.d/guvenhijyen.conf"; do
    if [[ -f "$nginx_conf" ]]; then
        cp "$nginx_conf" "${CONFIG_BACKUP_DIR}/$(basename "$nginx_conf")"
        echo "  Backed up: $(basename "$nginx_conf")"
    fi
done

# php.ini overrides
if [[ -f "${WP_PATH}/.user.ini" ]]; then
    cp "${WP_PATH}/.user.ini" "${CONFIG_BACKUP_DIR}/.user.ini"
    echo "  Backed up: .user.ini"
fi

echo ""

# -------------------------------------------------------------------------
# 4. Retention Policy
# -------------------------------------------------------------------------

echo "--- Retention Policy ---"
echo "  Keeping last ${RETENTION} backups of each type."

# Clean old database backups.
db_backups=$(ls -1t "${BACKUP_DIR}"/db-*.sql.gz 2>/dev/null || true)
db_count=$(echo "$db_backups" | grep -c '.' 2>/dev/null || echo "0")

if [[ $db_count -gt $RETENTION ]]; then
    echo "$db_backups" | tail -n +$((RETENTION + 1)) | while read -r old_file; do
        rm -f "$old_file"
        echo "  Removed old DB backup: $(basename "$old_file")"
    done
else
    echo "  DB backups within retention limit (${db_count}/${RETENTION})"
fi

# Clean old wp-content backups.
file_backups=$(ls -1t "${BACKUP_DIR}"/wp-content-*.tar.gz 2>/dev/null || true)
file_count=$(echo "$file_backups" | grep -c '.' 2>/dev/null || echo "0")

if [[ $file_count -gt $RETENTION ]]; then
    echo "$file_backups" | tail -n +$((RETENTION + 1)) | while read -r old_file; do
        rm -f "$old_file"
        echo "  Removed old file backup: $(basename "$old_file")"
    done
else
    echo "  File backups within retention limit (${file_count}/${RETENTION})"
fi

# Clean old config backups.
config_dirs=$(ls -1dt "${BACKUP_DIR}"/config-* 2>/dev/null || true)
config_count=$(echo "$config_dirs" | grep -c '.' 2>/dev/null || echo "0")

if [[ $config_count -gt $RETENTION ]]; then
    echo "$config_dirs" | tail -n +$((RETENTION + 1)) | while read -r old_dir; do
        rm -rf "$old_dir"
        echo "  Removed old config backup: $(basename "$old_dir")"
    done
else
    echo "  Config backups within retention limit (${config_count}/${RETENTION})"
fi

echo ""

# -------------------------------------------------------------------------
# Summary
# -------------------------------------------------------------------------

echo "================================================================="
echo "BACKUP COMPLETE"
echo "================================================================="
echo "  Timestamp  : ${TIMESTAMP}"
echo "  Location   : ${BACKUP_DIR}/"

if [[ "$SKIP_DB" == false ]]; then
    echo "  Database   : db-${TIMESTAMP}.sql.gz"
fi
if [[ "$SKIP_FILES" == false ]]; then
    echo "  wp-content : wp-content-${TIMESTAMP}.tar.gz"
fi
echo "  Config     : config-${TIMESTAMP}/"

total_size=$(du -sh "${BACKUP_DIR}" 2>/dev/null | cut -f1 || echo "unknown")
echo "  Total dir  : ${total_size}"
echo "================================================================="
echo ""
echo "Store a copy off-server for disaster recovery."

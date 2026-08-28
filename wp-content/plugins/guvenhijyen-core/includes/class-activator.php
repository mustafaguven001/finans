<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Activator {

    public static function activate(): void {
        self::create_tables();
        self::create_capabilities();
        self::create_pages();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gh_rfq (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reference VARCHAR(20) NOT NULL,
            idempotency_key VARCHAR(64) DEFAULT NULL,
            type ENUM('general','quick_quote','quote_list') NOT NULL DEFAULT 'general',
            company VARCHAR(255) NOT NULL,
            contact_name VARCHAR(255) NOT NULL,
            phone VARCHAR(30) NOT NULL,
            email VARCHAR(255) NOT NULL,
            sector VARCHAR(255) DEFAULT '',
            subject VARCHAR(500) DEFAULT '',
            message TEXT DEFAULT '',
            kvkk_consent TINYINT(1) NOT NULL DEFAULT 0,
            marketing_consent TINYINT(1) NOT NULL DEFAULT 0,
            status ENUM('new','reviewing','quote_prepared','sent','closed','cancelled') NOT NULL DEFAULT 'new',
            ip_address VARCHAR(45) DEFAULT '',
            user_agent TEXT DEFAULT '',
            notes TEXT DEFAULT '',
            is_test TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY reference (reference),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY status (status),
            KEY created_at (created_at),
            KEY company (company(50))
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gh_rfq_items (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rfq_id BIGINT UNSIGNED NOT NULL,
            product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            quantity DECIMAL(10,2) NOT NULL DEFAULT 1,
            sales_unit VARCHAR(50) NOT NULL DEFAULT 'adet',
            snapshot_product_name VARCHAR(500) DEFAULT '',
            snapshot_sku VARCHAR(100) DEFAULT '',
            snapshot_variation VARCHAR(500) DEFAULT '',
            snapshot_sales_unit_key VARCHAR(50) DEFAULT '',
            snapshot_sales_unit_label VARCHAR(100) DEFAULT '',
            snapshot_verified_brand VARCHAR(255) DEFAULT '',
            snapshot_procurement_status VARCHAR(50) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rfq_id (rfq_id),
            KEY product_id (product_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gh_rfq_status_history (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rfq_id BIGINT UNSIGNED NOT NULL,
            old_status VARCHAR(30) DEFAULT '',
            new_status VARCHAR(30) NOT NULL,
            notes TEXT DEFAULT '',
            changed_by BIGINT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY rfq_id (rfq_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gh_redirects (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            source_url VARCHAR(500) NOT NULL,
            target_url VARCHAR(500) NOT NULL DEFAULT '',
            redirect_type SMALLINT NOT NULL DEFAULT 301,
            hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
            notes VARCHAR(500) DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY source_url (source_url(191)),
            KEY redirect_type (redirect_type)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gh_import_audit (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            import_id VARCHAR(36) NOT NULL,
            source_file VARCHAR(500) NOT NULL,
            file_hash VARCHAR(64) NOT NULL,
            mode VARCHAR(20) NOT NULL DEFAULT 'dry_run',
            total_rows INT UNSIGNED NOT NULL DEFAULT 0,
            created_count INT UNSIGNED NOT NULL DEFAULT 0,
            updated_count INT UNSIGNED NOT NULL DEFAULT 0,
            skipped_count INT UNSIGNED NOT NULL DEFAULT 0,
            review_count INT UNSIGNED NOT NULL DEFAULT 0,
            failed_count INT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY import_id (import_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}gh_import_errors (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            import_id VARCHAR(36) NOT NULL,
            sheet_name VARCHAR(50) DEFAULT '',
            row_number INT UNSIGNED DEFAULT 0,
            migration_key VARCHAR(100) DEFAULT '',
            sku VARCHAR(100) DEFAULT '',
            field_name VARCHAR(100) DEFAULT '',
            error_code VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            severity ENUM('error','warning','info') NOT NULL DEFAULT 'error',
            recommended_action TEXT DEFAULT '',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY import_id (import_id),
            KEY severity (severity)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ( $sql as $query ) {
            dbDelta( $query );
        }

        update_option( 'gh_db_version', '1.0.0' );
    }

    private static function create_capabilities(): void {
        $admin = get_role( 'administrator' );
        if ( ! $admin ) {
            return;
        }

        $caps = [
            'manage_gh_rfq',
            'export_gh_rfq',
            'manage_gh_import',
            'manage_gh_settings',
            'manage_gh_documents',
            'manage_gh_redirects',
        ];

        foreach ( $caps as $cap ) {
            $admin->add_cap( $cap );
        }

        $editor = get_role( 'editor' );
        if ( $editor ) {
            $editor->add_cap( 'manage_gh_rfq' );
            $editor->add_cap( 'manage_gh_documents' );
        }
    }

    private static function create_pages(): void {
        $pages = [
            'teklif-iste' => [
                'title'    => 'Teklif İste',
                'content'  => '[guvenhijyen_rfq_form]',
                'template' => 'page-teklif-iste.php',
            ],
        ];

        foreach ( $pages as $slug => $page_data ) {
            $existing = get_page_by_path( $slug );
            if ( $existing ) {
                continue;
            }

            $page_id = wp_insert_post( [
                'post_title'   => $page_data['title'],
                'post_content' => $page_data['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
                'post_name'    => $slug,
            ] );

            if ( $page_id && ! is_wp_error( $page_id ) && ! empty( $page_data['template'] ) ) {
                update_post_meta( $page_id, '_wp_page_template', $page_data['template'] );
            }
        }
    }
}

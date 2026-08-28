<?php

defined( 'ABSPATH' ) || exit;

class GH_Migration_Helper {

	private const CROSSWALK_OPTION = 'gh_migration_crosswalk';

	public static function get_crosswalk( string $type ): array {
		$all = get_option( self::CROSSWALK_OPTION, [] );
		return $all[ $type ] ?? [];
	}

	public static function set_crosswalk( string $type, array $mappings ): void {
		$all           = get_option( self::CROSSWALK_OPTION, [] );
		$all[ $type ]  = $mappings;
		update_option( self::CROSSWALK_OPTION, $all, false );
	}

	public static function add_crosswalk_entry( string $type, string $source_key, $target_value ): void {
		$all = get_option( self::CROSSWALK_OPTION, [] );
		if ( ! isset( $all[ $type ] ) ) {
			$all[ $type ] = [];
		}
		$all[ $type ][ $source_key ] = $target_value;
		update_option( self::CROSSWALK_OPTION, $all, false );
	}

	public static function resolve_crosswalk( string $type, string $source_key ) {
		$mappings = self::get_crosswalk( $type );
		return $mappings[ $source_key ] ?? null;
	}

	public static function track_legacy_url( int $post_id, string $legacy_url ): void {
		$legacy_url = esc_url_raw( $legacy_url );
		if ( empty( $legacy_url ) ) {
			return;
		}

		update_post_meta( $post_id, '_gh_legacy_url', $legacy_url );

		global $wpdb;
		$table = $wpdb->prefix . 'gh_redirects';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE source_url = %s LIMIT 1",
				$legacy_url
			)
		);

		$new_url = get_permalink( $post_id );
		if ( ! $new_url ) {
			return;
		}

		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				[
					'target_url' => $new_url,
					'post_id'    => $post_id,
					'updated_at' => current_time( 'mysql' ),
				],
				[ 'id' => $exists ],
				[ '%s', '%d', '%s' ],
				[ '%d' ]
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				[
					'source_url' => $legacy_url,
					'target_url' => $new_url,
					'post_id'    => $post_id,
					'status'     => 301,
					'created_at' => current_time( 'mysql' ),
				],
				[ '%s', '%s', '%d', '%d', '%s' ]
			);
		}
	}

	public static function save_source_snapshot( int $post_id, array $source_data ): void {
		$snapshot = [
			'data'       => $source_data,
			'saved_at'   => current_time( 'mysql' ),
			'import_id'  => $source_data['_import_id'] ?? '',
		];
		update_post_meta( $post_id, '_gh_source_snapshot', wp_json_encode( $snapshot, JSON_UNESCAPED_UNICODE ) );
	}

	public static function get_source_snapshot( int $post_id ): ?array {
		$raw = get_post_meta( $post_id, '_gh_source_snapshot', true );
		if ( empty( $raw ) ) {
			return null;
		}
		$decoded = json_decode( $raw, true );
		return is_array( $decoded ) ? $decoded : null;
	}

	public static function detect_delta( int $post_id, array $new_data ): array {
		$snapshot = self::get_source_snapshot( $post_id );
		if ( ! $snapshot || empty( $snapshot['data'] ) ) {
			return [
				'has_changes' => true,
				'is_new'      => true,
				'changes'     => [],
			];
		}

		$old     = $snapshot['data'];
		$changes = [];

		$compare_fields = [
			'product_name', 'sku', 'slug', 'product_type',
			'short_description', 'long_description',
			'category', 'subcategory', 'brand',
			'sales_unit', 'minimum_quantity', 'quantity_step',
			'procurement_status', 'featured_image', 'gallery_images',
			'seo_title', 'meta_description',
		];

		foreach ( $compare_fields as $field ) {
			$old_val = $old[ $field ] ?? '';
			$new_val = $new_data[ $field ] ?? '';

			if ( (string) $old_val !== (string) $new_val ) {
				$changes[ $field ] = [
					'old' => $old_val,
					'new' => $new_val,
				];
			}
		}

		return [
			'has_changes' => ! empty( $changes ),
			'is_new'      => false,
			'changes'     => $changes,
		];
	}

	public static function reconciliation_report( string $import_id ): array {
		$audit = GH_Import_Error_Report::get_audit( $import_id );
		if ( ! $audit ) {
			return [ 'error' => __( 'Import audit record not found.', 'guvenhijyen' ) ];
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$actual_created = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
				WHERE meta_key = '_gh_import_id' AND meta_value = %s",
				$import_id
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm2.meta_value AS import_status, COUNT(*) AS count
				FROM {$wpdb->postmeta} pm1
				INNER JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id AND pm2.meta_key = '_gh_import_status'
				WHERE pm1.meta_key = '_gh_import_id' AND pm1.meta_value = %s
				GROUP BY pm2.meta_value",
				$import_id
			),
			ARRAY_A
		);

		$status_counts = [];
		foreach ( $by_status as $row ) {
			$status_counts[ $row['import_status'] ] = (int) $row['count'];
		}

		$expected_total = (int) $audit['created'] + (int) $audit['updated'];

		return [
			'import_id'         => $import_id,
			'expected_created'  => (int) $audit['created'],
			'expected_updated'  => (int) $audit['updated'],
			'expected_total'    => $expected_total,
			'actual_in_db'      => $actual_created,
			'discrepancy'       => $expected_total - $actual_created,
			'status_breakdown'  => $status_counts,
			'skipped'           => (int) $audit['skipped'],
			'failed'            => (int) $audit['failed'],
			'manual_review'     => (int) $audit['manual_review'],
			'error_summary'     => GH_Import_Error_Report::get_error_summary( $import_id ),
		];
	}

	public static function mark_for_rollback( string $import_id ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_gh_import_id' AND meta_value = %s",
				$import_id
			)
		);

		$count = 0;
		foreach ( $post_ids as $post_id ) {
			update_post_meta( (int) $post_id, '_gh_rollback_candidate', $import_id );
			$count++;
		}

		return $count;
	}

	public static function execute_rollback( string $import_id, bool $force_delete = false ): array {
		if ( ! current_user_can( 'manage_gh_import' ) ) {
			return [ 'error' => __( 'Insufficient permissions.', 'guvenhijyen' ) ];
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_gh_rollback_candidate' AND meta_value = %s",
				$import_id
			)
		);

		$results = [
			'trashed'  => 0,
			'deleted'  => 0,
			'restored' => 0,
			'failed'   => 0,
		];

		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;

			$snapshot = self::get_source_snapshot( $post_id );
			$was_new  = empty( $snapshot ) || empty( $snapshot['data']['existing_wp_post_id'] );

			if ( $was_new ) {
				if ( $force_delete ) {
					$deleted = wp_delete_post( $post_id, true );
					if ( $deleted ) {
						$results['deleted']++;
					} else {
						$results['failed']++;
					}
				} else {
					$trashed = wp_trash_post( $post_id );
					if ( $trashed ) {
						$results['trashed']++;
					} else {
						$results['failed']++;
					}
				}
			} else {
				if ( $snapshot && ! empty( $snapshot['data'] ) ) {
					$results['restored']++;
				}
				delete_post_meta( $post_id, '_gh_rollback_candidate' );
			}
		}

		return $results;
	}

	public static function get_import_items( string $import_id, int $page = 1, int $per_page = 50 ): array {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				WHERE meta_key = '_gh_import_id' AND meta_value = %s
				ORDER BY post_id ASC
				LIMIT %d OFFSET %d",
				$import_id,
				$per_page,
				$offset
			)
		);

		$items = [];
		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			$post    = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$items[] = [
				'post_id'       => $post_id,
				'title'         => $post->post_title,
				'type'          => $post->post_type,
				'status'        => $post->post_status,
				'import_status' => get_post_meta( $post_id, '_gh_import_status', true ),
				'sku'           => get_post_meta( $post_id, '_sku', true ),
				'migration_key' => get_post_meta( $post_id, '_gh_migration_key', true ),
			];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta}
				WHERE meta_key = '_gh_import_id' AND meta_value = %s",
				$import_id
			)
		);

		return [
			'items'    => $items,
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil( $total / $per_page ),
		];
	}

	public static function create_redirects_table(): void {
		global $wpdb;
		$table   = $wpdb->prefix . 'gh_redirects';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source_url varchar(2048) NOT NULL,
			target_url varchar(2048) NOT NULL,
			post_id bigint(20) unsigned DEFAULT NULL,
			status smallint(3) unsigned NOT NULL DEFAULT 301,
			hits int(11) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			KEY source_url (source_url(191)),
			KEY post_id (post_id)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}

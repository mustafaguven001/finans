<?php

defined( 'ABSPATH' ) || exit;

class GH_Import_Error_Report {

	private static bool $tables_checked = false;

	public static function create_tables(): void {
		global $wpdb;

		$charset = $wpdb->get_charset_collate();

		$audit_table = $wpdb->prefix . 'gh_import_audit';
		$error_table = $wpdb->prefix . 'gh_import_errors';

		$sql_audit = "CREATE TABLE {$audit_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			import_id varchar(64) NOT NULL,
			timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			source_file varchar(255) NOT NULL,
			file_hash varchar(64) NOT NULL,
			total_rows int(11) unsigned NOT NULL DEFAULT 0,
			created int(11) unsigned NOT NULL DEFAULT 0,
			updated int(11) unsigned NOT NULL DEFAULT 0,
			skipped int(11) unsigned NOT NULL DEFAULT 0,
			manual_review int(11) unsigned NOT NULL DEFAULT 0,
			failed int(11) unsigned NOT NULL DEFAULT 0,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			mode varchar(20) NOT NULL DEFAULT 'dry_run',
			status varchar(20) NOT NULL DEFAULT 'running',
			completed_at datetime DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY import_id (import_id),
			KEY file_hash (file_hash),
			KEY user_id (user_id)
		) {$charset};";

		$sql_errors = "CREATE TABLE {$error_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			import_id varchar(64) NOT NULL,
			sheet_name varchar(64) DEFAULT NULL,
			row_number int(11) unsigned DEFAULT NULL,
			migration_key varchar(128) DEFAULT NULL,
			sku varchar(128) DEFAULT NULL,
			field varchar(128) DEFAULT NULL,
			error_code varchar(64) NOT NULL,
			message text NOT NULL,
			recommended_action text DEFAULT NULL,
			severity enum('error','warning','info') NOT NULL DEFAULT 'error',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY import_id (import_id),
			KEY severity (severity),
			KEY error_code (error_code)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql_audit );
		dbDelta( $sql_errors );
	}

	private static function ensure_tables(): void {
		if ( self::$tables_checked ) {
			return;
		}
		global $wpdb;
		$table = $wpdb->prefix . 'gh_import_audit';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( ! $exists ) {
			self::create_tables();
		}
		self::$tables_checked = true;
	}

	public static function create_audit( array $data ): string {
		global $wpdb;
		self::ensure_tables();

		$import_id = $data['import_id'] ?? self::generate_import_id();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'gh_import_audit',
			[
				'import_id'   => $import_id,
				'source_file' => sanitize_file_name( $data['source_file'] ?? '' ),
				'file_hash'   => sanitize_text_field( $data['file_hash'] ?? '' ),
				'total_rows'  => absint( $data['total_rows'] ?? 0 ),
				'user_id'     => get_current_user_id(),
				'mode'        => sanitize_key( $data['mode'] ?? 'dry_run' ),
				'status'      => 'running',
			],
			[ '%s', '%s', '%s', '%d', '%d', '%s', '%s' ]
		);

		return $import_id;
	}

	public static function update_audit( string $import_id, array $data ): void {
		global $wpdb;
		self::ensure_tables();

		$allowed = [ 'total_rows', 'created', 'updated', 'skipped', 'manual_review', 'failed', 'status', 'completed_at' ];
		$update  = [];
		$formats = [];

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $data ) ) {
				continue;
			}
			if ( in_array( $key, [ 'status', 'completed_at' ], true ) ) {
				$update[ $key ] = sanitize_text_field( $data[ $key ] );
				$formats[]      = '%s';
			} else {
				$update[ $key ] = absint( $data[ $key ] );
				$formats[]      = '%d';
			}
		}

		if ( empty( $update ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$wpdb->prefix . 'gh_import_audit',
			$update,
			[ 'import_id' => $import_id ],
			$formats,
			[ '%s' ]
		);
	}

	public static function add_error( string $import_id, array $error_data ): void {
		global $wpdb;
		self::ensure_tables();

		$severity = $error_data['severity'] ?? 'error';
		if ( ! in_array( $severity, [ 'error', 'warning', 'info' ], true ) ) {
			$severity = 'error';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'gh_import_errors',
			[
				'import_id'          => $import_id,
				'sheet_name'         => sanitize_text_field( $error_data['sheet_name'] ?? '' ),
				'row_number'         => absint( $error_data['row_number'] ?? 0 ),
				'migration_key'      => sanitize_text_field( $error_data['migration_key'] ?? '' ),
				'sku'                => sanitize_text_field( $error_data['sku'] ?? '' ),
				'field'              => sanitize_text_field( $error_data['field'] ?? '' ),
				'error_code'         => sanitize_key( $error_data['error_code'] ?? 'unknown' ),
				'message'            => sanitize_text_field( $error_data['message'] ?? '' ),
				'recommended_action' => sanitize_text_field( $error_data['recommended_action'] ?? '' ),
				'severity'           => $severity,
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
	}

	public static function get_errors( string $import_id, ?string $severity = null, int $page = 1, int $per_page = 50 ): array {
		global $wpdb;
		self::ensure_tables();

		$table  = $wpdb->prefix . 'gh_import_errors';
		$offset = ( $page - 1 ) * $per_page;

		if ( $severity && in_array( $severity, [ 'error', 'warning', 'info' ], true ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE import_id = %s AND severity = %s ORDER BY row_number ASC LIMIT %d OFFSET %d",
					$import_id,
					$severity,
					$per_page,
					$offset
				),
				ARRAY_A
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE import_id = %s AND severity = %s",
					$import_id,
					$severity
				)
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE import_id = %s ORDER BY severity ASC, row_number ASC LIMIT %d OFFSET %d",
					$import_id,
					$per_page,
					$offset
				),
				ARRAY_A
			);
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$total = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE import_id = %s",
					$import_id
				)
			);
		}

		return [
			'rows'     => $rows ?: [],
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil( $total / $per_page ),
		];
	}

	public static function get_error_summary( string $import_id ): array {
		global $wpdb;
		self::ensure_tables();

		$table = $wpdb->prefix . 'gh_import_errors';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT severity, COUNT(*) as count FROM {$table} WHERE import_id = %s GROUP BY severity",
				$import_id
			),
			ARRAY_A
		);

		$summary = [ 'error' => 0, 'warning' => 0, 'info' => 0 ];
		foreach ( $counts as $row ) {
			$summary[ $row['severity'] ] = (int) $row['count'];
		}

		return $summary;
	}

	public static function get_audit( string $import_id ): ?array {
		global $wpdb;
		self::ensure_tables();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}gh_import_audit WHERE import_id = %s",
				$import_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	public static function get_audit_history( int $page = 1, int $per_page = 20 ): array {
		global $wpdb;
		self::ensure_tables();

		$table  = $wpdb->prefix . 'gh_import_audit';
		$offset = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY timestamp DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		return [
			'rows'     => $rows ?: [],
			'total'    => $total,
			'page'     => $page,
			'per_page' => $per_page,
			'pages'    => (int) ceil( $total / $per_page ),
		];
	}

	public static function export_errors( string $import_id, string $format = 'csv' ): ?string {
		$all_errors = [];
		$page       = 1;

		do {
			$result     = self::get_errors( $import_id, null, $page, 500 );
			$all_errors = array_merge( $all_errors, $result['rows'] );
			$page++;
		} while ( $page <= $result['pages'] );

		if ( empty( $all_errors ) ) {
			return null;
		}

		if ( 'csv' === $format ) {
			return self::errors_to_csv( $all_errors );
		}

		return wp_json_encode( $all_errors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	private static function errors_to_csv( array $errors ): string {
		$handle = fopen( 'php://temp', 'r+' );
		if ( ! $handle ) {
			return '';
		}

		fputcsv( $handle, [ 'sheet_name', 'row_number', 'migration_key', 'sku', 'field', 'error_code', 'message', 'recommended_action', 'severity' ] );

		foreach ( $errors as $error ) {
			fputcsv( $handle, [
				$error['sheet_name'] ?? '',
				$error['row_number'] ?? '',
				$error['migration_key'] ?? '',
				$error['sku'] ?? '',
				$error['field'] ?? '',
				$error['error_code'] ?? '',
				$error['message'] ?? '',
				$error['recommended_action'] ?? '',
				$error['severity'] ?? '',
			] );
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		return $csv;
	}

	public static function generate_import_id(): string {
		return 'imp_' . wp_generate_uuid4();
	}
}

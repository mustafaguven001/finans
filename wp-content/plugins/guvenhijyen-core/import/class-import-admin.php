<?php

defined( 'ABSPATH' ) || exit;

class GH_Import_Admin {

	private const CAPABILITY = 'manage_gh_import';
	private const MENU_SLUG  = 'gh-xlsx-import';
	private const NONCE_KEY  = 'gh_import_nonce';

	public static function init(): void {
		add_action( 'admin_menu', [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_upload' ] );
		add_action( 'admin_init', [ __CLASS__, 'register_capability' ] );
		add_action( 'admin_init', [ __CLASS__, 'handle_export_errors' ] );
	}

	public static function register_capability(): void {
		$admin = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( self::CAPABILITY ) ) {
			$admin->add_cap( self::CAPABILITY );
		}
	}

	public static function register_menu(): void {
		add_submenu_page(
			'guvenhijyen',
			__( 'XLSX Import', 'guvenhijyen' ),
			__( 'XLSX Import', 'guvenhijyen' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'guvenhijyen' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'upload';
		$import_id  = isset( $_GET['import_id'] ) ? sanitize_text_field( wp_unslash( $_GET['import_id'] ) ) : '';

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'XLSX Import', 'guvenhijyen' ) . '</h1>';

		echo '<nav class="nav-tab-wrapper">';
		$tabs = [
			'upload'  => __( 'Import', 'guvenhijyen' ),
			'history' => __( 'History', 'guvenhijyen' ),
		];
		if ( $import_id ) {
			$tabs['results'] = __( 'Results', 'guvenhijyen' );
		}

		foreach ( $tabs as $tab_key => $tab_label ) {
			$url   = add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => $tab_key ], admin_url( 'admin.php' ) );
			if ( 'results' === $tab_key && $import_id ) {
				$url = add_query_arg( 'import_id', $import_id, $url );
			}
			$class = ( $active_tab === $tab_key ) ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $tab_label ) . '</a>';
		}
		echo '</nav>';

		echo '<div class="tab-content" style="margin-top: 20px;">';

		switch ( $active_tab ) {
			case 'history':
				self::render_history_tab();
				break;
			case 'results':
				self::render_results_tab( $import_id );
				break;
			default:
				self::render_upload_tab();
				break;
		}

		echo '</div>';
		echo '</div>';
	}

	private static function render_upload_tab(): void {
		$max_upload = min(
			wp_max_upload_size(),
			(int) apply_filters( 'gh_import_max_file_size', 50 * MB_IN_BYTES )
		);
		$max_mb = round( $max_upload / MB_IN_BYTES, 1 );

		if ( isset( $_GET['import_result'] ) ) {
			$result = get_transient( 'gh_import_result_' . get_current_user_id() );
			if ( $result ) {
				delete_transient( 'gh_import_result_' . get_current_user_id() );
				self::render_result_notice( $result );
			}
		}

		echo '<div class="card" style="max-width: 800px;">';
		echo '<h2>' . esc_html__( 'Upload XLSX Workbook', 'guvenhijyen' ) . '</h2>';
		echo '<p>' . esc_html(
			sprintf(
				/* translators: %s: max file size */
				__( 'Upload a master import workbook (XLSX format, max %s MB).', 'guvenhijyen' ),
				$max_mb
			)
		) . '</p>';

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) . '">';
		wp_nonce_field( 'gh_xlsx_import', self::NONCE_KEY );

		echo '<table class="form-table">';
		echo '<tr>';
		echo '<th scope="row"><label for="gh_import_file">' . esc_html__( 'File', 'guvenhijyen' ) . '</label></th>';
		echo '<td>';
		echo '<input type="file" name="gh_import_file" id="gh_import_file" accept=".xlsx,.csv" required />';
		echo '<p class="description">' . esc_html__( 'Accepted formats: XLSX, CSV', 'guvenhijyen' ) . '</p>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row">' . esc_html__( 'Import Mode', 'guvenhijyen' ) . '</th>';
		echo '<td>';
		echo '<fieldset>';
		echo '<label><input type="radio" name="gh_import_mode" value="dry_run" checked="checked" /> ';
		echo esc_html__( 'Dry Run', 'guvenhijyen' ) . ' &mdash; <span class="description">';
		echo esc_html__( 'Validate without making changes', 'guvenhijyen' ) . '</span></label><br />';
		echo '<label><input type="radio" name="gh_import_mode" value="import" /> ';
		echo esc_html__( 'Import', 'guvenhijyen' ) . ' &mdash; <span class="description">';
		echo esc_html__( 'Process and save to database', 'guvenhijyen' ) . '</span></label>';
		echo '</fieldset>';
		echo '</td>';
		echo '</tr>';

		echo '<tr>';
		echo '<th scope="row"><label for="gh_images_path">' . esc_html__( 'Images Base Path', 'guvenhijyen' ) . '</label></th>';
		echo '<td>';
		echo '<input type="text" name="gh_images_path" id="gh_images_path" class="large-text" placeholder="' . esc_attr( ABSPATH . 'import/images/' ) . '" />';
		echo '<p class="description">' . esc_html__( 'Absolute path to the images folder on the server.', 'guvenhijyen' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		echo '</table>';

		submit_button( __( 'Start Import', 'guvenhijyen' ), 'primary', 'gh_start_import' );
		echo '</form>';
		echo '</div>';

		echo '<div class="card" style="max-width: 800px; margin-top: 20px;">';
		echo '<h3>' . esc_html__( 'Expected Sheet Structure', 'guvenhijyen' ) . '</h3>';
		echo '<p>' . esc_html__( 'The workbook should contain the following sheets:', 'guvenhijyen' ) . '</p>';
		echo '<ol>';
		$sheet_names = [
			'01_PRODUCTS', '02_VARIATIONS', '03_CATEGORIES', '04_BRANDS',
			'05_ATTRIBUTES', '06_PRODUCT_ATTRIBUTES', '07_COMPATIBILITY',
			'08_SECTORS', '09_PRODUCT_SECTORS', '10_DOCUMENTS',
			'11_DOCUMENT_RELATIONS', '12_IMAGES', '13_BLOG', '14_REDIRECTS',
			'15_IMPORT_ERRORS',
		];
		foreach ( $sheet_names as $name ) {
			echo '<li><code>' . esc_html( $name ) . '</code></li>';
		}
		echo '</ol>';
		echo '</div>';
	}

	private static function render_history_tab(): void {
		require_once __DIR__ . '/class-import-error-report.php';

		$page    = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$history = GH_Import_Error_Report::get_audit_history( $page, 20 );

		echo '<h2>' . esc_html__( 'Import History', 'guvenhijyen' ) . '</h2>';

		if ( empty( $history['rows'] ) ) {
			echo '<p>' . esc_html__( 'No imports have been performed yet.', 'guvenhijyen' ) . '</p>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Import ID', 'guvenhijyen' ) . '</th>';
		echo '<th>' . esc_html__( 'Date', 'guvenhijyen' ) . '</th>';
		echo '<th>' . esc_html__( 'File', 'guvenhijyen' ) . '</th>';
		echo '<th>' . esc_html__( 'Mode', 'guvenhijyen' ) . '</th>';
		echo '<th>' . esc_html__( 'Total', 'guvenhijyen' ) . '</th>';
		echo '<th>' . esc_html__( 'Created', 'guvenhijyen' ) . '</th>';
		echo '<th>' . esc_html__( 'Updated', 'guvenhijyen' ) . '</th>';
		echo '<th>' . esc_html__( 'Failed', 'guvenhijyen' ) . '</th>';
		echo '<th>' . esc_html__( 'Review', 'guvenhijyen' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'guvenhijyen' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'guvenhijyen' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		foreach ( $history['rows'] as $audit ) {
			$detail_url = add_query_arg( [
				'page'      => self::MENU_SLUG,
				'tab'       => 'results',
				'import_id' => $audit['import_id'],
			], admin_url( 'admin.php' ) );

			echo '<tr>';
			echo '<td><code>' . esc_html( substr( $audit['import_id'], 0, 16 ) ) . '...</code></td>';
			echo '<td>' . esc_html( $audit['timestamp'] ) . '</td>';
			echo '<td>' . esc_html( $audit['source_file'] ) . '</td>';
			echo '<td>' . esc_html( ucfirst( str_replace( '_', ' ', $audit['mode'] ) ) ) . '</td>';
			echo '<td>' . esc_html( $audit['total_rows'] ) . '</td>';
			echo '<td>' . esc_html( $audit['created'] ) . '</td>';
			echo '<td>' . esc_html( $audit['updated'] ) . '</td>';
			echo '<td>' . ( (int) $audit['failed'] > 0 ? '<strong style="color:#d63638;">' . esc_html( $audit['failed'] ) . '</strong>' : '0' ) . '</td>';
			echo '<td>' . esc_html( $audit['manual_review'] ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $audit['status'] ) ) . '</td>';
			echo '<td><a href="' . esc_url( $detail_url ) . '">' . esc_html__( 'View', 'guvenhijyen' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		if ( $history['pages'] > 1 ) {
			echo '<div class="tablenav bottom"><div class="tablenav-pages">';
			echo paginate_links( [
				'base'    => add_query_arg( 'paged', '%#%' ),
				'format'  => '',
				'current' => $page,
				'total'   => $history['pages'],
			] );
			echo '</div></div>';
		}
	}

	private static function render_results_tab( string $import_id ): void {
		if ( empty( $import_id ) ) {
			echo '<p>' . esc_html__( 'No import selected.', 'guvenhijyen' ) . '</p>';
			return;
		}

		require_once __DIR__ . '/class-import-error-report.php';

		$audit = GH_Import_Error_Report::get_audit( $import_id );
		if ( ! $audit ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Import record not found.', 'guvenhijyen' ) . '</p></div>';
			return;
		}

		echo '<h2>' . esc_html__( 'Import Results', 'guvenhijyen' ) . '</h2>';

		echo '<div class="card" style="max-width: 800px;">';
		echo '<h3>' . esc_html__( 'Summary', 'guvenhijyen' ) . '</h3>';
		echo '<table class="form-table">';

		$summary_fields = [
			'import_id'     => __( 'Import ID', 'guvenhijyen' ),
			'timestamp'     => __( 'Timestamp', 'guvenhijyen' ),
			'source_file'   => __( 'Source File', 'guvenhijyen' ),
			'mode'          => __( 'Mode', 'guvenhijyen' ),
			'status'        => __( 'Status', 'guvenhijyen' ),
			'total_rows'    => __( 'Total Rows', 'guvenhijyen' ),
			'created'       => __( 'Created', 'guvenhijyen' ),
			'updated'       => __( 'Updated', 'guvenhijyen' ),
			'skipped'       => __( 'Skipped', 'guvenhijyen' ),
			'manual_review' => __( 'Manual Review', 'guvenhijyen' ),
			'failed'        => __( 'Failed', 'guvenhijyen' ),
		];

		foreach ( $summary_fields as $key => $label ) {
			$value = $audit[ $key ] ?? '';
			echo '<tr><th scope="row">' . esc_html( $label ) . '</th>';
			echo '<td>' . esc_html( $value ) . '</td></tr>';
		}

		echo '</table>';
		echo '</div>';

		$error_summary = GH_Import_Error_Report::get_error_summary( $import_id );
		$total_issues  = array_sum( $error_summary );

		if ( $total_issues > 0 ) {
			echo '<div class="card" style="max-width: 800px; margin-top: 20px;">';
			echo '<h3>' . esc_html__( 'Issues', 'guvenhijyen' ) . '</h3>';

			echo '<ul>';
			if ( $error_summary['error'] > 0 ) {
				echo '<li style="color:#d63638;"><strong>';
				echo esc_html( sprintf(
					/* translators: %d: error count */
					_n( '%d Error', '%d Errors', $error_summary['error'], 'guvenhijyen' ),
					$error_summary['error']
				) );
				echo '</strong></li>';
			}
			if ( $error_summary['warning'] > 0 ) {
				echo '<li style="color:#dba617;"><strong>';
				echo esc_html( sprintf(
					/* translators: %d: warning count */
					_n( '%d Warning', '%d Warnings', $error_summary['warning'], 'guvenhijyen' ),
					$error_summary['warning']
				) );
				echo '</strong></li>';
			}
			if ( $error_summary['info'] > 0 ) {
				echo '<li>';
				echo esc_html( sprintf(
					/* translators: %d: info count */
					_n( '%d Info', '%d Infos', $error_summary['info'], 'guvenhijyen' ),
					$error_summary['info']
				) );
				echo '</li>';
			}
			echo '</ul>';

			$export_url = add_query_arg( [
				'page'             => self::MENU_SLUG,
				'gh_export_errors' => $import_id,
				'_wpnonce'         => wp_create_nonce( 'gh_export_errors' ),
			], admin_url( 'admin.php' ) );

			echo '<p><a href="' . esc_url( $export_url ) . '" class="button">' . esc_html__( 'Export Errors as CSV', 'guvenhijyen' ) . '</a></p>';

			$error_page = isset( $_GET['error_page'] ) ? max( 1, absint( $_GET['error_page'] ) ) : 1;
			$severity   = isset( $_GET['severity'] ) ? sanitize_key( $_GET['severity'] ) : null;
			$errors     = GH_Import_Error_Report::get_errors( $import_id, $severity, $error_page, 25 );

			if ( ! empty( $errors['rows'] ) ) {
				echo '<div style="margin-bottom: 10px;">';
				$base_url = add_query_arg( [ 'page' => self::MENU_SLUG, 'tab' => 'results', 'import_id' => $import_id ], admin_url( 'admin.php' ) );
				echo '<a href="' . esc_url( $base_url ) . '" class="button button-small' . ( null === $severity ? ' button-primary' : '' ) . '">' . esc_html__( 'All', 'guvenhijyen' ) . '</a> ';
				foreach ( [ 'error', 'warning', 'info' ] as $sev ) {
					$sev_url = add_query_arg( 'severity', $sev, $base_url );
					$active  = ( $severity === $sev ) ? ' button-primary' : '';
					echo '<a href="' . esc_url( $sev_url ) . '" class="button button-small' . esc_attr( $active ) . '">' . esc_html( ucfirst( $sev ) ) . '</a> ';
				}
				echo '</div>';

				echo '<table class="widefat striped">';
				echo '<thead><tr>';
				echo '<th>' . esc_html__( 'Sheet', 'guvenhijyen' ) . '</th>';
				echo '<th>' . esc_html__( 'Row', 'guvenhijyen' ) . '</th>';
				echo '<th>' . esc_html__( 'SKU', 'guvenhijyen' ) . '</th>';
				echo '<th>' . esc_html__( 'Field', 'guvenhijyen' ) . '</th>';
				echo '<th>' . esc_html__( 'Code', 'guvenhijyen' ) . '</th>';
				echo '<th>' . esc_html__( 'Message', 'guvenhijyen' ) . '</th>';
				echo '<th>' . esc_html__( 'Severity', 'guvenhijyen' ) . '</th>';
				echo '<th>' . esc_html__( 'Action', 'guvenhijyen' ) . '</th>';
				echo '</tr></thead>';
				echo '<tbody>';

				foreach ( $errors['rows'] as $error ) {
					$sev_color = match ( $error['severity'] ) {
						'error'   => '#d63638',
						'warning' => '#dba617',
						default   => '#2271b1',
					};

					echo '<tr>';
					echo '<td>' . esc_html( $error['sheet_name'] ) . '</td>';
					echo '<td>' . esc_html( $error['row_number'] ) . '</td>';
					echo '<td>' . esc_html( $error['sku'] ) . '</td>';
					echo '<td>' . esc_html( $error['field'] ) . '</td>';
					echo '<td><code>' . esc_html( $error['error_code'] ) . '</code></td>';
					echo '<td>' . esc_html( $error['message'] ) . '</td>';
					echo '<td style="color:' . esc_attr( $sev_color ) . ';">' . esc_html( ucfirst( $error['severity'] ) ) . '</td>';
					echo '<td>' . esc_html( $error['recommended_action'] ) . '</td>';
					echo '</tr>';
				}

				echo '</tbody></table>';

				if ( $errors['pages'] > 1 ) {
					echo '<div class="tablenav bottom"><div class="tablenav-pages">';
					echo paginate_links( [
						'base'    => add_query_arg( 'error_page', '%#%' ),
						'format'  => '',
						'current' => $error_page,
						'total'   => $errors['pages'],
					] );
					echo '</div></div>';
				}
			}

			echo '</div>';
		}
	}

	private static function render_result_notice( array $result ): void {
		if ( ! $result['success'] ) {
			echo '<div class="notice notice-error"><p>';
			echo esc_html( $result['error'] ?? __( 'Import failed.', 'guvenhijyen' ) );
			if ( ! empty( $result['issues'] ) ) {
				echo '<br />' . esc_html( implode( '; ', $result['issues'] ) );
			}
			echo '</p></div>';
			return;
		}

		$counters = $result['counters'] ?? [];
		$class    = ( $counters['failed'] ?? 0 ) > 0 ? 'notice-warning' : 'notice-success';

		echo '<div class="notice ' . esc_attr( $class ) . '"><p>';
		echo '<strong>' . esc_html(
			sprintf(
				/* translators: %s: import mode */
				__( '%s completed.', 'guvenhijyen' ),
				ucfirst( str_replace( '_', ' ', $result['mode'] ?? 'import' ) )
			)
		) . '</strong><br />';

		echo esc_html( sprintf(
			/* translators: 1: total, 2: created, 3: updated, 4: skipped, 5: review, 6: failed */
			__( 'Total: %1$d | Created: %2$d | Updated: %3$d | Skipped: %4$d | Review: %5$d | Failed: %6$d', 'guvenhijyen' ),
			$counters['total_rows'] ?? 0,
			$counters['created'] ?? 0,
			$counters['updated'] ?? 0,
			$counters['skipped'] ?? 0,
			$counters['manual_review'] ?? 0,
			$counters['failed'] ?? 0
		) );

		if ( ! empty( $result['import_id'] ) ) {
			$detail_url = add_query_arg( [
				'page'      => self::MENU_SLUG,
				'tab'       => 'results',
				'import_id' => $result['import_id'],
			], admin_url( 'admin.php' ) );
			echo '<br /><a href="' . esc_url( $detail_url ) . '">' . esc_html__( 'View Details', 'guvenhijyen' ) . '</a>';
		}

		echo '</p></div>';
	}

	public static function handle_upload(): void {
		if ( ! isset( $_POST['gh_start_import'] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'guvenhijyen' ) );
		}

		if ( ! check_admin_referer( 'gh_xlsx_import', self::NONCE_KEY ) ) {
			wp_die( esc_html__( 'Security check failed.', 'guvenhijyen' ) );
		}

		if ( empty( $_FILES['gh_import_file'] ) || UPLOAD_ERR_OK !== $_FILES['gh_import_file']['error'] ) {
			$error_messages = [
				UPLOAD_ERR_INI_SIZE   => __( 'File exceeds server upload limit.', 'guvenhijyen' ),
				UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds form upload limit.', 'guvenhijyen' ),
				UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'guvenhijyen' ),
				UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'guvenhijyen' ),
				UPLOAD_ERR_NO_TMP_DIR => __( 'Server missing temporary folder.', 'guvenhijyen' ),
				UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'guvenhijyen' ),
			];

			$error_code = $_FILES['gh_import_file']['error'] ?? UPLOAD_ERR_NO_FILE;
			$message    = $error_messages[ $error_code ] ?? __( 'File upload error.', 'guvenhijyen' );

			set_transient( 'gh_import_result_' . get_current_user_id(), [
				'success' => false,
				'error'   => $message,
			], 60 );

			wp_safe_redirect( add_query_arg( [
				'page'          => self::MENU_SLUG,
				'import_result' => '1',
			], admin_url( 'admin.php' ) ) );
			exit;
		}

		$file = $_FILES['gh_import_file'];

		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, [ 'xlsx', 'csv' ], true ) ) {
			set_transient( 'gh_import_result_' . get_current_user_id(), [
				'success' => false,
				'error'   => __( 'Invalid file type. Only XLSX and CSV files are accepted.', 'guvenhijyen' ),
			], 60 );

			wp_safe_redirect( add_query_arg( [
				'page'          => self::MENU_SLUG,
				'import_result' => '1',
			], admin_url( 'admin.php' ) ) );
			exit;
		}

		$max_size = (int) apply_filters( 'gh_import_max_file_size', 50 * MB_IN_BYTES );
		if ( $file['size'] > $max_size ) {
			set_transient( 'gh_import_result_' . get_current_user_id(), [
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: max size in MB */
					__( 'File size exceeds the maximum allowed size of %s MB.', 'guvenhijyen' ),
					$max_size / MB_IN_BYTES
				),
			], 60 );

			wp_safe_redirect( add_query_arg( [
				'page'          => self::MENU_SLUG,
				'import_result' => '1',
			], admin_url( 'admin.php' ) ) );
			exit;
		}

		$upload_dir = wp_upload_dir();
		$import_dir = trailingslashit( $upload_dir['basedir'] ) . 'gh-imports/';
		wp_mkdir_p( $import_dir );

		$dest = $import_dir . wp_unique_filename( $import_dir, sanitize_file_name( $file['name'] ) );
		if ( ! move_uploaded_file( $file['tmp_name'], $dest ) ) {
			set_transient( 'gh_import_result_' . get_current_user_id(), [
				'success' => false,
				'error'   => __( 'Failed to save uploaded file.', 'guvenhijyen' ),
			], 60 );

			wp_safe_redirect( add_query_arg( [
				'page'          => self::MENU_SLUG,
				'import_result' => '1',
			], admin_url( 'admin.php' ) ) );
			exit;
		}

		$mode        = isset( $_POST['gh_import_mode'] ) ? sanitize_key( $_POST['gh_import_mode'] ) : 'dry_run';
		$images_path = isset( $_POST['gh_images_path'] ) ? sanitize_text_field( wp_unslash( $_POST['gh_images_path'] ) ) : '';

		require_once __DIR__ . '/class-import-engine.php';

		$engine = new GH_Import_Engine();
		if ( ! empty( $images_path ) ) {
			$engine->set_images_base_path( $images_path );
		}

		$result = $engine->process( $dest, $mode );

		@unlink( $dest );

		set_transient( 'gh_import_result_' . get_current_user_id(), $result, 300 );

		$redirect_args = [
			'page'          => self::MENU_SLUG,
			'import_result' => '1',
		];

		if ( ! empty( $result['import_id'] ) && $result['success'] ) {
			$redirect_args['tab']       = 'results';
			$redirect_args['import_id'] = $result['import_id'];
		}

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_export_errors(): void {
		if ( empty( $_GET['gh_export_errors'] ) ) {
			return;
		}

		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'gh_export_errors' ) ) {
			return;
		}

		require_once __DIR__ . '/class-import-error-report.php';

		$import_id = sanitize_text_field( wp_unslash( $_GET['gh_export_errors'] ) );
		$csv       = GH_Import_Error_Report::export_errors( $import_id, 'csv' );

		if ( ! $csv ) {
			return;
		}

		$filename = 'import-errors-' . substr( $import_id, 0, 16 ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo "\xEF\xBB\xBF";
		echo $csv;
		exit;
	}
}

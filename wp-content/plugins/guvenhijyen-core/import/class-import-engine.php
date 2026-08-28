<?php

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/class-import-error-report.php';
require_once __DIR__ . '/class-text-importer.php';
require_once __DIR__ . '/class-image-importer.php';
require_once __DIR__ . '/class-product-importer.php';
require_once __DIR__ . '/class-migration-helper.php';

class GH_Import_Engine {

	private const EXPECTED_SHEETS = [
		'01_PRODUCTS',
		'02_VARIATIONS',
		'03_CATEGORIES',
		'04_BRANDS',
		'05_ATTRIBUTES',
		'06_PRODUCT_ATTRIBUTES',
		'07_COMPATIBILITY',
		'08_SECTORS',
		'09_PRODUCT_SECTORS',
		'10_DOCUMENTS',
		'11_DOCUMENT_RELATIONS',
		'12_IMAGES',
		'13_BLOG',
		'14_REDIRECTS',
		'15_IMPORT_ERRORS',
	];

	private const REQUIRED_COLUMNS = [
		'01_PRODUCTS'          => [ 'product_name', 'sku' ],
		'02_VARIATIONS'        => [ 'parent_sku', 'sku', 'variation_name' ],
		'03_CATEGORIES'        => [ 'category_name' ],
		'04_BRANDS'            => [ 'brand_name' ],
		'05_ATTRIBUTES'        => [ 'attribute_name' ],
		'06_PRODUCT_ATTRIBUTES'=> [ 'sku', 'attribute_name', 'attribute_value' ],
		'07_COMPATIBILITY'     => [ 'sku', 'compatible_sku' ],
		'08_SECTORS'           => [ 'sector_name' ],
		'09_PRODUCT_SECTORS'   => [ 'sku', 'sector_name' ],
		'10_DOCUMENTS'         => [ 'document_title', 'file_path' ],
		'11_DOCUMENT_RELATIONS'=> [ 'document_id', 'sku' ],
		'12_IMAGES'            => [ 'sku', 'filename' ],
		'13_BLOG'              => [ 'title', 'content' ],
		'14_REDIRECTS'         => [ 'source_url', 'target_url' ],
	];

	private string $import_id;
	private array $counters;
	private GH_Text_Importer $text_importer;
	private ?GH_Product_Importer $product_importer = null;
	private string $images_base_path = '';

	public function __construct() {
		$this->text_importer = new GH_Text_Importer();
	}

	public function set_images_base_path( string $path ): void {
		$this->images_base_path = trailingslashit( $path );
	}

	public function process( string $file_path, string $mode = 'dry_run' ): array {
		if ( ! current_user_can( 'manage_gh_import' ) ) {
			return [
				'success' => false,
				'error'   => __( 'You do not have permission to run imports.', 'guvenhijyen' ),
			];
		}

		if ( ! in_array( $mode, [ 'dry_run', 'import', 'review' ], true ) ) {
			return [
				'success' => false,
				'error'   => __( 'Invalid import mode.', 'guvenhijyen' ),
			];
		}

		$validation = $this->validate_workbook( $file_path );
		if ( ! $validation['valid'] ) {
			return [
				'success' => false,
				'error'   => __( 'Workbook validation failed.', 'guvenhijyen' ),
				'issues'  => $validation['issues'],
			];
		}

		$file_hash       = hash_file( 'sha256', $file_path );
		$this->import_id = GH_Import_Error_Report::generate_import_id();
		$this->counters  = [
			'total_rows'    => 0,
			'created'       => 0,
			'updated'       => 0,
			'skipped'       => 0,
			'manual_review' => 0,
			'failed'        => 0,
		];

		$this->product_importer = new GH_Product_Importer( $this->import_id, $this->images_base_path );

		if ( 'dry_run' !== $mode ) {
			GH_Import_Error_Report::create_audit( [
				'import_id'   => $this->import_id,
				'source_file' => basename( $file_path ),
				'file_hash'   => $file_hash,
				'mode'        => $mode,
			] );
		}

		$sheets = $this->read_workbook( $file_path );
		if ( is_wp_error( $sheets ) ) {
			return [
				'success'   => false,
				'import_id' => $this->import_id,
				'error'     => $sheets->get_error_message(),
			];
		}

		$process_order = [
			'03_CATEGORIES',
			'04_BRANDS',
			'05_ATTRIBUTES',
			'08_SECTORS',
			'01_PRODUCTS',
			'02_VARIATIONS',
			'06_PRODUCT_ATTRIBUTES',
			'07_COMPATIBILITY',
			'09_PRODUCT_SECTORS',
			'10_DOCUMENTS',
			'11_DOCUMENT_RELATIONS',
			'12_IMAGES',
			'13_BLOG',
			'14_REDIRECTS',
		];

		$sheet_results = [];
		foreach ( $process_order as $sheet_name ) {
			if ( ! isset( $sheets[ $sheet_name ] ) || empty( $sheets[ $sheet_name ] ) ) {
				continue;
			}
			$sheet_results[ $sheet_name ] = $this->import_sheet( $sheet_name, $sheets[ $sheet_name ], $mode );
		}

		if ( 'dry_run' !== $mode ) {
			GH_Import_Error_Report::update_audit( $this->import_id, array_merge(
				$this->counters,
				[
					'status'       => 'completed',
					'completed_at' => current_time( 'mysql' ),
				]
			) );
		}

		$result = [
			'success'       => true,
			'import_id'     => $this->import_id,
			'mode'          => $mode,
			'file_hash'     => $file_hash,
			'counters'      => $this->counters,
			'sheet_results' => $sheet_results,
		];

		if ( 'import' === $mode ) {
			$result['reconciliation'] = $this->reconcile( $this->import_id );
		}

		return $result;
	}

	public function validate_workbook( string $file_path ): array {
		$issues = [];

		if ( ! file_exists( $file_path ) ) {
			return [ 'valid' => false, 'issues' => [ __( 'File not found.', 'guvenhijyen' ) ] ];
		}

		if ( ! is_readable( $file_path ) ) {
			return [ 'valid' => false, 'issues' => [ __( 'File is not readable.', 'guvenhijyen' ) ] ];
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, [ 'xlsx', 'csv' ], true ) ) {
			return [ 'valid' => false, 'issues' => [ __( 'Invalid file format. Only XLSX and CSV are supported.', 'guvenhijyen' ) ] ];
		}

		$max_size = apply_filters( 'gh_import_max_file_size', 50 * MB_IN_BYTES );
		if ( filesize( $file_path ) > $max_size ) {
			return [
				'valid'  => false,
				'issues' => [
					sprintf(
						/* translators: %s: max size in MB */
						__( 'File exceeds maximum size of %s MB.', 'guvenhijyen' ),
						$max_size / MB_IN_BYTES
					),
				],
			];
		}

		if ( 'csv' === $extension ) {
			return [ 'valid' => true, 'issues' => [] ];
		}

		$sheets = $this->get_sheet_names( $file_path );
		if ( is_wp_error( $sheets ) ) {
			return [ 'valid' => false, 'issues' => [ $sheets->get_error_message() ] ];
		}

		$missing = [];
		foreach ( self::EXPECTED_SHEETS as $expected ) {
			if ( ! in_array( $expected, $sheets, true ) ) {
				$missing[] = $expected;
			}
		}

		if ( ! empty( $missing ) ) {
			$issues[] = sprintf(
				/* translators: %s: sheet names */
				__( 'Missing sheets: %s', 'guvenhijyen' ),
				implode( ', ', $missing )
			);
		}

		$workbook = $this->read_workbook( $file_path );
		if ( is_wp_error( $workbook ) ) {
			return [ 'valid' => false, 'issues' => [ $workbook->get_error_message() ] ];
		}

		foreach ( self::REQUIRED_COLUMNS as $sheet_name => $required_cols ) {
			if ( ! isset( $workbook[ $sheet_name ] ) || empty( $workbook[ $sheet_name ] ) ) {
				continue;
			}

			$first_row  = reset( $workbook[ $sheet_name ] );
			$header_row = array_keys( $first_row );

			foreach ( $required_cols as $col ) {
				if ( ! in_array( $col, $header_row, true ) ) {
					$issues[] = sprintf(
						/* translators: 1: column name, 2: sheet name */
						__( 'Missing required column "%1$s" in sheet "%2$s".', 'guvenhijyen' ),
						$col,
						$sheet_name
					);
				}
			}
		}

		$has_blocking = false;
		foreach ( $issues as $issue ) {
			if ( strpos( $issue, __( 'Missing required column', 'guvenhijyen' ) ) !== false ) {
				$has_blocking = true;
				break;
			}
		}

		return [
			'valid'  => ! $has_blocking,
			'issues' => $issues,
		];
	}

	public function validate_row( string $sheet, array $row_data, int $row_number ): array {
		$errors          = [];
		$required_cols   = self::REQUIRED_COLUMNS[ $sheet ] ?? [];

		foreach ( $required_cols as $col ) {
			if ( ! isset( $row_data[ $col ] ) || '' === trim( (string) $row_data[ $col ] ) ) {
				$errors[] = [
					'sheet_name'  => $sheet,
					'row_number'  => $row_number,
					'field'       => $col,
					'error_code'  => 'required_missing',
					'message'     => sprintf(
						/* translators: %s: column name */
						__( 'Required column "%s" is empty.', 'guvenhijyen' ),
						$col
					),
					'severity'    => 'error',
					'sku'         => $row_data['sku'] ?? '',
					'migration_key' => $row_data['migration_key'] ?? '',
				];
			}
		}

		$text_checks = $this->get_text_fields_for_sheet( $sheet );
		foreach ( $text_checks as $row_key => $field_name ) {
			if ( ! empty( $row_data[ $row_key ] ) ) {
				$text_errors = $this->text_importer->validate_field( $field_name, $row_data[ $row_key ] );
				foreach ( $text_errors as $te ) {
					$te['sheet_name']    = $sheet;
					$te['row_number']    = $row_number;
					$te['sku']           = $row_data['sku'] ?? '';
					$te['migration_key'] = $row_data['migration_key'] ?? '';
					$errors[]            = $te;
				}
			}
		}

		return $errors;
	}

	public function normalize_row( string $sheet, array $row_data ): array {
		$row_data = array_map( static function ( $value ) {
			if ( is_string( $value ) ) {
				return trim( $value );
			}
			return $value;
		}, $row_data );

		$text_checks = $this->get_text_fields_for_sheet( $sheet );
		foreach ( $text_checks as $row_key => $field_name ) {
			if ( ! empty( $row_data[ $row_key ] ) ) {
				$row_data[ $row_key ] = $this->text_importer->sanitize_field( $field_name, $row_data[ $row_key ] );
			}
		}

		if ( isset( $row_data['sku'] ) ) {
			$row_data['sku'] = sanitize_text_field( $row_data['sku'] );
		}
		if ( isset( $row_data['slug'] ) ) {
			$row_data['slug'] = sanitize_title( $row_data['slug'] );
		}

		return $row_data;
	}

	public function import_sheet( string $sheet_name, array $data, string $mode ): array {
		$sheet_result = [
			'processed' => 0,
			'created'   => 0,
			'updated'   => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'review'    => 0,
		];

		foreach ( $data as $row_index => $row_data ) {
			$row_number = $row_index + 2;
			$this->counters['total_rows']++;
			$sheet_result['processed']++;

			$row_errors = $this->validate_row( $sheet_name, $row_data, $row_number );
			$has_blocking = false;

			foreach ( $row_errors as $error ) {
				if ( 'error' === $error['severity'] ) {
					$has_blocking = true;
				}
				if ( 'dry_run' !== $mode ) {
					GH_Import_Error_Report::add_error( $this->import_id, $error );
				}
			}

			if ( $has_blocking ) {
				$this->counters['failed']++;
				$sheet_result['failed']++;
				continue;
			}

			$row_data = $this->normalize_row( $sheet_name, $row_data );

			$result = $this->process_row( $sheet_name, $row_data, $row_number, $mode );

			switch ( $result['status'] ) {
				case 'created':
				case 'would_create':
					$this->counters['created']++;
					$sheet_result['created']++;
					break;
				case 'updated':
				case 'would_update':
					$this->counters['updated']++;
					$sheet_result['updated']++;
					break;
				case 'skipped':
					$this->counters['skipped']++;
					$sheet_result['skipped']++;
					break;
				case 'manual_review':
				case 'would_review':
					$this->counters['manual_review']++;
					$sheet_result['review']++;
					break;
				case 'failed':
					$this->counters['failed']++;
					$sheet_result['failed']++;
					if ( 'dry_run' !== $mode && ! empty( $result['errors'] ) ) {
						foreach ( $result['errors'] as $error ) {
							$error['sheet_name']  = $sheet_name;
							$error['row_number']  = $row_number;
							GH_Import_Error_Report::add_error( $this->import_id, $error );
						}
					}
					break;
			}

			if ( 'dry_run' !== $mode && ! empty( $result['product_id'] ) ) {
				$row_data['_import_id'] = $this->import_id;
				GH_Migration_Helper::save_source_snapshot( $result['product_id'], $row_data );
			}
		}

		return $sheet_result;
	}

	public function reconcile( string $import_id ): array {
		return GH_Migration_Helper::reconciliation_report( $import_id );
	}

	private function process_row( string $sheet_name, array $row_data, int $row_number, string $mode ): array {
		switch ( $sheet_name ) {
			case '01_PRODUCTS':
				return $this->product_importer->import_product( $row_data, $mode );

			case '02_VARIATIONS':
				return $this->process_variation( $row_data, $mode );

			case '03_CATEGORIES':
				return $this->process_category( $row_data, $mode );

			case '04_BRANDS':
				return $this->process_brand( $row_data, $mode );

			case '05_ATTRIBUTES':
				return $this->process_attribute( $row_data, $mode );

			case '06_PRODUCT_ATTRIBUTES':
				return $this->process_product_attribute( $row_data, $mode );

			case '07_COMPATIBILITY':
				return $this->process_compatibility( $row_data, $mode );

			case '08_SECTORS':
				return $this->process_sector( $row_data, $mode );

			case '09_PRODUCT_SECTORS':
				return $this->process_product_sector( $row_data, $mode );

			case '10_DOCUMENTS':
				return $this->process_document( $row_data, $mode );

			case '11_DOCUMENT_RELATIONS':
				return $this->process_document_relation( $row_data, $mode );

			case '12_IMAGES':
				return $this->process_image_row( $row_data, $mode );

			case '13_BLOG':
				return $this->process_blog( $row_data, $mode );

			case '14_REDIRECTS':
				return $this->process_redirect( $row_data, $mode );

			default:
				return [ 'status' => 'skipped' ];
		}
	}

	private function process_variation( array $row_data, string $mode ): array {
		if ( 'dry_run' === $mode ) {
			$parent_id = wc_get_product_id_by_sku( $row_data['parent_sku'] ?? '' );
			return [
				'status' => $parent_id ? 'would_create' : 'failed',
				'errors' => $parent_id ? [] : [ [
					'field'      => 'parent_sku',
					'error_code' => 'parent_not_found',
					'message'    => __( 'Parent product not found for variation.', 'guvenhijyen' ),
					'severity'   => 'error',
				] ],
			];
		}

		$parent_id = wc_get_product_id_by_sku( $row_data['parent_sku'] ?? '' );
		if ( ! $parent_id ) {
			return [
				'status' => 'failed',
				'errors' => [ [
					'field'      => 'parent_sku',
					'error_code' => 'parent_not_found',
					'message'    => __( 'Parent product not found for variation.', 'guvenhijyen' ),
					'severity'   => 'error',
				] ],
			];
		}

		$existing = wc_get_product_id_by_sku( $row_data['sku'] ?? '' );
		if ( $existing ) {
			return [ 'status' => 'updated', 'product_id' => $existing ];
		}

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent_id );
		$variation->set_sku( sanitize_text_field( $row_data['sku'] ) );
		$variation->set_name( sanitize_text_field( $row_data['variation_name'] ?? '' ) );
		$variation->set_status( 'publish' );
		$variation_id = $variation->save();

		return [
			'status'     => $variation_id ? 'created' : 'failed',
			'product_id' => $variation_id,
		];
	}

	private function process_category( array $row_data, string $mode ): array {
		$name = sanitize_text_field( $row_data['category_name'] ?? '' );
		if ( empty( $name ) ) {
			return [ 'status' => 'failed' ];
		}

		$existing = get_term_by( 'name', $name, 'product_cat' );

		if ( 'dry_run' === $mode ) {
			return [ 'status' => $existing ? 'would_update' : 'would_create' ];
		}

		$parent_id = 0;
		if ( ! empty( $row_data['parent_category'] ) ) {
			$parent = get_term_by( 'name', sanitize_text_field( $row_data['parent_category'] ), 'product_cat' );
			if ( $parent ) {
				$parent_id = $parent->term_id;
			}
		}

		if ( $existing ) {
			$args = [ 'parent' => $parent_id ];
			if ( ! empty( $row_data['category_description'] ) ) {
				$args['description'] = $this->text_importer->sanitize_field( 'category_description', $row_data['category_description'] );
			}
			wp_update_term( $existing->term_id, 'product_cat', $args );
			return [ 'status' => 'updated', 'term_id' => $existing->term_id ];
		}

		$args = [ 'parent' => $parent_id ];
		if ( ! empty( $row_data['slug'] ) ) {
			$args['slug'] = sanitize_title( $row_data['slug'] );
		}
		if ( ! empty( $row_data['category_description'] ) ) {
			$args['description'] = $this->text_importer->sanitize_field( 'category_description', $row_data['category_description'] );
		}

		$result = wp_insert_term( $name, 'product_cat', $args );
		if ( is_wp_error( $result ) ) {
			return [ 'status' => 'failed', 'errors' => [ [
				'error_code' => 'term_insert_failed',
				'message'    => $result->get_error_message(),
				'severity'   => 'error',
			] ] ];
		}

		return [ 'status' => 'created', 'term_id' => $result['term_id'] ];
	}

	private function process_brand( array $row_data, string $mode ): array {
		$name     = sanitize_text_field( $row_data['brand_name'] ?? '' );
		$taxonomy = taxonomy_exists( 'product_brand' ) ? 'product_brand' : 'pa_brand';

		if ( empty( $name ) || ! taxonomy_exists( $taxonomy ) ) {
			return [ 'status' => 'failed' ];
		}

		$existing = get_term_by( 'name', $name, $taxonomy );

		if ( 'dry_run' === $mode ) {
			return [ 'status' => $existing ? 'would_update' : 'would_create' ];
		}

		if ( $existing ) {
			$args = [];
			if ( ! empty( $row_data['brand_description'] ) ) {
				$args['description'] = $this->text_importer->sanitize_field( 'brand_description', $row_data['brand_description'] );
			}
			if ( ! empty( $args ) ) {
				wp_update_term( $existing->term_id, $taxonomy, $args );
			}
			return [ 'status' => 'updated', 'term_id' => $existing->term_id ];
		}

		$args = [];
		if ( ! empty( $row_data['slug'] ) ) {
			$args['slug'] = sanitize_title( $row_data['slug'] );
		}
		if ( ! empty( $row_data['brand_description'] ) ) {
			$args['description'] = $this->text_importer->sanitize_field( 'brand_description', $row_data['brand_description'] );
		}

		$result = wp_insert_term( $name, $taxonomy, $args );
		if ( is_wp_error( $result ) ) {
			return [ 'status' => 'failed' ];
		}

		return [ 'status' => 'created', 'term_id' => $result['term_id'] ];
	}

	private function process_attribute( array $row_data, string $mode ): array {
		$name = sanitize_text_field( $row_data['attribute_name'] ?? '' );
		if ( empty( $name ) ) {
			return [ 'status' => 'failed' ];
		}

		if ( 'dry_run' === $mode ) {
			return [ 'status' => 'would_create' ];
		}

		$slug = wc_sanitize_taxonomy_name( $name );

		if ( wc_attribute_taxonomy_id_by_name( $slug ) ) {
			return [ 'status' => 'updated' ];
		}

		$attribute_id = wc_create_attribute( [
			'name'         => $name,
			'slug'         => $slug,
			'type'         => 'select',
			'order_by'     => 'menu_order',
			'has_archives' => false,
		] );

		if ( is_wp_error( $attribute_id ) ) {
			return [ 'status' => 'failed' ];
		}

		return [ 'status' => 'created' ];
	}

	private function process_product_attribute( array $row_data, string $mode ): array {
		if ( 'dry_run' === $mode ) {
			return [ 'status' => 'would_create' ];
		}

		$product_id = wc_get_product_id_by_sku( $row_data['sku'] ?? '' );
		if ( ! $product_id ) {
			return [ 'status' => 'failed', 'errors' => [ [
				'error_code' => 'product_not_found',
				'message'    => __( 'Product not found for attribute assignment.', 'guvenhijyen' ),
				'severity'   => 'error',
			] ] ];
		}

		$attr_name  = sanitize_text_field( $row_data['attribute_name'] ?? '' );
		$attr_value = sanitize_text_field( $row_data['attribute_value'] ?? '' );
		$taxonomy   = 'pa_' . wc_sanitize_taxonomy_name( $attr_name );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [ 'status' => 'failed' ];
		}

		wp_set_object_terms( $product_id, $attr_value, $taxonomy, true );

		$product = wc_get_product( $product_id );
		if ( $product ) {
			$attributes = $product->get_attributes();
			$attribute  = new \WC_Product_Attribute();
			$attribute->set_name( $taxonomy );
			$attribute->set_options( wp_get_object_terms( $product_id, $taxonomy, [ 'fields' => 'ids' ] ) );
			$attribute->set_visible( true );
			$attribute->set_variation( $product->is_type( 'variable' ) );
			$attributes[ $taxonomy ] = $attribute;
			$product->set_attributes( $attributes );
			$product->save();
		}

		return [ 'status' => 'created' ];
	}

	private function process_compatibility( array $row_data, string $mode ): array {
		if ( 'dry_run' === $mode ) {
			return [ 'status' => 'would_create' ];
		}

		$product_id    = wc_get_product_id_by_sku( $row_data['sku'] ?? '' );
		$compatible_id = wc_get_product_id_by_sku( $row_data['compatible_sku'] ?? '' );

		if ( ! $product_id || ! $compatible_id ) {
			return [ 'status' => 'failed' ];
		}

		$existing = get_post_meta( $product_id, '_gh_compatible_products', true );
		$existing = is_array( $existing ) ? $existing : [];

		if ( ! in_array( $compatible_id, $existing, true ) ) {
			$existing[] = $compatible_id;
			update_post_meta( $product_id, '_gh_compatible_products', $existing );
		}

		return [ 'status' => 'created' ];
	}

	private function process_sector( array $row_data, string $mode ): array {
		$name = sanitize_text_field( $row_data['sector_name'] ?? '' );
		if ( empty( $name ) ) {
			return [ 'status' => 'failed' ];
		}

		$existing = get_term_by( 'name', $name, 'product_sector' );

		if ( 'dry_run' === $mode ) {
			return [ 'status' => $existing ? 'would_update' : 'would_create' ];
		}

		if ( $existing ) {
			$args = [];
			if ( ! empty( $row_data['sector_description'] ) ) {
				$args['description'] = $this->text_importer->sanitize_field( 'sector_description', $row_data['sector_description'] );
			}
			if ( ! empty( $args ) ) {
				wp_update_term( $existing->term_id, 'product_sector', $args );
			}
			return [ 'status' => 'updated', 'term_id' => $existing->term_id ];
		}

		$args = [];
		if ( ! empty( $row_data['slug'] ) ) {
			$args['slug'] = sanitize_title( $row_data['slug'] );
		}
		if ( ! empty( $row_data['sector_description'] ) ) {
			$args['description'] = $this->text_importer->sanitize_field( 'sector_description', $row_data['sector_description'] );
		}

		$result = wp_insert_term( $name, 'product_sector', $args );
		if ( is_wp_error( $result ) ) {
			return [ 'status' => 'failed' ];
		}

		return [ 'status' => 'created', 'term_id' => $result['term_id'] ];
	}

	private function process_product_sector( array $row_data, string $mode ): array {
		if ( 'dry_run' === $mode ) {
			return [ 'status' => 'would_create' ];
		}

		$product_id = wc_get_product_id_by_sku( $row_data['sku'] ?? '' );
		if ( ! $product_id ) {
			return [ 'status' => 'failed' ];
		}

		$sector = get_term_by( 'name', sanitize_text_field( $row_data['sector_name'] ?? '' ), 'product_sector' );
		if ( ! $sector ) {
			return [ 'status' => 'failed' ];
		}

		wp_set_object_terms( $product_id, [ $sector->term_id ], 'product_sector', true );

		return [ 'status' => 'created' ];
	}

	private function process_document( array $row_data, string $mode ): array {
		$title = sanitize_text_field( $row_data['document_title'] ?? '' );
		if ( empty( $title ) ) {
			return [ 'status' => 'failed' ];
		}

		if ( 'dry_run' === $mode ) {
			return [ 'status' => 'would_create' ];
		}

		$existing = get_page_by_title( $title, OBJECT, 'gh_document' );
		if ( $existing ) {
			$update_data = [ 'ID' => $existing->ID ];
			if ( ! empty( $row_data['document_description'] ) ) {
				$update_data['post_content'] = $this->text_importer->sanitize_field( 'document_description', $row_data['document_description'] );
			}
			wp_update_post( $update_data );
			update_post_meta( $existing->ID, '_gh_document_file', sanitize_text_field( $row_data['file_path'] ?? '' ) );
			return [ 'status' => 'updated', 'product_id' => $existing->ID ];
		}

		$post_id = wp_insert_post( [
			'post_type'    => 'gh_document',
			'post_title'   => $title,
			'post_content' => $this->text_importer->sanitize_field(
				'document_description',
				$row_data['document_description'] ?? ''
			),
			'post_status'  => 'draft',
		] );

		if ( is_wp_error( $post_id ) ) {
			return [ 'status' => 'failed' ];
		}

		update_post_meta( $post_id, '_gh_document_file', sanitize_text_field( $row_data['file_path'] ?? '' ) );
		update_post_meta( $post_id, '_gh_import_id', $this->import_id );

		return [ 'status' => 'created', 'product_id' => $post_id ];
	}

	private function process_document_relation( array $row_data, string $mode ): array {
		if ( 'dry_run' === $mode ) {
			return [ 'status' => 'would_create' ];
		}

		$doc_id     = absint( $row_data['document_id'] ?? 0 );
		$product_id = wc_get_product_id_by_sku( $row_data['sku'] ?? '' );

		if ( ! $doc_id || ! $product_id ) {
			return [ 'status' => 'failed' ];
		}

		$existing = get_post_meta( $product_id, '_gh_documents', true );
		$existing = is_array( $existing ) ? $existing : [];

		if ( ! in_array( $doc_id, $existing, true ) ) {
			$existing[] = $doc_id;
			update_post_meta( $product_id, '_gh_documents', $existing );
		}

		return [ 'status' => 'created' ];
	}

	private function process_image_row( array $row_data, string $mode ): array {
		if ( 'dry_run' === $mode ) {
			$file_path = trailingslashit( $this->images_base_path ) . 'products/' . ( $row_data['filename'] ?? '' );
			if ( file_exists( $file_path ) ) {
				return [ 'status' => 'would_create' ];
			}
			return [
				'status' => 'failed',
				'errors' => [ [
					'error_code' => 'file_not_found',
					'message'    => __( 'Image file not found.', 'guvenhijyen' ),
					'severity'   => 'error',
				] ],
			];
		}

		$product_id = wc_get_product_id_by_sku( $row_data['sku'] ?? '' );
		if ( ! $product_id ) {
			return [ 'status' => 'failed' ];
		}

		$image_importer = new GH_Image_Importer();
		$file_path      = trailingslashit( $this->images_base_path ) . 'products/' . sanitize_file_name( $row_data['filename'] ?? '' );
		$image_type     = ( $row_data['type'] ?? 'product_gallery' ) === 'featured' ? 'product_featured' : 'product_gallery';

		$result = $image_importer->import_image( $file_path, $image_type, $product_id );

		if ( 'error' === $result['status'] ) {
			return [ 'status' => 'failed', 'errors' => $result['errors'] ];
		}

		if ( 'featured' === ( $row_data['type'] ?? '' ) && ! empty( $result['attachment_id'] ) ) {
			set_post_thumbnail( $product_id, $result['attachment_id'] );
		}

		return [ 'status' => 'created', 'product_id' => $product_id ];
	}

	private function process_blog( array $row_data, string $mode ): array {
		$title = sanitize_text_field( $row_data['title'] ?? '' );
		if ( empty( $title ) ) {
			return [ 'status' => 'failed' ];
		}

		if ( 'dry_run' === $mode ) {
			return [ 'status' => 'would_create' ];
		}

		$existing = get_page_by_title( $title, OBJECT, 'post' );
		if ( $existing ) {
			wp_update_post( [
				'ID'           => $existing->ID,
				'post_content' => $this->text_importer->sanitize_field( 'blog_content', $row_data['content'] ?? '' ),
			] );
			update_post_meta( $existing->ID, '_gh_import_id', $this->import_id );
			return [ 'status' => 'updated', 'product_id' => $existing->ID ];
		}

		$post_id = wp_insert_post( [
			'post_type'    => 'post',
			'post_title'   => $title,
			'post_content' => $this->text_importer->sanitize_field( 'blog_content', $row_data['content'] ?? '' ),
			'post_status'  => 'draft',
			'post_name'    => sanitize_title( $row_data['slug'] ?? $title ),
		] );

		if ( is_wp_error( $post_id ) ) {
			return [ 'status' => 'failed' ];
		}

		update_post_meta( $post_id, '_gh_import_id', $this->import_id );

		if ( ! empty( $row_data['seo_title'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_title', sanitize_text_field( $row_data['seo_title'] ) );
			update_post_meta( $post_id, 'rank_math_title', sanitize_text_field( $row_data['seo_title'] ) );
		}
		if ( ! empty( $row_data['meta_description'] ) ) {
			update_post_meta( $post_id, '_yoast_wpseo_metadesc', sanitize_text_field( $row_data['meta_description'] ) );
			update_post_meta( $post_id, 'rank_math_description', sanitize_text_field( $row_data['meta_description'] ) );
		}

		return [ 'status' => 'created', 'product_id' => $post_id ];
	}

	private function process_redirect( array $row_data, string $mode ): array {
		$source = esc_url_raw( $row_data['source_url'] ?? '' );
		$target = esc_url_raw( $row_data['target_url'] ?? '' );

		if ( empty( $source ) || empty( $target ) ) {
			return [ 'status' => 'failed' ];
		}

		if ( 'dry_run' === $mode ) {
			return [ 'status' => 'would_create' ];
		}

		global $wpdb;
		$table = $wpdb->prefix . 'gh_redirects';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE source_url = %s LIMIT 1",
				$source
			)
		);

		if ( $exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				[
					'target_url' => $target,
					'status'     => absint( $row_data['status_code'] ?? 301 ),
					'updated_at' => current_time( 'mysql' ),
				],
				[ 'id' => $exists ],
				[ '%s', '%d', '%s' ],
				[ '%d' ]
			);
			return [ 'status' => 'updated' ];
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$table,
			[
				'source_url' => $source,
				'target_url' => $target,
				'status'     => absint( $row_data['status_code'] ?? 301 ),
				'created_at' => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%d', '%s' ]
		);

		return [ 'status' => 'created' ];
	}

	private function read_workbook( string $file_path ): array|\WP_Error {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		if ( 'csv' === $extension ) {
			return $this->read_csv( $file_path );
		}

		if ( class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			return $this->read_xlsx_phpspreadsheet( $file_path );
		}

		return $this->read_xlsx_fallback( $file_path );
	}

	private function read_xlsx_phpspreadsheet( string $file_path ): array|\WP_Error {
		try {
			$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile( $file_path );
			$reader->setReadDataOnly( true );
			$spreadsheet = $reader->load( $file_path );

			$sheets = [];
			foreach ( $spreadsheet->getSheetNames() as $sheet_name ) {
				$worksheet = $spreadsheet->getSheetByName( $sheet_name );
				if ( ! $worksheet ) {
					continue;
				}

				$data    = $worksheet->toArray( null, true, true, false );
				$headers = array_shift( $data );

				if ( empty( $headers ) ) {
					continue;
				}

				$headers = array_map( static function ( $h ) {
					return trim( strtolower( (string) $h ) );
				}, $headers );

				$rows = [];
				foreach ( $data as $row ) {
					if ( $this->is_empty_row( $row ) ) {
						continue;
					}
					$mapped = [];
					foreach ( $headers as $col_index => $header ) {
						if ( '' === $header ) {
							continue;
						}
						$mapped[ $header ] = $row[ $col_index ] ?? '';
					}
					$rows[] = $mapped;
				}

				$sheets[ $sheet_name ] = $rows;
			}

			return $sheets;
		} catch ( \Exception $e ) {
			return new \WP_Error( 'spreadsheet_error', $e->getMessage() );
		}
	}

	private function read_xlsx_fallback( string $file_path ): array|\WP_Error {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error(
				'missing_dependency',
				__( 'Neither PhpSpreadsheet nor ZipArchive is available. Cannot read XLSX files.', 'guvenhijyen' )
			);
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			return new \WP_Error( 'zip_open_failed', __( 'Cannot open XLSX file.', 'guvenhijyen' ) );
		}

		$workbook_xml = $zip->getFromName( 'xl/workbook.xml' );
		if ( false === $workbook_xml ) {
			$zip->close();
			return new \WP_Error( 'invalid_xlsx', __( 'Invalid XLSX structure.', 'guvenhijyen' ) );
		}

		$shared_strings_xml = $zip->getFromName( 'xl/sharedStrings.xml' );
		$shared_strings     = [];
		if ( false !== $shared_strings_xml ) {
			$ss_doc = new \DOMDocument();
			$ss_doc->loadXML( $shared_strings_xml );
			$si_nodes = $ss_doc->getElementsByTagName( 'si' );
			foreach ( $si_nodes as $si ) {
				$text = '';
				$t_nodes = $si->getElementsByTagName( 't' );
				foreach ( $t_nodes as $t ) {
					$text .= $t->textContent;
				}
				$shared_strings[] = $text;
			}
		}

		$wb_doc = new \DOMDocument();
		$wb_doc->loadXML( $workbook_xml );
		$sheet_nodes = $wb_doc->getElementsByTagName( 'sheet' );

		$sheets    = [];
		$sheet_idx = 1;

		foreach ( $sheet_nodes as $sheet_node ) {
			$sheet_name = $sheet_node->getAttribute( 'name' );
			$sheet_xml  = $zip->getFromName( "xl/worksheets/sheet{$sheet_idx}.xml" );
			$sheet_idx++;

			if ( false === $sheet_xml ) {
				continue;
			}

			$sheet_doc = new \DOMDocument();
			$sheet_doc->loadXML( $sheet_xml );
			$row_nodes = $sheet_doc->getElementsByTagName( 'row' );

			$raw_rows = [];
			foreach ( $row_nodes as $row_node ) {
				$row_data = [];
				$cells    = $row_node->getElementsByTagName( 'c' );

				foreach ( $cells as $cell ) {
					$ref    = $cell->getAttribute( 'r' );
					$col    = preg_replace( '/\d/', '', $ref );
					$type   = $cell->getAttribute( 't' );
					$v_node = $cell->getElementsByTagName( 'v' )->item( 0 );
					$value  = $v_node ? $v_node->textContent : '';

					if ( 's' === $type && isset( $shared_strings[ (int) $value ] ) ) {
						$value = $shared_strings[ (int) $value ];
					}

					$col_index            = $this->column_to_index( $col );
					$row_data[ $col_index ] = $value;
				}

				$raw_rows[] = $row_data;
			}

			if ( empty( $raw_rows ) ) {
				continue;
			}

			$headers = array_shift( $raw_rows );
			$headers = array_map( static function ( $h ) {
				return trim( strtolower( (string) $h ) );
			}, $headers );

			$rows = [];
			foreach ( $raw_rows as $row ) {
				if ( $this->is_empty_row( $row ) ) {
					continue;
				}
				$mapped = [];
				foreach ( $headers as $col_index => $header ) {
					if ( '' === $header ) {
						continue;
					}
					$mapped[ $header ] = $row[ $col_index ] ?? '';
				}
				$rows[] = $mapped;
			}

			$sheets[ $sheet_name ] = $rows;
		}

		$zip->close();
		return $sheets;
	}

	private function read_csv( string $file_path ): array|\WP_Error {
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new \WP_Error( 'csv_open_failed', __( 'Cannot open CSV file.', 'guvenhijyen' ) );
		}

		$bom = fread( $handle, 3 );
		if ( "\xEF\xBB\xBF" !== $bom ) {
			rewind( $handle );
		}

		$headers = fgetcsv( $handle );
		if ( false === $headers ) {
			fclose( $handle );
			return new \WP_Error( 'csv_empty', __( 'CSV file is empty.', 'guvenhijyen' ) );
		}

		$headers = array_map( static function ( $h ) {
			return trim( strtolower( (string) $h ) );
		}, $headers );

		$rows = [];
		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			if ( $this->is_empty_row( $row ) ) {
				continue;
			}
			$mapped = [];
			foreach ( $headers as $i => $header ) {
				if ( '' === $header ) {
					continue;
				}
				$mapped[ $header ] = $row[ $i ] ?? '';
			}
			$rows[] = $mapped;
		}

		fclose( $handle );

		$sheet_name = pathinfo( $file_path, PATHINFO_FILENAME );
		return [ $sheet_name => $rows ];
	}

	private function get_sheet_names( string $file_path ): array|\WP_Error {
		if ( class_exists( '\PhpOffice\PhpSpreadsheet\IOFactory' ) ) {
			try {
				$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile( $file_path );
				$reader->setReadDataOnly( true );
				$spreadsheet = $reader->load( $file_path );
				return $spreadsheet->getSheetNames();
			} catch ( \Exception $e ) {
				return new \WP_Error( 'spreadsheet_error', $e->getMessage() );
			}
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'missing_dependency', __( 'Cannot read sheet names without PhpSpreadsheet or ZipArchive.', 'guvenhijyen' ) );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			return new \WP_Error( 'zip_open_failed', __( 'Cannot open XLSX file.', 'guvenhijyen' ) );
		}

		$workbook_xml = $zip->getFromName( 'xl/workbook.xml' );
		$zip->close();

		if ( false === $workbook_xml ) {
			return new \WP_Error( 'invalid_xlsx', __( 'Invalid XLSX structure.', 'guvenhijyen' ) );
		}

		$doc = new \DOMDocument();
		$doc->loadXML( $workbook_xml );
		$sheet_nodes = $doc->getElementsByTagName( 'sheet' );

		$names = [];
		foreach ( $sheet_nodes as $node ) {
			$names[] = $node->getAttribute( 'name' );
		}

		return $names;
	}

	private function is_empty_row( array $row ): bool {
		foreach ( $row as $cell ) {
			if ( '' !== trim( (string) $cell ) ) {
				return false;
			}
		}
		return true;
	}

	private function column_to_index( string $col ): int {
		$col   = strtoupper( $col );
		$index = 0;
		$len   = strlen( $col );
		for ( $i = 0; $i < $len; $i++ ) {
			$index = $index * 26 + ( ord( $col[ $i ] ) - ord( 'A' ) + 1 );
		}
		return $index - 1;
	}

	private function get_text_fields_for_sheet( string $sheet ): array {
		$map = [
			'01_PRODUCTS'   => [
				'product_name'      => 'product_name',
				'short_description' => 'short_description',
				'long_description'  => 'long_description',
				'seo_title'         => 'seo_title',
				'meta_description'  => 'meta_description',
			],
			'03_CATEGORIES' => [
				'category_description' => 'category_description',
			],
			'04_BRANDS'     => [
				'brand_description' => 'brand_description',
			],
			'08_SECTORS'    => [
				'sector_description' => 'sector_description',
			],
			'10_DOCUMENTS'  => [
				'document_description' => 'document_description',
			],
			'13_BLOG'       => [
				'content'          => 'blog_content',
				'seo_title'        => 'seo_title',
				'meta_description' => 'meta_description',
			],
		];

		return $map[ $sheet ] ?? [];
	}
}

<?php

defined( 'ABSPATH' ) || exit;

class GH_Product_Importer {

	private GH_Text_Importer $text_importer;
	private GH_Image_Importer $image_importer;
	private string $import_id;
	private string $images_base_path;

	private const REQUIRED_FIELDS = [ 'product_name', 'sku' ];

	private const VALID_PRODUCT_TYPES = [ 'simple', 'variable' ];

	private const FIELD_MAP = [
		'migration_key'       => '_gh_migration_key',
		'source_product_id'   => '_gh_source_product_id',
		'sales_unit'          => '_gh_sales_unit',
		'minimum_quantity'    => '_gh_minimum_quantity',
		'quantity_step'       => '_gh_quantity_step',
		'procurement_status'  => '_gh_procurement_status',
	];

	public function __construct( string $import_id, string $images_base_path = '' ) {
		$this->text_importer    = new GH_Text_Importer();
		$this->image_importer   = new GH_Image_Importer();
		$this->import_id        = $import_id;
		$this->images_base_path = $images_base_path;
	}

	public function import_product( array $row_data, string $mode = 'dry_run' ): array {
		$errors = $this->validate_product( $row_data );

		$has_blocking = false;
		$needs_review = false;
		foreach ( $errors as $error ) {
			if ( 'error' === $error['severity'] ) {
				$has_blocking = true;
			}
			if ( 'warning' === $error['severity'] ) {
				$needs_review = true;
			}
		}

		if ( $has_blocking ) {
			return [
				'status' => 'failed',
				'errors' => $errors,
			];
		}

		$row_data = $this->normalize_product( $row_data );
		$identity = $this->resolve_identity( $row_data );

		if ( 'dry_run' === $mode ) {
			$status = $identity['found'] ? 'would_update' : 'would_create';
			if ( $needs_review ) {
				$status = 'would_review';
			}
			return [
				'status'      => $status,
				'product_id'  => $identity['product_id'],
				'resolved_by' => $identity['resolved_by'],
				'errors'      => $errors,
			];
		}

		if ( $identity['found'] ) {
			$product_id = $this->update_product( $identity['product_id'], $row_data );
			$action     = 'updated';
		} else {
			$product_id = $this->create_product( $row_data );
			$action     = 'created';
		}

		if ( is_wp_error( $product_id ) ) {
			return [
				'status' => 'failed',
				'errors' => array_merge( $errors, [
					[
						'field'      => 'product',
						'error_code' => 'save_failed',
						'message'    => $product_id->get_error_message(),
						'severity'   => 'error',
					],
				] ),
			];
		}

		$this->save_meta( $product_id, $row_data );
		$this->handle_images( $product_id, $row_data );

		$final_status = $action;
		if ( $needs_review ) {
			update_post_meta( $product_id, '_gh_import_status', 'manual_review' );
			$final_status = 'manual_review';
		} else {
			update_post_meta( $product_id, '_gh_import_status', 'imported' );
		}

		update_post_meta( $product_id, '_gh_import_id', $this->import_id );
		update_post_meta( $product_id, '_gh_import_timestamp', current_time( 'mysql' ) );

		return [
			'status'      => $final_status,
			'product_id'  => $product_id,
			'resolved_by' => $identity['resolved_by'],
			'errors'      => $errors,
		];
	}

	public function resolve_identity( array $row_data ): array {
		$existing_id = ! empty( $row_data['existing_wp_post_id'] ) ? absint( $row_data['existing_wp_post_id'] ) : 0;
		if ( $existing_id && 'product' === get_post_type( $existing_id ) ) {
			return [
				'found'       => true,
				'product_id'  => $existing_id,
				'resolved_by' => 'existing_wp_post_id',
			];
		}

		if ( ! empty( $row_data['migration_key'] ) ) {
			$found = $this->find_by_meta( '_gh_migration_key', sanitize_text_field( $row_data['migration_key'] ) );
			if ( $found ) {
				return [
					'found'       => true,
					'product_id'  => $found,
					'resolved_by' => 'migration_key',
				];
			}
		}

		if ( ! empty( $row_data['sku'] ) ) {
			$found = wc_get_product_id_by_sku( sanitize_text_field( $row_data['sku'] ) );
			if ( $found ) {
				return [
					'found'       => true,
					'product_id'  => $found,
					'resolved_by' => 'sku',
				];
			}
		}

		return [
			'found'       => false,
			'product_id'  => 0,
			'resolved_by' => 'none',
		];
	}

	public function map_category( string $category_name, string $subcategory_name = '' ): array {
		$term_ids = [];

		if ( empty( $category_name ) ) {
			return $term_ids;
		}

		$parent_term = get_term_by( 'name', $category_name, 'product_cat' );
		if ( ! $parent_term ) {
			$result = wp_insert_term( $category_name, 'product_cat' );
			if ( is_wp_error( $result ) ) {
				return $term_ids;
			}
			$term_ids[] = $result['term_id'];
			$parent_id  = $result['term_id'];
		} else {
			$term_ids[] = $parent_term->term_id;
			$parent_id  = $parent_term->term_id;
		}

		if ( ! empty( $subcategory_name ) ) {
			$sub_term = get_term_by( 'name', $subcategory_name, 'product_cat' );

			if ( $sub_term && (int) $sub_term->parent === $parent_id ) {
				$term_ids[] = $sub_term->term_id;
			} elseif ( ! $sub_term ) {
				$result = wp_insert_term( $subcategory_name, 'product_cat', [ 'parent' => $parent_id ] );
				if ( ! is_wp_error( $result ) ) {
					$term_ids[] = $result['term_id'];
				}
			} else {
				$term_ids[] = $sub_term->term_id;
			}
		}

		return $term_ids;
	}

	public function map_brand( string $brand_name ): int {
		if ( empty( $brand_name ) ) {
			return 0;
		}

		$taxonomy = taxonomy_exists( 'product_brand' ) ? 'product_brand' : 'pa_brand';

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}

		$term = get_term_by( 'name', $brand_name, $taxonomy );
		if ( $term ) {
			return $term->term_id;
		}

		$result = wp_insert_term( $brand_name, $taxonomy );
		if ( is_wp_error( $result ) ) {
			return 0;
		}

		return $result['term_id'];
	}

	public function attach_images( int $product_id, ?string $featured, array $gallery, string $base_path ): array {
		return $this->image_importer->attach_to_product( $product_id, $featured, $gallery, $base_path );
	}

	private function validate_product( array $row_data ): array {
		$errors = [];

		foreach ( self::REQUIRED_FIELDS as $field ) {
			if ( empty( $row_data[ $field ] ) ) {
				$errors[] = [
					'field'              => $field,
					'error_code'         => 'required_missing',
					'message'            => sprintf(
						/* translators: %s: field name */
						__( 'Required field "%s" is missing.', 'guvenhijyen' ),
						$field
					),
					'severity'           => 'error',
					'recommended_action' => __( 'Provide a value for this field.', 'guvenhijyen' ),
				];
			}
		}

		if ( ! empty( $row_data['product_type'] ) ) {
			$type = strtolower( trim( $row_data['product_type'] ) );
			if ( ! in_array( $type, self::VALID_PRODUCT_TYPES, true ) ) {
				$errors[] = [
					'field'      => 'product_type',
					'error_code' => 'invalid_product_type',
					'message'    => sprintf(
						/* translators: %s: provided type */
						__( 'Invalid product type "%s". Use "simple" or "variable".', 'guvenhijyen' ),
						$type
					),
					'severity'   => 'error',
				];
			}
		}

		if ( ! empty( $row_data['product_type'] ) && 'variable' !== strtolower( $row_data['product_type'] ) && ! empty( $row_data['parent_sku'] ) ) {
			$errors[] = [
				'field'      => 'parent_sku',
				'error_code' => 'parent_sku_on_non_variable',
				'message'    => __( 'parent_sku is set but product_type is not "variable".', 'guvenhijyen' ),
				'severity'   => 'warning',
			];
		}

		$text_fields = [
			'product_name'      => 'product_name',
			'short_description' => 'short_description',
			'long_description'  => 'long_description',
			'seo_title'         => 'seo_title',
			'meta_description'  => 'meta_description',
		];

		foreach ( $text_fields as $row_key => $field_name ) {
			if ( ! empty( $row_data[ $row_key ] ) ) {
				$text_errors = $this->text_importer->validate_field( $field_name, $row_data[ $row_key ] );
				foreach ( $text_errors as $te ) {
					$te['sku'] = $row_data['sku'] ?? '';
					$errors[]  = $te;
				}
			}
		}

		if ( ! empty( $row_data['minimum_quantity'] ) ) {
			$min_qty = $row_data['minimum_quantity'];
			if ( ! is_numeric( $min_qty ) || (int) $min_qty < 1 ) {
				$errors[] = [
					'field'      => 'minimum_quantity',
					'error_code' => 'invalid_minimum_quantity',
					'message'    => __( 'Minimum quantity must be a positive integer.', 'guvenhijyen' ),
					'severity'   => 'error',
				];
			}
		}

		if ( ! empty( $row_data['quantity_step'] ) ) {
			$step = $row_data['quantity_step'];
			if ( ! is_numeric( $step ) || (int) $step < 1 ) {
				$errors[] = [
					'field'      => 'quantity_step',
					'error_code' => 'invalid_quantity_step',
					'message'    => __( 'Quantity step must be a positive integer.', 'guvenhijyen' ),
					'severity'   => 'error',
				];
			}
		}

		return $errors;
	}

	private function normalize_product( array $row_data ): array {
		$row_data['product_name'] = $this->text_importer->sanitize_field( 'product_name', $row_data['product_name'] ?? '' );
		$row_data['sku']          = sanitize_text_field( trim( $row_data['sku'] ?? '' ) );

		if ( ! empty( $row_data['slug'] ) ) {
			$row_data['slug'] = sanitize_title( $row_data['slug'] );
		} else {
			$row_data['slug'] = sanitize_title( $row_data['product_name'] );
		}

		$row_data['product_type'] = strtolower( trim( $row_data['product_type'] ?? 'simple' ) );

		if ( ! empty( $row_data['short_description'] ) ) {
			$row_data['short_description'] = $this->text_importer->sanitize_field( 'short_description', $row_data['short_description'] );
		}
		if ( ! empty( $row_data['long_description'] ) ) {
			$row_data['long_description'] = $this->text_importer->sanitize_field( 'long_description', $row_data['long_description'] );
		}
		if ( ! empty( $row_data['seo_title'] ) ) {
			$row_data['seo_title'] = $this->text_importer->sanitize_field( 'seo_title', $row_data['seo_title'] );
		}
		if ( ! empty( $row_data['meta_description'] ) ) {
			$row_data['meta_description'] = $this->text_importer->sanitize_field( 'meta_description', $row_data['meta_description'] );
		}

		$row_data['publication_status'] = 'draft';

		return $row_data;
	}

	private function create_product( array $row_data ): int|\WP_Error {
		$product_type = $row_data['product_type'] ?? 'simple';

		if ( 'variable' === $product_type ) {
			$product = new \WC_Product_Variable();
		} else {
			$product = new \WC_Product_Simple();
		}

		$product->set_name( $row_data['product_name'] );
		$product->set_sku( $row_data['sku'] );
		$product->set_slug( $row_data['slug'] ?? '' );
		$product->set_status( 'draft' );
		$product->set_catalog_visibility( 'visible' );

		if ( ! empty( $row_data['short_description'] ) ) {
			$product->set_short_description( $row_data['short_description'] );
		}
		if ( ! empty( $row_data['long_description'] ) ) {
			$product->set_description( $row_data['long_description'] );
		}

		$product_id = $product->save();

		if ( ! $product_id ) {
			return new \WP_Error( 'product_create_failed', __( 'Failed to create product.', 'guvenhijyen' ) );
		}

		$this->assign_terms( $product_id, $row_data );

		return $product_id;
	}

	private function update_product( int $product_id, array $row_data ): int|\WP_Error {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new \WP_Error( 'product_not_found', __( 'Product not found for update.', 'guvenhijyen' ) );
		}

		$product->set_name( $row_data['product_name'] );

		if ( ! empty( $row_data['sku'] ) ) {
			$product->set_sku( $row_data['sku'] );
		}
		if ( ! empty( $row_data['slug'] ) ) {
			$product->set_slug( $row_data['slug'] );
		}
		if ( ! empty( $row_data['short_description'] ) ) {
			$product->set_short_description( $row_data['short_description'] );
		}
		if ( ! empty( $row_data['long_description'] ) ) {
			$product->set_description( $row_data['long_description'] );
		}

		$product->save();
		$this->assign_terms( $product_id, $row_data );

		return $product_id;
	}

	private function assign_terms( int $product_id, array $row_data ): void {
		if ( ! empty( $row_data['category'] ) ) {
			$cat_ids = $this->map_category( $row_data['category'], $row_data['subcategory'] ?? '' );
			if ( ! empty( $cat_ids ) ) {
				wp_set_object_terms( $product_id, $cat_ids, 'product_cat' );
			}
		}

		if ( ! empty( $row_data['brand'] ) ) {
			$brand_id = $this->map_brand( $row_data['brand'] );
			if ( $brand_id ) {
				$taxonomy = taxonomy_exists( 'product_brand' ) ? 'product_brand' : 'pa_brand';
				wp_set_object_terms( $product_id, [ $brand_id ], $taxonomy );
			}
		}
	}

	private function save_meta( int $product_id, array $row_data ): void {
		foreach ( self::FIELD_MAP as $row_key => $meta_key ) {
			if ( isset( $row_data[ $row_key ] ) && '' !== $row_data[ $row_key ] ) {
				update_post_meta( $product_id, $meta_key, sanitize_text_field( $row_data[ $row_key ] ) );
			}
		}

		if ( ! empty( $row_data['source_product_id'] ) ) {
			update_post_meta( $product_id, '_gh_source_product_id', sanitize_text_field( $row_data['source_product_id'] ) );
		}

		if ( ! empty( $row_data['seo_title'] ) ) {
			update_post_meta( $product_id, '_yoast_wpseo_title', sanitize_text_field( $row_data['seo_title'] ) );
			update_post_meta( $product_id, 'rank_math_title', sanitize_text_field( $row_data['seo_title'] ) );
		}
		if ( ! empty( $row_data['meta_description'] ) ) {
			update_post_meta( $product_id, '_yoast_wpseo_metadesc', sanitize_text_field( $row_data['meta_description'] ) );
			update_post_meta( $product_id, 'rank_math_description', sanitize_text_field( $row_data['meta_description'] ) );
		}
	}

	private function handle_images( int $product_id, array $row_data ): void {
		if ( empty( $this->images_base_path ) ) {
			return;
		}

		$featured = $row_data['featured_image'] ?? null;
		$gallery  = [];

		if ( ! empty( $row_data['gallery_images'] ) ) {
			$gallery = array_map( 'trim', explode( ',', $row_data['gallery_images'] ) );
			$gallery = array_filter( $gallery );
		}

		if ( empty( $featured ) && ! empty( $row_data['sku'] ) ) {
			$resolved = $this->image_importer->resolve_product_images( $row_data['sku'], $this->images_base_path );
			$featured = $resolved['featured'];
			if ( empty( $gallery ) ) {
				$gallery = $resolved['gallery'];
			}
		}

		if ( $featured || ! empty( $gallery ) ) {
			$this->attach_images( $product_id, $featured, $gallery, $this->images_base_path );
		}
	}

	private function find_by_meta( string $meta_key, string $meta_value ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				INNER JOIN {$wpdb->posts} ON {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id
				WHERE {$wpdb->postmeta}.meta_key = %s
				AND {$wpdb->postmeta}.meta_value = %s
				AND {$wpdb->posts}.post_type IN ('product', 'product_variation')
				LIMIT 1",
				$meta_key,
				$meta_value
			)
		);

		return $post_id ? (int) $post_id : 0;
	}
}

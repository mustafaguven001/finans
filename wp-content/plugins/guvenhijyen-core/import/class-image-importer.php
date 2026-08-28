<?php

defined( 'ABSPATH' ) || exit;

class GH_Image_Importer {

	private const IMAGE_STANDARDS = [
		'product_featured' => [
			'width'     => 1600,
			'height'    => 1600,
			'ratio'     => '1:1',
			'min_width' => 1200,
			'min_height'=> 1200,
			'formats'   => [ 'jpg', 'jpeg', 'png', 'webp' ],
		],
		'product_gallery' => [
			'width'     => 1600,
			'height'    => 1600,
			'ratio'     => '1:1',
			'min_width' => 800,
			'min_height'=> 800,
			'formats'   => [ 'jpg', 'jpeg', 'png', 'webp' ],
		],
		'category' => [
			'width'     => 1600,
			'height'    => 1200,
			'ratio'     => '4:3',
			'min_width' => 800,
			'min_height'=> 600,
			'formats'   => [ 'jpg', 'jpeg', 'png', 'webp' ],
		],
		'sector' => [
			'width'     => 1920,
			'height'    => 1080,
			'ratio'     => '16:9',
			'min_width' => 1280,
			'min_height'=> 720,
			'formats'   => [ 'jpg', 'jpeg', 'png', 'webp' ],
		],
		'blog' => [
			'width'     => 1600,
			'height'    => 900,
			'ratio'     => '16:9',
			'min_width' => 1200,
			'min_height'=> 675,
			'formats'   => [ 'jpg', 'jpeg', 'png', 'webp' ],
		],
		'brand_logo' => [
			'width'     => 800,
			'height'    => 400,
			'ratio'     => '2:1',
			'min_width' => 200,
			'min_height'=> 100,
			'formats'   => [ 'svg', 'png', 'jpg', 'jpeg', 'webp' ],
		],
	];

	private const FOLDER_MAP = [
		'product_featured' => 'products',
		'product_gallery'  => 'products',
		'category'         => 'categories',
		'sector'           => 'sectors',
		'blog'             => 'blog',
		'brand_logo'       => 'brands',
	];

	private array $hash_registry = [];

	public function validate_image( string $file_path, string $image_type ): array {
		$errors = [];
		$standards = self::IMAGE_STANDARDS[ $image_type ] ?? null;

		if ( ! $standards ) {
			$errors[] = $this->make_error( 'unknown_type', __( 'Unknown image type.', 'guvenhijyen' ) );
			return $errors;
		}

		if ( ! file_exists( $file_path ) ) {
			$errors[] = $this->make_error(
				'file_not_found',
				sprintf(
					/* translators: %s: file path */
					__( 'Image file not found: %s', 'guvenhijyen' ),
					basename( $file_path )
				)
			);
			return $errors;
		}

		if ( ! is_readable( $file_path ) ) {
			$errors[] = $this->make_error( 'file_not_readable', __( 'Image file is not readable.', 'guvenhijyen' ) );
			return $errors;
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, $standards['formats'], true ) ) {
			$errors[] = $this->make_error(
				'invalid_format',
				sprintf(
					/* translators: 1: file extension, 2: allowed formats */
					__( 'Invalid format "%1$s". Allowed: %2$s', 'guvenhijyen' ),
					$extension,
					implode( ', ', $standards['formats'] )
				)
			);
			return $errors;
		}

		if ( 'svg' === $extension ) {
			$svg_errors = $this->validate_svg( $file_path );
			$errors     = array_merge( $errors, $svg_errors );
			return $errors;
		}

		$image_info = @getimagesize( $file_path );
		if ( false === $image_info ) {
			$errors[] = $this->make_error(
				'corrupt_file',
				__( 'Image file appears to be corrupt or unreadable.', 'guvenhijyen' ),
				'error',
				__( 'Re-export the image from the original source.', 'guvenhijyen' )
			);
			return $errors;
		}

		$width  = $image_info[0];
		$height = $image_info[1];

		if ( $width < $standards['min_width'] || $height < $standards['min_height'] ) {
			$errors[] = $this->make_error(
				'below_minimum',
				sprintf(
					/* translators: 1: actual dimensions, 2: minimum dimensions */
					__( 'Image dimensions %1$s are below minimum %2$s.', 'guvenhijyen' ),
					"{$width}x{$height}",
					"{$standards['min_width']}x{$standards['min_height']}"
				),
				'error',
				__( 'Provide a higher resolution image.', 'guvenhijyen' )
			);
		}

		if ( $width < $standards['width'] || $height < $standards['height'] ) {
			$errors[] = $this->make_error(
				'below_recommended',
				sprintf(
					/* translators: 1: actual dimensions, 2: recommended dimensions */
					__( 'Image dimensions %1$s are below recommended %2$s.', 'guvenhijyen' ),
					"{$width}x{$height}",
					"{$standards['width']}x{$standards['height']}"
				),
				'warning'
			);
		}

		$file_hash  = hash_file( 'sha256', $file_path );
		$duplicates = $this->check_duplicate_hash( $file_hash, $file_path );
		if ( ! empty( $duplicates ) ) {
			$errors[] = $this->make_error(
				'duplicate_hash',
				sprintf(
					/* translators: %s: duplicate file paths */
					__( 'Duplicate image detected. Same hash as: %s', 'guvenhijyen' ),
					implode( ', ', $duplicates )
				),
				'warning',
				__( 'Verify this is intentional. Duplicates will not be auto-deleted.', 'guvenhijyen' )
			);
		}

		return $errors;
	}

	public function import_image( string $file_path, string $image_type, int $parent_id = 0 ): array {
		$errors = $this->validate_image( $file_path, $image_type );
		$has_blocking = false;

		foreach ( $errors as $error ) {
			if ( 'error' === $error['severity'] ) {
				$has_blocking = true;
				break;
			}
		}

		if ( $has_blocking ) {
			return [
				'status' => 'error',
				'errors' => $errors,
			];
		}

		$file_path = $this->normalize_orientation( $file_path );

		$normalized_name = $this->normalize_filename( $file_path );
		$file_array      = [
			'name'     => $normalized_name,
			'tmp_name' => $file_path,
			'error'    => 0,
			'size'     => filesize( $file_path ),
		];

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$existing_id = $this->find_existing_attachment( $normalized_name );
		if ( $existing_id ) {
			return [
				'status'        => 'existing',
				'attachment_id' => $existing_id,
				'errors'        => $errors,
			];
		}

		$attachment_id = media_handle_sideload( $file_array, $parent_id );

		if ( is_wp_error( $attachment_id ) ) {
			return [
				'status' => 'error',
				'errors' => array_merge( $errors, [
					$this->make_error( 'upload_failed', $attachment_id->get_error_message() ),
				] ),
			];
		}

		$file_hash = hash_file( 'sha256', get_attached_file( $attachment_id ) );
		update_post_meta( $attachment_id, '_gh_image_hash', $file_hash );
		update_post_meta( $attachment_id, '_gh_image_type', $image_type );
		update_post_meta( $attachment_id, '_gh_import_source', basename( $file_path ) );

		return [
			'status'        => 'imported',
			'attachment_id' => $attachment_id,
			'errors'        => $errors,
		];
	}

	public function attach_to_product( int $product_id, ?string $featured_file, array $gallery_files, string $base_path ): array {
		$results = [
			'featured' => null,
			'gallery'  => [],
			'errors'   => [],
		];

		if ( $featured_file ) {
			$featured_path = trailingslashit( $base_path ) . 'products/' . $featured_file;
			$result        = $this->import_image( $featured_path, 'product_featured', $product_id );

			if ( 'error' !== $result['status'] && ! empty( $result['attachment_id'] ) ) {
				set_post_thumbnail( $product_id, $result['attachment_id'] );
				$results['featured'] = $result['attachment_id'];
			}
			$results['errors'] = array_merge( $results['errors'], $result['errors'] ?? [] );
		}

		$gallery_ids = [];
		foreach ( $gallery_files as $gallery_file ) {
			$gallery_path = trailingslashit( $base_path ) . 'products/' . $gallery_file;
			$result       = $this->import_image( $gallery_path, 'product_gallery', $product_id );

			if ( 'error' !== $result['status'] && ! empty( $result['attachment_id'] ) ) {
				$gallery_ids[] = $result['attachment_id'];
			}
			$results['errors'] = array_merge( $results['errors'], $result['errors'] ?? [] );
		}

		if ( ! empty( $gallery_ids ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$product->set_gallery_image_ids( $gallery_ids );
				$product->save();
			}
			$results['gallery'] = $gallery_ids;
		}

		return $results;
	}

	public function resolve_product_images( string $sku, string $base_path ): array {
		$images_dir = trailingslashit( $base_path ) . 'products/';
		$featured   = null;
		$gallery    = [];

		if ( ! is_dir( $images_dir ) ) {
			return [ 'featured' => null, 'gallery' => [] ];
		}

		$pattern = $images_dir . sanitize_file_name( $sku ) . '-*';
		$files   = glob( $pattern );

		if ( empty( $files ) ) {
			return [ 'featured' => null, 'gallery' => [] ];
		}

		sort( $files );
		foreach ( $files as $file ) {
			$basename = basename( $file );
			if ( null === $featured ) {
				$featured = $basename;
			} else {
				$gallery[] = $basename;
			}
		}

		return [
			'featured' => $featured,
			'gallery'  => $gallery,
		];
	}

	public function normalize_filename( string $file_path ): string {
		$name      = pathinfo( $file_path, PATHINFO_FILENAME );
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		$name = sanitize_file_name( $name );
		$name = strtolower( $name );

		$turkish_map = [
			'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
			'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u',
		];
		$name = strtr( $name, $turkish_map );

		$name = preg_replace( '/[^a-z0-9\-_]/', '-', $name );
		$name = preg_replace( '/-+/', '-', $name );
		$name = trim( $name, '-' );

		return $name . '.' . $extension;
	}

	private function normalize_orientation( string $file_path ): string {
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, [ 'jpg', 'jpeg' ], true ) ) {
			return $file_path;
		}

		if ( ! function_exists( 'exif_read_data' ) ) {
			return $file_path;
		}

		$exif = @exif_read_data( $file_path );
		if ( empty( $exif['Orientation'] ) || 1 === (int) $exif['Orientation'] ) {
			return $file_path;
		}

		$editor = wp_get_image_editor( $file_path );
		if ( is_wp_error( $editor ) ) {
			return $file_path;
		}

		$orientation = (int) $exif['Orientation'];
		switch ( $orientation ) {
			case 3:
				$editor->rotate( 180 );
				break;
			case 6:
				$editor->rotate( -90 );
				break;
			case 8:
				$editor->rotate( 90 );
				break;
		}

		$result = $editor->save( $file_path );
		if ( is_wp_error( $result ) ) {
			return $file_path;
		}

		return $result['path'] ?? $file_path;
	}

	private function validate_svg( string $file_path ): array {
		$errors  = [];
		$content = file_get_contents( $file_path );

		if ( false === $content ) {
			$errors[] = $this->make_error( 'svg_unreadable', __( 'Cannot read SVG file.', 'guvenhijyen' ) );
			return $errors;
		}

		if ( preg_match( '/<\s*script/i', $content ) ) {
			$errors[] = $this->make_error(
				'svg_script',
				__( 'SVG contains script elements. This is not allowed.', 'guvenhijyen' ),
				'error',
				__( 'Remove all script elements from the SVG.', 'guvenhijyen' )
			);
		}

		if ( preg_match( '/\bon\w+\s*=/i', $content ) ) {
			$errors[] = $this->make_error(
				'svg_event_handler',
				__( 'SVG contains inline event handlers.', 'guvenhijyen' ),
				'error',
				__( 'Remove all event handler attributes from the SVG.', 'guvenhijyen' )
			);
		}

		if ( preg_match( '/<\s*foreignObject/i', $content ) ) {
			$errors[] = $this->make_error(
				'svg_foreign_object',
				__( 'SVG contains foreignObject elements.', 'guvenhijyen' ),
				'warning'
			);
		}

		return $errors;
	}

	private function find_existing_attachment( string $filename ): ?int {
		global $wpdb;

		$name_no_ext = pathinfo( $filename, PATHINFO_FILENAME );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_name = %s LIMIT 1",
				$name_no_ext
			)
		);

		return $id ? (int) $id : null;
	}

	private function check_duplicate_hash( string $hash, string $current_path ): array {
		$duplicates = [];

		if ( isset( $this->hash_registry[ $hash ] ) ) {
			foreach ( $this->hash_registry[ $hash ] as $path ) {
				if ( $path !== $current_path ) {
					$duplicates[] = basename( $path );
				}
			}
		}

		$this->hash_registry[ $hash ][] = $current_path;

		return $duplicates;
	}

	public function reset_hash_registry(): void {
		$this->hash_registry = [];
	}

	public function get_standards( string $image_type ): ?array {
		return self::IMAGE_STANDARDS[ $image_type ] ?? null;
	}

	public function get_expected_filename( string $type, string $identifier, int $index = 1 ): string {
		switch ( $type ) {
			case 'product_featured':
			case 'product_gallery':
				return sprintf( '%s-%02d.jpg', sanitize_file_name( $identifier ), $index );
			case 'category':
				return sprintf( 'category-%s.jpg', sanitize_title( $identifier ) );
			case 'sector':
				return sprintf( 'sector-%s.jpg', sanitize_title( $identifier ) );
			case 'blog':
				return sprintf( 'blog-%s.jpg', sanitize_title( $identifier ) );
			case 'brand_logo':
				return sprintf( 'brand-%s.svg', sanitize_title( $identifier ) );
			default:
				return sanitize_file_name( $identifier );
		}
	}

	private function make_error( string $code, string $message, string $severity = 'error', string $action = '' ): array {
		return [
			'field'              => 'image',
			'error_code'         => $code,
			'message'            => $message,
			'severity'           => $severity,
			'recommended_action' => $action,
		];
	}
}

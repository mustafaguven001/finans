<?php

defined( 'ABSPATH' ) || exit;

class GH_Text_Importer {

	private const TURKISH_CHARS = 'çğıöşüÇĞİÖŞÜ';

	private const FIELD_RULES = [
		'product_name'         => [ 'max_length' => 200, 'required' => true, 'html' => false ],
		'short_description'    => [ 'max_length' => 500, 'required' => false, 'html' => true ],
		'long_description'     => [ 'max_length' => 10000, 'required' => false, 'html' => true ],
		'category_description' => [ 'max_length' => 5000, 'required' => false, 'html' => true ],
		'brand_description'    => [ 'max_length' => 5000, 'required' => false, 'html' => true ],
		'sector_description'   => [ 'max_length' => 5000, 'required' => false, 'html' => true ],
		'document_description' => [ 'max_length' => 2000, 'required' => false, 'html' => true ],
		'blog_content'         => [ 'max_length' => 50000, 'required' => false, 'html' => true ],
		'seo_title'            => [ 'max_length' => 60, 'required' => false, 'html' => false ],
		'meta_description'     => [ 'max_length' => 160, 'required' => false, 'html' => false ],
	];

	private const LOREM_PATTERNS = [
		'lorem ipsum',
		'dolor sit amet',
		'consectetur adipiscing',
		'sed do eiusmod',
		'tempor incididunt',
		'ut labore et dolore',
		'magna aliqua',
	];

	private const PLACEHOLDER_PATTERNS = [
		'/^\[.*\]$/',
		'/^TODO/i',
		'/^FIXME/i',
		'/^placeholder/i',
		'/^test\s*(text|content|data)?$/i',
		'/^xxx+$/i',
		'/^sample\s*(text|content|data)?$/i',
		'/^insert\s+.*\s+here$/i',
		'/^buraya\s+.*\s+yaz/i',
	];

	private const UNSUPPORTED_CLAIM_PATTERNS = [
		'/\b(fda|ce|iso)\s*(onay|sertifika)/iu',
		'/\b(kanserojen|kanser\s+önle)/iu',
		'/\b(tedavi\s+eder|iyileştirir|şifa)/iu',
		'/\b%\s*\d+\s*(bakteri|virüs|mikrop)\s*(öldür|yok\s*ed)/iu',
		'/\b(tıbbi\s+cihaz|medikal\s+ürün)/iu',
		'/\b(garantili\s+sonuç|kesin\s+çözüm)/iu',
	];

	public function validate_field( string $field_name, string $value ): array {
		$errors = [];
		$rules  = self::FIELD_RULES[ $field_name ] ?? null;

		if ( ! $rules ) {
			$errors[] = $this->make_error( $field_name, 'unknown_field', __( 'Unknown text field type.', 'guvenhijyen' ) );
			return $errors;
		}

		if ( $rules['required'] && $this->is_empty_content( $value ) ) {
			$errors[] = $this->make_error(
				$field_name,
				'required_empty',
				__( 'Required field is empty.', 'guvenhijyen' ),
				'error'
			);
			return $errors;
		}

		if ( $this->is_empty_content( $value ) ) {
			return $errors;
		}

		$encoding_result = $this->validate_encoding( $value );
		if ( is_wp_error( $encoding_result ) ) {
			$errors[] = $this->make_error(
				$field_name,
				'invalid_encoding',
				$encoding_result->get_error_message(),
				'error'
			);
			return $errors;
		}

		if ( $this->contains_script_tags( $value ) ) {
			$errors[] = $this->make_error(
				$field_name,
				'script_injection',
				__( 'Content contains script tags.', 'guvenhijyen' ),
				'error',
				__( 'Remove all script tags from the content.', 'guvenhijyen' )
			);
		}

		if ( $this->is_lorem_ipsum( $value ) ) {
			$errors[] = $this->make_error(
				$field_name,
				'lorem_ipsum',
				__( 'Content contains Lorem Ipsum placeholder text.', 'guvenhijyen' ),
				'error',
				__( 'Replace with actual product/business content.', 'guvenhijyen' )
			);
		}

		if ( $this->is_placeholder( $value ) ) {
			$errors[] = $this->make_error(
				$field_name,
				'placeholder_text',
				__( 'Content appears to be placeholder text.', 'guvenhijyen' ),
				'error',
				__( 'Replace with actual content.', 'guvenhijyen' )
			);
		}

		if ( mb_strlen( $value ) > $rules['max_length'] ) {
			$errors[] = $this->make_error(
				$field_name,
				'too_long',
				sprintf(
					/* translators: 1: current length, 2: max length */
					__( 'Content exceeds maximum length (%1$d / %2$d characters).', 'guvenhijyen' ),
					mb_strlen( $value ),
					$rules['max_length']
				),
				'error',
				__( 'Shorten the content to fit within the limit.', 'guvenhijyen' )
			);
		}

		$claim_issues = $this->detect_unsupported_claims( $value );
		if ( ! empty( $claim_issues ) ) {
			$errors[] = $this->make_error(
				$field_name,
				'unsupported_claims',
				sprintf(
					/* translators: %s: matched claim patterns */
					__( 'Content may contain unsupported claims: %s', 'guvenhijyen' ),
					implode( ', ', $claim_issues )
				),
				'warning',
				__( 'Review claims and ensure supporting documentation exists. Item flagged for manual review.', 'guvenhijyen' )
			);
		}

		return $errors;
	}

	public function sanitize_field( string $field_name, string $value ): string {
		$rules = self::FIELD_RULES[ $field_name ] ?? null;
		if ( ! $rules ) {
			return sanitize_text_field( $value );
		}

		$value = $this->normalize_encoding( $value );
		$value = $this->normalize_whitespace( $value );

		if ( $rules['html'] ) {
			$value = wp_kses_post( $value );
		} else {
			$value = sanitize_text_field( $value );
		}

		$value = $this->preserve_turkish_chars( $value );

		return $value;
	}

	public function validate_encoding( string $value ): true|\WP_Error {
		if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
			return new \WP_Error(
				'invalid_encoding',
				__( 'Content is not valid UTF-8. Please ensure the file is saved with UTF-8 encoding.', 'guvenhijyen' )
			);
		}

		if ( preg_match( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $value ) ) {
			return new \WP_Error(
				'control_characters',
				__( 'Content contains invalid control characters.', 'guvenhijyen' )
			);
		}

		return true;
	}

	public function normalize_encoding( string $value ): string {
		if ( ! mb_check_encoding( $value, 'UTF-8' ) ) {
			$detected = mb_detect_encoding( $value, [ 'UTF-8', 'ISO-8859-9', 'Windows-1254', 'ISO-8859-1' ], true );
			if ( $detected && 'UTF-8' !== $detected ) {
				$value = mb_convert_encoding( $value, 'UTF-8', $detected );
			}
		}

		$value = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $value );

		return $value;
	}

	private function normalize_whitespace( string $value ): string {
		$value = str_replace( [ "\r\n", "\r" ], "\n", $value );
		$value = preg_replace( '/[^\S\n]+/', ' ', $value );
		$value = preg_replace( '/\n{3,}/', "\n\n", $value );
		$value = trim( $value );

		return $value;
	}

	private function preserve_turkish_chars( string $value ): string {
		$replacements = [
			'Ã§' => 'ç', 'ÄŸ' => 'ğ', 'Ä±' => 'ı',
			'Ã¶' => 'ö', 'ÅŸ' => 'ş', 'Ã¼' => 'ü',
			'Ã‡' => 'Ç', 'Äž' => 'Ğ', 'Ä°' => 'İ',
			'Ã–' => 'Ö', 'Åž' => 'Ş', 'Ãœ' => 'Ü',
		];

		return strtr( $value, $replacements );
	}

	private function contains_script_tags( string $value ): bool {
		return (bool) preg_match( '/<\s*script/i', $value );
	}

	private function is_lorem_ipsum( string $value ): bool {
		$lower = mb_strtolower( $value );
		foreach ( self::LOREM_PATTERNS as $pattern ) {
			if ( false !== strpos( $lower, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	private function is_placeholder( string $value ): bool {
		$trimmed = trim( $value );
		foreach ( self::PLACEHOLDER_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $trimmed ) ) {
				return true;
			}
		}
		return false;
	}

	private function is_empty_content( string $value ): bool {
		$stripped = wp_strip_all_tags( $value );
		$stripped = trim( $stripped );
		return '' === $stripped;
	}

	private function detect_unsupported_claims( string $value ): array {
		$matches = [];
		foreach ( self::UNSUPPORTED_CLAIM_PATTERNS as $pattern ) {
			if ( preg_match( $pattern, $value, $m ) ) {
				$matches[] = $m[0];
			}
		}
		return $matches;
	}

	private function make_error( string $field, string $code, string $message, string $severity = 'error', string $action = '' ): array {
		return [
			'field'              => $field,
			'error_code'         => $code,
			'message'            => $message,
			'severity'           => $severity,
			'recommended_action' => $action,
		];
	}

	public function get_field_rules(): array {
		return self::FIELD_RULES;
	}

	public function has_turkish_content( string $value ): bool {
		return (bool) preg_match( '/[' . self::TURKISH_CHARS . ']/u', $value );
	}
}

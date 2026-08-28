<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Search_Integration {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        add_action( 'pre_get_posts', [ $this, 'extend_product_search' ] );
        add_filter( 'posts_search', [ $this, 'search_by_sku' ], 10, 2 );
        add_filter( 'posts_join', [ $this, 'search_join' ], 10, 2 );
        add_filter( 'posts_orderby', [ $this, 'sku_exact_match_priority' ], 10, 2 );
        add_filter( 'posts_distinct', [ $this, 'search_distinct' ], 10, 2 );
        add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );
    }

    public function extend_product_search( \WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
            return;
        }

        $search_term = $query->get( 's' );
        if ( empty( $search_term ) ) {
            return;
        }

        $query->set( 'post_type', [ 'product' ] );

        $meta_query = $query->get( 'meta_query', [] );
        if ( ! is_array( $meta_query ) ) {
            $meta_query = [];
        }
        $query->set( 'meta_query', $meta_query );

        $query->set( 'gh_search_active', true );
    }

    public function search_by_sku( string $search, \WP_Query $query ): string {
        if ( ! $query->get( 'gh_search_active' ) ) {
            return $search;
        }

        global $wpdb;

        $search_term = $query->get( 's' );
        if ( empty( $search_term ) ) {
            return $search;
        }

        $normalized = $this->normalize_search_term( $search_term );
        $like       = '%' . $wpdb->esc_like( $normalized ) . '%';

        $sku_search = $wpdb->prepare(
            " OR ({$wpdb->postmeta}.meta_key = '_sku' AND REPLACE(REPLACE(LOWER({$wpdb->postmeta}.meta_value), '-', ''), ' ', '') LIKE %s)",
            $like
        );

        $search = preg_replace(
            '/\)\s*$/',
            $sku_search . ')',
            $search
        );

        return $search;
    }

    public function search_join( string $join, \WP_Query $query ): string {
        if ( ! $query->get( 'gh_search_active' ) ) {
            return $join;
        }

        global $wpdb;

        if ( strpos( $join, $wpdb->postmeta ) === false ) {
            $join .= " LEFT JOIN {$wpdb->postmeta} ON ({$wpdb->posts}.ID = {$wpdb->postmeta}.post_id)";
        }

        return $join;
    }

    public function sku_exact_match_priority( string $orderby, \WP_Query $query ): string {
        if ( ! $query->get( 'gh_search_active' ) ) {
            return $orderby;
        }

        global $wpdb;

        $search_term = $query->get( 's' );
        $normalized  = $this->normalize_search_term( $search_term );

        $exact_sku = $wpdb->prepare(
            "CASE WHEN EXISTS (
                SELECT 1 FROM {$wpdb->postmeta} pm_sku
                WHERE pm_sku.post_id = {$wpdb->posts}.ID
                AND pm_sku.meta_key = '_sku'
                AND REPLACE(REPLACE(LOWER(pm_sku.meta_value), '-', ''), ' ', '') = %s
            ) THEN 0 ELSE 1 END",
            $normalized
        );

        return $exact_sku . ', ' . $orderby;
    }

    public function search_distinct( string $distinct, \WP_Query $query ): string {
        if ( $query->get( 'gh_search_active' ) ) {
            return 'DISTINCT';
        }
        return $distinct;
    }

    private function normalize_search_term( string $term ): string {
        $term = mb_strtolower( trim( $term ), 'UTF-8' );
        $term = str_replace( [ '-', ' ', '.', '/' ], '', $term );

        $tr_map = [
            'ı' => 'i',
            'ğ' => 'g',
            'ü' => 'u',
            'ş' => 's',
            'ö' => 'o',
            'ç' => 'c',
        ];
        $term = strtr( $term, $tr_map );

        return $term;
    }

    public function register_rest_routes(): void {
        register_rest_route( 'guvenhijyen/v1', '/products/search', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'rest_product_search' ],
            'permission_callback' => '__return_true',
            'args'                => [
                'q' => [
                    'required'          => true,
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ( $param ) {
                        return is_string( $param ) && mb_strlen( $param ) >= 2;
                    },
                ],
                'limit' => [
                    'default'           => 10,
                    'sanitize_callback' => 'absint',
                ],
            ],
        ] );
    }

    public function rest_product_search( \WP_REST_Request $request ): \WP_REST_Response {
        $term  = $request->get_param( 'q' );
        $limit = min( (int) $request->get_param( 'limit' ), 20 );

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            's'              => $term,
            'posts_per_page' => $limit,
            'gh_search_active' => true,
        ];

        $query    = new WP_Query( $args );
        $results  = [];

        foreach ( $query->posts as $post ) {
            $product = wc_get_product( $post->ID );
            if ( ! $product ) {
                continue;
            }

            if ( class_exists( 'GH_Publication_Rules' ) && ! GH_Publication_Rules::instance()->is_publish_ready( $post->ID ) ) {
                continue;
            }

            $result = [
                'id'         => $post->ID,
                'name'       => $product->get_name(),
                'sku'        => $product->get_sku(),
                'type'       => $product->get_type(),
                'image'      => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
                'permalink'  => get_permalink( $post->ID ),
                'sales_unit' => '',
            ];

            if ( class_exists( 'GH_Sales_Unit' ) ) {
                $unit_key          = GH_Sales_Unit::instance()->get_sales_unit( $post->ID );
                $result['sales_unit'] = GH_Sales_Unit::instance()->get_unit_label( $unit_key );
            }

            $brands = get_the_terms( $post->ID, 'product_brand' );
            $result['brand'] = ( $brands && ! is_wp_error( $brands ) ) ? $brands[0]->name : '';

            $categories = get_the_terms( $post->ID, 'product_cat' );
            $result['category'] = ( $categories && ! is_wp_error( $categories ) ) ? $categories[0]->name : '';

            if ( $product->is_type( 'variable' ) ) {
                $variations = $product->get_available_variations();
                $result['has_variations'] = true;
                $result['variations']     = array_map( function ( $v ) {
                    return [
                        'id'         => $v['variation_id'],
                        'sku'        => $v['sku'] ?? '',
                        'attributes' => $v['attributes'] ?? [],
                        'image'      => $v['image']['thumb_src'] ?? '',
                    ];
                }, array_slice( $variations, 0, 50 ) );
            } else {
                $result['has_variations'] = false;
            }

            $results[] = $result;
        }

        return new WP_REST_Response( [
            'results' => $results,
            'total'   => $query->found_posts,
            'query'   => $term,
        ] );
    }

    public function resolve_variation_sku_to_parent( string $variation_sku ): ?int {
        global $wpdb;

        $variation_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_sku' AND meta_value = %s LIMIT 1",
                $variation_sku
            )
        );

        if ( ! $variation_id ) {
            return null;
        }

        $post = get_post( $variation_id );
        if ( ! $post ) {
            return null;
        }

        if ( $post->post_type === 'product_variation' ) {
            return $post->post_parent;
        }

        if ( $post->post_type === 'product' ) {
            return (int) $variation_id;
        }

        return null;
    }
}

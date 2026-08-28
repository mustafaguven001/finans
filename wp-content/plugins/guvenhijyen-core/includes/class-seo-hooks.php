<?php

defined('ABSPATH') || exit;

class GH_SEO_Hooks {

    public static function init(): void {
        add_filter('woocommerce_structured_data_product', [__CLASS__, 'suppress_product_schema'], 999);
        add_action('wp_head', [__CLASS__, 'output_organization_schema'], 1);
        add_action('wp_head', [__CLASS__, 'remove_wc_structured_data'], 0);
        add_action('wp_head', [__CLASS__, 'noindex_filter_and_search_pages']);

        // Remove WooCommerce's JSON-LD output for products entirely
        add_filter('woocommerce_structured_data_product', '__return_empty_array', 9999);

        // Prevent stray Offer schema from WooCommerce blocks or other sources
        add_filter('woocommerce_structured_data_type_for_page', [__CLASS__, 'filter_structured_data_types']);
    }

    /**
     * Strip all price/offer/review/rating data from any residual product schema.
     */
    public static function suppress_product_schema(array $markup): array {
        unset(
            $markup['offers'],
            $markup['review'],
            $markup['aggregateRating'],
            $markup['priceValidUntil'],
            $markup['price'],
            $markup['priceCurrency'],
            $markup['availability']
        );
        return $markup;
    }

    public static function remove_wc_structured_data(): void {
        if (!is_singular('product')) {
            return;
        }
        // Remove WooCommerce default structured data output for products
        if (function_exists('WC') && isset(WC()->structured_data)) {
            remove_action('woocommerce_single_product_summary', [WC()->structured_data, 'generate_product_data'], 60);
            remove_action('wp_footer', [WC()->structured_data, 'output_structured_data'], 10);
        }
    }

    public static function filter_structured_data_types(array $types): array {
        $key = array_search('product', $types, true);
        if ($key !== false) {
            unset($types[$key]);
        }
        return $types;
    }

    public static function output_organization_schema(): void {
        if (!is_front_page() && !is_home()) {
            return;
        }

        if (!class_exists('GH_Company_Settings')) {
            return;
        }

        $schema = GH_Company_Settings::get_structured_data();
        if (empty($schema['name'])) {
            return;
        }

        $url = home_url('/');
        $schema['url'] = $url;

        echo '<script type="application/ld+json">';
        echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        echo '</script>' . "\n";
    }

    public static function noindex_filter_and_search_pages(): void {
        if (self::is_filtered_archive()) {
            self::output_noindex();
            self::output_canonical_for_filtered();
            return;
        }

        if (is_search()) {
            self::output_noindex();
        }
    }

    private static function is_filtered_archive(): bool {
        if (!is_tax('product_cat') && !is_post_type_archive('product')) {
            return false;
        }
        $filter_params = [
            'filter_',
            'min_price',
            'max_price',
            'orderby',
            'pa_',
        ];
        foreach ($_GET as $key => $value) {
            foreach ($filter_params as $prefix) {
                if (strpos($key, $prefix) === 0) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function output_noindex(): void {
        // Only output if Rank Math hasn't already handled it
        if (did_action('rank_math/head')) {
            return;
        }
        echo '<meta name="robots" content="noindex, follow" />' . "\n";
    }

    private static function output_canonical_for_filtered(): void {
        if (did_action('rank_math/head')) {
            return;
        }
        $queried = get_queried_object();
        if ($queried instanceof \WP_Term) {
            $canonical = get_term_link($queried);
        } else {
            $canonical = get_post_type_archive_link('product');
        }
        if ($canonical && !is_wp_error($canonical)) {
            echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
        }
    }
}

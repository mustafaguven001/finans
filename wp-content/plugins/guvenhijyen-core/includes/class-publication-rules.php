<?php

defined('ABSPATH') || exit;

class GH_Publication_Rules {

    public static function init(): void {
        add_action('pre_get_posts', [__CLASS__, 'exclude_unready_from_frontend']);
        add_filter('manage_edit-product_columns', [__CLASS__, 'add_admin_column']);
        add_action('manage_product_posts_custom_column', [__CLASS__, 'render_admin_column'], 10, 2);
    }

    public static function is_publish_ready(int $product_id): bool {
        return empty(self::get_publish_blockers($product_id));
    }

    public static function get_publish_blockers(int $product_id): array {
        $blockers = [];
        $post = get_post($product_id);

        if (!$post || $post->post_type !== 'product') {
            $blockers[] = __('Invalid product.', 'guvenhijyen');
            return $blockers;
        }

        if ($post->post_status !== 'publish') {
            $blockers[] = __('Product is not published.', 'guvenhijyen');
        }

        $product = wc_get_product($product_id);
        if (!$product || !$product->get_sku()) {
            $blockers[] = __('Product has no SKU.', 'guvenhijyen');
        }

        $terms = wp_get_post_terms($product_id, 'product_cat', ['fields' => 'ids']);
        if (empty($terms) || is_wp_error($terms)) {
            $blockers[] = __('Product has no category.', 'guvenhijyen');
        }

        if (!has_post_thumbnail($product_id)) {
            $blockers[] = __('Product has no featured image.', 'guvenhijyen');
        }

        if (class_exists('GH_Procurement') && GH_Procurement::get_status($product_id) !== GH_Procurement::STATUS_ACTIVE) {
            $blockers[] = __('Procurement status is not active.', 'guvenhijyen');
        }

        return $blockers;
    }

    public static function exclude_unready_from_frontend(\WP_Query $query): void {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        $dominated_queries = $query->is_post_type_archive('product')
            || $query->is_tax('product_cat')
            || $query->is_tax('product_tag')
            || $query->is_tax('product_brand')
            || $query->is_tax('product_sector');

        if (!$dominated_queries) {
            return;
        }

        $meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key'     => '_gh_procurement_status',
            'value'   => GH_Procurement::STATUS_ACTIVE,
            'compare' => '=',
        ];

        $meta_query[] = [
            'key'     => '_thumbnail_id',
            'compare' => 'EXISTS',
        ];

        $query->set('meta_query', $meta_query);
    }

    public static function add_admin_column(array $columns): array {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'gh_procurement') {
                $new['gh_publish_ready'] = __('Ready', 'guvenhijyen');
            }
        }
        if (!isset($new['gh_publish_ready'])) {
            $new['gh_publish_ready'] = __('Ready', 'guvenhijyen');
        }
        return $new;
    }

    public static function render_admin_column(string $column, int $post_id): void {
        if ($column !== 'gh_publish_ready') {
            return;
        }

        if (self::is_publish_ready($post_id)) {
            echo '<span style="color:#00a32a;font-size:18px" title="' . esc_attr__('Publish ready', 'guvenhijyen') . '">&#10003;</span>';
            return;
        }

        $blockers = self::get_publish_blockers($post_id);
        $tooltip = implode("\n", array_map('esc_attr', $blockers));
        echo '<span style="color:#d63638;font-size:18px;cursor:help" title="' . esc_attr($tooltip) . '">&#10007;</span>';
    }
}

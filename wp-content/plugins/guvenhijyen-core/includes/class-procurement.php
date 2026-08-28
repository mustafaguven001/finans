<?php

defined('ABSPATH') || exit;

class GH_Procurement {

    public const STATUS_ACTIVE                = 'active';
    public const STATUS_TEMPORARILY_UNAVAILABLE = 'temporarily_unavailable';
    public const STATUS_DISCONTINUED           = 'discontinued';

    private const META_KEY = '_gh_procurement_status';

    public static function init(): void {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_product', [__CLASS__, 'save_meta_box']);
        add_filter('manage_edit-product_columns', [__CLASS__, 'add_admin_column']);
        add_action('manage_product_posts_custom_column', [__CLASS__, 'render_admin_column'], 10, 2);
        add_filter('manage_edit-product_sortable_columns', [__CLASS__, 'sortable_column']);
        add_action('pre_get_posts', [__CLASS__, 'sort_by_procurement']);
    }

    public static function get_statuses(): array {
        return [
            self::STATUS_ACTIVE                 => __('Active', 'guvenhijyen'),
            self::STATUS_TEMPORARILY_UNAVAILABLE => __('Temporarily Unavailable', 'guvenhijyen'),
            self::STATUS_DISCONTINUED            => __('Discontinued', 'guvenhijyen'),
        ];
    }

    public static function get_status(int $product_id): string {
        $status = get_post_meta($product_id, self::META_KEY, true);
        return array_key_exists($status, self::get_statuses()) ? $status : self::STATUS_ACTIVE;
    }

    public static function set_status(int $product_id, string $status): bool {
        if (!array_key_exists($status, self::get_statuses())) {
            return false;
        }
        return (bool) update_post_meta($product_id, self::META_KEY, $status);
    }

    public static function needs_review(int $product_id): bool {
        $status = self::get_status($product_id);
        if ($status === self::STATUS_TEMPORARILY_UNAVAILABLE) {
            return true;
        }

        $last_modified = get_post_modified_time('U', true, $product_id);
        $six_months_ago = strtotime('-6 months');
        return $status === self::STATUS_ACTIVE && $last_modified < $six_months_ago;
    }

    public static function add_meta_box(): void {
        add_meta_box(
            'gh_procurement_status',
            __('Procurement Status', 'guvenhijyen'),
            [__CLASS__, 'render_meta_box'],
            'product',
            'side',
            'high'
        );
    }

    public static function render_meta_box(\WP_Post $post): void {
        wp_nonce_field('gh_procurement_save', 'gh_procurement_nonce');
        $current = self::get_status($post->ID);
        $statuses = self::get_statuses();
        echo '<select name="gh_procurement_status" style="width:100%">';
        foreach ($statuses as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($current, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';

        if (self::needs_review($post->ID)) {
            echo '<p style="color:#d63638;margin-top:8px">';
            echo esc_html__('This product needs procurement review.', 'guvenhijyen');
            echo '</p>';
        }
    }

    public static function save_meta_box(int $post_id): void {
        if (!isset($_POST['gh_procurement_nonce']) ||
            !wp_verify_nonce(sanitize_key($_POST['gh_procurement_nonce']), 'gh_procurement_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $status = sanitize_text_field(wp_unslash($_POST['gh_procurement_status'] ?? ''));
        self::set_status($post_id, $status);
    }

    public static function add_admin_column(array $columns): array {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'product_tag') {
                $new['gh_procurement'] = __('Procurement', 'guvenhijyen');
            }
        }
        if (!isset($new['gh_procurement'])) {
            $new['gh_procurement'] = __('Procurement', 'guvenhijyen');
        }
        return $new;
    }

    public static function render_admin_column(string $column, int $post_id): void {
        if ($column !== 'gh_procurement') {
            return;
        }
        $status = self::get_status($post_id);
        $labels = self::get_statuses();
        $colors = [
            self::STATUS_ACTIVE                 => '#00a32a',
            self::STATUS_TEMPORARILY_UNAVAILABLE => '#dba617',
            self::STATUS_DISCONTINUED            => '#d63638',
        ];
        printf(
            '<mark style="background:%s;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px">%s</mark>',
            esc_attr($colors[$status] ?? '#999'),
            esc_html($labels[$status] ?? $status)
        );
    }

    public static function sortable_column(array $columns): array {
        $columns['gh_procurement'] = 'gh_procurement';
        return $columns;
    }

    public static function sort_by_procurement(\WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->get('orderby') === 'gh_procurement') {
            $query->set('meta_key', self::META_KEY);
            $query->set('orderby', 'meta_value');
        }
    }
}

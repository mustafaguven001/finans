<?php

defined('ABSPATH') || exit;

class GH_Brand_Manager {

    private const TAX = 'product_brand';

    public static function init(): void {
        add_action(self::TAX . '_add_form_fields', [__CLASS__, 'add_form_fields']);
        add_action(self::TAX . '_edit_form_fields', [__CLASS__, 'edit_form_fields']);
        add_action('created_' . self::TAX, [__CLASS__, 'save_fields']);
        add_action('edited_' . self::TAX, [__CLASS__, 'save_fields']);
        add_filter('manage_edit-' . self::TAX . '_columns', [__CLASS__, 'add_columns']);
        add_filter('manage_' . self::TAX . '_custom_column', [__CLASS__, 'render_column'], 10, 3);
        add_filter('get_terms_args', [__CLASS__, 'filter_frontend_terms'], 10, 2);
    }

    public static function is_brand_verified(int $brand_id): bool {
        return (bool) get_term_meta($brand_id, 'gh_brand_verified', true);
    }

    public static function is_brand_ready(int $brand_id): bool {
        if (!self::is_brand_verified($brand_id)) {
            return false;
        }
        $logo = get_term_meta($brand_id, 'gh_brand_logo', true);
        $desc = get_term_meta($brand_id, 'gh_brand_description', true);
        return !empty($logo) && !empty($desc);
    }

    public static function add_form_fields(): void {
        wp_nonce_field('gh_brand_meta_save', 'gh_brand_nonce');
        ?>
        <div class="form-field">
            <label><?php esc_html_e('Brand Logo (Attachment ID)', 'guvenhijyen'); ?></label>
            <input type="number" name="gh_brand_logo" value="" />
        </div>
        <div class="form-field">
            <label><?php esc_html_e('Brand Description', 'guvenhijyen'); ?></label>
            <textarea name="gh_brand_description" rows="4"></textarea>
        </div>
        <div class="form-field">
            <label><?php esc_html_e('Brand Website', 'guvenhijyen'); ?></label>
            <input type="url" name="gh_brand_website" value="" />
        </div>
        <div class="form-field">
            <label>
                <input type="checkbox" name="gh_brand_verified" value="1" />
                <?php esc_html_e('Verified', 'guvenhijyen'); ?>
            </label>
        </div>
        <?php
    }

    public static function edit_form_fields(\WP_Term $term): void {
        wp_nonce_field('gh_brand_meta_save', 'gh_brand_nonce');
        $logo     = get_term_meta($term->term_id, 'gh_brand_logo', true);
        $desc     = get_term_meta($term->term_id, 'gh_brand_description', true);
        $website  = get_term_meta($term->term_id, 'gh_brand_website', true);
        $verified = get_term_meta($term->term_id, 'gh_brand_verified', true);
        ?>
        <tr class="form-field">
            <th><label><?php esc_html_e('Brand Logo (Attachment ID)', 'guvenhijyen'); ?></label></th>
            <td><input type="number" name="gh_brand_logo" value="<?php echo esc_attr($logo); ?>" /></td>
        </tr>
        <tr class="form-field">
            <th><label><?php esc_html_e('Brand Description', 'guvenhijyen'); ?></label></th>
            <td><textarea name="gh_brand_description" rows="4"><?php echo esc_textarea($desc); ?></textarea></td>
        </tr>
        <tr class="form-field">
            <th><label><?php esc_html_e('Brand Website', 'guvenhijyen'); ?></label></th>
            <td><input type="url" name="gh_brand_website" value="<?php echo esc_attr($website); ?>" /></td>
        </tr>
        <tr class="form-field">
            <th><label><?php esc_html_e('Verified', 'guvenhijyen'); ?></label></th>
            <td>
                <label>
                    <input type="checkbox" name="gh_brand_verified" value="1" <?php checked($verified, '1'); ?> />
                    <?php esc_html_e('This brand is verified', 'guvenhijyen'); ?>
                </label>
            </td>
        </tr>
        <?php
    }

    public static function save_fields(int $term_id): void {
        if (!isset($_POST['gh_brand_nonce']) ||
            !wp_verify_nonce(sanitize_key($_POST['gh_brand_nonce']), 'gh_brand_meta_save')) {
            return;
        }
        if (!current_user_can('manage_product_terms')) {
            return;
        }

        update_term_meta($term_id, 'gh_brand_logo', absint($_POST['gh_brand_logo'] ?? 0));
        update_term_meta($term_id, 'gh_brand_description', sanitize_textarea_field(wp_unslash($_POST['gh_brand_description'] ?? '')));
        update_term_meta($term_id, 'gh_brand_website', esc_url_raw(wp_unslash($_POST['gh_brand_website'] ?? '')));
        update_term_meta($term_id, 'gh_brand_verified', !empty($_POST['gh_brand_verified']) ? '1' : '');

        $ready = self::is_brand_ready($term_id) ? '1' : '';
        update_term_meta($term_id, 'gh_brand_ready', $ready);
    }

    public static function add_columns(array $columns): array {
        $columns['gh_brand_ready'] = __('Ready', 'guvenhijyen');
        return $columns;
    }

    public static function render_column(string $content, string $column, int $term_id): string {
        if ($column !== 'gh_brand_ready') {
            return $content;
        }
        if (self::is_brand_ready($term_id)) {
            return '<span style="color:#00a32a;font-size:18px">&#10003;</span>';
        }
        $reasons = [];
        if (!self::is_brand_verified($term_id)) {
            $reasons[] = __('Not verified', 'guvenhijyen');
        }
        if (!get_term_meta($term_id, 'gh_brand_logo', true)) {
            $reasons[] = __('No logo', 'guvenhijyen');
        }
        if (!get_term_meta($term_id, 'gh_brand_description', true)) {
            $reasons[] = __('No description', 'guvenhijyen');
        }
        return '<span style="color:#d63638;font-size:18px;cursor:help" title="' . esc_attr(implode(', ', $reasons)) . '">&#10007;</span>';
    }

    public static function filter_frontend_terms(array $args, array $taxonomies): array {
        if (is_admin() || !in_array(self::TAX, $taxonomies, true)) {
            return $args;
        }

        $meta_query = $args['meta_query'] ?? [];
        $meta_query[] = [
            'key'   => 'gh_brand_ready',
            'value' => '1',
        ];
        $args['meta_query'] = $meta_query;

        return $args;
    }
}

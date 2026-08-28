<?php

defined('ABSPATH') || exit;

class GH_Sector_Manager {

    private const TAX = 'product_sector';

    public static function init(): void {
        add_action(self::TAX . '_add_form_fields', [__CLASS__, 'add_form_fields']);
        add_action(self::TAX . '_edit_form_fields', [__CLASS__, 'edit_form_fields']);
        add_action('created_' . self::TAX, [__CLASS__, 'save_fields']);
        add_action('edited_' . self::TAX, [__CLASS__, 'save_fields']);
        add_filter('manage_edit-' . self::TAX . '_columns', [__CLASS__, 'add_columns']);
        add_filter('manage_' . self::TAX . '_custom_column', [__CLASS__, 'render_column'], 10, 3);
        add_filter('get_terms_args', [__CLASS__, 'filter_frontend_terms'], 10, 2);
    }

    public static function is_sector_ready(int $sector_id): bool {
        $desc  = get_term_meta($sector_id, 'gh_sector_description', true);
        $image = get_term_meta($sector_id, 'gh_sector_image', true);
        $icon  = get_term_meta($sector_id, 'gh_sector_icon', true);
        return !empty($desc) && (!empty($image) || !empty($icon));
    }

    public static function get_products_for_sector(int $sector_id, array $args = []): \WP_Query {
        $defaults = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'tax_query'      => [
                [
                    'taxonomy' => self::TAX,
                    'field'    => 'term_id',
                    'terms'    => $sector_id,
                ],
            ],
        ];
        return new \WP_Query(wp_parse_args($args, $defaults));
    }

    public static function get_sectors_for_product(int $product_id): array {
        $terms = wp_get_post_terms($product_id, self::TAX);
        return is_wp_error($terms) ? [] : $terms;
    }

    public static function add_form_fields(): void {
        wp_nonce_field('gh_sector_meta_save', 'gh_sector_nonce');
        ?>
        <div class="form-field">
            <label><?php esc_html_e('Sector Description', 'guvenhijyen'); ?></label>
            <textarea name="gh_sector_description" rows="4"></textarea>
        </div>
        <div class="form-field">
            <label><?php esc_html_e('Sector Image (Attachment ID)', 'guvenhijyen'); ?></label>
            <input type="number" name="gh_sector_image" value="" />
        </div>
        <div class="form-field">
            <label><?php esc_html_e('Sector Icon (CSS class or SVG)', 'guvenhijyen'); ?></label>
            <input type="text" name="gh_sector_icon" value="" />
        </div>
        <?php
    }

    public static function edit_form_fields(\WP_Term $term): void {
        wp_nonce_field('gh_sector_meta_save', 'gh_sector_nonce');
        $desc  = get_term_meta($term->term_id, 'gh_sector_description', true);
        $image = get_term_meta($term->term_id, 'gh_sector_image', true);
        $icon  = get_term_meta($term->term_id, 'gh_sector_icon', true);
        ?>
        <tr class="form-field">
            <th><label><?php esc_html_e('Sector Description', 'guvenhijyen'); ?></label></th>
            <td><textarea name="gh_sector_description" rows="4"><?php echo esc_textarea($desc); ?></textarea></td>
        </tr>
        <tr class="form-field">
            <th><label><?php esc_html_e('Sector Image (Attachment ID)', 'guvenhijyen'); ?></label></th>
            <td><input type="number" name="gh_sector_image" value="<?php echo esc_attr($image); ?>" /></td>
        </tr>
        <tr class="form-field">
            <th><label><?php esc_html_e('Sector Icon (CSS class or SVG)', 'guvenhijyen'); ?></label></th>
            <td><input type="text" name="gh_sector_icon" value="<?php echo esc_attr($icon); ?>" /></td>
        </tr>
        <?php
    }

    public static function save_fields(int $term_id): void {
        if (!isset($_POST['gh_sector_nonce']) ||
            !wp_verify_nonce(sanitize_key($_POST['gh_sector_nonce']), 'gh_sector_meta_save')) {
            return;
        }
        if (!current_user_can('manage_product_terms')) {
            return;
        }

        update_term_meta($term_id, 'gh_sector_description', sanitize_textarea_field(wp_unslash($_POST['gh_sector_description'] ?? '')));
        update_term_meta($term_id, 'gh_sector_image', absint($_POST['gh_sector_image'] ?? 0));
        update_term_meta($term_id, 'gh_sector_icon', sanitize_text_field(wp_unslash($_POST['gh_sector_icon'] ?? '')));

        $ready = self::is_sector_ready($term_id) ? '1' : '';
        update_term_meta($term_id, 'gh_sector_ready', $ready);
    }

    public static function add_columns(array $columns): array {
        $columns['gh_sector_ready'] = __('Ready', 'guvenhijyen');
        return $columns;
    }

    public static function render_column(string $content, string $column, int $term_id): string {
        if ($column !== 'gh_sector_ready') {
            return $content;
        }
        if (self::is_sector_ready($term_id)) {
            return '<span style="color:#00a32a;font-size:18px">&#10003;</span>';
        }
        $reasons = [];
        if (!get_term_meta($term_id, 'gh_sector_description', true)) {
            $reasons[] = __('No description', 'guvenhijyen');
        }
        if (!get_term_meta($term_id, 'gh_sector_image', true) && !get_term_meta($term_id, 'gh_sector_icon', true)) {
            $reasons[] = __('No image or icon', 'guvenhijyen');
        }
        return '<span style="color:#d63638;font-size:18px;cursor:help" title="' . esc_attr(implode(', ', $reasons)) . '">&#10007;</span>';
    }

    public static function filter_frontend_terms(array $args, array $taxonomies): array {
        if (is_admin() || !in_array(self::TAX, $taxonomies, true)) {
            return $args;
        }

        $meta_query = $args['meta_query'] ?? [];
        $meta_query[] = [
            'key'   => 'gh_sector_ready',
            'value' => '1',
        ];
        $args['meta_query'] = $meta_query;

        return $args;
    }
}

<?php

defined('ABSPATH') || exit;

class GH_Sales_Unit {

    private const META_UNIT     = '_gh_sales_unit';
    private const META_MIN_QTY  = '_gh_minimum_quantity';
    private const META_QTY_STEP = '_gh_quantity_step';

    private static array $units = [
        'adet'  => ['label_tr' => 'Adet',   'label_en' => 'Piece'],
        'kutu'  => ['label_tr' => 'Kutu',   'label_en' => 'Box'],
        'koli'  => ['label_tr' => 'Koli',   'label_en' => 'Carton'],
        'paket' => ['label_tr' => 'Paket',  'label_en' => 'Pack'],
        'galon' => ['label_tr' => 'Galon',  'label_en' => 'Gallon'],
        'litre' => ['label_tr' => 'Litre',  'label_en' => 'Litre'],
        'kg'    => ['label_tr' => 'Kg',     'label_en' => 'Kg'],
        'metre' => ['label_tr' => 'Metre',  'label_en' => 'Metre'],
        'rulo'  => ['label_tr' => 'Rulo',   'label_en' => 'Roll'],
        'palet' => ['label_tr' => 'Palet',  'label_en' => 'Pallet'],
        'bidon' => ['label_tr' => 'Bidon',  'label_en' => 'Jerrican'],
        'top'   => ['label_tr' => 'Top',    'label_en' => 'Bale'],
    ];

    public static function init(): void {
        add_action('woocommerce_product_options_general_product_data', [__CLASS__, 'render_product_fields']);
        add_action('woocommerce_process_product_meta', [__CLASS__, 'save_product_fields']);
        add_filter('manage_edit-product_columns', [__CLASS__, 'add_admin_column']);
        add_action('manage_product_posts_custom_column', [__CLASS__, 'render_admin_column'], 10, 2);
    }

    public static function get_sales_unit(int $product_id): string {
        return get_post_meta($product_id, self::META_UNIT, true) ?: '';
    }

    public static function get_minimum_quantity(int $product_id): int {
        return (int) (get_post_meta($product_id, self::META_MIN_QTY, true) ?: 1);
    }

    public static function get_quantity_step(int $product_id): int {
        return (int) (get_post_meta($product_id, self::META_QTY_STEP, true) ?: 1);
    }

    public static function get_available_units(): array {
        return self::$units;
    }

    public static function get_unit_label(string $key, string $lang = 'tr'): string {
        $label_key = 'label_' . $lang;
        return self::$units[$key][$label_key] ?? $key;
    }

    public static function render_product_fields(): void {
        echo '<div class="options_group">';

        woocommerce_wp_select([
            'id'      => self::META_UNIT,
            'label'   => __('Sales Unit', 'guvenhijyen'),
            'options' => self::get_options_for_select(),
        ]);

        woocommerce_wp_text_input([
            'id'                => self::META_MIN_QTY,
            'label'             => __('Minimum Quantity', 'guvenhijyen'),
            'type'              => 'number',
            'custom_attributes' => ['min' => '1', 'step' => '1'],
        ]);

        woocommerce_wp_text_input([
            'id'                => self::META_QTY_STEP,
            'label'             => __('Quantity Step', 'guvenhijyen'),
            'type'              => 'number',
            'custom_attributes' => ['min' => '1', 'step' => '1'],
        ]);

        echo '</div>';
    }

    public static function save_product_fields(int $post_id): void {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $unit = sanitize_text_field(wp_unslash($_POST[self::META_UNIT] ?? ''));
        if ($unit === '' || array_key_exists($unit, self::$units)) {
            update_post_meta($post_id, self::META_UNIT, $unit);
        }

        $min_qty = absint($_POST[self::META_MIN_QTY] ?? 1);
        update_post_meta($post_id, self::META_MIN_QTY, max(1, $min_qty));

        $step = absint($_POST[self::META_QTY_STEP] ?? 1);
        update_post_meta($post_id, self::META_QTY_STEP, max(1, $step));
    }

    public static function add_admin_column(array $columns): array {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'sku') {
                $new['gh_sales_unit'] = __('Sales Unit', 'guvenhijyen');
            }
        }
        if (!isset($new['gh_sales_unit'])) {
            $new['gh_sales_unit'] = __('Sales Unit', 'guvenhijyen');
        }
        return $new;
    }

    public static function render_admin_column(string $column, int $post_id): void {
        if ($column !== 'gh_sales_unit') {
            return;
        }
        $unit = self::get_sales_unit($post_id);
        if ($unit) {
            echo esc_html(self::get_unit_label($unit));
        } else {
            echo '<span style="color:#999">&mdash;</span>';
        }
    }

    private static function get_options_for_select(): array {
        $options = ['' => __('Select unit...', 'guvenhijyen')];
        foreach (self::$units as $key => $labels) {
            $options[$key] = $labels['label_tr'] . ' (' . $labels['label_en'] . ')';
        }
        return $options;
    }
}

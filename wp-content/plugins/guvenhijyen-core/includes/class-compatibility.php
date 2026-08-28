<?php

defined('ABSPATH') || exit;

class GH_Compatibility {

    public const TYPE_COMPATIBLE_CONSUMABLE = 'compatible_consumable';
    public const TYPE_COMPATIBLE_DEVICE     = 'compatible_device';
    public const TYPE_ACCESSORY             = 'accessory';
    public const TYPE_ALTERNATIVE           = 'alternative';
    public const TYPE_COMPLEMENTARY         = 'complementary';

    private const META_KEY = '_gh_product_relationships';

    private const SYMMETRIC_TYPES = [
        self::TYPE_ALTERNATIVE,
        self::TYPE_COMPLEMENTARY,
    ];

    public static function init(): void {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_box']);
        add_action('save_post_product', [__CLASS__, 'save_meta_box']);
    }

    public static function get_types(): array {
        return [
            self::TYPE_COMPATIBLE_CONSUMABLE => __('Compatible Consumable', 'guvenhijyen'),
            self::TYPE_COMPATIBLE_DEVICE     => __('Compatible Device', 'guvenhijyen'),
            self::TYPE_ACCESSORY             => __('Accessory', 'guvenhijyen'),
            self::TYPE_ALTERNATIVE           => __('Alternative', 'guvenhijyen'),
            self::TYPE_COMPLEMENTARY         => __('Complementary', 'guvenhijyen'),
        ];
    }

    public static function add_relationship(int $product_a, int $product_b, string $type): bool {
        if (!array_key_exists($type, self::get_types()) || $product_a === $product_b) {
            return false;
        }

        $added_forward = self::store_relationship($product_a, $product_b, $type);

        if (in_array($type, self::SYMMETRIC_TYPES, true)) {
            self::store_relationship($product_b, $product_a, $type);
        }

        return $added_forward;
    }

    public static function get_relationships(int $product_id, ?string $type = null): array {
        $relationships = get_post_meta($product_id, self::META_KEY, true);
        if (!is_array($relationships)) {
            return [];
        }

        if ($type === null) {
            return $relationships;
        }

        return array_values(array_filter(
            $relationships,
            static fn(array $rel): bool => $rel['type'] === $type
        ));
    }

    public static function remove_relationship(int $product_a, int $product_b, string $type): bool {
        $removed = self::remove_stored_relationship($product_a, $product_b, $type);

        if (in_array($type, self::SYMMETRIC_TYPES, true)) {
            self::remove_stored_relationship($product_b, $product_a, $type);
        }

        return $removed;
    }

    private static function store_relationship(int $source, int $target, string $type): bool {
        $relationships = get_post_meta($source, self::META_KEY, true);
        if (!is_array($relationships)) {
            $relationships = [];
        }

        foreach ($relationships as $rel) {
            if ((int) $rel['product_id'] === $target && $rel['type'] === $type) {
                return false;
            }
        }

        $relationships[] = [
            'product_id' => $target,
            'type'       => $type,
        ];

        return (bool) update_post_meta($source, self::META_KEY, $relationships);
    }

    private static function remove_stored_relationship(int $source, int $target, string $type): bool {
        $relationships = get_post_meta($source, self::META_KEY, true);
        if (!is_array($relationships)) {
            return false;
        }

        $filtered = array_values(array_filter(
            $relationships,
            static fn(array $rel): bool => !((int) $rel['product_id'] === $target && $rel['type'] === $type)
        ));

        if (count($filtered) === count($relationships)) {
            return false;
        }

        return (bool) update_post_meta($source, self::META_KEY, $filtered);
    }

    public static function add_meta_box(): void {
        add_meta_box(
            'gh_compatibility',
            __('Product Relationships', 'guvenhijyen'),
            [__CLASS__, 'render_meta_box'],
            'product',
            'normal',
            'default'
        );
    }

    public static function render_meta_box(\WP_Post $post): void {
        wp_nonce_field('gh_compatibility_save', 'gh_compatibility_nonce');
        $relationships = self::get_relationships($post->ID);
        $types = self::get_types();
        ?>
        <div id="gh-relationships-wrap">
            <table class="widefat" id="gh-relationships-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Product ID', 'guvenhijyen'); ?></th>
                        <th><?php esc_html_e('Product', 'guvenhijyen'); ?></th>
                        <th><?php esc_html_e('Relationship Type', 'guvenhijyen'); ?></th>
                        <th><?php esc_html_e('Action', 'guvenhijyen'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($relationships as $i => $rel): ?>
                        <tr>
                            <td>
                                <input type="number" name="gh_rel[<?php echo (int) $i; ?>][product_id]"
                                       value="<?php echo esc_attr($rel['product_id']); ?>" class="small-text" />
                            </td>
                            <td><?php echo esc_html(get_the_title((int) $rel['product_id'])); ?></td>
                            <td>
                                <select name="gh_rel[<?php echo (int) $i; ?>][type]">
                                    <?php foreach ($types as $val => $label): ?>
                                        <option value="<?php echo esc_attr($val); ?>" <?php selected($rel['type'], $val); ?>>
                                            <?php echo esc_html($label); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <label>
                                    <input type="checkbox" name="gh_rel[<?php echo (int) $i; ?>][remove]" value="1" />
                                    <?php esc_html_e('Remove', 'guvenhijyen'); ?>
                                </label>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <h4><?php esc_html_e('Add New Relationship', 'guvenhijyen'); ?></h4>
            <table class="form-table">
                <tr>
                    <th><label><?php esc_html_e('Product ID', 'guvenhijyen'); ?></label></th>
                    <td><input type="number" name="gh_new_rel_product_id" class="small-text" /></td>
                </tr>
                <tr>
                    <th><label><?php esc_html_e('Type', 'guvenhijyen'); ?></label></th>
                    <td>
                        <select name="gh_new_rel_type">
                            <option value=""><?php esc_html_e('Select...', 'guvenhijyen'); ?></option>
                            <?php foreach ($types as $val => $label): ?>
                                <option value="<?php echo esc_attr($val); ?>"><?php echo esc_html($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }

    public static function save_meta_box(int $post_id): void {
        if (!isset($_POST['gh_compatibility_nonce']) ||
            !wp_verify_nonce(sanitize_key($_POST['gh_compatibility_nonce']), 'gh_compatibility_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $existing = self::get_relationships($post_id);
        $submitted = isset($_POST['gh_rel']) && is_array($_POST['gh_rel']) ? $_POST['gh_rel'] : [];

        foreach ($submitted as $i => $entry) {
            if (!empty($entry['remove']) && isset($existing[$i])) {
                self::remove_relationship(
                    $post_id,
                    (int) $existing[$i]['product_id'],
                    sanitize_text_field($existing[$i]['type'])
                );
            }
        }

        $new_product = absint($_POST['gh_new_rel_product_id'] ?? 0);
        $new_type    = sanitize_text_field(wp_unslash($_POST['gh_new_rel_type'] ?? ''));

        if ($new_product > 0 && $new_type && get_post_type($new_product) === 'product') {
            self::add_relationship($post_id, $new_product, $new_type);
        }
    }
}

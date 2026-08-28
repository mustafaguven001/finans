<?php

defined('ABSPATH') || exit;

class GH_Quote_List {

    private static string $session_key = 'gh_quote_list';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_routes(): void {
        $namespace = 'guvenhijyen/v1';

        register_rest_route($namespace, '/quote-list', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_get'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/quote-list/add', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle_add'],
            'permission_callback' => '__return_true',
            'args'                => self::get_item_args(),
        ]);

        register_rest_route($namespace, '/quote-list/update', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [__CLASS__, 'handle_update'],
            'permission_callback' => '__return_true',
            'args'                => self::get_item_args(),
        ]);

        register_rest_route($namespace, '/quote-list/remove', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle_remove'],
            'permission_callback' => '__return_true',
            'args'                => [
                'item_key' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);

        register_rest_route($namespace, '/quote-list/clear', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle_clear'],
            'permission_callback' => '__return_true',
        ]);
    }

    public static function enqueue_assets(): void {
        wp_enqueue_script(
            'gh-quote-list',
            GH_CORE_URL . 'assets/js/quote-list.js',
            [],
            GH_CORE_VERSION,
            true
        );

        wp_localize_script('gh-quote-list', 'ghQuoteList', [
            'restUrl'    => esc_url_raw(rest_url('guvenhijyen/v1/quote-list')),
            'nonce'      => wp_create_nonce('wp_rest'),
            'sessionKey' => self::$session_key,
            'i18n'       => [
                'header'            => __('TEKLIF LISTEM', 'guvenhijyen'),
                'empty'             => __('Teklif listeniz bos.', 'guvenhijyen'),
                'added'             => __('Urun teklif listesine eklendi.', 'guvenhijyen'),
                'removed'           => __('Urun teklif listesinden cikarildi.', 'guvenhijyen'),
                'updated'           => __('Miktar guncellendi.', 'guvenhijyen'),
                'cleared'           => __('Teklif listesi temizlendi.', 'guvenhijyen'),
                'select_variation'  => __('Lutfen bir varyant seciniz.', 'guvenhijyen'),
                'error'             => __('Bir hata olustu.', 'guvenhijyen'),
            ],
        ]);
    }

    public static function handle_get(): WP_REST_Response {
        $items = self::get_items();
        return new WP_REST_Response([
            'success' => true,
            'items'   => $items,
            'count'   => count($items),
        ], 200);
    }

    public static function handle_add(WP_REST_Request $request): WP_REST_Response {
        $product_id   = (int) $request->get_param('product_id');
        $variation_id = (int) $request->get_param('variation_id');
        $quantity     = max(1, (int) $request->get_param('quantity'));

        $validation = self::validate_item($product_id, $variation_id, $quantity);
        if (is_wp_error($validation)) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => $validation->get_error_code(),
                'message' => $validation->get_error_message(),
            ], 422);
        }

        $items    = self::get_items();
        $item_key = self::make_item_key($product_id, $variation_id);

        if (isset($items[$item_key])) {
            $items[$item_key]['quantity'] += $quantity;
        } else {
            $product = wc_get_product($product_id);
            $target  = $variation_id > 0 ? wc_get_product($variation_id) : $product;
            $actual  = $target ?: $product;

            $sales_unit_key   = $actual->get_meta('_gh_sales_unit') ?: 'adet';
            $sales_unit_label = self::resolve_sales_unit_label($sales_unit_key);

            $variation_text = '';
            if ($variation_id > 0 && $target && $target->is_type('variation')) {
                $attrs = $target->get_variation_attributes();
                $parts = [];
                foreach ($attrs as $attr_key => $attr_value) {
                    $taxonomy = str_replace('attribute_', '', $attr_key);
                    $label    = wc_attribute_label($taxonomy, $product);
                    $term     = get_term_by('slug', $attr_value, $taxonomy);
                    $value    = $term ? $term->name : $attr_value;
                    $parts[]  = $label . ': ' . $value;
                }
                $variation_text = implode(', ', $parts);
            }

            $items[$item_key] = [
                'item_key'         => $item_key,
                'product_id'       => $product_id,
                'variation_id'     => $variation_id,
                'quantity'         => $quantity,
                'product_name'     => $product->get_name(),
                'sku'              => $actual->get_sku(),
                'variation'        => $variation_text,
                'sales_unit_key'   => $sales_unit_key,
                'sales_unit_label' => $sales_unit_label,
                'thumbnail'        => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: '',
                'permalink'        => get_permalink($product_id),
            ];
        }

        self::save_items($items);

        return new WP_REST_Response([
            'success' => true,
            'items'   => $items,
            'count'   => count($items),
        ], 200);
    }

    public static function handle_update(WP_REST_Request $request): WP_REST_Response {
        $product_id   = (int) $request->get_param('product_id');
        $variation_id = (int) $request->get_param('variation_id');
        $quantity     = max(1, (int) $request->get_param('quantity'));
        $item_key     = self::make_item_key($product_id, $variation_id);

        $items = self::get_items();

        if (!isset($items[$item_key])) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => 'not_found',
                'message' => __('Urun teklif listesinde bulunamadi.', 'guvenhijyen'),
            ], 404);
        }

        $validation = self::validate_item($product_id, $variation_id, $quantity);
        if (is_wp_error($validation)) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => $validation->get_error_code(),
                'message' => $validation->get_error_message(),
            ], 422);
        }

        $items[$item_key]['quantity'] = $quantity;
        self::save_items($items);

        return new WP_REST_Response([
            'success' => true,
            'items'   => $items,
            'count'   => count($items),
        ], 200);
    }

    public static function handle_remove(WP_REST_Request $request): WP_REST_Response {
        $item_key = sanitize_text_field($request->get_param('item_key'));
        $items    = self::get_items();

        if (!isset($items[$item_key])) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => 'not_found',
                'message' => __('Urun teklif listesinde bulunamadi.', 'guvenhijyen'),
            ], 404);
        }

        unset($items[$item_key]);
        self::save_items($items);

        return new WP_REST_Response([
            'success' => true,
            'items'   => $items,
            'count'   => count($items),
        ], 200);
    }

    public static function handle_clear(): WP_REST_Response {
        self::save_items([]);

        return new WP_REST_Response([
            'success' => true,
            'items'   => [],
            'count'   => 0,
        ], 200);
    }

    public static function to_rfq_items(): array {
        $items    = self::get_items();
        $rfq_items = [];

        foreach ($items as $item) {
            $rfq_items[] = [
                'product_id'   => $item['product_id'],
                'variation_id' => $item['variation_id'],
                'quantity'     => $item['quantity'],
                'sales_unit'   => $item['sales_unit_key'],
            ];
        }

        return $rfq_items;
    }

    public static function get_count(): int {
        return count(self::get_items());
    }

    public static function get_items(): array {
        if (!session_id() && !headers_sent()) {
            session_start();
        }

        return $_SESSION[self::$session_key] ?? [];
    }

    private static function save_items(array $items): void {
        if (!session_id() && !headers_sent()) {
            session_start();
        }

        $_SESSION[self::$session_key] = $items;
    }

    private static function make_item_key(int $product_id, int $variation_id): string {
        return $product_id . '_' . $variation_id;
    }

    private static function validate_item(int $product_id, int $variation_id, int $quantity): true|WP_Error {
        if ($product_id <= 0) {
            return new WP_Error('invalid_product', __('Gecersiz urun.', 'guvenhijyen'));
        }

        $product = wc_get_product($product_id);
        if (!$product || $product->get_status() !== 'publish') {
            return new WP_Error('product_not_found', __('Urun bulunamadi veya yayinda degil.', 'guvenhijyen'));
        }

        if ($product->is_type('variable')) {
            if ($variation_id <= 0) {
                return new WP_Error('variation_required', __('Degisken urun icin varyant secimi zorunludur.', 'guvenhijyen'));
            }
            $variation = wc_get_product($variation_id);
            if (!$variation || $variation->get_parent_id() !== $product_id) {
                return new WP_Error('invalid_variation', __('Varyant bu urune ait degil.', 'guvenhijyen'));
            }
        }

        if ($quantity <= 0) {
            return new WP_Error('invalid_quantity', __('Miktar 0\'dan buyuk olmalidir.', 'guvenhijyen'));
        }

        $target = $variation_id > 0 ? wc_get_product($variation_id) : $product;
        if ($target) {
            $min_qty  = (int) $target->get_meta('_gh_min_order_quantity');
            $step_qty = (int) $target->get_meta('_gh_quantity_step');

            if ($min_qty > 0 && $quantity < $min_qty) {
                return new WP_Error(
                    'below_minimum',
                    sprintf(__('Minimum siparis miktari: %d', 'guvenhijyen'), $min_qty)
                );
            }

            if ($step_qty > 1 && ($quantity % $step_qty) !== 0) {
                return new WP_Error(
                    'invalid_step',
                    sprintf(__('Miktar %d\'nin katlari olmalidir.', 'guvenhijyen'), $step_qty)
                );
            }
        }

        return true;
    }

    private static function resolve_sales_unit_label(string $key): string {
        $units = [
            'adet'  => __('Adet', 'guvenhijyen'),
            'paket' => __('Paket', 'guvenhijyen'),
            'koli'  => __('Koli', 'guvenhijyen'),
            'kutu'  => __('Kutu', 'guvenhijyen'),
            'kg'    => __('Kilogram', 'guvenhijyen'),
            'litre' => __('Litre', 'guvenhijyen'),
            'metre' => __('Metre', 'guvenhijyen'),
            'top'   => __('Top', 'guvenhijyen'),
            'rulo'  => __('Rulo', 'guvenhijyen'),
        ];

        if (isset($units[$key])) {
            return $units[$key];
        }

        if (class_exists('GH_Sales_Unit')) {
            $all_units = GH_Sales_Unit::get_all_units();
            if (isset($all_units[$key])) {
                return $all_units[$key];
            }
        }

        return $key;
    }
}

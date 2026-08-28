<?php

defined('ABSPATH') || exit;

remove_action('woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10);
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
remove_action('woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30);
remove_action('woocommerce_grouped_add_to_cart', 'woocommerce_grouped_add_to_cart', 30);
remove_action('woocommerce_external_add_to_cart', 'woocommerce_external_add_to_cart', 30);

remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_price', 10);
remove_action('woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10);

remove_action('woocommerce_before_main_content', 'woocommerce_breadcrumb', 20);

add_filter('woocommerce_is_purchasable', '__return_false');

add_filter('woocommerce_get_price_html', '__return_empty_string');

add_filter('woocommerce_cart_item_price', '__return_empty_string');
add_filter('woocommerce_cart_item_subtotal', '__return_empty_string');
add_filter('woocommerce_cart_subtotal', '__return_empty_string');

add_filter('woocommerce_widget_cart_is_hidden', '__return_true');

add_action('wp_enqueue_scripts', static function (): void {
    wp_dequeue_script('wc-cart-fragments');
}, 20);

add_filter('woocommerce_add_to_cart_validation', '__return_false');

add_action('template_redirect', static function (): void {
    if (is_cart() || is_checkout() || is_account_page()) {
        wp_safe_redirect(home_url('/'), 301);
        exit;
    }
});

add_filter('woocommerce_account_menu_items', '__return_empty_array');

add_filter('woocommerce_email_classes', static function (array $emails): array {
    $remove = [
        'WC_Email_New_Order',
        'WC_Email_Cancelled_Order',
        'WC_Email_Failed_Order',
        'WC_Email_Customer_On_Hold_Order',
        'WC_Email_Customer_Processing_Order',
        'WC_Email_Customer_Completed_Order',
        'WC_Email_Customer_Refunded_Order',
        'WC_Email_Customer_Invoice',
    ];
    foreach ($remove as $class) {
        unset($emails[$class]);
    }
    return $emails;
});

add_filter('woocommerce_structured_data_product_offer', '__return_empty_array');

add_filter('woocommerce_structured_data_type_for_page', static function (array $types): array {
    $types = array_diff($types, ['product']);
    return $types;
});

add_action('wp', static function (): void {
    if (function_exists('WC') && isset(WC()->structured_data)) {
        remove_action('woocommerce_single_product_summary', [WC()->structured_data, 'generate_product_data'], 60);
    }
});

add_filter('woocommerce_enqueue_styles', static function (array $styles): array {
    unset($styles['woocommerce-smallscreen']);
    return $styles;
});

add_filter('wc_add_to_cart_message_html', '__return_empty_string');

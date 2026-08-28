<?php
/**
 * Güven Hijyen - WooCommerce Catalog Mode (must-use plugin)
 * Forces WooCommerce into catalog-only mode: no prices, no cart, no checkout.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'init', function () {
    remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
    remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price', 10 );
    remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );
}, 99 );

add_filter( 'woocommerce_is_purchasable', '__return_false' );

add_filter( 'woocommerce_get_price_html', '__return_empty_string', 999 );

add_filter( 'woocommerce_cart_item_price', '__return_empty_string' );

add_action( 'wp_enqueue_scripts', function () {
    wp_dequeue_script( 'wc-cart-fragments' );
    wp_deregister_script( 'wc-cart-fragments' );
}, 99 );

add_filter( 'woocommerce_widget_cart_is_hidden', '__return_true' );

add_action( 'template_redirect', function () {
    $blocked_pages = [ 'cart', 'checkout', 'myaccount' ];
    foreach ( $blocked_pages as $page ) {
        if ( function_exists( 'is_' . $page ) && call_user_func( 'is_' . $page ) ) {
            wp_safe_redirect( home_url( '/' ), 301 );
            exit;
        }
    }

    if ( is_cart() || is_checkout() || is_account_page() ) {
        wp_safe_redirect( home_url( '/' ), 301 );
        exit;
    }
}, 1 );

add_action( 'woocommerce_product_options_pricing', function () {
    echo '<div class="notice notice-info inline"><p>';
    echo esc_html__( 'Fiyat alanları Güven Hijyen katalog modunda devre dışıdır.', 'guvenhijyen' );
    echo '</p></div>';
} );

add_filter( 'woocommerce_order_button_html', '__return_empty_string' );
add_filter( 'woocommerce_checkout_show_terms', '__return_false' );

add_filter( 'woocommerce_email_classes', function ( $emails ) {
    $remove = [
        'WC_Email_New_Order',
        'WC_Email_Customer_Processing_Order',
        'WC_Email_Customer_Completed_Order',
        'WC_Email_Customer_Invoice',
        'WC_Email_Customer_Note',
        'WC_Email_Customer_On_Hold_Order',
        'WC_Email_Cancelled_Order',
        'WC_Email_Failed_Order',
        'WC_Email_Customer_Refunded_Order',
    ];
    foreach ( $remove as $class ) {
        unset( $emails[ $class ] );
    }
    return $emails;
} );

add_filter( 'woocommerce_structured_data_product_offer', '__return_empty_array' );

add_action( 'wp_head', function () {
    if ( ! is_product() && ! is_product_category() && ! is_shop() ) {
        return;
    }
    ?>
    <script type="application/ld+json">
    <?php
    // Intentionally empty: prevents WooCommerce from injecting Product Offer schema
    ?>
    </script>
    <?php
}, 1 );

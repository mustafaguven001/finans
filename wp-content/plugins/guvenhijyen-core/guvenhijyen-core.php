<?php
/**
 * Plugin Name: Güven Hijyen Core
 * Description: Core business logic for Güven Hijyen B2B product catalog.
 * Version: 1.0.0
 * Author: Güven Hijyen
 * Text Domain: guvenhijyen
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * WC requires at least: 7.0
 */

defined('ABSPATH') || exit;

define('GH_CORE_VERSION', '1.0.0');
define('GH_CORE_FILE', __FILE__);
define('GH_CORE_DIR', plugin_dir_path(__FILE__));
define('GH_CORE_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, 'gh_core_activate');
register_deactivation_hook(__FILE__, 'gh_core_deactivate');

function gh_core_activate(): void {
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html__('Güven Hijyen Core requires PHP 7.4 or higher.', 'guvenhijyen'),
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }

    require_once GH_CORE_DIR . 'includes/class-activator.php';
    GH_Activator::activate();

    flush_rewrite_rules();
}

function gh_core_deactivate(): void {
    flush_rewrite_rules();
}

add_action('before_woocommerce_init', static function (): void {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('guvenhijyen', false, dirname(plugin_basename(__FILE__)) . '/languages');

    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', static function (): void {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('Güven Hijyen Core requires WooCommerce to be installed and active.', 'guvenhijyen');
            echo '</p></div>';
        });
        return;
    }

    require_once GH_CORE_DIR . 'includes/class-company-settings.php';
    require_once GH_CORE_DIR . 'includes/class-procurement.php';
    require_once GH_CORE_DIR . 'includes/class-publication-rules.php';
    require_once GH_CORE_DIR . 'includes/class-brand-manager.php';
    require_once GH_CORE_DIR . 'includes/class-sector-manager.php';
    require_once GH_CORE_DIR . 'includes/class-compatibility.php';
    require_once GH_CORE_DIR . 'includes/class-document-system.php';
    require_once GH_CORE_DIR . 'includes/class-seo-hooks.php';
    require_once GH_CORE_DIR . 'includes/class-sales-unit.php';
    require_once GH_CORE_DIR . 'includes/class-redirect-manager.php';
    require_once GH_CORE_DIR . 'includes/class-blog-manager.php';
    require_once GH_CORE_DIR . 'includes/class-rate-limiter.php';
    require_once GH_CORE_DIR . 'includes/class-search-integration.php';
    require_once GH_CORE_DIR . 'includes/class-content-quality.php';
    require_once GH_CORE_DIR . 'includes/class-elementor-widgets.php';

    GH_Company_Settings::init();
    GH_Procurement::init();
    GH_Publication_Rules::init();
    GH_Brand_Manager::init();
    GH_Sector_Manager::init();
    GH_Compatibility::init();
    GH_Document_System::init();
    GH_SEO_Hooks::init();
    GH_Sales_Unit::init();
    GH_Redirect_Manager::init();
    GH_Blog_Manager::init();
    GH_Search_Integration::instance();
    GH_Content_Quality::instance();
    GH_Elementor_Widgets::instance()->init();

    require_once GH_CORE_DIR . 'includes/class-rfq-domain.php';
    require_once GH_CORE_DIR . 'includes/class-rfq-rest-api.php';
    require_once GH_CORE_DIR . 'includes/class-rfq-email.php';
    require_once GH_CORE_DIR . 'includes/class-quote-list.php';
    require_once GH_CORE_DIR . 'includes/class-whatsapp.php';
    require_once GH_CORE_DIR . 'public/class-rfq-frontend.php';

    GH_RFQ_Domain::init();
    GH_RFQ_REST_API::init();
    GH_RFQ_Email::init();
    GH_Quote_List::init();
    GH_WhatsApp::init();
    GH_RFQ_Frontend::init();

    if (is_admin()) {
        require_once GH_CORE_DIR . 'includes/class-admin-menu.php';
        require_once GH_CORE_DIR . 'includes/class-rfq-admin.php';
        GH_Admin_Menu::instance();
        GH_RFQ_Admin::init();

        require_once GH_CORE_DIR . 'import/class-import-admin.php';
        GH_Import_Admin::init();
    }
});

add_action('init', 'gh_register_taxonomies', 5);
add_action('init', 'gh_register_post_types', 5);

function gh_register_taxonomies(): void {
    if (!taxonomy_exists('product_sector')) {
        register_taxonomy('product_sector', 'product', [
            'labels' => [
                'name'          => __('Sectors', 'guvenhijyen'),
                'singular_name' => __('Sector', 'guvenhijyen'),
                'search_items'  => __('Search Sectors', 'guvenhijyen'),
                'all_items'     => __('All Sectors', 'guvenhijyen'),
                'edit_item'     => __('Edit Sector', 'guvenhijyen'),
                'add_new_item'  => __('Add New Sector', 'guvenhijyen'),
                'new_item_name' => __('New Sector Name', 'guvenhijyen'),
                'menu_name'     => __('Sectors', 'guvenhijyen'),
            ],
            'hierarchical'      => true,
            'public'            => true,
            'show_in_rest'      => true,
            'show_admin_column' => true,
            'rewrite'           => ['slug' => 'sektor', 'with_front' => false],
        ]);
    }
}

function gh_register_post_types(): void {
    register_post_type('gh_document', [
        'labels' => [
            'name'               => __('Documents', 'guvenhijyen'),
            'singular_name'      => __('Document', 'guvenhijyen'),
            'add_new'            => __('Add New Document', 'guvenhijyen'),
            'add_new_item'       => __('Add New Document', 'guvenhijyen'),
            'edit_item'          => __('Edit Document', 'guvenhijyen'),
            'view_item'          => __('View Document', 'guvenhijyen'),
            'search_items'       => __('Search Documents', 'guvenhijyen'),
            'not_found'          => __('No documents found', 'guvenhijyen'),
            'not_found_in_trash' => __('No documents found in Trash', 'guvenhijyen'),
            'menu_name'          => __('Documents', 'guvenhijyen'),
        ],
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => 'guvenhijyen',
        'show_in_rest'       => true,
        'supports'           => ['title', 'thumbnail'],
        'has_archive'        => false,
        'rewrite'            => false,
        'capability_type'    => 'post',
    ]);
}


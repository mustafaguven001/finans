<?php

defined('ABSPATH') || exit;

define('GH_THEME_VERSION', '1.0.0');
define('GH_THEME_DIR', get_stylesheet_directory());
define('GH_THEME_URI', get_stylesheet_directory_uri());

add_action('wp_enqueue_scripts', static function (): void {
    wp_enqueue_style(
        'hello-elementor',
        get_template_directory_uri() . '/style.css',
        [],
        wp_get_theme('hello-elementor')->get('Version')
    );

    wp_enqueue_style(
        'guvenhijyen-child',
        get_stylesheet_uri(),
        ['hello-elementor'],
        GH_THEME_VERSION
    );

    wp_enqueue_script(
        'gh-quote-list',
        GH_THEME_URI . '/assets/js/quote-list.js',
        [],
        GH_THEME_VERSION,
        ['strategy' => 'defer', 'in_footer' => true]
    );

    wp_localize_script('gh-quote-list', 'ghQuoteList', [
        'restUrl'  => esc_url_raw(rest_url('guvenhijyen/v1/')),
        'nonce'    => wp_create_nonce('wp_rest'),
        'listPage' => esc_url(get_permalink(get_page_by_path('teklif-iste'))),
    ]);

    wp_enqueue_script(
        'gh-product-actions',
        GH_THEME_URI . '/assets/js/product-actions.js',
        ['gh-quote-list'],
        GH_THEME_VERSION,
        ['strategy' => 'defer', 'in_footer' => true]
    );

    if (is_product()) {
        $product = wc_get_product(get_the_ID());
        $whatsapp = '';
        if (class_exists('GH_Company_Settings')) {
            $whatsapp = GH_Company_Settings::get('whatsapp');
        }
        wp_localize_script('gh-product-actions', 'ghProduct', [
            'id'        => get_the_ID(),
            'title'     => get_the_title(),
            'sku'       => $product ? $product->get_sku() : '',
            'url'       => get_permalink(),
            'whatsapp'  => $whatsapp,
            'isVariable' => $product && $product->is_type('variable'),
        ]);
    }

    if (is_page_template('page-teklif-iste.php')) {
        wp_enqueue_script(
            'gh-rfq-form',
            GH_THEME_URI . '/assets/js/rfq-form.js',
            ['gh-quote-list'],
            GH_THEME_VERSION,
            ['strategy' => 'defer', 'in_footer' => true]
        );

        wp_localize_script('gh-rfq-form', 'ghRfqForm', [
            'restUrl'    => esc_url_raw(rest_url('guvenhijyen/v1/')),
            'nonce'      => wp_create_nonce('wp_rest'),
            'searchUrl'  => esc_url_raw(rest_url('guvenhijyen/v1/products/search')),
        ]);
    }
});

add_action('after_setup_theme', static function (): void {
    add_theme_support('post-thumbnails');
    add_theme_support('title-tag');
    add_theme_support('html5', [
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'style',
        'script',
    ]);
    add_theme_support('custom-logo', [
        'height'      => 80,
        'width'       => 240,
        'flex-width'  => true,
        'flex-height' => true,
    ]);
    add_theme_support('woocommerce');

    register_nav_menus([
        'primary' => __('Primary Menu', 'guvenhijyen'),
        'mobile'  => __('Mobile Menu', 'guvenhijyen'),
        'footer'  => __('Footer Menu', 'guvenhijyen'),
    ]);

    add_image_size('product-thumb', 400, 400, true);
    add_image_size('product-full', 1600, 1600, false);
    add_image_size('category-banner', 1600, 1200, true);
    add_image_size('sector-banner', 1920, 1080, true);
    add_image_size('blog-featured', 1600, 900, true);
});

add_action('widgets_init', static function (): void {
    register_sidebar([
        'name'          => __('Sidebar', 'guvenhijyen'),
        'id'            => 'sidebar',
        'before_widget' => '<div id="%1$s" class="widget %2$s">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ]);

    $footer_areas = [
        'footer-1' => __('Footer Column 1', 'guvenhijyen'),
        'footer-2' => __('Footer Column 2', 'guvenhijyen'),
        'footer-3' => __('Footer Column 3', 'guvenhijyen'),
        'footer-4' => __('Footer Column 4', 'guvenhijyen'),
    ];

    foreach ($footer_areas as $id => $name) {
        register_sidebar([
            'name'          => $name,
            'id'            => $id,
            'before_widget' => '<div id="%1$s" class="widget %2$s">',
            'after_widget'  => '</div>',
            'before_title'  => '<h3 class="widget-title">',
            'after_title'   => '</h3>',
        ]);
    }
});

add_action('after_setup_theme', static function (): void {
    if (class_exists('WooCommerce')) {
        require_once GH_THEME_DIR . '/inc/woocommerce-overrides.php';
    }
}, 20);

function gh_get_company(string $field): string {
    if (class_exists('GH_Company_Settings')) {
        return GH_Company_Settings::get($field);
    }
    return '';
}

function gh_get_company_all(): array {
    if (class_exists('GH_Company_Settings')) {
        return GH_Company_Settings::get_all();
    }
    return [];
}

function gh_get_company_schema(): array {
    if (class_exists('GH_Company_Settings')) {
        return GH_Company_Settings::get_structured_data();
    }
    return [];
}

function gh_whatsapp_url(string $message = ''): string {
    $number = gh_get_company('whatsapp');
    if (!$number) {
        return '';
    }
    $number = preg_replace('/[^0-9]/', '', $number);
    $url = 'https://wa.me/' . $number;
    if ($message) {
        $url .= '?text=' . rawurlencode($message);
    }
    return $url;
}

function gh_render_breadcrumb(): void {
    if (function_exists('rank_math_the_breadcrumbs')) {
        rank_math_the_breadcrumbs();
        return;
    }

    if (function_exists('yoast_breadcrumb')) {
        yoast_breadcrumb('<nav class="gh-breadcrumb" aria-label="' . esc_attr__('Breadcrumb', 'guvenhijyen') . '">', '</nav>');
        return;
    }

    if (is_front_page()) {
        return;
    }

    echo '<nav class="gh-breadcrumb" aria-label="' . esc_attr__('Breadcrumb', 'guvenhijyen') . '">';
    echo '<a href="' . esc_url(home_url('/')) . '">' . esc_html__('Home', 'guvenhijyen') . '</a>';

    if (is_product_category()) {
        $current = get_queried_object();
        $ancestors = get_ancestors($current->term_id, 'product_cat');
        $ancestors = array_reverse($ancestors);
        foreach ($ancestors as $ancestor_id) {
            $ancestor = get_term($ancestor_id, 'product_cat');
            if ($ancestor && !is_wp_error($ancestor)) {
                echo '<span class="gh-breadcrumb__separator" aria-hidden="true">/</span>';
                echo '<a href="' . esc_url(get_term_link($ancestor)) . '">' . esc_html($ancestor->name) . '</a>';
            }
        }
        echo '<span class="gh-breadcrumb__separator" aria-hidden="true">/</span>';
        echo '<span aria-current="page">' . esc_html($current->name) . '</span>';
    } elseif (is_product()) {
        $terms = wc_get_product_terms(get_the_ID(), 'product_cat', ['orderby' => 'parent', 'order' => 'ASC']);
        if ($terms) {
            $term = end($terms);
            $ancestors = get_ancestors($term->term_id, 'product_cat');
            $ancestors = array_reverse($ancestors);
            foreach ($ancestors as $ancestor_id) {
                $ancestor = get_term($ancestor_id, 'product_cat');
                if ($ancestor && !is_wp_error($ancestor)) {
                    echo '<span class="gh-breadcrumb__separator" aria-hidden="true">/</span>';
                    echo '<a href="' . esc_url(get_term_link($ancestor)) . '">' . esc_html($ancestor->name) . '</a>';
                }
            }
            echo '<span class="gh-breadcrumb__separator" aria-hidden="true">/</span>';
            echo '<a href="' . esc_url(get_term_link($term)) . '">' . esc_html($term->name) . '</a>';
        }
        echo '<span class="gh-breadcrumb__separator" aria-hidden="true">/</span>';
        echo '<span aria-current="page">' . esc_html(get_the_title()) . '</span>';
    } elseif (is_page()) {
        echo '<span class="gh-breadcrumb__separator" aria-hidden="true">/</span>';
        echo '<span aria-current="page">' . esc_html(get_the_title()) . '</span>';
    }

    echo '</nav>';
}

add_filter('woocommerce_product_tabs', static function (array $tabs): array {
    unset($tabs['reviews']);
    return $tabs;
});

add_filter('woocommerce_enqueue_styles', static function (array $styles): array {
    return $styles;
});

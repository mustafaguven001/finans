<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Elementor_Widgets {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
        add_action( 'elementor/elements/categories_registered', [ $this, 'register_category' ] );
    }

    public function register_category( $elements_manager ): void {
        $elements_manager->add_category( 'guvenhijyen', [
            'title' => 'Güven Hijyen',
            'icon'  => 'eicon-building',
        ] );
    }

    public function register_widgets( $widgets_manager ): void {
        $widget_files = [
            'rfq-cta'           => 'GH_Widget_RFQ_CTA',
            'product-grid'      => 'GH_Widget_Product_Grid',
            'brand-grid'        => 'GH_Widget_Brand_Grid',
            'sector-grid'       => 'GH_Widget_Sector_Grid',
            'document-list'     => 'GH_Widget_Document_List',
            'company-info'      => 'GH_Widget_Company_Info',
            'quote-list-button' => 'GH_Widget_Quote_List_Button',
        ];

        foreach ( $widget_files as $slug => $class_name ) {
            $file = GH_CORE_DIR . 'includes/widgets/class-widget-' . $slug . '.php';
            if ( file_exists( $file ) ) {
                require_once $file;
                if ( class_exists( $class_name ) ) {
                    $widgets_manager->register( new $class_name() );
                }
            }
        }
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Widget_Brand_Grid extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'gh_brand_grid';
    }

    public function get_title(): string {
        return 'Marka Grid';
    }

    public function get_icon(): string {
        return 'eicon-gallery-grid';
    }

    public function get_categories(): array {
        return [ 'guvenhijyen' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => 'Ayarlar',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'heading', [
            'label'   => 'Başlık',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Markalarımız',
        ] );

        $this->add_control( 'columns', [
            'label'   => 'Sütun',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '6',
            'options' => [ '4' => '4', '5' => '5', '6' => '6', '8' => '8' ],
        ] );

        $this->add_control( 'limit', [
            'label'   => 'Marka Sayısı',
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 12,
            'min'     => 1,
            'max'     => 50,
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $brands = get_terms( [
            'taxonomy'   => 'product_brand',
            'hide_empty' => true,
            'number'     => (int) $settings['limit'],
        ] );

        if ( is_wp_error( $brands ) || empty( $brands ) ) {
            return;
        }

        if ( class_exists( 'GH_Brand_Manager' ) ) {
            $brands = array_filter( $brands, fn( $b ) => GH_Brand_Manager::instance()->is_brand_ready( $b->term_id ) );
        }

        if ( empty( $brands ) ) {
            return;
        }

        if ( ! empty( $settings['heading'] ) ) {
            printf( '<h2 class="gh-section-heading">%s</h2>', esc_html( $settings['heading'] ) );
        }

        printf(
            '<div class="gh-brand-grid" style="display:grid;grid-template-columns:repeat(%s,1fr);gap:16px;align-items:center;">',
            esc_attr( $settings['columns'] )
        );

        foreach ( $brands as $brand ) {
            $logo = get_term_meta( $brand->term_id, 'brand_logo', true );
            $link = get_term_link( $brand );

            echo '<div class="gh-brand-item" style="text-align:center;">';
            echo '<a href="' . esc_url( $link ) . '" title="' . esc_attr( $brand->name ) . '">';

            if ( $logo ) {
                $logo_url = wp_get_attachment_image_url( $logo, 'medium' );
                if ( $logo_url ) {
                    printf(
                        '<img src="%s" alt="%s" loading="lazy" style="max-width:120px;max-height:60px;object-fit:contain;">',
                        esc_url( $logo_url ),
                        esc_attr( $brand->name )
                    );
                } else {
                    echo '<span style="font-weight:600;color:var(--gh-primary);">' . esc_html( $brand->name ) . '</span>';
                }
            } else {
                echo '<span style="font-weight:600;color:var(--gh-primary);">' . esc_html( $brand->name ) . '</span>';
            }

            echo '</a>';
            echo '</div>';
        }

        echo '</div>';
    }
}

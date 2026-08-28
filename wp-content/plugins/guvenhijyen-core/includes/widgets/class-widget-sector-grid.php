<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Widget_Sector_Grid extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'gh_sector_grid';
    }

    public function get_title(): string {
        return 'Sektör Grid';
    }

    public function get_icon(): string {
        return 'eicon-posts-grid';
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
            'default' => 'Sektörel Çözümler',
        ] );

        $this->add_control( 'columns', [
            'label'   => 'Sütun',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '3',
            'options' => [ '2' => '2', '3' => '3', '4' => '4' ],
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $sectors = get_terms( [
            'taxonomy'   => 'product_sector',
            'hide_empty' => false,
        ] );

        if ( is_wp_error( $sectors ) || empty( $sectors ) ) {
            return;
        }

        if ( class_exists( 'GH_Sector_Manager' ) ) {
            $sectors = array_filter( $sectors, fn( $s ) => GH_Sector_Manager::instance()->is_sector_ready( $s->term_id ) );
        }

        if ( empty( $sectors ) ) {
            return;
        }

        if ( ! empty( $settings['heading'] ) ) {
            printf( '<h2 class="gh-section-heading">%s</h2>', esc_html( $settings['heading'] ) );
        }

        printf(
            '<div class="gh-sector-grid" style="display:grid;grid-template-columns:repeat(%s,1fr);gap:20px;">',
            esc_attr( $settings['columns'] )
        );

        foreach ( $sectors as $sector ) {
            $image_id = get_term_meta( $sector->term_id, 'sector_image', true );
            $desc     = get_term_meta( $sector->term_id, 'sector_description', true );
            $link     = get_term_link( $sector );

            echo '<div class="gh-sector-card" style="background:#fff;border:1px solid var(--gh-border,#dee2e6);border-radius:10px;overflow:hidden;">';

            if ( $image_id ) {
                $img_url = wp_get_attachment_image_url( $image_id, 'sector-banner' );
                if ( $img_url ) {
                    printf(
                        '<div style="aspect-ratio:16/9;overflow:hidden;"><img src="%s" alt="%s" loading="lazy" style="width:100%%;height:100%%;object-fit:cover;"></div>',
                        esc_url( $img_url ),
                        esc_attr( $sector->name )
                    );
                }
            }

            echo '<div style="padding:20px;">';
            printf( '<h3 style="margin:0 0 8px;font-size:18px;"><a href="%s" style="color:inherit;text-decoration:none;">%s</a></h3>', esc_url( $link ), esc_html( $sector->name ) );

            if ( $desc ) {
                printf( '<p style="color:#666;font-size:14px;margin:0 0 12px;">%s</p>', esc_html( wp_trim_words( $desc, 20 ) ) );
            }

            printf( '<a href="%s" class="gh-btn gh-btn-outline gh-btn-sm">%s</a>', esc_url( $link ), esc_html__( 'Ürünleri İncele', 'guvenhijyen' ) );

            echo '</div></div>';
        }

        echo '</div>';
    }
}

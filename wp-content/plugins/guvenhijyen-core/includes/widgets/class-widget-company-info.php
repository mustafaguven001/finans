<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Widget_Company_Info extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'gh_company_info';
    }

    public function get_title(): string {
        return 'Firma Bilgileri';
    }

    public function get_icon(): string {
        return 'eicon-info-circle';
    }

    public function get_categories(): array {
        return [ 'guvenhijyen' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => 'Görünüm',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'show_phone', [
            'label'   => 'Telefon',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'show_email', [
            'label'   => 'E-posta',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'show_address', [
            'label'   => 'Adres',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'show_social', [
            'label'   => 'Sosyal Medya',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        if ( ! class_exists( 'GH_Company_Settings' ) ) {
            return;
        }

        $info = GH_Company_Settings::get_all();
        if ( empty( $info ) ) {
            return;
        }

        echo '<div class="gh-company-info">';

        if ( 'yes' === $settings['show_phone'] && ! empty( $info['phone'] ) ) {
            printf(
                '<div class="gh-company-info__item"><strong>%s</strong> <a href="tel:%s">%s</a></div>',
                esc_html__( 'Telefon:', 'guvenhijyen' ),
                esc_attr( preg_replace( '/[^0-9+]/', '', $info['phone'] ) ),
                esc_html( $info['phone'] )
            );
        }

        if ( 'yes' === $settings['show_email'] && ! empty( $info['email'] ) ) {
            printf(
                '<div class="gh-company-info__item"><strong>%s</strong> <a href="mailto:%s">%s</a></div>',
                esc_html__( 'E-posta:', 'guvenhijyen' ),
                esc_attr( $info['email'] ),
                esc_html( $info['email'] )
            );
        }

        if ( 'yes' === $settings['show_address'] ) {
            $parts = array_filter( [
                $info['address'] ?? '',
                $info['district'] ?? '',
                $info['city'] ?? '',
            ] );
            if ( $parts ) {
                printf(
                    '<div class="gh-company-info__item"><strong>%s</strong> %s</div>',
                    esc_html__( 'Adres:', 'guvenhijyen' ),
                    esc_html( implode( ', ', $parts ) )
                );

                if ( ! empty( $info['map_url'] ) ) {
                    printf(
                        '<div class="gh-company-info__item"><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></div>',
                        esc_url( $info['map_url'] ),
                        esc_html__( 'Haritada Göster', 'guvenhijyen' )
                    );
                }
            }
        }

        if ( 'yes' === $settings['show_social'] ) {
            $social = [
                'instagram_url' => 'Instagram',
                'facebook_url'  => 'Facebook',
                'linkedin_url'  => 'LinkedIn',
                'youtube_url'   => 'YouTube',
            ];

            $links = [];
            foreach ( $social as $key => $label ) {
                if ( ! empty( $info[ $key ] ) ) {
                    $links[] = sprintf(
                        '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                        esc_url( $info[ $key ] ),
                        esc_html( $label )
                    );
                }
            }

            if ( $links ) {
                echo '<div class="gh-company-info__social">' . implode( ' ', $links ) . '</div>';
            }
        }

        echo '</div>';
    }
}

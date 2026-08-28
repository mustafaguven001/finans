<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Widget_Quote_List_Button extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'gh_quote_list_button';
    }

    public function get_title(): string {
        return 'Teklif Listesi Butonu';
    }

    public function get_icon(): string {
        return 'eicon-cart';
    }

    public function get_categories(): array {
        return [ 'guvenhijyen' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => 'Ayarlar',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'button_text', [
            'label'   => 'Buton Metni',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Teklif Listesi',
        ] );

        $this->add_control( 'show_count', [
            'label'   => 'Ürün Sayısını Göster',
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ] );

        $this->add_control( 'style', [
            'label'   => 'Stil',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'primary',
            'options' => [
                'primary' => 'Birincil',
                'outline' => 'Çerçeveli',
                'minimal' => 'Minimal',
            ],
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $rfq_page = get_page_by_path( 'teklif-iste' );
        $rfq_url  = $rfq_page ? get_permalink( $rfq_page ) : home_url( '/teklif-iste/' );

        $btn_class = 'gh-btn';
        switch ( $settings['style'] ) {
            case 'outline':
                $btn_class .= ' gh-btn-outline';
                break;
            case 'minimal':
                $btn_class .= ' gh-btn-minimal';
                break;
            default:
                $btn_class .= ' gh-btn-primary';
                break;
        }

        echo '<div class="gh-quote-list-button-widget">';
        printf(
            '<a href="%s" class="%s" data-gh-quote-list-trigger>',
            esc_url( $rfq_url ),
            esc_attr( $btn_class )
        );

        echo '<span class="gh-quote-list-button__icon" aria-hidden="true">&#128203;</span> ';
        printf( '<span class="gh-quote-list-button__text">%s</span>', esc_html( $settings['button_text'] ) );

        if ( 'yes' === $settings['show_count'] ) {
            echo ' <span class="gh-quote-list-indicator" data-gh-quote-count aria-label="';
            echo esc_attr__( 'Listedeki ürün sayısı', 'guvenhijyen' );
            echo '">0</span>';
        }

        echo '</a>';
        echo '</div>';
    }
}

<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Widget_RFQ_CTA extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'gh_rfq_cta';
    }

    public function get_title(): string {
        return 'RFQ CTA';
    }

    public function get_icon(): string {
        return 'eicon-call-to-action';
    }

    public function get_categories(): array {
        return [ 'guvenhijyen' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => 'İçerik',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'heading', [
            'label'   => 'Başlık',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Toplu Alım veya Özel Teklif mi Arıyorsunuz?',
        ] );

        $this->add_control( 'description', [
            'label'   => 'Açıklama',
            'type'    => \Elementor\Controls_Manager::TEXTAREA,
            'default' => 'Ürün kataloğumuzu inceleyin, ihtiyacınız olan ürünleri seçin ve hemen teklif talep edin.',
        ] );

        $this->add_control( 'button_text', [
            'label'   => 'Buton Metni',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => 'Teklif İste',
        ] );

        $this->add_control( 'show_whatsapp', [
            'label'        => 'WhatsApp Butonu',
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
        ] );

        $this->add_control( 'show_phone', [
            'label'        => 'Telefon Göster',
            'type'         => \Elementor\Controls_Manager::SWITCHER,
            'default'      => 'yes',
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $rfq_url = home_url( '/teklif-iste/' );

        $whatsapp_number = '';
        $phone           = '';
        if ( class_exists( 'GH_Company_Settings' ) ) {
            $whatsapp_number = GH_Company_Settings::get( 'whatsapp' );
            $phone           = GH_Company_Settings::get( 'phone' );
        }

        ?>
        <div class="gh-rfq-cta">
            <h2><?php echo esc_html( $settings['heading'] ); ?></h2>
            <p><?php echo esc_html( $settings['description'] ); ?></p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;">
                <a href="<?php echo esc_url( $rfq_url ); ?>" class="gh-btn gh-btn-rfq gh-btn-lg">
                    <?php echo esc_html( $settings['button_text'] ); ?>
                </a>
                <?php if ( 'yes' === $settings['show_whatsapp'] && $whatsapp_number ) : ?>
                <a href="<?php echo esc_url( 'https://wa.me/' . preg_replace( '/[^0-9]/', '', $whatsapp_number ) ); ?>"
                   class="gh-btn gh-btn-whatsapp gh-btn-lg"
                   target="_blank"
                   rel="noopener noreferrer">
                    WhatsApp
                </a>
                <?php endif; ?>
            </div>
            <?php if ( 'yes' === $settings['show_phone'] && $phone ) : ?>
            <p style="margin-top:16px;font-size:15px;opacity:0.9;">
                veya bizi arayın: <a href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>" style="color:#fff;font-weight:bold;"><?php echo esc_html( $phone ); ?></a>
            </p>
            <?php endif; ?>
        </div>
        <?php
    }
}

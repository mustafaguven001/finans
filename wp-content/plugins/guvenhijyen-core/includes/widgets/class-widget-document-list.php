<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Widget_Document_List extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'gh_document_list';
    }

    public function get_title(): string {
        return 'Doküman Listesi';
    }

    public function get_icon(): string {
        return 'eicon-document-file';
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
            'default' => 'Katalog ve Dokümanlar',
        ] );

        $this->add_control( 'document_type', [
            'label'   => 'Doküman Türü',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '',
            'options' => [
                ''                  => 'Tümü',
                'catalog'           => 'Katalog',
                'technical_datasheet' => 'Teknik Veri Sayfası',
                'safety_datasheet'  => 'Güvenlik Veri Sayfası',
                'user_manual'       => 'Kullanım Kılavuzu',
                'certificate'       => 'Sertifika',
                'authorization'     => 'Yetki Belgesi',
            ],
        ] );

        $this->add_control( 'limit', [
            'label'   => 'Sayı',
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 6,
            'min'     => 1,
            'max'     => 20,
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $args = [
            'post_type'      => 'gh_document',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $settings['limit'],
            'orderby'        => 'title',
            'order'          => 'ASC',
        ];

        if ( ! empty( $settings['document_type'] ) ) {
            $args['meta_query'] = [ [
                'key'   => '_gh_document_type',
                'value' => sanitize_key( $settings['document_type'] ),
            ] ];
        }

        $args['meta_query'][] = [
            'key'   => '_gh_document_lifecycle',
            'value' => 'active',
        ];

        $query = new WP_Query( $args );

        if ( ! $query->have_posts() ) {
            return;
        }

        if ( ! empty( $settings['heading'] ) ) {
            printf( '<h2 class="gh-section-heading">%s</h2>', esc_html( $settings['heading'] ) );
        }

        $type_icons = [
            'catalog'             => '📋',
            'technical_datasheet' => '📊',
            'safety_datasheet'    => '⚠️',
            'user_manual'         => '📖',
            'certificate'         => '🏆',
            'authorization'       => '✅',
            'declaration'         => '📜',
            'other'               => '📄',
        ];

        $type_labels = [
            'catalog'             => 'Katalog',
            'technical_datasheet' => 'Teknik Veri Sayfası',
            'safety_datasheet'    => 'Güvenlik Veri Sayfası',
            'user_manual'         => 'Kullanım Kılavuzu',
            'certificate'         => 'Sertifika',
            'authorization'       => 'Yetki Belgesi',
            'declaration'         => 'Beyanname',
            'other'               => 'Diğer',
        ];

        echo '<div class="gh-document-list">';

        while ( $query->have_posts() ) {
            $query->the_post();
            $doc_id   = get_the_ID();
            $doc_type = get_post_meta( $doc_id, '_gh_document_type', true );

            $current_revision = null;
            if ( class_exists( 'GH_Document_System' ) ) {
                $current_revision = GH_Document_System::instance()->get_current_revision( $doc_id );
            }

            if ( ! $current_revision ) {
                continue;
            }

            $icon     = $type_icons[ $doc_type ] ?? '📄';
            $label    = $type_labels[ $doc_type ] ?? $doc_type;
            $file_url = isset( $current_revision['attachment_id'] ) ? wp_get_attachment_url( $current_revision['attachment_id'] ) : '';
            $version  = $current_revision['version'] ?? '';

            echo '<div class="gh-document-item">';
            printf( '<div class="gh-document-item__icon">%s</div>', esc_html( $icon ) );
            echo '<div class="gh-document-item__info">';
            printf( '<div class="gh-document-item__title">%s</div>', esc_html( get_the_title() ) );
            printf(
                '<div class="gh-document-item__meta">%s%s</div>',
                esc_html( $label ),
                $version ? ' &mdash; v' . esc_html( $version ) : ''
            );
            echo '</div>';

            if ( $file_url ) {
                printf(
                    '<a href="%s" target="_blank" rel="noopener noreferrer" class="gh-btn gh-btn-outline gh-btn-sm">%s</a>',
                    esc_url( $file_url ),
                    esc_html__( 'İndir', 'guvenhijyen' )
                );
            }

            echo '</div>';
        }

        echo '</div>';
        wp_reset_postdata();
    }
}

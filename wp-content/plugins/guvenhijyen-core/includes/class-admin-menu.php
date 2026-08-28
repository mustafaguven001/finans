<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Admin_Menu {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        add_action( 'admin_menu', [ $this, 'register_menus' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
    }

    public function register_menus(): void {
        add_menu_page(
            'Güven Hijyen',
            'Güven Hijyen',
            'manage_options',
            'guvenhijyen',
            [ $this, 'render_dashboard' ],
            'dashicons-building',
            3
        );

        add_submenu_page(
            'guvenhijyen',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'guvenhijyen',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'guvenhijyen',
            'Firma Ayarları',
            'Firma Ayarları',
            'manage_gh_settings',
            'guvenhijyen-settings',
            [ GH_Company_Settings::class, 'render_settings_page' ]
        );

        add_submenu_page(
            'guvenhijyen',
            'Teklif Talepleri',
            'Teklif Talepleri',
            'manage_gh_rfq',
            'guvenhijyen-rfq',
            function () {
                if ( class_exists( 'GH_RFQ_Admin' ) ) {
                    GH_RFQ_Admin::instance()->render_page();
                }
            }
        );

        add_submenu_page(
            'guvenhijyen',
            'Dokümanlar',
            'Dokümanlar',
            'manage_gh_documents',
            'edit.php?post_type=gh_document'
        );

        add_submenu_page(
            'guvenhijyen',
            'XLSX Import',
            'XLSX Import',
            'manage_gh_import',
            'guvenhijyen-import',
            function () {
                if ( class_exists( 'GH_Import_Admin' ) ) {
                    GH_Import_Admin::instance()->render_page();
                }
            }
        );

        add_submenu_page(
            'guvenhijyen',
            'Yönlendirmeler',
            'Yönlendirmeler',
            'manage_gh_redirects',
            'guvenhijyen-redirects',
            function () {
                if ( class_exists( 'GH_Redirect_Manager' ) ) {
                    GH_Redirect_Manager::instance()->render_admin_page();
                }
            }
        );
    }

    public function render_dashboard(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'Yetkisiz erişim.', 'guvenhijyen' ) );
        }

        $stats = $this->get_dashboard_stats();

        ?>
        <div class="wrap">
            <h1><?php echo esc_html__( 'Güven Hijyen Dashboard', 'guvenhijyen' ); ?></h1>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:20px;">
                <?php foreach ( $stats as $stat ) : ?>
                <div style="background:#fff;border:1px solid #ddd;border-left:4px solid <?php echo esc_attr( $stat['color'] ); ?>;padding:16px;border-radius:4px;">
                    <div style="font-size:13px;color:#666;margin-bottom:4px;"><?php echo esc_html( $stat['label'] ); ?></div>
                    <div style="font-size:28px;font-weight:700;color:#1d2327;"><?php echo esc_html( $stat['value'] ); ?></div>
                    <?php if ( ! empty( $stat['sub'] ) ) : ?>
                    <div style="font-size:12px;color:#999;margin-top:4px;"><?php echo esc_html( $stat['sub'] ); ?></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top:24px;display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:4px;">
                    <h2 style="margin-top:0;"><?php echo esc_html__( 'Son Teklif Talepleri', 'guvenhijyen' ); ?></h2>
                    <?php $this->render_recent_rfqs(); ?>
                </div>
                <div style="background:#fff;border:1px solid #ddd;padding:20px;border-radius:4px;">
                    <h2 style="margin-top:0;"><?php echo esc_html__( 'Hızlı Linkler', 'guvenhijyen' ); ?></h2>
                    <ul>
                        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=guvenhijyen-settings' ) ); ?>">Firma Ayarları</a></li>
                        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=guvenhijyen-rfq' ) ); ?>">Teklif Talepleri</a></li>
                        <li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=product' ) ); ?>">Ürünler</a></li>
                        <li><a href="<?php echo esc_url( admin_url( 'edit.php?post_type=gh_document' ) ); ?>">Dokümanlar</a></li>
                        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=guvenhijyen-import' ) ); ?>">XLSX Import</a></li>
                        <li><a href="<?php echo esc_url( admin_url( 'admin.php?page=guvenhijyen-redirects' ) ); ?>">Yönlendirmeler</a></li>
                    </ul>
                </div>
            </div>
        </div>
        <?php
    }

    private function get_dashboard_stats(): array {
        global $wpdb;

        $product_count = wp_count_posts( 'product' );
        $published     = $product_count->publish ?? 0;
        $draft         = $product_count->draft ?? 0;

        $rfq_count = 0;
        $rfq_new   = 0;
        $table     = $wpdb->prefix . 'gh_rfq';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table ) {
            $rfq_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
            $rfq_new   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'new' ) );
        }

        $doc_count  = wp_count_posts( 'gh_document' );
        $doc_active = $doc_count->publish ?? 0;

        $brand_count = wp_count_terms( [ 'taxonomy' => 'product_brand', 'hide_empty' => false ] );
        if ( is_wp_error( $brand_count ) ) {
            $brand_count = 0;
        }

        $category_count = wp_count_terms( [ 'taxonomy' => 'product_cat', 'hide_empty' => false ] );
        if ( is_wp_error( $category_count ) ) {
            $category_count = 0;
        }

        return [
            [ 'label' => 'Yayındaki Ürünler',     'value' => $published,  'color' => '#2271b1', 'sub' => $draft . ' taslak' ],
            [ 'label' => 'Teklif Talepleri',       'value' => $rfq_count, 'color' => '#00a32a', 'sub' => $rfq_new . ' yeni' ],
            [ 'label' => 'Dokümanlar',             'value' => $doc_active, 'color' => '#dba617', 'sub' => '' ],
            [ 'label' => 'Markalar',               'value' => $brand_count, 'color' => '#8c5cb4', 'sub' => '' ],
            [ 'label' => 'Kategoriler',            'value' => $category_count, 'color' => '#d63638', 'sub' => '' ],
        ];
    }

    private function render_recent_rfqs(): void {
        global $wpdb;

        $table = $wpdb->prefix . 'gh_rfq';
        if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
            echo '<p>' . esc_html__( 'Henüz teklif talebi yok.', 'guvenhijyen' ) . '</p>';
            return;
        }

        $rfqs = $wpdb->get_results(
            "SELECT reference, type, company, contact_name, status, created_at FROM {$table} ORDER BY created_at DESC LIMIT 5"
        );

        if ( empty( $rfqs ) ) {
            echo '<p>' . esc_html__( 'Henüz teklif talebi yok.', 'guvenhijyen' ) . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>Referans</th><th>Tür</th><th>Firma</th><th>Durum</th><th>Tarih</th>';
        echo '</tr></thead><tbody>';

        $type_labels = [
            'general'     => 'Genel',
            'quick_quote' => 'Hızlı',
            'quote_list'  => 'Liste',
        ];

        $status_labels = [
            'new'             => 'Yeni',
            'reviewing'       => 'İnceleniyor',
            'quote_prepared'  => 'Teklif Hazır',
            'sent'            => 'Gönderildi',
            'closed'          => 'Kapatıldı',
            'cancelled'       => 'İptal',
        ];

        foreach ( $rfqs as $rfq ) {
            echo '<tr>';
            printf( '<td><strong>%s</strong></td>', esc_html( $rfq->reference ) );
            printf( '<td>%s</td>', esc_html( $type_labels[ $rfq->type ] ?? $rfq->type ) );
            printf( '<td>%s</td>', esc_html( $rfq->company ) );
            printf( '<td>%s</td>', esc_html( $status_labels[ $rfq->status ] ?? $rfq->status ) );
            printf( '<td>%s</td>', esc_html( wp_date( 'd.m.Y H:i', strtotime( $rfq->created_at ) ) ) );
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    public function enqueue_admin_assets( string $hook ): void {
        if ( strpos( $hook, 'guvenhijyen' ) === false ) {
            return;
        }

        wp_enqueue_style(
            'guvenhijyen-admin',
            plugins_url( 'assets/css/admin.css', dirname( __FILE__ ) ),
            [],
            GUVENHIJYEN_VERSION
        );
    }
}

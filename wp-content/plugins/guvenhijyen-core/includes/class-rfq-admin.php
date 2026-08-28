<?php

defined('ABSPATH') || exit;

class GH_RFQ_Admin {

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_capabilities']);
        add_action('admin_init', [__CLASS__, 'handle_actions']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_menu(): void {
        add_submenu_page(
            'guvenhijyen',
            __('Teklif Talepleri', 'guvenhijyen'),
            __('Teklif Talepleri', 'guvenhijyen'),
            'manage_gh_rfq',
            'gh-rfq',
            [__CLASS__, 'render_page']
        );
    }

    public static function register_capabilities(): void {
        $admin_role = get_role('administrator');
        if ($admin_role && !$admin_role->has_cap('manage_gh_rfq')) {
            $admin_role->add_cap('manage_gh_rfq');
            $admin_role->add_cap('export_gh_rfq');
        }
    }

    public static function enqueue_assets(string $hook): void {
        if (strpos($hook, 'gh-rfq') === false) {
            return;
        }

        wp_enqueue_style(
            'gh-rfq-admin',
            GH_CORE_URL . 'assets/css/rfq-admin.css',
            [],
            GH_CORE_VERSION
        );
    }

    public static function handle_actions(): void {
        if (!isset($_GET['page']) || $_GET['page'] !== 'gh-rfq') {
            return;
        }

        if (isset($_GET['action']) && $_GET['action'] === 'export') {
            self::handle_export();
        }

        if (isset($_POST['gh_rfq_status_update']) && isset($_POST['_wpnonce'])) {
            self::handle_status_update();
        }

        if (isset($_POST['gh_rfq_bulk_action']) && isset($_POST['_wpnonce'])) {
            self::handle_bulk_action();
        }
    }

    public static function render_page(): void {
        if (!current_user_can('manage_gh_rfq')) {
            wp_die(esc_html__('Bu sayfaya erisim yetkiniz yok.', 'guvenhijyen'));
        }

        $view = isset($_GET['view']) ? sanitize_text_field(wp_unslash($_GET['view'])) : 'list';

        echo '<div class="wrap">';

        if ($view === 'detail' && isset($_GET['reference'])) {
            self::render_detail_view();
        } else {
            self::render_list_view();
        }

        echo '</div>';
    }

    private static function render_list_view(): void {
        $current_status = isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '';
        $current_type   = isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : '';
        $search         = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $paged          = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $orderby        = isset($_GET['orderby']) ? sanitize_text_field(wp_unslash($_GET['orderby'])) : 'created_at';
        $order          = isset($_GET['order']) ? sanitize_text_field(wp_unslash($_GET['order'])) : 'DESC';

        $result = GH_RFQ_Domain::list_rfqs([
            'page'     => $paged,
            'per_page' => 20,
            'status'   => $current_status,
            'type'     => $current_type,
            'search'   => $search,
            'orderby'  => $orderby,
            'order'    => $order,
        ]);

        $items       = $result['items'];
        $total       = $result['total'];
        $total_pages = $result['total_pages'];

        echo '<h1 class="wp-heading-inline">' . esc_html__('Teklif Talepleri', 'guvenhijyen') . '</h1>';

        if (current_user_can('export_gh_rfq')) {
            $export_url = wp_nonce_url(
                add_query_arg(['page' => 'gh-rfq', 'action' => 'export', 'status' => $current_status, 'type' => $current_type, 's' => $search], admin_url('admin.php')),
                'gh_rfq_export'
            );
            echo ' <a href="' . esc_url($export_url) . '" class="page-title-action">' . esc_html__('Disari Aktar', 'guvenhijyen') . '</a>';
        }

        echo '<hr class="wp-header-end">';

        if (isset($_GET['status_updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Durum guncellendi.', 'guvenhijyen') . '</p></div>';
        }
        if (isset($_GET['bulk_updated'])) {
            $count = (int) $_GET['bulk_updated'];
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(sprintf(__('%d talep guncellendi.', 'guvenhijyen'), $count)) . '</p></div>';
        }

        self::render_filters($current_status, $current_type, $search);

        echo '<form method="post">';
        wp_nonce_field('gh_rfq_bulk', '_wpnonce');

        echo '<div class="tablenav top">';
        echo '<div class="alignleft actions bulkactions">';
        echo '<select name="bulk_status">';
        echo '<option value="">' . esc_html__('Toplu Islem', 'guvenhijyen') . '</option>';
        foreach (GH_RFQ_Domain::get_statuses() as $status) {
            echo '<option value="' . esc_attr($status) . '">' . esc_html(GH_RFQ_Domain::get_status_label($status)) . '</option>';
        }
        echo '</select>';
        echo '<input type="submit" name="gh_rfq_bulk_action" class="button action" value="' . esc_attr__('Uygula', 'guvenhijyen') . '">';
        echo '</div>';

        if ($total_pages > 1) {
            self::render_pagination($paged, $total_pages, $total);
        }
        echo '</div>';

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<td class="manage-column column-cb check-column"><input type="checkbox" id="cb-select-all"></td>';
        echo '<th class="manage-column">' . esc_html__('Referans', 'guvenhijyen') . '</th>';
        echo '<th class="manage-column">' . esc_html__('Tip', 'guvenhijyen') . '</th>';
        echo '<th class="manage-column">' . esc_html__('Firma', 'guvenhijyen') . '</th>';
        echo '<th class="manage-column">' . esc_html__('Yetkili', 'guvenhijyen') . '</th>';
        echo '<th class="manage-column">' . esc_html__('Tarih', 'guvenhijyen') . '</th>';
        echo '<th class="manage-column">' . esc_html__('Urunler', 'guvenhijyen') . '</th>';
        echo '<th class="manage-column">' . esc_html__('Durum', 'guvenhijyen') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        if (empty($items)) {
            echo '<tr><td colspan="8">' . esc_html__('Teklif talebi bulunamadi.', 'guvenhijyen') . '</td></tr>';
        }

        foreach ($items as $rfq) {
            $detail_url = add_query_arg([
                'page'      => 'gh-rfq',
                'view'      => 'detail',
                'reference' => $rfq['reference'],
            ], admin_url('admin.php'));

            $status_class = self::get_status_css_class($rfq['status']);

            echo '<tr>';
            echo '<th class="check-column"><input type="checkbox" name="references[]" value="' . esc_attr($rfq['reference']) . '"></th>';
            echo '<td><a href="' . esc_url($detail_url) . '"><strong>' . esc_html($rfq['reference']) . '</strong></a></td>';
            echo '<td><span class="gh-rfq-badge gh-rfq-type-' . esc_attr($rfq['type']) . '">' . esc_html(GH_RFQ_Domain::get_type_label($rfq['type'])) . '</span></td>';
            echo '<td>' . esc_html($rfq['company']) . '</td>';
            echo '<td>' . esc_html($rfq['contact_name']) . '</td>';

            $created = wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($rfq['created_at']));
            echo '<td>' . esc_html($created) . '</td>';

            echo '<td>' . esc_html($rfq['item_count'] ?? 0) . '</td>';
            echo '<td><span class="gh-rfq-status ' . esc_attr($status_class) . '">' . esc_html(GH_RFQ_Domain::get_status_label($rfq['status'])) . '</span></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

        if ($total_pages > 1) {
            echo '<div class="tablenav bottom">';
            self::render_pagination($paged, $total_pages, $total);
            echo '</div>';
        }

        echo '</form>';
    }

    private static function render_detail_view(): void {
        $reference = sanitize_text_field(wp_unslash($_GET['reference']));
        $rfq       = GH_RFQ_Domain::get($reference);

        if (!$rfq) {
            echo '<div class="notice notice-error"><p>' . esc_html__('Teklif talebi bulunamadi.', 'guvenhijyen') . '</p></div>';
            return;
        }

        $list_url = add_query_arg(['page' => 'gh-rfq'], admin_url('admin.php'));

        echo '<h1>';
        echo '<a href="' . esc_url($list_url) . '">&larr; ' . esc_html__('Teklif Talepleri', 'guvenhijyen') . '</a>';
        echo ' / ' . esc_html($rfq['reference']);
        echo '</h1>';

        if (isset($_GET['status_updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Durum guncellendi.', 'guvenhijyen') . '</p></div>';
        }

        echo '<div id="poststuff"><div id="post-body" class="metabox-holder columns-2">';

        echo '<div id="post-body-content">';

        echo '<div class="postbox">';
        echo '<h2 class="hndle">' . esc_html__('Musteri Bilgileri', 'guvenhijyen') . '</h2>';
        echo '<div class="inside">';
        echo '<table class="form-table">';
        self::detail_row(__('Referans', 'guvenhijyen'), $rfq['reference']);
        self::detail_row(__('Tip', 'guvenhijyen'), GH_RFQ_Domain::get_type_label($rfq['type']));
        self::detail_row(__('Firma', 'guvenhijyen'), $rfq['company']);
        self::detail_row(__('Yetkili', 'guvenhijyen'), $rfq['contact_name']);
        self::detail_row(__('Telefon', 'guvenhijyen'), $rfq['phone']);
        self::detail_row(__('E-posta', 'guvenhijyen'), $rfq['email']);
        if (!empty($rfq['sector'])) {
            self::detail_row(__('Sektor', 'guvenhijyen'), $rfq['sector']);
        }
        if (!empty($rfq['subject'])) {
            self::detail_row(__('Konu', 'guvenhijyen'), $rfq['subject']);
        }
        if (!empty($rfq['message'])) {
            self::detail_row(__('Mesaj', 'guvenhijyen'), nl2br(esc_html($rfq['message'])), false);
        }
        self::detail_row(__('KVKK Onay', 'guvenhijyen'), $rfq['kvkk_consent'] ? __('Evet', 'guvenhijyen') : __('Hayir', 'guvenhijyen'));
        self::detail_row(__('Pazarlama Onay', 'guvenhijyen'), $rfq['marketing_consent'] ? __('Evet', 'guvenhijyen') : __('Hayir', 'guvenhijyen'));
        self::detail_row(__('IP Adresi', 'guvenhijyen'), $rfq['ip_address']);

        $created = wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($rfq['created_at']));
        self::detail_row(__('Olusturma Tarihi', 'guvenhijyen'), $created);

        $updated = wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($rfq['updated_at']));
        self::detail_row(__('Son Guncelleme', 'guvenhijyen'), $updated);
        echo '</table>';
        echo '</div></div>';

        if (!empty($rfq['items'])) {
            echo '<div class="postbox">';
            echo '<h2 class="hndle">' . esc_html__('Urunler', 'guvenhijyen') . '</h2>';
            echo '<div class="inside">';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Urun', 'guvenhijyen') . '</th>';
            echo '<th>' . esc_html__('SKU', 'guvenhijyen') . '</th>';
            echo '<th>' . esc_html__('Varyant', 'guvenhijyen') . '</th>';
            echo '<th>' . esc_html__('Marka', 'guvenhijyen') . '</th>';
            echo '<th>' . esc_html__('Miktar', 'guvenhijyen') . '</th>';
            echo '<th>' . esc_html__('Birim', 'guvenhijyen') . '</th>';
            echo '<th>' . esc_html__('Tedarik', 'guvenhijyen') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($rfq['items'] as $item) {
                $snapshot = $item['snapshot'] ?? [];
                echo '<tr>';
                echo '<td>' . esc_html($snapshot['product_name'] ?? '') . '</td>';
                echo '<td>' . esc_html($snapshot['sku'] ?? '') . '</td>';
                echo '<td>' . esc_html($snapshot['variation'] ?? '-') . '</td>';
                echo '<td>' . esc_html($snapshot['verified_brand'] ?? '-') . '</td>';
                echo '<td>' . esc_html($item['quantity']) . '</td>';
                echo '<td>' . esc_html($snapshot['sales_unit_label'] ?? $item['sales_unit']) . '</td>';
                echo '<td>' . esc_html($snapshot['procurement_status'] ?? '-') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div></div>';
        }

        if (!empty($rfq['status_history'])) {
            echo '<div class="postbox">';
            echo '<h2 class="hndle">' . esc_html__('Durum Gecmisi', 'guvenhijyen') . '</h2>';
            echo '<div class="inside">';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Tarih', 'guvenhijyen') . '</th>';
            echo '<th>' . esc_html__('Eski Durum', 'guvenhijyen') . '</th>';
            echo '<th>' . esc_html__('Yeni Durum', 'guvenhijyen') . '</th>';
            echo '<th>' . esc_html__('Not', 'guvenhijyen') . '</th>';
            echo '<th>' . esc_html__('Degistiren', 'guvenhijyen') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($rfq['status_history'] as $history) {
                $date = wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($history['created_at']));
                echo '<tr>';
                echo '<td>' . esc_html($date) . '</td>';
                echo '<td>' . ($history['old_status'] ? esc_html(GH_RFQ_Domain::get_status_label($history['old_status'])) : '-') . '</td>';
                echo '<td>' . esc_html(GH_RFQ_Domain::get_status_label($history['new_status'])) . '</td>';
                echo '<td>' . esc_html($history['notes']) . '</td>';
                echo '<td>' . esc_html($history['changed_by_name'] ?? '') . '</td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '</div></div>';
        }

        echo '</div>';

        echo '<div id="postbox-container-1" class="postbox-container" style="width:280px;float:right;">';
        echo '<div class="postbox">';
        echo '<h2 class="hndle">' . esc_html__('Durum Guncelle', 'guvenhijyen') . '</h2>';
        echo '<div class="inside">';
        echo '<form method="post">';
        wp_nonce_field('gh_rfq_status_update_' . $rfq['reference'], '_wpnonce');
        echo '<input type="hidden" name="reference" value="' . esc_attr($rfq['reference']) . '">';

        echo '<p><label for="new_status"><strong>' . esc_html__('Yeni Durum:', 'guvenhijyen') . '</strong></label></p>';
        echo '<select name="new_status" id="new_status" class="widefat">';
        foreach (GH_RFQ_Domain::get_statuses() as $status) {
            $selected = selected($rfq['status'], $status, false);
            echo '<option value="' . esc_attr($status) . '"' . $selected . '>' . esc_html(GH_RFQ_Domain::get_status_label($status)) . '</option>';
        }
        echo '</select>';

        echo '<p><label for="status_notes"><strong>' . esc_html__('Not:', 'guvenhijyen') . '</strong></label></p>';
        echo '<textarea name="status_notes" id="status_notes" class="widefat" rows="3"></textarea>';

        echo '<p>';
        echo '<input type="submit" name="gh_rfq_status_update" class="button button-primary" value="' . esc_attr__('Guncelle', 'guvenhijyen') . '">';
        echo '</p>';
        echo '</form>';
        echo '</div></div>';

        echo '<div class="postbox">';
        echo '<h2 class="hndle">' . esc_html__('Mevcut Durum', 'guvenhijyen') . '</h2>';
        echo '<div class="inside">';
        $status_class = self::get_status_css_class($rfq['status']);
        echo '<p><span class="gh-rfq-status ' . esc_attr($status_class) . '" style="font-size:14px;padding:6px 12px;">' . esc_html(GH_RFQ_Domain::get_status_label($rfq['status'])) . '</span></p>';
        echo '</div></div>';

        echo '</div>';

        echo '</div></div>';
    }

    private static function render_filters(string $status, string $type, string $search): void {
        $base_url = add_query_arg(['page' => 'gh-rfq'], admin_url('admin.php'));

        echo '<ul class="subsubsub">';
        $all_class = empty($status) ? ' class="current"' : '';
        echo '<li><a href="' . esc_url($base_url) . '"' . $all_class . '>' . esc_html__('Tumu', 'guvenhijyen') . '</a> | </li>';

        foreach (GH_RFQ_Domain::get_statuses() as $s) {
            $is_current = ($status === $s) ? ' class="current"' : '';
            $url = add_query_arg('status', $s, $base_url);
            echo '<li><a href="' . esc_url($url) . '"' . $is_current . '>' . esc_html(GH_RFQ_Domain::get_status_label($s)) . '</a>';
            if ($s !== 'cancelled') {
                echo ' | ';
            }
            echo '</li>';
        }
        echo '</ul>';

        echo '<form method="get" class="search-box" style="float:right;margin:5px 0;">';
        echo '<input type="hidden" name="page" value="gh-rfq">';
        if ($status) {
            echo '<input type="hidden" name="status" value="' . esc_attr($status) . '">';
        }
        if ($type) {
            echo '<input type="hidden" name="type" value="' . esc_attr($type) . '">';
        }
        echo '<input type="search" name="s" value="' . esc_attr($search) . '" placeholder="' . esc_attr__('Ara...', 'guvenhijyen') . '">';
        echo '<input type="submit" class="button" value="' . esc_attr__('Ara', 'guvenhijyen') . '">';
        echo '</form>';

        echo '<div class="clear"></div>';
    }

    private static function render_pagination(int $current, int $total_pages, int $total): void {
        echo '<div class="tablenav-pages">';
        echo '<span class="displaying-num">' . esc_html(sprintf(__('%d kayit', 'guvenhijyen'), $total)) . '</span>';

        $page_links = paginate_links([
            'base'      => add_query_arg('paged', '%#%'),
            'format'    => '',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
            'total'     => $total_pages,
            'current'   => $current,
            'type'      => 'plain',
        ]);

        if ($page_links) {
            echo '<span class="pagination-links">' . $page_links . '</span>';
        }
        echo '</div>';
    }

    private static function handle_status_update(): void {
        $reference = sanitize_text_field(wp_unslash($_POST['reference'] ?? ''));
        if (empty($reference)) {
            return;
        }

        if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce']), 'gh_rfq_status_update_' . $reference)) {
            wp_die(esc_html__('Guvenlik dogrulamasi basarisiz.', 'guvenhijyen'));
        }

        if (!current_user_can('manage_gh_rfq')) {
            wp_die(esc_html__('Yetkiniz yok.', 'guvenhijyen'));
        }

        $new_status = sanitize_text_field(wp_unslash($_POST['new_status'] ?? ''));
        $notes      = sanitize_textarea_field(wp_unslash($_POST['status_notes'] ?? ''));

        GH_RFQ_Domain::update_status($reference, $new_status, $notes);

        wp_safe_redirect(add_query_arg([
            'page'           => 'gh-rfq',
            'view'           => 'detail',
            'reference'      => $reference,
            'status_updated' => 1,
        ], admin_url('admin.php')));
        exit;
    }

    private static function handle_bulk_action(): void {
        if (!wp_verify_nonce(wp_unslash($_POST['_wpnonce']), 'gh_rfq_bulk')) {
            wp_die(esc_html__('Guvenlik dogrulamasi basarisiz.', 'guvenhijyen'));
        }

        if (!current_user_can('manage_gh_rfq')) {
            wp_die(esc_html__('Yetkiniz yok.', 'guvenhijyen'));
        }

        $references = isset($_POST['references']) ? array_map('sanitize_text_field', wp_unslash($_POST['references'])) : [];
        $new_status = sanitize_text_field(wp_unslash($_POST['bulk_status'] ?? ''));

        if (empty($references) || empty($new_status)) {
            return;
        }

        $results = GH_RFQ_Domain::bulk_update_status($references, $new_status);
        $count   = count(array_filter($results, function ($r) { return $r['success']; }));

        wp_safe_redirect(add_query_arg([
            'page'         => 'gh-rfq',
            'bulk_updated' => $count,
        ], admin_url('admin.php')));
        exit;
    }

    private static function handle_export(): void {
        if (!current_user_can('export_gh_rfq')) {
            wp_die(esc_html__('Disari aktarma yetkiniz yok.', 'guvenhijyen'));
        }

        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(wp_unslash($_GET['_wpnonce']), 'gh_rfq_export')) {
            wp_die(esc_html__('Guvenlik dogrulamasi basarisiz.', 'guvenhijyen'));
        }

        $args = [
            'status' => isset($_GET['status']) ? sanitize_text_field(wp_unslash($_GET['status'])) : '',
            'type'   => isset($_GET['type']) ? sanitize_text_field(wp_unslash($_GET['type'])) : '',
            'search' => isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '',
        ];

        $export_data = GH_RFQ_Domain::export_rfqs($args);

        $filename = 'teklif-talepleri-' . wp_date('Y-m-d-His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, [
            __('Referans', 'guvenhijyen'),
            __('Tip', 'guvenhijyen'),
            __('Firma', 'guvenhijyen'),
            __('Yetkili', 'guvenhijyen'),
            __('Telefon', 'guvenhijyen'),
            __('E-posta', 'guvenhijyen'),
            __('Sektor', 'guvenhijyen'),
            __('Konu', 'guvenhijyen'),
            __('Mesaj', 'guvenhijyen'),
            __('Urunler', 'guvenhijyen'),
            __('Durum', 'guvenhijyen'),
            __('Tarih', 'guvenhijyen'),
        ], ';');

        foreach ($export_data as $row) {
            fputcsv($output, array_values($row), ';');
        }

        fclose($output);
        exit;
    }

    private static function detail_row(string $label, string $value, bool $escape = true): void {
        echo '<tr>';
        echo '<th scope="row">' . esc_html($label) . '</th>';
        echo '<td>' . ($escape ? esc_html($value) : $value) . '</td>';
        echo '</tr>';
    }

    private static function get_status_css_class(string $status): string {
        $map = [
            'new'            => 'gh-rfq-status--new',
            'reviewing'      => 'gh-rfq-status--reviewing',
            'quote_prepared' => 'gh-rfq-status--prepared',
            'sent'           => 'gh-rfq-status--sent',
            'closed'         => 'gh-rfq-status--closed',
            'cancelled'      => 'gh-rfq-status--cancelled',
        ];
        return $map[$status] ?? '';
    }
}

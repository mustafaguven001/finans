<?php

defined('ABSPATH') || exit;

class GH_RFQ_Domain {

    private static array $valid_statuses = [
        'new', 'reviewing', 'quote_prepared', 'sent', 'closed', 'cancelled',
    ];

    private static array $valid_types = [
        'general', 'quick_quote', 'quote_list',
    ];

    public static function init(): void {
        add_action('activate_guvenhijyen-core/guvenhijyen-core.php', [__CLASS__, 'create_tables']);
    }

    public static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $rfq_table       = $wpdb->prefix . 'gh_rfq';
        $items_table     = $wpdb->prefix . 'gh_rfq_items';
        $history_table   = $wpdb->prefix . 'gh_rfq_status_history';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql_rfq = "CREATE TABLE {$rfq_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            reference varchar(20) NOT NULL,
            idempotency_key varchar(64) DEFAULT NULL,
            type varchar(20) NOT NULL DEFAULT 'general',
            company varchar(255) NOT NULL,
            contact_name varchar(255) NOT NULL,
            phone varchar(30) NOT NULL,
            email varchar(255) NOT NULL,
            sector varchar(100) DEFAULT '',
            subject varchar(500) DEFAULT '',
            message longtext DEFAULT '',
            kvkk_consent tinyint(1) NOT NULL DEFAULT 1,
            marketing_consent tinyint(1) NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'new',
            ip_address varchar(45) DEFAULT '',
            user_agent varchar(500) DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY reference (reference),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY status (status),
            KEY type (type),
            KEY created_at (created_at),
            KEY email (email)
        ) {$charset_collate};";

        $sql_items = "CREATE TABLE {$items_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rfq_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            variation_id bigint(20) unsigned NOT NULL DEFAULT 0,
            quantity int(10) unsigned NOT NULL DEFAULT 1,
            sales_unit varchar(50) DEFAULT 'adet',
            snapshot longtext DEFAULT '',
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY rfq_id (rfq_id)
        ) {$charset_collate};";

        $sql_history = "CREATE TABLE {$history_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            rfq_id bigint(20) unsigned NOT NULL,
            old_status varchar(20) DEFAULT '',
            new_status varchar(20) NOT NULL,
            notes text DEFAULT '',
            changed_by bigint(20) unsigned DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY rfq_id (rfq_id)
        ) {$charset_collate};";

        dbDelta($sql_rfq);
        dbDelta($sql_items);
        dbDelta($sql_history);

        update_option('gh_rfq_db_version', '1.0.0');
    }

    public static function create(array $data): array {
        global $wpdb;

        $idempotency_key = sanitize_text_field($data['idempotency_key'] ?? '');
        if ($idempotency_key !== '') {
            $existing = self::find_by_idempotency_key($idempotency_key);
            if ($existing !== null) {
                return ['success' => true, 'reference' => $existing['reference'], 'duplicate' => true];
            }
        }

        $errors = self::validate($data);
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $type = sanitize_text_field($data['type'] ?? 'general');
        if (!in_array($type, self::$valid_types, true)) {
            $type = 'general';
        }

        $customer    = $data['customer'] ?? [];
        $company     = sanitize_text_field($customer['company'] ?? '');
        $contact     = sanitize_text_field($customer['contact_name'] ?? '');
        $phone       = sanitize_text_field($customer['phone'] ?? '');
        $email       = sanitize_email($customer['email'] ?? '');
        $sector      = sanitize_text_field($customer['sector'] ?? '');
        $subject     = sanitize_text_field($data['subject'] ?? '');
        $message     = sanitize_textarea_field($data['message'] ?? '');
        $kvkk        = !empty($data['consent']['kvkk']);
        $marketing   = !empty($data['consent']['marketing']);
        $ip_address  = self::get_client_ip();
        $user_agent  = isset($_SERVER['HTTP_USER_AGENT'])
            ? substr(sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])), 0, 500)
            : '';
        $now         = current_time('mysql', true);
        $reference   = self::generate_reference();

        $rfq_table = $wpdb->prefix . 'gh_rfq';

        $inserted = $wpdb->insert(
            $rfq_table,
            [
                'reference'         => $reference,
                'idempotency_key'   => $idempotency_key !== '' ? $idempotency_key : null,
                'type'              => $type,
                'company'           => $company,
                'contact_name'      => $contact,
                'phone'             => $phone,
                'email'             => $email,
                'sector'            => $sector,
                'subject'           => $subject,
                'message'           => $message,
                'kvkk_consent'      => $kvkk ? 1 : 0,
                'marketing_consent' => $marketing ? 1 : 0,
                'status'            => 'new',
                'ip_address'        => $ip_address,
                'user_agent'        => $user_agent,
                'created_at'        => $now,
                'updated_at'        => $now,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        if ($inserted === false) {
            if ($idempotency_key !== '' && $wpdb->last_error && strpos($wpdb->last_error, 'Duplicate') !== false) {
                $existing = self::find_by_idempotency_key($idempotency_key);
                if ($existing !== null) {
                    return ['success' => true, 'reference' => $existing['reference'], 'duplicate' => true];
                }
            }
            return ['success' => false, 'errors' => ['db' => __('Teklif talebi kaydedilemedi.', 'guvenhijyen')]];
        }

        $rfq_id = (int) $wpdb->insert_id;

        $items = $data['items'] ?? [];
        if ($type !== 'general' && !empty($items)) {
            self::insert_items($rfq_id, $items);
        }

        self::record_status_change($rfq_id, '', 'new', __('Teklif talebi olu??turuldu.', 'guvenhijyen'), 0);

        do_action('gh_rfq_created', $reference, $rfq_id, $data);

        return ['success' => true, 'reference' => $reference, 'duplicate' => false];
    }

    public static function get(string $reference): ?array {
        global $wpdb;

        $rfq_table   = $wpdb->prefix . 'gh_rfq';
        $items_table = $wpdb->prefix . 'gh_rfq_items';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$rfq_table} WHERE reference = %s", $reference),
            ARRAY_A
        );

        if ($row === null) {
            return null;
        }

        $items = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$items_table} WHERE rfq_id = %d ORDER BY id ASC", $row['id']),
            ARRAY_A
        );

        foreach ($items as &$item) {
            $item['snapshot'] = json_decode($item['snapshot'], true) ?: [];
        }
        unset($item);

        $row['items'] = $items;
        $row['status_history'] = self::get_status_history((int) $row['id']);

        return $row;
    }

    public static function update_status(string $reference, string $new_status, string $notes = ''): array {
        global $wpdb;

        if (!in_array($new_status, self::$valid_statuses, true)) {
            return ['success' => false, 'error' => __('Ge??ersiz durum.', 'guvenhijyen')];
        }

        $rfq_table = $wpdb->prefix . 'gh_rfq';

        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT id, status FROM {$rfq_table} WHERE reference = %s", $reference),
            ARRAY_A
        );

        if ($row === null) {
            return ['success' => false, 'error' => __('Teklif talebi bulunamad??.', 'guvenhijyen')];
        }

        $old_status = $row['status'];

        if ($old_status === $new_status) {
            return ['success' => true, 'changed' => false];
        }

        $updated = $wpdb->update(
            $rfq_table,
            [
                'status'     => $new_status,
                'updated_at' => current_time('mysql', true),
            ],
            ['id' => $row['id']],
            ['%s', '%s'],
            ['%d']
        );

        if ($updated === false) {
            return ['success' => false, 'error' => __('Durum g??ncellenemedi.', 'guvenhijyen')];
        }

        $changed_by = get_current_user_id();
        self::record_status_change((int) $row['id'], $old_status, $new_status, sanitize_textarea_field($notes), $changed_by);

        do_action('gh_rfq_status_changed', $reference, $old_status, $new_status, $notes);

        return ['success' => true, 'changed' => true, 'old_status' => $old_status, 'new_status' => $new_status];
    }

    public static function list_rfqs(array $args = []): array {
        global $wpdb;

        $rfq_table = $wpdb->prefix . 'gh_rfq';

        $defaults = [
            'per_page' => 20,
            'page'     => 1,
            'status'   => '',
            'type'     => '',
            'search'   => '',
            'orderby'  => 'created_at',
            'order'    => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $allowed_orderby = ['created_at', 'updated_at', 'reference', 'company', 'status'];
        $orderby = in_array($args['orderby'], $allowed_orderby, true) ? $args['orderby'] : 'created_at';
        $order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $where  = ['1=1'];
        $values = [];

        if ($args['status'] !== '' && in_array($args['status'], self::$valid_statuses, true)) {
            $where[]  = 'status = %s';
            $values[] = $args['status'];
        }

        if ($args['type'] !== '' && in_array($args['type'], self::$valid_types, true)) {
            $where[]  = 'type = %s';
            $values[] = $args['type'];
        }

        if ($args['search'] !== '') {
            $like     = '%' . $wpdb->esc_like(sanitize_text_field($args['search'])) . '%';
            $where[]  = '(reference LIKE %s OR company LIKE %s OR contact_name LIKE %s OR email LIKE %s)';
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
            $values[] = $like;
        }

        $where_clause = implode(' AND ', $where);

        $per_page = max(1, min(100, (int) $args['per_page']));
        $page     = max(1, (int) $args['page']);
        $offset   = ($page - 1) * $per_page;

        $count_sql = "SELECT COUNT(*) FROM {$rfq_table} WHERE {$where_clause}";
        if (!empty($values)) {
            $count_sql = $wpdb->prepare($count_sql, ...$values);
        }
        $total = (int) $wpdb->get_var($count_sql);

        $query = "SELECT * FROM {$rfq_table} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
        $query_values   = array_merge($values, [$per_page, $offset]);
        $results = $wpdb->get_results($wpdb->prepare($query, ...$query_values), ARRAY_A);

        if ($results) {
            $items_table = $wpdb->prefix . 'gh_rfq_items';
            $rfq_ids = array_column($results, 'id');
            $placeholders = implode(',', array_fill(0, count($rfq_ids), '%d'));
            $item_counts = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT rfq_id, COUNT(*) as item_count FROM {$items_table} WHERE rfq_id IN ({$placeholders}) GROUP BY rfq_id",
                    ...$rfq_ids
                ),
                OBJECT_K
            );

            foreach ($results as &$row) {
                $row['item_count'] = isset($item_counts[$row['id']]) ? (int) $item_counts[$row['id']]->item_count : 0;
            }
            unset($row);
        }

        return [
            'items'       => $results ?: [],
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => (int) ceil($total / $per_page),
        ];
    }

    public static function bulk_update_status(array $references, string $new_status, string $notes = ''): array {
        $results = [];
        foreach ($references as $reference) {
            $results[$reference] = self::update_status(sanitize_text_field($reference), $new_status, $notes);
        }
        return $results;
    }

    public static function get_statuses(): array {
        return self::$valid_statuses;
    }

    public static function get_status_label(string $status): string {
        $labels = [
            'new'            => __('Yeni', 'guvenhijyen'),
            'reviewing'      => __('??nceleniyor', 'guvenhijyen'),
            'quote_prepared' => __('Teklif Haz??rland??', 'guvenhijyen'),
            'sent'           => __('G??nderildi', 'guvenhijyen'),
            'closed'         => __('Kapand??', 'guvenhijyen'),
            'cancelled'      => __('??ptal Edildi', 'guvenhijyen'),
        ];
        return $labels[$status] ?? $status;
    }

    public static function get_type_label(string $type): string {
        $labels = [
            'general'     => __('Genel', 'guvenhijyen'),
            'quick_quote' => __('H??zl?? Teklif', 'guvenhijyen'),
            'quote_list'  => __('Teklif Listesi', 'guvenhijyen'),
        ];
        return $labels[$type] ?? $type;
    }

    private static function validate(array $data): array {
        $errors   = [];
        $customer = $data['customer'] ?? [];

        if (empty($customer['company'])) {
            $errors['company'] = __('Firma ad?? zorunludur.', 'guvenhijyen');
        }

        if (empty($customer['contact_name'])) {
            $errors['contact_name'] = __('Yetkili ad?? zorunludur.', 'guvenhijyen');
        }

        if (empty($customer['phone'])) {
            $errors['phone'] = __('Telefon numaras?? zorunludur.', 'guvenhijyen');
        } elseif (!self::validate_turkish_phone($customer['phone'])) {
            $errors['phone'] = __('Ge??erli bir telefon numaras?? giriniz.', 'guvenhijyen');
        }

        if (empty($customer['email'])) {
            $errors['email'] = __('E-posta adresi zorunludur.', 'guvenhijyen');
        } elseif (!is_email($customer['email'])) {
            $errors['email'] = __('Ge??erli bir e-posta adresi giriniz.', 'guvenhijyen');
        }

        if (empty($data['consent']['kvkk'])) {
            $errors['kvkk'] = __('KVKK onay?? zorunludur.', 'guvenhijyen');
        }

        $type  = $data['type'] ?? 'general';
        $items = $data['items'] ?? [];

        if ($type !== 'general' && !empty($items)) {
            foreach ($items as $index => $item) {
                $item_errors = self::validate_item($item, $index);
                if (!empty($item_errors)) {
                    $errors = array_merge($errors, $item_errors);
                }
            }
        }

        return $errors;
    }

    private static function validate_item(array $item, int $index): array {
        $errors     = [];
        $product_id = (int) ($item['product_id'] ?? 0);
        $prefix     = sprintf('items[%d]', $index);

        if ($product_id <= 0) {
            $errors[$prefix . '.product_id'] = __('Ge??ersiz ??r??n.', 'guvenhijyen');
            return $errors;
        }

        $product = wc_get_product($product_id);
        if (!$product || $product->get_status() !== 'publish') {
            $errors[$prefix . '.product_id'] = __('??r??n bulunamad?? veya yay??nda de??il.', 'guvenhijyen');
            return $errors;
        }

        $variation_id = (int) ($item['variation_id'] ?? 0);
        if ($product->is_type('variable')) {
            if ($variation_id <= 0) {
                $errors[$prefix . '.variation_id'] = __('De??i??ken ??r??n i??in varyant se??imi zorunludur.', 'guvenhijyen');
                return $errors;
            }
            $variation = wc_get_product($variation_id);
            if (!$variation || $variation->get_parent_id() !== $product_id) {
                $errors[$prefix . '.variation_id'] = __('Varyant bu ??r??ne ait de??il.', 'guvenhijyen');
                return $errors;
            }
        }

        $quantity = (int) ($item['quantity'] ?? 0);
        if ($quantity <= 0) {
            $errors[$prefix . '.quantity'] = __('Miktar 0\'dan b??y??k olmal??d??r.', 'guvenhijyen');
            return $errors;
        }

        $target = $variation_id > 0 ? wc_get_product($variation_id) : $product;
        if ($target) {
            $min_qty  = (int) $target->get_meta('_gh_min_order_quantity');
            $step_qty = (int) $target->get_meta('_gh_quantity_step');
            if ($min_qty > 0 && $quantity < $min_qty) {
                $errors[$prefix . '.quantity'] = sprintf(
                    __('Minimum sipari?? miktar??: %d', 'guvenhijyen'),
                    $min_qty
                );
            }
            if ($step_qty > 1 && ($quantity % $step_qty) !== 0) {
                $errors[$prefix . '.quantity'] = sprintf(
                    __('Miktar %d\'nin katlar?? olmal??d??r.', 'guvenhijyen'),
                    $step_qty
                );
            }
        }

        return $errors;
    }

    private static function insert_items(int $rfq_id, array $items): void {
        global $wpdb;

        $items_table = $wpdb->prefix . 'gh_rfq_items';
        $now         = current_time('mysql', true);

        foreach ($items as $item) {
            $product_id   = (int) ($item['product_id'] ?? 0);
            $variation_id = (int) ($item['variation_id'] ?? 0);
            $quantity     = max(1, (int) ($item['quantity'] ?? 1));

            $product = wc_get_product($product_id);
            if (!$product) {
                continue;
            }

            $target       = $variation_id > 0 ? wc_get_product($variation_id) : $product;
            $actual_target = $target ?: $product;

            $sales_unit_key   = $actual_target->get_meta('_gh_sales_unit') ?: 'adet';
            $sales_unit_label = self::resolve_sales_unit_label($sales_unit_key);

            $snapshot = [
                'product_name'       => $product->get_name(),
                'sku'                => $actual_target->get_sku(),
                'variation'          => '',
                'sales_unit_key'     => $sales_unit_key,
                'sales_unit_label'   => $sales_unit_label,
                'verified_brand'     => $product->get_meta('_gh_verified_brand') ?: '',
                'procurement_status' => $product->get_meta('_gh_procurement_status') ?: '',
            ];

            if ($variation_id > 0 && $target && $target->is_type('variation')) {
                $attributes = $target->get_variation_attributes();
                $parts      = [];
                foreach ($attributes as $attr_key => $attr_value) {
                    $taxonomy = str_replace('attribute_', '', $attr_key);
                    $label    = wc_attribute_label($taxonomy, $product);
                    $term     = get_term_by('slug', $attr_value, $taxonomy);
                    $value    = $term ? $term->name : $attr_value;
                    $parts[]  = $label . ': ' . $value;
                }
                $snapshot['variation'] = implode(', ', $parts);
            }

            $wpdb->insert(
                $items_table,
                [
                    'rfq_id'       => $rfq_id,
                    'product_id'   => $product_id,
                    'variation_id' => $variation_id,
                    'quantity'     => $quantity,
                    'sales_unit'   => $sales_unit_key,
                    'snapshot'     => wp_json_encode($snapshot, JSON_UNESCAPED_UNICODE),
                    'created_at'   => $now,
                ],
                ['%d', '%d', '%d', '%d', '%s', '%s', '%s']
            );
        }
    }

    private static function resolve_sales_unit_label(string $key): string {
        $units = [
            'adet'   => __('Adet', 'guvenhijyen'),
            'paket'  => __('Paket', 'guvenhijyen'),
            'koli'   => __('Koli', 'guvenhijyen'),
            'kutu'   => __('Kutu', 'guvenhijyen'),
            'kg'     => __('Kilogram', 'guvenhijyen'),
            'litre'  => __('Litre', 'guvenhijyen'),
            'metre'  => __('Metre', 'guvenhijyen'),
            'top'    => __('Top', 'guvenhijyen'),
            'rulo'   => __('Rulo', 'guvenhijyen'),
            'palet'  => __('Palet', 'guvenhijyen'),
            'cift'   => __('??ift', 'guvenhijyen'),
            'torba'  => __('Torba', 'guvenhijyen'),
            'galon'  => __('Galon', 'guvenhijyen'),
            'bidon'  => __('Bidon', 'guvenhijyen'),
            'varil'  => __('Varil', 'guvenhijyen'),
        ];

        if (isset($units[$key])) {
            return $units[$key];
        }

        if (class_exists('GH_Sales_Unit')) {
            $all_units = GH_Sales_Unit::get_all_units();
            if (isset($all_units[$key])) {
                return $all_units[$key];
            }
        }

        return $key;
    }

    private static function generate_reference(): string {
        global $wpdb;

        $rfq_table = $wpdb->prefix . 'gh_rfq';
        $max_attempts = 10;

        for ($i = 0; $i < $max_attempts; $i++) {
            $random    = strtoupper(wp_generate_password(6, false, false));
            $reference = 'GH-RFQ-' . $random;

            $exists = $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$rfq_table} WHERE reference = %s", $reference)
            );

            if ((int) $exists === 0) {
                return $reference;
            }
        }

        $reference = 'GH-RFQ-' . strtoupper(wp_generate_password(8, false, false));
        return $reference;
    }

    private static function find_by_idempotency_key(string $key): ?array {
        global $wpdb;

        $rfq_table = $wpdb->prefix . 'gh_rfq';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT id, reference FROM {$rfq_table} WHERE idempotency_key = %s", $key),
            ARRAY_A
        );
    }

    private static function record_status_change(int $rfq_id, string $old_status, string $new_status, string $notes, int $changed_by): void {
        global $wpdb;

        $history_table = $wpdb->prefix . 'gh_rfq_status_history';

        $wpdb->insert(
            $history_table,
            [
                'rfq_id'     => $rfq_id,
                'old_status' => $old_status,
                'new_status' => $new_status,
                'notes'      => $notes,
                'changed_by' => $changed_by,
                'created_at' => current_time('mysql', true),
            ],
            ['%d', '%s', '%s', '%s', '%d', '%s']
        );
    }

    private static function get_status_history(int $rfq_id): array {
        global $wpdb;

        $history_table = $wpdb->prefix . 'gh_rfq_status_history';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$history_table} WHERE rfq_id = %d ORDER BY created_at ASC",
                $rfq_id
            ),
            ARRAY_A
        );

        if (!$rows) {
            return [];
        }

        foreach ($rows as &$row) {
            if ((int) $row['changed_by'] > 0) {
                $user = get_userdata((int) $row['changed_by']);
                $row['changed_by_name'] = $user ? $user->display_name : __('Bilinmeyen', 'guvenhijyen');
            } else {
                $row['changed_by_name'] = __('Sistem', 'guvenhijyen');
            }
        }
        unset($row);

        return $rows;
    }

    private static function validate_turkish_phone(string $phone): bool {
        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (preg_match('/^90[1-9][0-9]{9}$/', $digits)) {
            return true;
        }

        if (preg_match('/^0[1-9][0-9]{9}$/', $digits)) {
            return true;
        }

        if (preg_match('/^[1-9][0-9]{9}$/', $digits)) {
            return true;
        }

        return false;
    }

    private static function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    public static function get_rfq_id_by_reference(string $reference): int {
        global $wpdb;

        $rfq_table = $wpdb->prefix . 'gh_rfq';

        $id = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM {$rfq_table} WHERE reference = %s", $reference)
        );

        return $id ? (int) $id : 0;
    }

    public static function export_rfqs(array $args = []): array {
        $args['per_page'] = 10000;
        $args['page']     = 1;
        $result           = self::list_rfqs($args);

        $export_items = [];
        foreach ($result['items'] as $rfq) {
            $full = self::get($rfq['reference']);
            $items_text = '';
            if ($full && !empty($full['items'])) {
                $parts = [];
                foreach ($full['items'] as $item) {
                    $snapshot = $item['snapshot'] ?? [];
                    $name     = $snapshot['product_name'] ?? '';
                    $sku      = $snapshot['sku'] ?? '';
                    $qty      = $item['quantity'];
                    $unit     = $snapshot['sales_unit_label'] ?? $item['sales_unit'];
                    $parts[]  = "{$name} (SKU: {$sku}) - {$qty} {$unit}";
                }
                $items_text = implode(' | ', $parts);
            }

            $export_items[] = [
                'reference'    => $rfq['reference'],
                'type'         => self::get_type_label($rfq['type']),
                'company'      => $rfq['company'],
                'contact_name' => $rfq['contact_name'],
                'phone'        => $rfq['phone'],
                'email'        => $rfq['email'],
                'sector'       => $rfq['sector'],
                'subject'      => $rfq['subject'],
                'message'      => $rfq['message'],
                'items'        => $items_text,
                'status'       => self::get_status_label($rfq['status']),
                'created_at'   => $rfq['created_at'],
            ];
        }

        return $export_items;
    }
}

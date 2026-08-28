<?php

defined('ABSPATH') || exit;

class GH_Document_System {

    public const TYPES = [
        'catalog'            => 'Catalog',
        'technical_datasheet'=> 'Technical Datasheet',
        'safety_datasheet'   => 'Safety Datasheet',
        'user_manual'        => 'User Manual',
        'certificate'        => 'Certificate',
        'authorization'      => 'Authorization',
        'declaration'        => 'Declaration',
        'other'              => 'Other',
    ];

    public const STATE_ACTIVE   = 'active';
    public const STATE_ARCHIVED = 'archived';

    private const META_REVISIONS    = '_gh_doc_revisions';
    private const META_TYPE         = '_gh_doc_type';
    private const META_STATE        = '_gh_doc_state';
    private const META_RELATED      = '_gh_doc_related';

    public static function init(): void {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_gh_document', [__CLASS__, 'save_meta']);
        add_filter('manage_gh_document_posts_columns', [__CLASS__, 'admin_columns']);
        add_action('manage_gh_document_posts_custom_column', [__CLASS__, 'render_admin_column'], 10, 2);
    }

    public static function get_document_types(): array {
        $translated = [];
        foreach (self::TYPES as $key => $label) {
            $translated[$key] = __($label, 'guvenhijyen');
        }
        return $translated;
    }

    public static function get_current_revision(int $document_id): ?array {
        $revisions = get_post_meta($document_id, self::META_REVISIONS, true);
        if (!is_array($revisions)) {
            return null;
        }
        foreach ($revisions as $rev) {
            if (!empty($rev['is_current'])) {
                return $rev;
            }
        }
        return null;
    }

    public static function get_all_revisions(int $document_id): array {
        $revisions = get_post_meta($document_id, self::META_REVISIONS, true);
        return is_array($revisions) ? $revisions : [];
    }

    public static function add_revision(int $document_id, array $data): bool {
        $revisions = self::get_all_revisions($document_id);

        $required = ['attachment_id', 'version'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }

        if (!empty($data['is_current'])) {
            $revisions = array_map(static function (array $rev): array {
                $rev['is_current'] = false;
                return $rev;
            }, $revisions);
        }

        $revisions[] = [
            'attachment_id' => absint($data['attachment_id']),
            'version'       => sanitize_text_field($data['version']),
            'document_date' => sanitize_text_field($data['document_date'] ?? ''),
            'revision_date' => sanitize_text_field($data['revision_date'] ?? ''),
            'document_code' => sanitize_text_field($data['document_code'] ?? ''),
            'revision_code' => sanitize_text_field($data['revision_code'] ?? ''),
            'uploaded_at'   => current_time('mysql'),
            'is_current'    => !empty($data['is_current']),
        ];

        return (bool) update_post_meta($document_id, self::META_REVISIONS, $revisions);
    }

    public static function set_related(int $document_id, array $related): void {
        $sanitized = [
            'products'   => array_map('absint', $related['products'] ?? []),
            'categories' => array_map('absint', $related['categories'] ?? []),
            'brands'     => array_map('absint', $related['brands'] ?? []),
            'sectors'    => array_map('absint', $related['sectors'] ?? []),
        ];
        update_post_meta($document_id, self::META_RELATED, $sanitized);
    }

    public static function get_related(int $document_id): array {
        $related = get_post_meta($document_id, self::META_RELATED, true);
        if (!is_array($related)) {
            return ['products' => [], 'categories' => [], 'brands' => [], 'sectors' => []];
        }
        return $related;
    }

    public static function has_current_revision(int $document_id): bool {
        return self::get_current_revision($document_id) !== null;
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'gh_doc_details',
            __('Document Details', 'guvenhijyen'),
            [__CLASS__, 'render_details_meta_box'],
            'gh_document',
            'normal',
            'high'
        );
        add_meta_box(
            'gh_doc_revisions',
            __('Revisions', 'guvenhijyen'),
            [__CLASS__, 'render_revisions_meta_box'],
            'gh_document',
            'normal',
            'default'
        );
        add_meta_box(
            'gh_doc_relations',
            __('Related Items', 'guvenhijyen'),
            [__CLASS__, 'render_relations_meta_box'],
            'gh_document',
            'side',
            'default'
        );
    }

    public static function render_details_meta_box(\WP_Post $post): void {
        wp_nonce_field('gh_document_save', 'gh_document_nonce');
        $doc_type = get_post_meta($post->ID, self::META_TYPE, true);
        $state    = get_post_meta($post->ID, self::META_STATE, true) ?: self::STATE_ACTIVE;
        $types    = self::get_document_types();
        ?>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e('Document Type', 'guvenhijyen'); ?></label></th>
                <td>
                    <select name="gh_doc_type">
                        <?php foreach ($types as $val => $label): ?>
                            <option value="<?php echo esc_attr($val); ?>" <?php selected($doc_type, $val); ?>>
                                <?php echo esc_html($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('State', 'guvenhijyen'); ?></label></th>
                <td>
                    <select name="gh_doc_state">
                        <option value="<?php echo esc_attr(self::STATE_ACTIVE); ?>" <?php selected($state, self::STATE_ACTIVE); ?>>
                            <?php esc_html_e('Active', 'guvenhijyen'); ?>
                        </option>
                        <option value="<?php echo esc_attr(self::STATE_ARCHIVED); ?>" <?php selected($state, self::STATE_ARCHIVED); ?>>
                            <?php esc_html_e('Archived', 'guvenhijyen'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function render_revisions_meta_box(\WP_Post $post): void {
        $revisions = self::get_all_revisions($post->ID);
        ?>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e('Version', 'guvenhijyen'); ?></th>
                    <th><?php esc_html_e('Document Code', 'guvenhijyen'); ?></th>
                    <th><?php esc_html_e('Date', 'guvenhijyen'); ?></th>
                    <th><?php esc_html_e('Uploaded', 'guvenhijyen'); ?></th>
                    <th><?php esc_html_e('Current', 'guvenhijyen'); ?></th>
                    <th><?php esc_html_e('File', 'guvenhijyen'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($revisions)): ?>
                    <tr><td colspan="6"><?php esc_html_e('No revisions yet.', 'guvenhijyen'); ?></td></tr>
                <?php else: ?>
                    <?php foreach ($revisions as $rev): ?>
                        <tr<?php echo !empty($rev['is_current']) ? ' style="background:#e8f5e9"' : ''; ?>>
                            <td><?php echo esc_html($rev['version']); ?></td>
                            <td><?php echo esc_html($rev['document_code'] ?? ''); ?></td>
                            <td><?php echo esc_html($rev['document_date'] ?? ''); ?></td>
                            <td><?php echo esc_html($rev['uploaded_at'] ?? ''); ?></td>
                            <td><?php echo !empty($rev['is_current']) ? '&#10003;' : ''; ?></td>
                            <td>
                                <?php
                                $url = wp_get_attachment_url((int) $rev['attachment_id']);
                                if ($url) {
                                    printf('<a href="%s" target="_blank">%s</a>', esc_url($url), esc_html__('View', 'guvenhijyen'));
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <h4><?php esc_html_e('Add New Revision', 'guvenhijyen'); ?></h4>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e('Attachment ID', 'guvenhijyen'); ?></label></th>
                <td><input type="number" name="gh_rev_attachment_id" class="small-text" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('Version', 'guvenhijyen'); ?></label></th>
                <td><input type="text" name="gh_rev_version" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('Document Date', 'guvenhijyen'); ?></label></th>
                <td><input type="date" name="gh_rev_document_date" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('Revision Date', 'guvenhijyen'); ?></label></th>
                <td><input type="date" name="gh_rev_revision_date" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('Document Code', 'guvenhijyen'); ?></label></th>
                <td><input type="text" name="gh_rev_document_code" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('Revision Code', 'guvenhijyen'); ?></label></th>
                <td><input type="text" name="gh_rev_revision_code" class="regular-text" /></td>
            </tr>
            <tr>
                <th><label><?php esc_html_e('Set as Current', 'guvenhijyen'); ?></label></th>
                <td><input type="checkbox" name="gh_rev_is_current" value="1" checked /></td>
            </tr>
        </table>
        <?php
    }

    public static function render_relations_meta_box(\WP_Post $post): void {
        $related = self::get_related($post->ID);
        ?>
        <p>
            <label><?php esc_html_e('Product IDs (comma-separated)', 'guvenhijyen'); ?></label><br />
            <input type="text" name="gh_rel_products" value="<?php echo esc_attr(implode(',', $related['products'])); ?>" class="widefat" />
        </p>
        <p>
            <label><?php esc_html_e('Category IDs (comma-separated)', 'guvenhijyen'); ?></label><br />
            <input type="text" name="gh_rel_categories" value="<?php echo esc_attr(implode(',', $related['categories'])); ?>" class="widefat" />
        </p>
        <p>
            <label><?php esc_html_e('Brand IDs (comma-separated)', 'guvenhijyen'); ?></label><br />
            <input type="text" name="gh_rel_brands" value="<?php echo esc_attr(implode(',', $related['brands'])); ?>" class="widefat" />
        </p>
        <p>
            <label><?php esc_html_e('Sector IDs (comma-separated)', 'guvenhijyen'); ?></label><br />
            <input type="text" name="gh_rel_sectors" value="<?php echo esc_attr(implode(',', $related['sectors'])); ?>" class="widefat" />
        </p>
        <?php
    }

    public static function save_meta(int $post_id): void {
        if (!isset($_POST['gh_document_nonce']) ||
            !wp_verify_nonce(sanitize_key($_POST['gh_document_nonce']), 'gh_document_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $doc_type = sanitize_text_field(wp_unslash($_POST['gh_doc_type'] ?? ''));
        if (array_key_exists($doc_type, self::TYPES)) {
            update_post_meta($post_id, self::META_TYPE, $doc_type);
        }

        $state = sanitize_text_field(wp_unslash($_POST['gh_doc_state'] ?? ''));
        if (in_array($state, [self::STATE_ACTIVE, self::STATE_ARCHIVED], true)) {
            update_post_meta($post_id, self::META_STATE, $state);
        }

        $attachment_id = absint($_POST['gh_rev_attachment_id'] ?? 0);
        $version       = sanitize_text_field(wp_unslash($_POST['gh_rev_version'] ?? ''));
        if ($attachment_id && $version) {
            self::add_revision($post_id, [
                'attachment_id' => $attachment_id,
                'version'       => $version,
                'document_date' => sanitize_text_field(wp_unslash($_POST['gh_rev_document_date'] ?? '')),
                'revision_date' => sanitize_text_field(wp_unslash($_POST['gh_rev_revision_date'] ?? '')),
                'document_code' => sanitize_text_field(wp_unslash($_POST['gh_rev_document_code'] ?? '')),
                'revision_code' => sanitize_text_field(wp_unslash($_POST['gh_rev_revision_code'] ?? '')),
                'is_current'    => !empty($_POST['gh_rev_is_current']),
            ]);
        }

        $parse_ids = static fn(string $input): array => array_filter(array_map('absint', explode(',', $input)));
        self::set_related($post_id, [
            'products'   => $parse_ids(sanitize_text_field(wp_unslash($_POST['gh_rel_products'] ?? ''))),
            'categories' => $parse_ids(sanitize_text_field(wp_unslash($_POST['gh_rel_categories'] ?? ''))),
            'brands'     => $parse_ids(sanitize_text_field(wp_unslash($_POST['gh_rel_brands'] ?? ''))),
            'sectors'    => $parse_ids(sanitize_text_field(wp_unslash($_POST['gh_rel_sectors'] ?? ''))),
        ]);
    }

    public static function admin_columns(array $columns): array {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['gh_doc_type']     = __('Type', 'guvenhijyen');
                $new['gh_doc_state']    = __('State', 'guvenhijyen');
                $new['gh_doc_revision'] = __('Current Rev.', 'guvenhijyen');
            }
        }
        return $new;
    }

    public static function render_admin_column(string $column, int $post_id): void {
        switch ($column) {
            case 'gh_doc_type':
                $type = get_post_meta($post_id, self::META_TYPE, true);
                $types = self::get_document_types();
                echo esc_html($types[$type] ?? $type);
                break;

            case 'gh_doc_state':
                $state = get_post_meta($post_id, self::META_STATE, true) ?: self::STATE_ACTIVE;
                $color = $state === self::STATE_ACTIVE ? '#00a32a' : '#999';
                printf(
                    '<mark style="background:%s;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px">%s</mark>',
                    esc_attr($color),
                    esc_html(ucfirst($state))
                );
                break;

            case 'gh_doc_revision':
                $rev = self::get_current_revision($post_id);
                if ($rev) {
                    echo esc_html($rev['version']);
                } else {
                    echo '<span style="color:#d63638">' . esc_html__('None', 'guvenhijyen') . '</span>';
                }
                break;
        }
    }
}

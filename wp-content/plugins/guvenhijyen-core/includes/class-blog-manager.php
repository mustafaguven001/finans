<?php
/**
 * Blog/Knowledge Center Manager for Güven Hijyen.
 *
 * Uses native WordPress Posts with quality state meta to gate
 * public visibility. Only posts that are both post_status=publish
 * AND content_quality_status=approved appear on the frontend.
 *
 * Does NOT generate fake authors or fake freshness dates.
 */

defined('ABSPATH') || exit;

class GH_Blog_Manager {

    public const QUALITY_UNREVIEWED   = 'unreviewed';
    public const QUALITY_NEEDS_REWRITE = 'needs_rewrite';
    public const QUALITY_APPROVED     = 'approved';

    private const META_QUALITY       = '_gh_content_quality_status';
    private const META_RELATED_PRODUCTS   = '_gh_related_products';
    private const META_RELATED_CATEGORIES = '_gh_related_categories';
    private const META_RELATED_SECTORS    = '_gh_related_sectors';
    private const META_RELATED_DOCUMENTS  = '_gh_related_documents';

    public static function init(): void {
        add_action('add_meta_boxes', [__CLASS__, 'add_meta_boxes']);
        add_action('save_post_post', [__CLASS__, 'save_meta_boxes']);
        add_action('pre_get_posts', [__CLASS__, 'filter_frontend_posts']);
        add_filter('manage_posts_columns', [__CLASS__, 'add_admin_columns']);
        add_action('manage_posts_custom_column', [__CLASS__, 'render_admin_columns'], 10, 2);
        add_filter('manage_edit-post_sortable_columns', [__CLASS__, 'sortable_columns']);
        add_action('pre_get_posts', [__CLASS__, 'sort_by_quality']);
    }

    // =====================================================================
    // Quality Status
    // =====================================================================

    /**
     * Get the list of quality statuses.
     */
    public static function get_quality_statuses(): array {
        return [
            self::QUALITY_UNREVIEWED    => __('Unreviewed', 'guvenhijyen'),
            self::QUALITY_NEEDS_REWRITE => __('Needs Rewrite', 'guvenhijyen'),
            self::QUALITY_APPROVED      => __('Approved', 'guvenhijyen'),
        ];
    }

    /**
     * Get the quality status for a post.
     */
    public static function get_quality_status(int $post_id): string {
        $status = get_post_meta($post_id, self::META_QUALITY, true);
        return array_key_exists($status, self::get_quality_statuses()) ? $status : self::QUALITY_UNREVIEWED;
    }

    /**
     * Set the quality status for a post.
     */
    public static function set_quality_status(int $post_id, string $status): bool {
        if (!array_key_exists($status, self::get_quality_statuses())) {
            return false;
        }
        return (bool) update_post_meta($post_id, self::META_QUALITY, $status);
    }

    /**
     * Check if a post is visible on the frontend.
     * Requires post_status=publish AND content_quality_status=approved.
     */
    public static function is_publicly_visible(int $post_id): bool {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post') {
            return false;
        }
        if ($post->post_status !== 'publish') {
            return false;
        }
        return self::get_quality_status($post_id) === self::QUALITY_APPROVED;
    }

    // =====================================================================
    // Relationships
    // =====================================================================

    /**
     * Get related product SKUs for a post.
     */
    public static function get_related_products(int $post_id): array {
        $skus = get_post_meta($post_id, self::META_RELATED_PRODUCTS, true);
        return is_array($skus) ? $skus : [];
    }

    /**
     * Set related product SKUs for a post.
     *
     * @param int   $post_id Post ID.
     * @param array $skus    Array of product SKU strings.
     */
    public static function set_related_products(int $post_id, array $skus): bool {
        $skus = array_map('sanitize_text_field', $skus);
        $skus = array_values(array_filter($skus));
        return (bool) update_post_meta($post_id, self::META_RELATED_PRODUCTS, $skus);
    }

    /**
     * Get related category names for a post.
     */
    public static function get_related_categories(int $post_id): array {
        $categories = get_post_meta($post_id, self::META_RELATED_CATEGORIES, true);
        return is_array($categories) ? $categories : [];
    }

    /**
     * Set related category names for a post.
     */
    public static function set_related_categories(int $post_id, array $categories): bool {
        $categories = array_map('sanitize_text_field', $categories);
        $categories = array_values(array_filter($categories));
        return (bool) update_post_meta($post_id, self::META_RELATED_CATEGORIES, $categories);
    }

    /**
     * Get related sector names for a post.
     */
    public static function get_related_sectors(int $post_id): array {
        $sectors = get_post_meta($post_id, self::META_RELATED_SECTORS, true);
        return is_array($sectors) ? $sectors : [];
    }

    /**
     * Set related sector names for a post.
     */
    public static function set_related_sectors(int $post_id, array $sectors): bool {
        $sectors = array_map('sanitize_text_field', $sectors);
        $sectors = array_values(array_filter($sectors));
        return (bool) update_post_meta($post_id, self::META_RELATED_SECTORS, $sectors);
    }

    /**
     * Get related document keys for a post.
     */
    public static function get_related_documents(int $post_id): array {
        $docs = get_post_meta($post_id, self::META_RELATED_DOCUMENTS, true);
        return is_array($docs) ? $docs : [];
    }

    /**
     * Set related document keys for a post.
     */
    public static function set_related_documents(int $post_id, array $document_keys): bool {
        $document_keys = array_map('sanitize_text_field', $document_keys);
        $document_keys = array_values(array_filter($document_keys));
        return (bool) update_post_meta($post_id, self::META_RELATED_DOCUMENTS, $document_keys);
    }

    /**
     * Resolve related product SKUs to WooCommerce product IDs.
     * Returns array of [post_id => sku] for products that exist and are published.
     */
    public static function resolve_related_products(int $post_id): array {
        $skus = self::get_related_products($post_id);
        if (empty($skus)) {
            return [];
        }

        $resolved = [];
        foreach ($skus as $sku) {
            $product_id = wc_get_product_id_by_sku($sku);
            if ($product_id > 0 && get_post_status($product_id) === 'publish') {
                $resolved[$product_id] = $sku;
            }
        }

        return $resolved;
    }

    // =====================================================================
    // Frontend Filtering
    // =====================================================================

    /**
     * Filter pre_get_posts to exclude non-approved posts from frontend.
     */
    public static function filter_frontend_posts(\WP_Query $query): void {
        if (is_admin() || !$query->is_main_query()) {
            return;
        }

        // Apply to blog archive, category archives, tag archives, search (posts only), and author archives.
        $dominated_queries = $query->is_home()
            || $query->is_category()
            || $query->is_tag()
            || $query->is_author()
            || ($query->is_search() && ($query->get('post_type') === 'post' || empty($query->get('post_type'))));

        if (!$dominated_queries) {
            return;
        }

        // Only show posts with quality status = approved.
        $meta_query = $query->get('meta_query') ?: [];
        $meta_query[] = [
            'key'     => self::META_QUALITY,
            'value'   => self::QUALITY_APPROVED,
            'compare' => '=',
        ];

        $query->set('meta_query', $meta_query);
    }

    // =====================================================================
    // Admin Meta Boxes
    // =====================================================================

    /**
     * Register meta boxes for blog posts.
     */
    public static function add_meta_boxes(): void {
        add_meta_box(
            'gh_content_quality',
            __('Content Quality', 'guvenhijyen'),
            [__CLASS__, 'render_quality_meta_box'],
            'post',
            'side',
            'high'
        );

        add_meta_box(
            'gh_blog_relationships',
            __('Related Content', 'guvenhijyen'),
            [__CLASS__, 'render_relationships_meta_box'],
            'post',
            'normal',
            'default'
        );
    }

    /**
     * Render the quality status meta box.
     */
    public static function render_quality_meta_box(\WP_Post $post): void {
        wp_nonce_field('gh_blog_meta_save', 'gh_blog_meta_nonce');
        $current  = self::get_quality_status($post->ID);
        $statuses = self::get_quality_statuses();
        $colors   = [
            self::QUALITY_UNREVIEWED    => '#dba617',
            self::QUALITY_NEEDS_REWRITE => '#d63638',
            self::QUALITY_APPROVED      => '#00a32a',
        ];

        echo '<select name="gh_content_quality_status" style="width:100%">';
        foreach ($statuses as $value => $label) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr($value),
                selected($current, $value, false),
                esc_html($label)
            );
        }
        echo '</select>';

        printf(
            '<p style="margin-top:8px"><mark style="background:%s;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px">%s</mark></p>',
            esc_attr($colors[$current] ?? '#999'),
            esc_html($statuses[$current] ?? $current)
        );

        if ($post->post_status === 'publish' && $current !== self::QUALITY_APPROVED) {
            echo '<p style="color:#d63638;margin-top:8px">';
            esc_html_e('This post is published but NOT approved. It will NOT appear on the frontend.', 'guvenhijyen');
            echo '</p>';
        }
    }

    /**
     * Render the relationships meta box.
     */
    public static function render_relationships_meta_box(\WP_Post $post): void {
        $related_products   = self::get_related_products($post->ID);
        $related_categories = self::get_related_categories($post->ID);
        $related_sectors    = self::get_related_sectors($post->ID);
        $related_documents  = self::get_related_documents($post->ID);

        ?>
        <table class="form-table">
            <tr>
                <th>
                    <label for="gh_related_products">
                        <?php esc_html_e('Related Product SKUs', 'guvenhijyen'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" name="gh_related_products" id="gh_related_products"
                           class="large-text"
                           value="<?php echo esc_attr(implode(', ', $related_products)); ?>"
                           placeholder="GH-DET-BM-020, GH-DET-EL-005" />
                    <p class="description">
                        <?php esc_html_e('Comma-separated product SKUs.', 'guvenhijyen'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="gh_related_categories">
                        <?php esc_html_e('Related Categories', 'guvenhijyen'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" name="gh_related_categories" id="gh_related_categories"
                           class="large-text"
                           value="<?php echo esc_attr(implode(', ', $related_categories)); ?>"
                           placeholder="Mutfak Hijyeni, Bulaşık Makinesi Kimyasalları" />
                    <p class="description">
                        <?php esc_html_e('Comma-separated product category names.', 'guvenhijyen'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="gh_related_sectors">
                        <?php esc_html_e('Related Sectors', 'guvenhijyen'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" name="gh_related_sectors" id="gh_related_sectors"
                           class="large-text"
                           value="<?php echo esc_attr(implode(', ', $related_sectors)); ?>"
                           placeholder="Otel ve Konaklama, Hastane ve Sağlık" />
                    <p class="description">
                        <?php esc_html_e('Comma-separated sector names.', 'guvenhijyen'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th>
                    <label for="gh_related_documents">
                        <?php esc_html_e('Related Documents', 'guvenhijyen'); ?>
                    </label>
                </th>
                <td>
                    <input type="text" name="gh_related_documents" id="gh_related_documents"
                           class="large-text"
                           value="<?php echo esc_attr(implode(', ', $related_documents)); ?>"
                           placeholder="DOC-001, DOC-015" />
                    <p class="description">
                        <?php esc_html_e('Comma-separated document keys.', 'guvenhijyen'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save meta box data.
     */
    public static function save_meta_boxes(int $post_id): void {
        if (!isset($_POST['gh_blog_meta_nonce']) ||
            !wp_verify_nonce(sanitize_key($_POST['gh_blog_meta_nonce']), 'gh_blog_meta_save')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Quality status.
        $quality = sanitize_text_field(wp_unslash($_POST['gh_content_quality_status'] ?? ''));
        self::set_quality_status($post_id, $quality);

        // Related products.
        $products_raw = sanitize_text_field(wp_unslash($_POST['gh_related_products'] ?? ''));
        $products = self::parse_comma_list($products_raw);
        self::set_related_products($post_id, $products);

        // Related categories.
        $categories_raw = sanitize_text_field(wp_unslash($_POST['gh_related_categories'] ?? ''));
        $categories = self::parse_comma_list($categories_raw);
        self::set_related_categories($post_id, $categories);

        // Related sectors.
        $sectors_raw = sanitize_text_field(wp_unslash($_POST['gh_related_sectors'] ?? ''));
        $sectors = self::parse_comma_list($sectors_raw);
        self::set_related_sectors($post_id, $sectors);

        // Related documents.
        $documents_raw = sanitize_text_field(wp_unslash($_POST['gh_related_documents'] ?? ''));
        $documents = self::parse_comma_list($documents_raw);
        self::set_related_documents($post_id, $documents);
    }

    // =====================================================================
    // Admin Columns
    // =====================================================================

    /**
     * Add quality status column to the posts admin list.
     */
    public static function add_admin_columns(array $columns): array {
        $screen = get_current_screen();
        if ($screen && $screen->post_type !== 'post') {
            return $columns;
        }

        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['gh_quality'] = __('Quality', 'guvenhijyen');
            }
        }
        if (!isset($new['gh_quality'])) {
            $new['gh_quality'] = __('Quality', 'guvenhijyen');
        }
        return $new;
    }

    /**
     * Render the quality status column.
     */
    public static function render_admin_columns(string $column, int $post_id): void {
        if ($column !== 'gh_quality') {
            return;
        }

        // Only render for posts, not other post types.
        if (get_post_type($post_id) !== 'post') {
            return;
        }

        $status   = self::get_quality_status($post_id);
        $statuses = self::get_quality_statuses();
        $colors   = [
            self::QUALITY_UNREVIEWED    => '#dba617',
            self::QUALITY_NEEDS_REWRITE => '#d63638',
            self::QUALITY_APPROVED      => '#00a32a',
        ];

        printf(
            '<mark style="background:%s;color:#fff;padding:2px 8px;border-radius:3px;font-size:12px">%s</mark>',
            esc_attr($colors[$status] ?? '#999'),
            esc_html($statuses[$status] ?? $status)
        );

        // Warning if published but not approved.
        $post = get_post($post_id);
        if ($post && $post->post_status === 'publish' && $status !== self::QUALITY_APPROVED) {
            echo '<br><small style="color:#d63638">';
            esc_html_e('Not visible on frontend', 'guvenhijyen');
            echo '</small>';
        }
    }

    /**
     * Make the quality column sortable.
     */
    public static function sortable_columns(array $columns): array {
        $columns['gh_quality'] = 'gh_quality';
        return $columns;
    }

    /**
     * Handle sorting by quality status in admin.
     */
    public static function sort_by_quality(\WP_Query $query): void {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }
        if ($query->get('orderby') === 'gh_quality') {
            $query->set('meta_key', self::META_QUALITY);
            $query->set('orderby', 'meta_value');
        }
    }

    // =====================================================================
    // Utility
    // =====================================================================

    /**
     * Parse a comma-separated string into an array of trimmed, non-empty values.
     */
    private static function parse_comma_list(string $input): array {
        if (empty($input)) {
            return [];
        }
        $items = explode(',', $input);
        $items = array_map('trim', $items);
        return array_values(array_filter($items, static fn(string $s): bool => $s !== ''));
    }
}

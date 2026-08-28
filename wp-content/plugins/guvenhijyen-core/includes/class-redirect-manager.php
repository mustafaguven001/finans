<?php
/**
 * Redirect Manager for Güven Hijyen.
 *
 * Manages URL redirects stored in a custom database table.
 * Handles 301 (permanent), 302 (temporary), and 410 (gone) redirects.
 * Detects and prevents chains, loops, and collisions.
 */

defined('ABSPATH') || exit;

class GH_Redirect_Manager {

    private const TABLE_SUFFIX = 'gh_redirects';

    private const TYPE_301 = 301;
    private const TYPE_302 = 302;
    private const TYPE_410 = 410;

    /**
     * Legacy WooCommerce URLs that receive special handling.
     * These are NOT blanket homepage redirects.
     */
    private const LEGACY_URLS = [
        '/magaza/'   => ['target' => '/urunler/', 'type' => 301, 'notes' => 'Legacy WooCommerce shop page -> product archive'],
        '/sepetim/'  => ['target' => '',           'type' => 410, 'notes' => 'Legacy WooCommerce cart page (no longer exists)'],
        '/checkout/' => ['target' => '',           'type' => 410, 'notes' => 'Legacy WooCommerce checkout page (no longer exists)'],
        '/hesabim/'  => ['target' => '',           'type' => 410, 'notes' => 'Legacy WooCommerce my-account page (no longer exists)'],
    ];

    private static bool $tables_checked = false;

    public static function init(): void {
        add_action('template_redirect', [__CLASS__, 'handle_redirect'], 1);
        add_action('admin_menu', [__CLASS__, 'register_admin_page']);

        register_activation_hook(GH_CORE_FILE, [__CLASS__, 'create_table']);
    }

    // =====================================================================
    // Database Table
    // =====================================================================

    /**
     * Get the full table name.
     */
    private static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    /**
     * Create the redirects table.
     */
    public static function create_table(): void {
        global $wpdb;

        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_url varchar(2048) NOT NULL,
            target_url varchar(2048) NOT NULL DEFAULT '',
            redirect_type smallint(3) unsigned NOT NULL DEFAULT 301,
            hit_count bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            notes text DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_url (source_url(191)),
            KEY redirect_type (redirect_type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Ensure the table exists.
     */
    private static function ensure_table(): void {
        if (self::$tables_checked) {
            return;
        }
        global $wpdb;
        $table = self::table_name();
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) {
            self::create_table();
        }
        self::$tables_checked = true;
    }

    // =====================================================================
    // Redirect Handling
    // =====================================================================

    /**
     * Handle redirects on template_redirect (priority 1 for early execution).
     */
    public static function handle_redirect(): void {
        if (is_admin()) {
            return;
        }

        $request_path = self::normalize_path(wp_unslash($_SERVER['REQUEST_URI'] ?? ''));

        if (empty($request_path) || $request_path === '/') {
            return;
        }

        // Check legacy URLs first (hardcoded for reliability).
        if (isset(self::LEGACY_URLS[$request_path])) {
            $legacy = self::LEGACY_URLS[$request_path];
            self::increment_hit_count($request_path);

            if ($legacy['type'] === 410) {
                status_header(410);
                nocache_headers();
                // Serve a minimal 410 Gone response.
                if (file_exists(get_stylesheet_directory() . '/410.php')) {
                    include get_stylesheet_directory() . '/410.php';
                } else {
                    wp_die(
                        esc_html__('This page no longer exists.', 'guvenhijyen'),
                        esc_html__('Gone', 'guvenhijyen'),
                        ['response' => 410]
                    );
                }
                exit;
            }

            wp_redirect(home_url($legacy['target']), $legacy['type']);
            exit;
        }

        // Check database redirects.
        $redirect = self::find_redirect($request_path);

        if (!$redirect) {
            return;
        }

        self::increment_hit_count($request_path);

        $type = (int) $redirect['redirect_type'];

        if ($type === 410) {
            status_header(410);
            nocache_headers();
            if (file_exists(get_stylesheet_directory() . '/410.php')) {
                include get_stylesheet_directory() . '/410.php';
            } else {
                wp_die(
                    esc_html__('This page no longer exists.', 'guvenhijyen'),
                    esc_html__('Gone', 'guvenhijyen'),
                    ['response' => 410]
                );
            }
            exit;
        }

        $target = $redirect['target_url'];
        if (!empty($target)) {
            // If target is a relative URL, prepend home_url.
            if (strpos($target, '/') === 0) {
                $target = home_url($target);
            }
            wp_redirect($target, $type);
            exit;
        }
    }

    /**
     * Find a redirect for the given path.
     */
    public static function find_redirect(string $path): ?array {
        global $wpdb;
        self::ensure_table();

        $path = self::normalize_path($path);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE source_url = %s LIMIT 1",
                self::table_name(),
                $path
            ),
            ARRAY_A
        );

        return $row ?: null;
    }

    /**
     * Increment the hit counter for a redirect.
     */
    private static function increment_hit_count(string $path): void {
        global $wpdb;
        self::ensure_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE %i SET hit_count = hit_count + 1 WHERE source_url = %s",
                self::table_name(),
                self::normalize_path($path)
            )
        );
    }

    // =====================================================================
    // CRUD Operations
    // =====================================================================

    /**
     * Add a redirect.
     *
     * @return int|false The redirect ID on success, false on failure.
     */
    public static function add_redirect(string $source, string $target, int $type = 301, string $notes = '') {
        global $wpdb;
        self::ensure_table();

        $source = self::normalize_path($source);
        $target = ($type === 410) ? '' : self::normalize_path($target);

        if (!in_array($type, [301, 302, 410], true)) {
            return false;
        }

        // Validate: no self-redirect.
        if ($type !== 410 && $source === $target) {
            return false;
        }

        // Validate: no chain.
        if ($type !== 410 && self::would_create_chain($source, $target)) {
            return false;
        }

        // Validate: no loop.
        if ($type !== 410 && self::would_create_loop($source, $target)) {
            return false;
        }

        // Validate: no collision with existing WordPress URL.
        if (self::collides_with_wp_url($source)) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $inserted = $wpdb->insert(
            self::table_name(),
            [
                'source_url'    => $source,
                'target_url'    => $target,
                'redirect_type' => $type,
                'notes'         => sanitize_text_field($notes),
            ],
            ['%s', '%s', '%d', '%s']
        );

        return $inserted ? (int) $wpdb->insert_id : false;
    }

    /**
     * Update an existing redirect.
     */
    public static function update_redirect(int $id, array $data): bool {
        global $wpdb;
        self::ensure_table();

        $allowed = ['source_url', 'target_url', 'redirect_type', 'notes'];
        $update  = [];
        $formats = [];

        foreach ($allowed as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            if ($key === 'redirect_type') {
                $type = (int) $data[$key];
                if (!in_array($type, [301, 302, 410], true)) {
                    return false;
                }
                $update[$key] = $type;
                $formats[]    = '%d';
            } elseif ($key === 'source_url' || $key === 'target_url') {
                $update[$key] = self::normalize_path($data[$key]);
                $formats[]    = '%s';
            } else {
                $update[$key] = sanitize_text_field($data[$key]);
                $formats[]    = '%s';
            }
        }

        if (empty($update)) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            self::table_name(),
            $update,
            ['id' => $id],
            $formats,
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Delete a redirect by ID.
     */
    public static function delete_redirect(int $id): bool {
        global $wpdb;
        self::ensure_table();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            self::table_name(),
            ['id' => $id],
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Get all redirects with pagination.
     */
    public static function get_redirects(int $page = 1, int $per_page = 50, ?string $search = null): array {
        global $wpdb;
        self::ensure_table();

        $table  = self::table_name();
        $offset = ($page - 1) * $per_page;

        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i WHERE source_url LIKE %s OR target_url LIKE %s OR notes LIKE %s ORDER BY hit_count DESC, created_at DESC LIMIT %d OFFSET %d",
                    $table,
                    $like,
                    $like,
                    $like,
                    $per_page,
                    $offset
                ),
                ARRAY_A
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM %i WHERE source_url LIKE %s OR target_url LIKE %s OR notes LIKE %s",
                    $table,
                    $like,
                    $like,
                    $like
                )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM %i ORDER BY hit_count DESC, created_at DESC LIMIT %d OFFSET %d",
                    $table,
                    $per_page,
                    $offset
                ),
                ARRAY_A
            );
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
            $total = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM %i", $table)
            );
        }

        return [
            'rows'     => $rows ?: [],
            'total'    => $total,
            'page'     => $page,
            'per_page' => $per_page,
            'pages'    => (int) ceil($total / $per_page),
        ];
    }

    // =====================================================================
    // Import from XLSX
    // =====================================================================

    /**
     * Import redirects from parsed XLSX rows.
     *
     * @param array  $rows      Array of ['source_url', 'target_url', 'redirect_type', 'notes'].
     * @param string $import_id Import ID for error reporting.
     * @return array Counts: created, skipped, failed.
     */
    public static function import_redirects(array $rows, string $import_id = ''): array {
        $counts = ['created' => 0, 'skipped' => 0, 'failed' => 0];

        foreach ($rows as $i => $row) {
            $source = self::normalize_path($row['source_url'] ?? '');
            $target = self::normalize_path($row['target_url'] ?? '');
            $type   = (int) ($row['redirect_type'] ?? 301);
            $notes  = $row['notes'] ?? '';

            // Validate source.
            if (empty($source)) {
                self::log_import_error($import_id, $i, 'source_url', 'E_REQUIRED_FIELD', 'source_url is empty.');
                $counts['failed']++;
                continue;
            }

            // Validate type.
            if (!in_array($type, [301, 302, 410], true)) {
                self::log_import_error($import_id, $i, 'redirect_type', 'E_INVALID_ENUM', "Invalid redirect_type: {$type}. Must be 301, 302, or 410.");
                $counts['failed']++;
                continue;
            }

            // Validate target for non-410.
            if ($type !== 410 && empty($target)) {
                self::log_import_error($import_id, $i, 'target_url', 'E_REQUIRED_FIELD', 'target_url is required for 301/302 redirects.');
                $counts['failed']++;
                continue;
            }

            // Check for existing redirect with same source.
            $existing = self::find_redirect($source);
            if ($existing) {
                $counts['skipped']++;
                continue;
            }

            // Validate: no chain.
            if ($type !== 410 && self::would_create_chain($source, $target)) {
                self::log_import_error($import_id, $i, 'target_url', 'E_REDIRECT_CHAIN', "Target URL '{$target}' is itself a redirect source. Update to point to the final destination.");
                $counts['failed']++;
                continue;
            }

            // Validate: no loop.
            if ($type !== 410 && self::would_create_loop($source, $target)) {
                self::log_import_error($import_id, $i, 'target_url', 'E_REDIRECT_LOOP', "Redirect from '{$source}' to '{$target}' would create a loop.");
                $counts['failed']++;
                continue;
            }

            // Validate: no collision.
            if (self::collides_with_wp_url($source)) {
                self::log_import_error($import_id, $i, 'source_url', 'E_REDIRECT_COLLISION', "Source URL '{$source}' matches an existing WordPress URL.");
                $counts['failed']++;
                continue;
            }

            $result = self::add_redirect($source, $target, $type, $notes);
            if ($result) {
                $counts['created']++;
            } else {
                $counts['failed']++;
            }
        }

        return $counts;
    }

    // =====================================================================
    // Validation Helpers
    // =====================================================================

    /**
     * Check if adding a redirect from $source to $target would create a chain
     * (target is itself a source of another redirect).
     */
    public static function would_create_chain(string $source, string $target): bool {
        $target = self::normalize_path($target);
        $existing = self::find_redirect($target);
        return $existing !== null;
    }

    /**
     * Check if adding a redirect from $source to $target would create a loop.
     * Follows the redirect chain up to 10 hops to detect cycles.
     */
    public static function would_create_loop(string $source, string $target, int $max_hops = 10): bool {
        $source   = self::normalize_path($source);
        $current  = self::normalize_path($target);
        $visited  = [$source => true];
        $hops     = 0;

        while ($hops < $max_hops) {
            if (isset($visited[$current])) {
                return true;
            }

            $visited[$current] = true;
            $next = self::find_redirect($current);

            if (!$next || (int) $next['redirect_type'] === 410) {
                break;
            }

            $current = self::normalize_path($next['target_url']);
            $hops++;
        }

        return false;
    }

    /**
     * Check if the source URL collides with an existing live WordPress URL.
     * Checks posts, pages, and product slugs.
     */
    public static function collides_with_wp_url(string $source): bool {
        $source = self::normalize_path($source);

        // Remove leading/trailing slashes for slug comparison.
        $slug = trim($source, '/');

        // Skip empty or root.
        if (empty($slug)) {
            return true;
        }

        // Check if a published post/page/product exists at this URL.
        $post_id = url_to_postid(home_url($source));
        if ($post_id > 0) {
            $post = get_post($post_id);
            if ($post && $post->post_status === 'publish') {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate a redirect target URL does not point to a noindex page.
     * Returns true if the target appears to be noindex.
     */
    public static function target_is_noindex(string $target_url): bool {
        $post_id = url_to_postid(home_url($target_url));
        if ($post_id <= 0) {
            return false;
        }

        // Check Yoast SEO noindex.
        $yoast_noindex = get_post_meta($post_id, '_yoast_wpseo_meta-robots-noindex', true);
        if ($yoast_noindex === '1') {
            return true;
        }

        // Check Rank Math noindex.
        $rank_robots = get_post_meta($post_id, 'rank_math_robots', true);
        if (is_array($rank_robots) && in_array('noindex', $rank_robots, true)) {
            return true;
        }

        return false;
    }

    // =====================================================================
    // Admin Page
    // =====================================================================

    /**
     * Register the admin page under the Güven Hijyen menu.
     */
    public static function register_admin_page(): void {
        add_submenu_page(
            'guvenhijyen',
            __('Redirects', 'guvenhijyen'),
            __('Redirects', 'guvenhijyen'),
            'manage_options',
            'gh-redirects',
            [__CLASS__, 'render_admin_page']
        );
    }

    /**
     * Render the redirects admin page.
     */
    public static function render_admin_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle form actions.
        self::handle_admin_actions();

        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : null;
        $page   = max(1, absint($_GET['paged'] ?? 1));
        $data   = self::get_redirects($page, 50, $search);
        $type_labels = [
            301 => __('301 Permanent', 'guvenhijyen'),
            302 => __('302 Temporary', 'guvenhijyen'),
            410 => __('410 Gone', 'guvenhijyen'),
        ];

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e('Redirects', 'guvenhijyen'); ?></h1>

            <form method="get">
                <input type="hidden" name="page" value="gh-redirects" />
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr($search ?? ''); ?>"
                           placeholder="<?php esc_attr_e('Search redirects...', 'guvenhijyen'); ?>" />
                    <input type="submit" class="button" value="<?php esc_attr_e('Search', 'guvenhijyen'); ?>" />
                </p>
            </form>

            <h2><?php esc_html_e('Add New Redirect', 'guvenhijyen'); ?></h2>

            <form method="post">
                <?php wp_nonce_field('gh_redirect_add', 'gh_redirect_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="source_url"><?php esc_html_e('Source URL', 'guvenhijyen'); ?></label></th>
                        <td><input type="text" name="source_url" id="source_url" class="regular-text"
                                   placeholder="/eski-sayfa/" required /></td>
                    </tr>
                    <tr>
                        <th><label for="target_url"><?php esc_html_e('Target URL', 'guvenhijyen'); ?></label></th>
                        <td><input type="text" name="target_url" id="target_url" class="regular-text"
                                   placeholder="/yeni-sayfa/" /></td>
                    </tr>
                    <tr>
                        <th><label for="redirect_type"><?php esc_html_e('Type', 'guvenhijyen'); ?></label></th>
                        <td>
                            <select name="redirect_type" id="redirect_type">
                                <option value="301"><?php echo esc_html($type_labels[301]); ?></option>
                                <option value="302"><?php echo esc_html($type_labels[302]); ?></option>
                                <option value="410"><?php echo esc_html($type_labels[410]); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="notes"><?php esc_html_e('Notes', 'guvenhijyen'); ?></label></th>
                        <td><input type="text" name="notes" id="notes" class="large-text" /></td>
                    </tr>
                </table>
                <p><input type="submit" name="gh_add_redirect" class="button button-primary"
                          value="<?php esc_attr_e('Add Redirect', 'guvenhijyen'); ?>" /></p>
            </form>

            <h2><?php esc_html_e('Existing Redirects', 'guvenhijyen'); ?>
                <span class="count">(<?php echo esc_html($data['total']); ?>)</span>
            </h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Source URL', 'guvenhijyen'); ?></th>
                        <th><?php esc_html_e('Target URL', 'guvenhijyen'); ?></th>
                        <th><?php esc_html_e('Type', 'guvenhijyen'); ?></th>
                        <th><?php esc_html_e('Hits', 'guvenhijyen'); ?></th>
                        <th><?php esc_html_e('Created', 'guvenhijyen'); ?></th>
                        <th><?php esc_html_e('Notes', 'guvenhijyen'); ?></th>
                        <th><?php esc_html_e('Actions', 'guvenhijyen'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['rows'])): ?>
                        <tr>
                            <td colspan="7"><?php esc_html_e('No redirects found.', 'guvenhijyen'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['rows'] as $row): ?>
                            <tr>
                                <td><code><?php echo esc_html($row['source_url']); ?></code></td>
                                <td>
                                    <?php if ((int) $row['redirect_type'] === 410): ?>
                                        <em><?php esc_html_e('(gone)', 'guvenhijyen'); ?></em>
                                    <?php else: ?>
                                        <code><?php echo esc_html($row['target_url']); ?></code>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($type_labels[(int) $row['redirect_type']] ?? $row['redirect_type']); ?></td>
                                <td><?php echo esc_html(number_format_i18n($row['hit_count'])); ?></td>
                                <td><?php echo esc_html($row['created_at']); ?></td>
                                <td><?php echo esc_html($row['notes']); ?></td>
                                <td>
                                    <form method="post" style="display:inline">
                                        <?php wp_nonce_field('gh_redirect_delete_' . $row['id'], 'gh_redirect_delete_nonce'); ?>
                                        <input type="hidden" name="redirect_id" value="<?php echo esc_attr($row['id']); ?>" />
                                        <button type="submit" name="gh_delete_redirect" class="button button-link-delete"
                                                onclick="return confirm('<?php esc_attr_e('Delete this redirect?', 'guvenhijyen'); ?>')">
                                            <?php esc_html_e('Delete', 'guvenhijyen'); ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <?php if ($data['pages'] > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base'    => add_query_arg('paged', '%#%'),
                            'format'  => '',
                            'current' => $data['page'],
                            'total'   => $data['pages'],
                        ]);
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <h3><?php esc_html_e('Legacy URL Handling', 'guvenhijyen'); ?></h3>
            <p><?php esc_html_e('The following legacy WooCommerce URLs are handled automatically:', 'guvenhijyen'); ?></p>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('URL', 'guvenhijyen'); ?></th>
                        <th><?php esc_html_e('Action', 'guvenhijyen'); ?></th>
                        <th><?php esc_html_e('Notes', 'guvenhijyen'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (self::LEGACY_URLS as $url => $cfg): ?>
                        <tr>
                            <td><code><?php echo esc_html($url); ?></code></td>
                            <td>
                                <?php if ($cfg['type'] === 410): ?>
                                    <?php echo esc_html($type_labels[410]); ?>
                                <?php else: ?>
                                    <?php echo esc_html($type_labels[$cfg['type']]); ?>
                                    &rarr; <code><?php echo esc_html($cfg['target']); ?></code>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($cfg['notes']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Handle admin form submissions (add/delete).
     */
    private static function handle_admin_actions(): void {
        // Add redirect.
        if (isset($_POST['gh_add_redirect'])) {
            if (!check_admin_referer('gh_redirect_add', 'gh_redirect_nonce')) {
                return;
            }

            $source = sanitize_text_field(wp_unslash($_POST['source_url'] ?? ''));
            $target = sanitize_text_field(wp_unslash($_POST['target_url'] ?? ''));
            $type   = absint($_POST['redirect_type'] ?? 301);
            $notes  = sanitize_text_field(wp_unslash($_POST['notes'] ?? ''));

            $result = self::add_redirect($source, $target, $type, $notes);

            if ($result) {
                add_settings_error('gh_redirects', 'redirect_added', __('Redirect added.', 'guvenhijyen'), 'success');
            } else {
                add_settings_error('gh_redirects', 'redirect_failed', __('Failed to add redirect. Check for chains, loops, or collisions.', 'guvenhijyen'), 'error');
            }

            settings_errors('gh_redirects');
        }

        // Delete redirect.
        if (isset($_POST['gh_delete_redirect'])) {
            $id = absint($_POST['redirect_id'] ?? 0);
            if (!check_admin_referer('gh_redirect_delete_' . $id, 'gh_redirect_delete_nonce')) {
                return;
            }

            if (self::delete_redirect($id)) {
                add_settings_error('gh_redirects', 'redirect_deleted', __('Redirect deleted.', 'guvenhijyen'), 'success');
            } else {
                add_settings_error('gh_redirects', 'redirect_delete_failed', __('Failed to delete redirect.', 'guvenhijyen'), 'error');
            }

            settings_errors('gh_redirects');
        }
    }

    // =====================================================================
    // Utility
    // =====================================================================

    /**
     * Normalize a URL path: ensure leading slash, lowercase, remove query string.
     */
    private static function normalize_path(string $path): string {
        // Parse URL to extract path only.
        $parsed = wp_parse_url($path);
        $path   = $parsed['path'] ?? '/';

        // Ensure leading slash.
        if (strpos($path, '/') !== 0) {
            $path = '/' . $path;
        }

        // Lowercase.
        $path = mb_strtolower($path, 'UTF-8');

        // Ensure trailing slash for consistency (unless it has a file extension).
        if (!preg_match('/\.\w{2,4}$/', $path) && substr($path, -1) !== '/') {
            $path .= '/';
        }

        return $path;
    }

    /**
     * Log an import error if the error reporting system is available.
     */
    private static function log_import_error(string $import_id, int $row, string $field, string $code, string $message): void {
        if (empty($import_id) || !class_exists('GH_Import_Error_Report')) {
            return;
        }

        GH_Import_Error_Report::add_error($import_id, [
            'sheet_name'         => '14_REDIRECTS',
            'row_number'         => $row + 2, // +2 for header row + 0-indexing.
            'field'              => $field,
            'error_code'         => $code,
            'message'            => $message,
            'recommended_action' => 'Review and correct the redirect data in the XLSX file.',
            'severity'           => 'error',
        ]);
    }
}

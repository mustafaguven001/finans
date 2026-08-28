<?php

defined('ABSPATH') || exit;

class GH_RFQ_REST_API {

    private static string $namespace = 'guvenhijyen/v1';

    public static function init(): void {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void {
        register_rest_route(self::$namespace, '/rfq/submit', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [__CLASS__, 'handle_submit'],
            'permission_callback' => '__return_true',
            'args'                => self::get_submit_args(),
        ]);

        register_rest_route(self::$namespace, '/rfq/(?P<reference>[A-Z0-9\-]+)', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_get'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
            'args'                => [
                'reference' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function ($value) {
                        return (bool) preg_match('/^GH-RFQ-[A-Z0-9]+$/', $value);
                    },
                ],
            ],
        ]);

        register_rest_route(self::$namespace, '/rfq/(?P<reference>[A-Z0-9\-]+)/status', [
            'methods'             => WP_REST_Server::EDITABLE,
            'callback'            => [__CLASS__, 'handle_status_update'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
            'args'                => [
                'reference' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'status' => [
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'notes' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_textarea_field',
                ],
            ],
        ]);

        register_rest_route(self::$namespace, '/rfq/list', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [__CLASS__, 'handle_list'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
            'args'                => [
                'page' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 1,
                    'sanitize_callback' => 'absint',
                ],
                'per_page' => [
                    'required'          => false,
                    'type'              => 'integer',
                    'default'           => 20,
                    'sanitize_callback' => 'absint',
                ],
                'status' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'type' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'search' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => '',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'orderby' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'created_at',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
                'order' => [
                    'required'          => false,
                    'type'              => 'string',
                    'default'           => 'DESC',
                    'sanitize_callback' => 'sanitize_text_field',
                ],
            ],
        ]);
    }

    public static function handle_submit(WP_REST_Request $request): WP_REST_Response {
        $rate_check = self::check_rate_limit();
        if (is_wp_error($rate_check)) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => 'rate_limit_exceeded',
                'message' => $rate_check->get_error_message(),
            ], 429);
        }

        $honeypot = $request->get_param('website_url');
        if (!empty($honeypot)) {
            return new WP_REST_Response([
                'success' => true,
                'reference' => 'GH-RFQ-000000',
            ], 200);
        }

        $nonce = $request->get_header('X-WP-Nonce') ?: $request->get_param('_wpnonce');
        if (!$nonce || !wp_verify_nonce($nonce, 'wp_rest')) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => 'invalid_nonce',
                'message' => __('Guvenlik dogrulamasi basarisiz.', 'guvenhijyen'),
            ], 403);
        }

        $data = [
            'type'            => $request->get_param('type'),
            'idempotency_key' => $request->get_param('idempotency_key'),
            'customer'        => $request->get_param('customer'),
            'subject'         => $request->get_param('subject'),
            'message'         => $request->get_param('message'),
            'consent'         => $request->get_param('consent'),
            'items'           => $request->get_param('items'),
        ];

        $result = GH_RFQ_Domain::create($data);

        if (!$result['success']) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => 'validation_error',
                'errors'  => $result['errors'],
            ], 422);
        }

        self::increment_rate_limit();

        return new WP_REST_Response([
            'success'   => true,
            'reference' => $result['reference'],
            'duplicate' => $result['duplicate'] ?? false,
        ], 201);
    }

    public static function handle_get(WP_REST_Request $request): WP_REST_Response {
        $reference = $request->get_param('reference');
        $rfq       = GH_RFQ_Domain::get($reference);

        if ($rfq === null) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => 'not_found',
                'message' => __('Teklif talebi bulunamadi.', 'guvenhijyen'),
            ], 404);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => $rfq,
        ], 200);
    }

    public static function handle_status_update(WP_REST_Request $request): WP_REST_Response {
        $reference  = $request->get_param('reference');
        $new_status = $request->get_param('status');
        $notes      = $request->get_param('notes');

        $result = GH_RFQ_Domain::update_status($reference, $new_status, $notes);

        if (!$result['success']) {
            return new WP_REST_Response([
                'success' => false,
                'code'    => 'update_failed',
                'message' => $result['error'],
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'data'    => $result,
        ], 200);
    }

    public static function handle_list(WP_REST_Request $request): WP_REST_Response {
        $args = [
            'page'     => $request->get_param('page'),
            'per_page' => $request->get_param('per_page'),
            'status'   => $request->get_param('status'),
            'type'     => $request->get_param('type'),
            'search'   => $request->get_param('search'),
            'orderby'  => $request->get_param('orderby'),
            'order'    => $request->get_param('order'),
        ];

        $result = GH_RFQ_Domain::list_rfqs($args);

        return new WP_REST_Response([
            'success' => true,
            'data'    => $result,
        ], 200);
    }

    public static function check_admin_permission(): bool {
        return current_user_can('manage_gh_rfq') || current_user_can('manage_options');
    }

    private static function get_submit_args(): array {
        return [
            'type' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'validate_callback' => function ($value) {
                    return in_array($value, ['general', 'quick_quote', 'quote_list'], true);
                },
            ],
            'idempotency_key' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'customer' => [
                'required'          => true,
                'type'              => 'object',
                'properties'        => [
                    'company'      => ['type' => 'string', 'required' => true],
                    'contact_name' => ['type' => 'string', 'required' => true],
                    'phone'        => ['type' => 'string', 'required' => true],
                    'email'        => ['type' => 'string', 'required' => true, 'format' => 'email'],
                    'sector'       => ['type' => 'string', 'required' => false],
                ],
            ],
            'subject' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'message' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'consent' => [
                'required'          => true,
                'type'              => 'object',
                'properties'        => [
                    'kvkk'      => ['type' => 'boolean', 'required' => true],
                    'marketing' => ['type' => 'boolean', 'required' => false],
                ],
            ],
            'items' => [
                'required'          => false,
                'type'              => 'array',
                'default'           => [],
                'items'             => [
                    'type'       => 'object',
                    'properties' => [
                        'product_id'   => ['type' => 'integer', 'required' => true],
                        'variation_id' => ['type' => 'integer', 'required' => false, 'default' => 0],
                        'quantity'     => ['type' => 'integer', 'required' => true, 'minimum' => 1],
                        'sales_unit'   => ['type' => 'string', 'required' => false, 'default' => 'adet'],
                    ],
                ],
            ],
            'website_url' => [
                'required' => false,
                'type'     => 'string',
                'default'  => '',
            ],
            '_wpnonce' => [
                'required' => false,
                'type'     => 'string',
            ],
        ];
    }

    private static function get_rate_limit_key(): string {
        $ip = '';
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = sanitize_text_field(wp_unslash($_SERVER[$header]));
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                break;
            }
        }
        return 'gh_rfq_rate_' . md5($ip);
    }

    private static function check_rate_limit(): true|WP_Error {
        $key   = self::get_rate_limit_key();
        $count = (int) get_transient($key);

        if ($count >= 5) {
            return new WP_Error(
                'rate_limit_exceeded',
                __('Cok fazla istek gonderdiniz. Lutfen daha sonra tekrar deneyiniz.', 'guvenhijyen')
            );
        }

        return true;
    }

    private static function increment_rate_limit(): void {
        $key   = self::get_rate_limit_key();
        $count = (int) get_transient($key);
        set_transient($key, $count + 1, HOUR_IN_SECONDS);
    }
}

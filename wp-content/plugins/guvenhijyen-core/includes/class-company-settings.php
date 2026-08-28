<?php

defined('ABSPATH') || exit;

class GH_Company_Settings {

    private static string $option_group = 'gh_company_settings';
    private static string $option_name = 'gh_company_info';

    private static array $fields = [
        'company_name' => ['type' => 'text',     'label' => 'Company Name'],
        'phone'        => ['type' => 'text',     'label' => 'Phone'],
        'whatsapp'     => ['type' => 'text',     'label' => 'WhatsApp'],
        'email'        => ['type' => 'email',    'label' => 'Email'],
        'address'      => ['type' => 'textarea', 'label' => 'Address'],
        'district'     => ['type' => 'text',     'label' => 'District'],
        'city'         => ['type' => 'text',     'label' => 'City'],
        'map_url'      => ['type' => 'url',      'label' => 'Map URL'],
        'instagram_url'=> ['type' => 'url',      'label' => 'Instagram URL'],
        'facebook_url' => ['type' => 'url',      'label' => 'Facebook URL'],
        'linkedin_url' => ['type' => 'url',      'label' => 'LinkedIn URL'],
        'youtube_url'  => ['type' => 'url',      'label' => 'YouTube URL'],
    ];

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);

        if (function_exists('acf_add_options_page')) {
            add_action('acf/init', [__CLASS__, 'register_acf_fields']);
        }
    }

    public static function register_menu(): void {
        if (function_exists('acf_add_options_page')) {
            return;
        }

        add_submenu_page(
            'guvenhijyen',
            __('Company Settings', 'guvenhijyen'),
            __('Company Settings', 'guvenhijyen'),
            'manage_options',
            'gh-company-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function register_settings(): void {
        register_setting(self::$option_group, self::$option_name, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize_settings'],
        ]);

        add_settings_section(
            'gh_company_main',
            __('Company Information', 'guvenhijyen'),
            '__return_null',
            self::$option_group
        );

        foreach (self::$fields as $key => $field) {
            add_settings_field(
                $key,
                __($field['label'], 'guvenhijyen'),
                [__CLASS__, 'render_field'],
                self::$option_group,
                'gh_company_main',
                ['key' => $key, 'field' => $field]
            );
        }
    }

    public static function sanitize_settings(array $input): array {
        $sanitized = [];
        foreach (self::$fields as $key => $field) {
            $value = $input[$key] ?? '';
            switch ($field['type']) {
                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;
                case 'url':
                    $sanitized[$key] = esc_url_raw($value);
                    break;
                case 'textarea':
                    $sanitized[$key] = sanitize_textarea_field($value);
                    break;
                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }
        return $sanitized;
    }

    public static function render_field(array $args): void {
        $options = get_option(self::$option_name, []);
        $key     = $args['key'];
        $field   = $args['field'];
        $value   = $options[$key] ?? '';
        $name    = self::$option_name . '[' . $key . ']';

        if ($field['type'] === 'textarea') {
            printf(
                '<textarea name="%s" class="large-text" rows="3">%s</textarea>',
                esc_attr($name),
                esc_textarea($value)
            );
        } else {
            printf(
                '<input type="%s" name="%s" value="%s" class="regular-text" />',
                esc_attr($field['type']),
                esc_attr($name),
                esc_attr($value)
            );
        }
    }

    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap">';
        echo '<h1>' . esc_html(get_admin_page_title()) . '</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields(self::$option_group);
        do_settings_sections(self::$option_group);
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    public static function register_acf_fields(): void {
        acf_add_options_page([
            'page_title' => __('Company Settings', 'guvenhijyen'),
            'menu_title' => __('Company Settings', 'guvenhijyen'),
            'menu_slug'  => 'gh-company-settings',
            'parent_slug'=> 'guvenhijyen',
            'capability' => 'manage_options',
        ]);

        $acf_fields = [];
        foreach (self::$fields as $key => $field) {
            $acf_type = match ($field['type']) {
                'email'    => 'email',
                'url'      => 'url',
                'textarea' => 'textarea',
                default    => 'text',
            };
            $acf_fields[] = [
                'key'   => 'field_gh_' . $key,
                'label' => $field['label'],
                'name'  => 'gh_' . $key,
                'type'  => $acf_type,
            ];
        }

        acf_add_local_field_group([
            'key'      => 'group_gh_company',
            'title'    => __('Company Information', 'guvenhijyen'),
            'fields'   => $acf_fields,
            'location' => [
                [['param' => 'options_page', 'operator' => '==', 'value' => 'gh-company-settings']],
            ],
        ]);
    }

    public static function get(string $field): string {
        if (function_exists('get_field') && get_field('gh_' . $field, 'option') !== null) {
            return (string) get_field('gh_' . $field, 'option');
        }

        $options = get_option(self::$option_name, []);
        return (string) ($options[$field] ?? '');
    }

    public static function get_all(): array {
        $all = [];
        foreach (array_keys(self::$fields) as $key) {
            $all[$key] = self::get($key);
        }
        return $all;
    }

    public static function get_structured_data(): array {
        $data = self::get_all();

        $schema = [
            '@context' => 'https://schema.org',
            '@type'    => ['Organization', 'LocalBusiness'],
            'name'     => $data['company_name'],
            'email'    => $data['email'],
            'telephone'=> $data['phone'],
        ];

        if ($data['address'] || $data['district'] || $data['city']) {
            $schema['address'] = [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $data['address'],
                'addressLocality' => $data['district'],
                'addressRegion'   => $data['city'],
                'addressCountry'  => 'TR',
            ];
        }

        if ($data['map_url']) {
            $schema['hasMap'] = $data['map_url'];
        }

        $same_as = array_filter([
            $data['instagram_url'],
            $data['facebook_url'],
            $data['linkedin_url'],
            $data['youtube_url'],
        ]);
        if ($same_as) {
            $schema['sameAs'] = array_values($same_as);
        }

        return $schema;
    }
}

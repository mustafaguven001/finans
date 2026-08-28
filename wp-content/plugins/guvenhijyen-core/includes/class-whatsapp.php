<?php

defined('ABSPATH') || exit;

class GH_WhatsApp {

    public static function init(): void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_shortcode('guvenhijyen_whatsapp_button', [__CLASS__, 'render_shortcode']);
    }

    public static function get_number(): string {
        $raw = GH_Company_Settings::get('whatsapp');
        if (empty($raw)) {
            return '';
        }
        return preg_replace('/[^0-9]/', '', $raw);
    }

    public static function compose_message(string $context, ?WC_Product $product = null, ?WC_Product $variation = null): string {
        $company_name = GH_Company_Settings::get('company_name') ?: get_bloginfo('name');

        switch ($context) {
            case 'product_inquiry':
                return self::compose_product_message($product, $variation, $company_name);

            case 'quick_quote':
                return self::compose_quick_quote_message($product, $variation, $company_name);

            case 'general_inquiry':
            default:
                return self::compose_general_message($company_name);
        }
    }

    public static function get_url(string $context, ?WC_Product $product = null, ?WC_Product $variation = null): string {
        $number = self::get_number();
        if (empty($number)) {
            return '';
        }

        $message = self::compose_message($context, $product, $variation);

        return 'https://wa.me/' . $number . '?text=' . rawurlencode($message);
    }

    public static function render_button(string $context, ?WC_Product $product = null, ?WC_Product $variation = null, array $attrs = []): string {
        $url = self::get_url($context, $product, $variation);
        if (empty($url)) {
            return '';
        }

        $defaults = [
            'class' => 'gh-whatsapp-btn',
            'text'  => __('WhatsApp ile Iletisime Gec', 'guvenhijyen'),
            'icon'  => true,
        ];
        $attrs = wp_parse_args($attrs, $defaults);

        $icon_svg = '';
        if ($attrs['icon']) {
            $icon_svg = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px;">'
                . '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>'
                . '</svg>';
        }

        $output = '<a href="' . esc_url($url) . '"'
            . ' class="' . esc_attr($attrs['class']) . '"'
            . ' target="_blank"'
            . ' rel="noopener noreferrer"'
            . '>'
            . $icon_svg
            . esc_html($attrs['text'])
            . '</a>';

        return $output;
    }

    public static function render_shortcode(array $atts): string {
        $atts = shortcode_atts([
            'context' => 'general_inquiry',
            'text'    => __('WhatsApp ile Iletisime Gec', 'guvenhijyen'),
            'class'   => 'gh-whatsapp-btn',
        ], $atts, 'guvenhijyen_whatsapp_button');

        return self::render_button(
            sanitize_text_field($atts['context']),
            null,
            null,
            [
                'class' => sanitize_html_class($atts['class']),
                'text'  => sanitize_text_field($atts['text']),
            ]
        );
    }

    public static function render_product_button(WC_Product $product, ?WC_Product $variation = null): string {
        if ($product->is_type('variable') && $variation === null) {
            $number = self::get_number();
            if (empty($number)) {
                return '';
            }

            return '<a href="#"'
                . ' class="gh-whatsapp-btn gh-whatsapp-btn--variable"'
                . ' data-product-id="' . esc_attr($product->get_id()) . '"'
                . ' data-whatsapp-number="' . esc_attr($number) . '"'
                . ' data-company-name="' . esc_attr(GH_Company_Settings::get('company_name') ?: get_bloginfo('name')) . '"'
                . '>'
                . '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:6px;">'
                . '<path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>'
                . '</svg>'
                . esc_html__('WhatsApp ile Bilgi Al', 'guvenhijyen')
                . '</a>';
        }

        return self::render_button(
            'product_inquiry',
            $product,
            $variation,
            ['text' => __('WhatsApp ile Bilgi Al', 'guvenhijyen')]
        );
    }

    public static function enqueue_assets(): void {
        if (!is_product()) {
            return;
        }

        wp_enqueue_script(
            'gh-whatsapp',
            GH_CORE_URL . 'assets/js/whatsapp.js',
            ['jquery'],
            GH_CORE_VERSION,
            true
        );

        wp_localize_script('gh-whatsapp', 'ghWhatsApp', [
            'selectVariation' => __('Lutfen bir varyant seciniz.', 'guvenhijyen'),
        ]);
    }

    private static function compose_general_message(string $company_name): string {
        return sprintf(
            "%s\n\n%s",
            sprintf(__('Merhaba, %s hakkinda bilgi almak istiyorum.', 'guvenhijyen'), $company_name),
            __('Lutfen benimle iletisime gecin.', 'guvenhijyen')
        );
    }

    private static function compose_product_message(?WC_Product $product, ?WC_Product $variation, string $company_name): string {
        if (!$product) {
            return self::compose_general_message($company_name);
        }

        $target = $variation ?: $product;
        $lines  = [];

        $lines[] = __('Merhaba,', 'guvenhijyen');
        $lines[] = '';
        $lines[] = sprintf(__('Asagidaki urun hakkinda bilgi almak istiyorum:', 'guvenhijyen'));
        $lines[] = '';
        $lines[] = sprintf(__('Urun: %s', 'guvenhijyen'), $product->get_name());

        $sku = $target->get_sku();
        if (!empty($sku)) {
            $lines[] = sprintf(__('SKU: %s', 'guvenhijyen'), $sku);
        }

        if ($variation && $variation->is_type('variation')) {
            $attrs = $variation->get_variation_attributes();
            $parts = [];
            foreach ($attrs as $attr_key => $attr_value) {
                $taxonomy = str_replace('attribute_', '', $attr_key);
                $label    = wc_attribute_label($taxonomy, $product);
                $term     = get_term_by('slug', $attr_value, $taxonomy);
                $value    = $term ? $term->name : $attr_value;
                $parts[]  = $label . ': ' . $value;
            }
            if (!empty($parts)) {
                $lines[] = sprintf(__('Varyant: %s', 'guvenhijyen'), implode(', ', $parts));
            }
        }

        $lines[] = '';
        $lines[] = sprintf(__('Urun Linki: %s', 'guvenhijyen'), get_permalink($product->get_id()));

        return implode("\n", $lines);
    }

    private static function compose_quick_quote_message(?WC_Product $product, ?WC_Product $variation, string $company_name): string {
        if (!$product) {
            return self::compose_general_message($company_name);
        }

        $target = $variation ?: $product;
        $lines  = [];

        $lines[] = __('Merhaba,', 'guvenhijyen');
        $lines[] = '';
        $lines[] = sprintf(__('Asagidaki urun icin fiyat teklifi almak istiyorum:', 'guvenhijyen'));
        $lines[] = '';
        $lines[] = sprintf(__('Urun: %s', 'guvenhijyen'), $product->get_name());

        $sku = $target->get_sku();
        if (!empty($sku)) {
            $lines[] = sprintf(__('SKU: %s', 'guvenhijyen'), $sku);
        }

        if ($variation && $variation->is_type('variation')) {
            $attrs = $variation->get_variation_attributes();
            $parts = [];
            foreach ($attrs as $attr_key => $attr_value) {
                $taxonomy = str_replace('attribute_', '', $attr_key);
                $label    = wc_attribute_label($taxonomy, $product);
                $term     = get_term_by('slug', $attr_value, $taxonomy);
                $value    = $term ? $term->name : $attr_value;
                $parts[]  = $label . ': ' . $value;
            }
            if (!empty($parts)) {
                $lines[] = sprintf(__('Varyant: %s', 'guvenhijyen'), implode(', ', $parts));
            }
        }

        $lines[] = '';
        $lines[] = __('Lutfen fiyat ve teslimat bilgisi paylasir misiniz?', 'guvenhijyen');

        return implode("\n", $lines);
    }
}

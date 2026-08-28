<?php

defined('ABSPATH') || exit;

class GH_RFQ_Frontend {

    public static function init(): void {
        add_shortcode('guvenhijyen_rfq_form', [__CLASS__, 'render_rfq_form']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets']);
    }

    public static function register_assets(): void {
        wp_register_style(
            'gh-rfq-frontend',
            GH_CORE_URL . 'assets/css/rfq-frontend.css',
            [],
            GH_CORE_VERSION
        );

        wp_register_script(
            'gh-rfq-frontend',
            GH_CORE_URL . 'assets/js/rfq-frontend.js',
            ['jquery', 'wp-util'],
            GH_CORE_VERSION,
            true
        );
    }

    public static function render_rfq_form(array $atts = []): string {
        wp_enqueue_style('gh-rfq-frontend');
        wp_enqueue_script('gh-rfq-frontend');

        wp_localize_script('gh-rfq-frontend', 'ghRFQ', [
            'restUrl'        => esc_url_raw(rest_url('guvenhijyen/v1')),
            'nonce'          => wp_create_nonce('wp_rest'),
            'idempotencyKey' => wp_generate_uuid4(),
            'i18n'           => [
                'submitting'       => __('Gonderiliyor...', 'guvenhijyen'),
                'submit'           => __('Teklif Talebi Gonder', 'guvenhijyen'),
                'success'          => __('Teklif talebiniz basariyla alindi.', 'guvenhijyen'),
                'referenceLabel'   => __('Referans Numaraniz:', 'guvenhijyen'),
                'error'            => __('Bir hata olustu. Lutfen tekrar deneyiniz.', 'guvenhijyen'),
                'requiredField'    => __('Bu alan zorunludur.', 'guvenhijyen'),
                'invalidEmail'     => __('Gecerli bir e-posta adresi giriniz.', 'guvenhijyen'),
                'invalidPhone'     => __('Gecerli bir telefon numarasi giriniz.', 'guvenhijyen'),
                'kvkkRequired'     => __('KVKK onayi zorunludur.', 'guvenhijyen'),
                'selectVariation'  => __('Lutfen bir varyant seciniz.', 'guvenhijyen'),
                'productAdded'     => __('Urun teklif listesine eklendi.', 'guvenhijyen'),
                'productRemoved'   => __('Urun listeden cikarildi.', 'guvenhijyen'),
                'emptyQuoteList'   => __('Teklif listeniz bos.', 'guvenhijyen'),
                'searchProduct'    => __('Urun ara...', 'guvenhijyen'),
                'noResults'        => __('Sonuc bulunamadi.', 'guvenhijyen'),
            ],
        ]);

        $sectors = self::get_sectors();

        ob_start();
        ?>
        <div id="gh-rfq-form-wrapper" class="gh-rfq-form-wrapper">

            <ul class="gh-rfq-tabs" role="tablist">
                <li role="presentation">
                    <button type="button"
                            class="gh-rfq-tab gh-rfq-tab--active"
                            role="tab"
                            aria-selected="true"
                            data-tab="general">
                        <?php esc_html_e('Genel Teklif Talebi', 'guvenhijyen'); ?>
                    </button>
                </li>
                <li role="presentation">
                    <button type="button"
                            class="gh-rfq-tab"
                            role="tab"
                            aria-selected="false"
                            data-tab="product">
                        <?php esc_html_e('Urun Bazli Teklif', 'guvenhijyen'); ?>
                    </button>
                </li>
            </ul>

            <div class="gh-rfq-tab-content gh-rfq-tab-content--active" data-tab-content="general">
                <form id="gh-rfq-general-form" class="gh-rfq-form" novalidate>
                    <input type="hidden" name="type" value="general">
                    <input type="hidden" name="idempotency_key" value="">

                    <div class="gh-rfq-form__honeypot" aria-hidden="true" style="position:absolute;left:-9999px;">
                        <label for="gh_website_url"><?php esc_html_e('Website', 'guvenhijyen'); ?></label>
                        <input type="text" name="website_url" id="gh_website_url" tabindex="-1" autocomplete="off">
                    </div>

                    <?php self::render_customer_fields($sectors); ?>

                    <div class="gh-rfq-form__group">
                        <label for="gh_rfq_subject"><?php esc_html_e('Konu', 'guvenhijyen'); ?></label>
                        <input type="text"
                               id="gh_rfq_subject"
                               name="subject"
                               class="gh-rfq-form__input"
                               maxlength="500">
                    </div>

                    <div class="gh-rfq-form__group">
                        <label for="gh_rfq_message"><?php esc_html_e('Mesajiniz', 'guvenhijyen'); ?></label>
                        <textarea id="gh_rfq_message"
                                  name="message"
                                  class="gh-rfq-form__input"
                                  rows="5"></textarea>
                    </div>

                    <?php self::render_consent_fields(); ?>

                    <div class="gh-rfq-form__actions">
                        <button type="submit" class="gh-rfq-form__submit">
                            <?php esc_html_e('Teklif Talebi Gonder', 'guvenhijyen'); ?>
                        </button>
                    </div>

                    <div class="gh-rfq-form__messages" aria-live="polite"></div>
                </form>
            </div>

            <div class="gh-rfq-tab-content" data-tab-content="product">

                <div class="gh-rfq-product-search">
                    <h3><?php esc_html_e('Urun Ekle', 'guvenhijyen'); ?></h3>

                    <div class="gh-rfq-form__group">
                        <label for="gh_rfq_product_search"><?php esc_html_e('Urun Ara', 'guvenhijyen'); ?></label>
                        <input type="text"
                               id="gh_rfq_product_search"
                               class="gh-rfq-form__input"
                               placeholder="<?php esc_attr_e('Urun adi veya SKU giriniz...', 'guvenhijyen'); ?>"
                               autocomplete="off">
                        <div id="gh_rfq_search_results" class="gh-rfq-search-results"></div>
                    </div>

                    <div id="gh_rfq_selected_product" class="gh-rfq-selected-product" hidden>
                        <div class="gh-rfq-selected-product__info">
                            <span id="gh_rfq_selected_name" class="gh-rfq-selected-product__name"></span>
                            <span id="gh_rfq_selected_sku" class="gh-rfq-selected-product__sku"></span>
                        </div>

                        <div id="gh_rfq_variation_selector" class="gh-rfq-form__group" hidden>
                            <label for="gh_rfq_variation"><?php esc_html_e('Varyant Seciniz', 'guvenhijyen'); ?></label>
                            <select id="gh_rfq_variation" class="gh-rfq-form__input">
                                <option value=""><?php esc_html_e('Seciniz...', 'guvenhijyen'); ?></option>
                            </select>
                        </div>

                        <div class="gh-rfq-quantity-row">
                            <div class="gh-rfq-form__group">
                                <label for="gh_rfq_quantity"><?php esc_html_e('Miktar', 'guvenhijyen'); ?></label>
                                <input type="number"
                                       id="gh_rfq_quantity"
                                       class="gh-rfq-form__input"
                                       value="1"
                                       min="1"
                                       step="1">
                            </div>
                            <div class="gh-rfq-form__group">
                                <label><?php esc_html_e('Birim', 'guvenhijyen'); ?></label>
                                <span id="gh_rfq_sales_unit" class="gh-rfq-sales-unit"></span>
                            </div>
                        </div>

                        <button type="button" id="gh_rfq_add_to_list" class="gh-rfq-form__btn gh-rfq-form__btn--secondary">
                            <?php esc_html_e('Teklif Listesine Ekle', 'guvenhijyen'); ?>
                        </button>
                    </div>
                </div>

                <div class="gh-rfq-quote-list">
                    <h3>
                        <?php esc_html_e('Teklif Listem', 'guvenhijyen'); ?>
                        (<span id="gh_rfq_list_count">0</span>)
                    </h3>

                    <div id="gh_rfq_quote_items" class="gh-rfq-quote-items">
                        <p class="gh-rfq-quote-items__empty">
                            <?php esc_html_e('Teklif listeniz bos.', 'guvenhijyen'); ?>
                        </p>
                    </div>
                </div>

                <form id="gh-rfq-product-form" class="gh-rfq-form" novalidate>
                    <input type="hidden" name="type" value="quote_list">
                    <input type="hidden" name="idempotency_key" value="">

                    <div class="gh-rfq-form__honeypot" aria-hidden="true" style="position:absolute;left:-9999px;">
                        <label for="gh_website_url_2"><?php esc_html_e('Website', 'guvenhijyen'); ?></label>
                        <input type="text" name="website_url" id="gh_website_url_2" tabindex="-1" autocomplete="off">
                    </div>

                    <h3><?php esc_html_e('Iletisim Bilgileri', 'guvenhijyen'); ?></h3>

                    <?php self::render_customer_fields($sectors, '_product'); ?>

                    <div class="gh-rfq-form__group">
                        <label for="gh_rfq_message_product"><?php esc_html_e('Ek Notunuz', 'guvenhijyen'); ?></label>
                        <textarea id="gh_rfq_message_product"
                                  name="message"
                                  class="gh-rfq-form__input"
                                  rows="3"></textarea>
                    </div>

                    <?php self::render_consent_fields('_product'); ?>

                    <div class="gh-rfq-form__actions">
                        <button type="submit" class="gh-rfq-form__submit">
                            <?php esc_html_e('Teklif Talebi Gonder', 'guvenhijyen'); ?>
                        </button>
                    </div>

                    <div class="gh-rfq-form__messages" aria-live="polite"></div>
                </form>
            </div>

            <div id="gh-rfq-success" class="gh-rfq-success" hidden>
                <div class="gh-rfq-success__icon">&#10003;</div>
                <h2><?php esc_html_e('Teklif Talebiniz Alindi', 'guvenhijyen'); ?></h2>
                <p><?php esc_html_e('En kisa surede sizinle iletisime gececegiz.', 'guvenhijyen'); ?></p>
                <div class="gh-rfq-success__reference">
                    <span><?php esc_html_e('Referans Numaraniz:', 'guvenhijyen'); ?></span>
                    <strong id="gh_rfq_success_reference"></strong>
                </div>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    private static function render_customer_fields(array $sectors, string $suffix = ''): void {
        ?>
        <div class="gh-rfq-form__row">
            <div class="gh-rfq-form__group gh-rfq-form__group--half">
                <label for="gh_rfq_company<?php echo esc_attr($suffix); ?>">
                    <?php esc_html_e('Firma Adi', 'guvenhijyen'); ?> <span class="gh-rfq-required">*</span>
                </label>
                <input type="text"
                       id="gh_rfq_company<?php echo esc_attr($suffix); ?>"
                       name="customer[company]"
                       class="gh-rfq-form__input"
                       required
                       maxlength="255">
            </div>
            <div class="gh-rfq-form__group gh-rfq-form__group--half">
                <label for="gh_rfq_contact<?php echo esc_attr($suffix); ?>">
                    <?php esc_html_e('Yetkili Ad Soyad', 'guvenhijyen'); ?> <span class="gh-rfq-required">*</span>
                </label>
                <input type="text"
                       id="gh_rfq_contact<?php echo esc_attr($suffix); ?>"
                       name="customer[contact_name]"
                       class="gh-rfq-form__input"
                       required
                       maxlength="255">
            </div>
        </div>

        <div class="gh-rfq-form__row">
            <div class="gh-rfq-form__group gh-rfq-form__group--half">
                <label for="gh_rfq_phone<?php echo esc_attr($suffix); ?>">
                    <?php esc_html_e('Telefon', 'guvenhijyen'); ?> <span class="gh-rfq-required">*</span>
                </label>
                <input type="tel"
                       id="gh_rfq_phone<?php echo esc_attr($suffix); ?>"
                       name="customer[phone]"
                       class="gh-rfq-form__input"
                       required
                       maxlength="30"
                       placeholder="05XX XXX XX XX">
            </div>
            <div class="gh-rfq-form__group gh-rfq-form__group--half">
                <label for="gh_rfq_email<?php echo esc_attr($suffix); ?>">
                    <?php esc_html_e('E-posta', 'guvenhijyen'); ?> <span class="gh-rfq-required">*</span>
                </label>
                <input type="email"
                       id="gh_rfq_email<?php echo esc_attr($suffix); ?>"
                       name="customer[email]"
                       class="gh-rfq-form__input"
                       required
                       maxlength="255">
            </div>
        </div>

        <div class="gh-rfq-form__group">
            <label for="gh_rfq_sector<?php echo esc_attr($suffix); ?>"><?php esc_html_e('Sektor', 'guvenhijyen'); ?></label>
            <select id="gh_rfq_sector<?php echo esc_attr($suffix); ?>"
                    name="customer[sector]"
                    class="gh-rfq-form__input">
                <option value=""><?php esc_html_e('Seciniz...', 'guvenhijyen'); ?></option>
                <?php foreach ($sectors as $sector) : ?>
                    <option value="<?php echo esc_attr($sector->name); ?>">
                        <?php echo esc_html($sector->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    private static function render_consent_fields(string $suffix = ''): void {
        ?>
        <div class="gh-rfq-form__group gh-rfq-form__consent">
            <label class="gh-rfq-form__checkbox">
                <input type="checkbox"
                       name="consent[kvkk]"
                       id="gh_rfq_kvkk<?php echo esc_attr($suffix); ?>"
                       required
                       value="1">
                <span>
                    <?php
                    printf(
                        esc_html__('%s metnini okudum ve kabul ediyorum.', 'guvenhijyen'),
                        '<a href="' . esc_url(get_privacy_policy_url()) . '" target="_blank" rel="noopener">'
                        . esc_html__('KVKK Aydinlatma', 'guvenhijyen')
                        . '</a>'
                    );
                    ?>
                    <span class="gh-rfq-required">*</span>
                </span>
            </label>
        </div>

        <div class="gh-rfq-form__group gh-rfq-form__consent">
            <label class="gh-rfq-form__checkbox">
                <input type="checkbox"
                       name="consent[marketing]"
                       id="gh_rfq_marketing<?php echo esc_attr($suffix); ?>"
                       value="1">
                <span>
                    <?php esc_html_e('Kampanya ve duyurulardan haberdar olmak istiyorum.', 'guvenhijyen'); ?>
                </span>
            </label>
        </div>
        <?php
    }

    private static function get_sectors(): array {
        if (!taxonomy_exists('product_sector')) {
            return [];
        }

        $terms = get_terms([
            'taxonomy'   => 'product_sector',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ]);

        if (is_wp_error($terms)) {
            return [];
        }

        return $terms;
    }
}

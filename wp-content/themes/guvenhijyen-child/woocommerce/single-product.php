<?php

defined('ABSPATH') || exit;

get_header();

while (have_posts()) :
    the_post();

    global $product;

    if (!$product || !is_a($product, 'WC_Product')) {
        continue;
    }

    $product_id = $product->get_id();
    $sku = $product->get_sku();
    $is_variable = $product->is_type('variable');

    $brand = null;
    $brand_logo_url = '';
    $brand_verified = false;
    $brands = wp_get_post_terms($product_id, 'product_brand');
    if ($brands && !is_wp_error($brands)) {
        $brand = $brands[0];
        $brand_verified = class_exists('GH_Brand_Manager') && GH_Brand_Manager::is_brand_verified($brand->term_id);
        $brand_logo_id = get_term_meta($brand->term_id, 'gh_brand_logo', true);
        if ($brand_logo_id) {
            $brand_logo_url = wp_get_attachment_image_url($brand_logo_id, 'thumbnail');
        }
    }

    $sales_unit = '';
    if (class_exists('GH_Sales_Unit')) {
        $sales_unit = GH_Sales_Unit::get_label($product_id);
    }
    if (!$sales_unit) {
        $sales_unit = get_post_meta($product_id, '_gh_sales_unit', true);
    }

    $min_qty = (int) get_post_meta($product_id, '_gh_min_quantity', true) ?: 1;
    $step_qty = (int) get_post_meta($product_id, '_gh_step_quantity', true) ?: 1;

    $whatsapp_url = gh_whatsapp_url(
        sprintf(
            '%s (%s) hakkında bilgi almak istiyorum. %s',
            $product->get_name(),
            $sku,
            get_permalink()
        )
    );

    $visible_attributes = [];
    foreach ($product->get_attributes() as $attribute) {
        if (!$attribute->get_visible()) {
            continue;
        }
        if ($attribute->get_variation()) {
            continue;
        }
        $label = wc_attribute_label($attribute->get_name());
        if ($attribute->is_taxonomy()) {
            $values = wc_get_product_terms($product_id, $attribute->get_name(), ['fields' => 'names']);
        } else {
            $values = $attribute->get_options();
        }
        if ($values) {
            $visible_attributes[] = [
                'label'  => $label,
                'values' => implode(', ', $values),
            ];
        }
    }

    $documents = [];
    if (class_exists('GH_Document_System')) {
        $documents = GH_Document_System::get_product_documents($product_id);
    }

    $teklif_page = get_page_by_path('teklif-iste');
    $teklif_url = $teklif_page ? add_query_arg('product_id', $product_id, get_permalink($teklif_page)) : '#';

    ?>
    <div class="gh-single-product">
        <div class="gh-container">
            <?php gh_render_breadcrumb(); ?>

            <div class="gh-single-product__layout">
                <div class="gh-single-product__gallery">
                    <?php
                    if (function_exists('woocommerce_show_product_images')) {
                        woocommerce_show_product_images();
                    }
                    ?>
                </div>

                <div class="gh-single-product__info">
                    <?php if ($brand && $brand_verified) : ?>
                        <div class="gh-single-product__brand">
                            <?php if ($brand_logo_url) : ?>
                                <img src="<?php echo esc_url($brand_logo_url); ?>" alt="<?php echo esc_attr($brand->name); ?>" width="32" height="32" loading="lazy">
                            <?php endif; ?>
                            <span><?php echo esc_html($brand->name); ?></span>
                        </div>
                    <?php endif; ?>

                    <h1 class="gh-single-product__title"><?php the_title(); ?></h1>

                    <?php if ($sku) : ?>
                        <div class="gh-single-product__sku">
                            <span><?php esc_html_e('SKU:', 'guvenhijyen'); ?> <code id="product-sku"><?php echo esc_html($sku); ?></code></span>
                            <button type="button" class="gh-single-product__sku-copy js-copy-sku" data-sku="<?php echo esc_attr($sku); ?>" aria-label="<?php esc_attr_e('Copy SKU', 'guvenhijyen'); ?>">
                                <?php esc_html_e('Copy', 'guvenhijyen'); ?>
                            </button>
                        </div>
                    <?php endif; ?>

                    <?php if ($product->get_short_description()) : ?>
                        <div class="gh-single-product__short-desc">
                            <?php echo wp_kses_post($product->get_short_description()); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($visible_attributes) : ?>
                        <table class="gh-single-product__attributes-table">
                            <tbody>
                                <?php foreach ($visible_attributes as $attr) : ?>
                                    <tr>
                                        <th><?php echo esc_html($attr['label']); ?></th>
                                        <td><?php echo esc_html($attr['values']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if ($sales_unit) : ?>
                        <div class="gh-single-product__sales-unit">
                            <?php
                            printf(
                                esc_html__('Sales Unit: %s', 'guvenhijyen'),
                                '<strong>' . esc_html($sales_unit) . '</strong>'
                            );
                            ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($is_variable) : ?>
                        <div class="gh-single-product__variations" id="product-variations">
                            <?php
                            $variation_attributes = $product->get_variation_attributes();
                            foreach ($variation_attributes as $attribute_name => $options) :
                                $label = wc_attribute_label($attribute_name);
                                $sanitized_name = sanitize_title($attribute_name);
                                ?>
                                <div class="gh-single-product__variation-row">
                                    <label for="attr-<?php echo esc_attr($sanitized_name); ?>">
                                        <?php echo esc_html($label); ?>
                                    </label>
                                    <select
                                        id="attr-<?php echo esc_attr($sanitized_name); ?>"
                                        name="attribute_<?php echo esc_attr($sanitized_name); ?>"
                                        class="js-variation-select"
                                        data-attribute="<?php echo esc_attr($attribute_name); ?>"
                                        required
                                    >
                                        <option value=""><?php printf(esc_html__('%s select', 'guvenhijyen'), esc_html($label)); ?></option>
                                        <?php foreach ($options as $option) :
                                            $option_label = $option;
                                            if (taxonomy_exists($attribute_name)) {
                                                $term = get_term_by('slug', $option, $attribute_name);
                                                if ($term) {
                                                    $option_label = $term->name;
                                                }
                                            }
                                            ?>
                                            <option value="<?php echo esc_attr($option); ?>"><?php echo esc_html($option_label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="gh-single-product__quantity">
                        <label for="product-quantity"><?php esc_html_e('Quantity', 'guvenhijyen'); ?></label>
                        <input
                            type="number"
                            id="product-quantity"
                            class="js-product-quantity"
                            value="<?php echo esc_attr($min_qty); ?>"
                            min="<?php echo esc_attr($min_qty); ?>"
                            step="<?php echo esc_attr($step_qty); ?>"
                        >
                        <?php if ($sales_unit) : ?>
                            <span><?php echo esc_html($sales_unit); ?></span>
                        <?php endif; ?>
                    </div>

                    <div class="gh-single-product__actions">
                        <button
                            type="button"
                            class="gh-btn gh-btn--primary gh-btn--lg js-add-to-quote"
                            data-product-id="<?php echo esc_attr($product_id); ?>"
                            data-product-name="<?php echo esc_attr($product->get_name()); ?>"
                            data-sku="<?php echo esc_attr($sku); ?>"
                            <?php echo $is_variable ? 'disabled' : ''; ?>
                        >
                            <?php esc_html_e('Teklif Listesine Ekle', 'guvenhijyen'); ?>
                        </button>

                        <a
                            href="<?php echo esc_url($teklif_url); ?>"
                            class="gh-btn gh-btn--secondary gh-btn--lg js-quick-quote"
                            data-product-id="<?php echo esc_attr($product_id); ?>"
                            <?php echo $is_variable ? 'aria-disabled="true" tabindex="-1"' : ''; ?>
                        >
                            <?php esc_html_e('Hızlı Teklif Al', 'guvenhijyen'); ?>
                        </a>

                        <?php if ($whatsapp_url) : ?>
                            <a
                                href="<?php echo esc_url($whatsapp_url); ?>"
                                class="gh-btn gh-btn--whatsapp gh-btn--lg js-whatsapp-btn"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                <?php esc_html_e('WhatsApp ile Sor', 'guvenhijyen'); ?>
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_variable) : ?>
                        <p class="gh-single-product__variation-notice" id="variation-notice" role="alert">
                            <?php esc_html_e('Please select all options before adding to quote list.', 'guvenhijyen'); ?>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $tabs = [];
            $long_description = $product->get_description();
            if ($long_description) {
                $tabs['description'] = __('Description', 'guvenhijyen');
            }

            if ($visible_attributes) {
                $tabs['specifications'] = __('Technical Specifications', 'guvenhijyen');
            }

            if (class_exists('GH_Compatibility')) {
                $compatible = GH_Compatibility::get_compatible_products($product_id);
                if ($compatible) {
                    $tabs['compatibility'] = __('Compatibility', 'guvenhijyen');
                }
            }

            if ($documents) {
                $tabs['documents'] = __('Documents', 'guvenhijyen');
            }

            $related_ids = wc_get_related_products($product_id, 4);
            if ($related_ids) {
                $tabs['related'] = __('Related Products', 'guvenhijyen');
            }
            ?>

            <?php if ($tabs) : ?>
            <div class="gh-product-tabs">
                <ul class="gh-product-tabs__nav" role="tablist">
                    <?php $first = true; ?>
                    <?php foreach ($tabs as $tab_id => $tab_label) : ?>
                        <li role="presentation">
                            <button
                                role="tab"
                                id="tab-<?php echo esc_attr($tab_id); ?>"
                                aria-controls="panel-<?php echo esc_attr($tab_id); ?>"
                                aria-selected="<?php echo $first ? 'true' : 'false'; ?>"
                                <?php echo $first ? '' : 'tabindex="-1"'; ?>
                            >
                                <?php echo esc_html($tab_label); ?>
                            </button>
                        </li>
                    <?php $first = false; endforeach; ?>
                </ul>

                <?php $first = true; ?>
                <?php foreach ($tabs as $tab_id => $tab_label) : ?>
                    <div
                        role="tabpanel"
                        id="panel-<?php echo esc_attr($tab_id); ?>"
                        aria-labelledby="tab-<?php echo esc_attr($tab_id); ?>"
                        class="gh-product-tabs__panel"
                        <?php echo $first ? '' : 'hidden'; ?>
                    >
                        <?php if ($tab_id === 'description') : ?>
                            <div class="gh-prose">
                                <?php echo wp_kses_post($long_description); ?>
                            </div>

                        <?php elseif ($tab_id === 'specifications') : ?>
                            <table class="gh-single-product__attributes-table">
                                <tbody>
                                    <?php foreach ($visible_attributes as $attr) : ?>
                                        <tr>
                                            <th><?php echo esc_html($attr['label']); ?></th>
                                            <td><?php echo esc_html($attr['values']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>

                        <?php elseif ($tab_id === 'compatibility' && isset($compatible)) : ?>
                            <div class="gh-product-grid">
                                <?php foreach ($compatible as $compat_product) :
                                    $GLOBALS['product'] = wc_get_product($compat_product);
                                    if ($GLOBALS['product']) {
                                        get_template_part('template-parts/product-card');
                                    }
                                endforeach; ?>
                                <?php $GLOBALS['product'] = $product; ?>
                            </div>

                        <?php elseif ($tab_id === 'documents') : ?>
                            <ul class="gh-document-list">
                                <?php foreach ($documents as $doc) :
                                    $doc_post = get_post($doc);
                                    if (!$doc_post) continue;

                                    $file_id = get_post_meta($doc_post->ID, '_gh_document_file', true);
                                    $revision = get_post_meta($doc_post->ID, '_gh_current_revision', true);
                                    if (!$file_id || !$revision) continue;

                                    $file_url = wp_get_attachment_url($file_id);
                                    if (!$file_url) continue;
                                    ?>
                                    <li>
                                        <a href="<?php echo esc_url($file_url); ?>" target="_blank" rel="noopener noreferrer">
                                            <?php echo esc_html($doc_post->post_title); ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>

                        <?php elseif ($tab_id === 'related') : ?>
                            <div class="gh-product-grid">
                                <?php foreach ($related_ids as $related_id) :
                                    $GLOBALS['product'] = wc_get_product($related_id);
                                    if ($GLOBALS['product']) {
                                        get_template_part('template-parts/product-card');
                                    }
                                endforeach; ?>
                                <?php $GLOBALS['product'] = $product; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php $first = false; endforeach; ?>
            </div>
            <?php endif; ?>

            <?php get_template_part('template-parts/rfq-cta'); ?>
        </div>
    </div>

    <script>
    (function() {
        var tablist = document.querySelector('.gh-product-tabs__nav');
        if (!tablist) return;

        var tabs = tablist.querySelectorAll('[role="tab"]');
        var panels = document.querySelectorAll('.gh-product-tabs__panel');

        function switchTab(newTab) {
            tabs.forEach(function(tab) {
                tab.setAttribute('aria-selected', 'false');
                tab.setAttribute('tabindex', '-1');
            });
            newTab.setAttribute('aria-selected', 'true');
            newTab.removeAttribute('tabindex');
            newTab.focus();

            panels.forEach(function(panel) {
                panel.hidden = true;
            });
            var target = document.getElementById(newTab.getAttribute('aria-controls'));
            if (target) target.hidden = false;
        }

        tablist.addEventListener('click', function(e) {
            var tab = e.target.closest('[role="tab"]');
            if (tab) switchTab(tab);
        });

        tablist.addEventListener('keydown', function(e) {
            var idx = Array.prototype.indexOf.call(tabs, document.activeElement);
            if (idx < 0) return;
            var dir = 0;
            if (e.key === 'ArrowRight') dir = 1;
            else if (e.key === 'ArrowLeft') dir = -1;
            else if (e.key === 'Home') { e.preventDefault(); switchTab(tabs[0]); return; }
            else if (e.key === 'End') { e.preventDefault(); switchTab(tabs[tabs.length - 1]); return; }
            if (dir) {
                e.preventDefault();
                var next = (idx + dir + tabs.length) % tabs.length;
                switchTab(tabs[next]);
            }
        });
    })();
    </script>

<?php endwhile;

get_footer();

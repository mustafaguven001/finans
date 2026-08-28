(function ($) {
    'use strict';

    if (typeof ghRFQ === 'undefined') {
        return;
    }

    var config = ghRFQ;
    var searchTimer = null;
    var selectedProduct = null;
    var quoteListItems = {};

    function init() {
        initTabs();
        initGeneralForm();
        initProductForm();
        initProductSearch();
        initAddToList();
        loadQuoteListFromSession();
        setIdempotencyKeys();
    }

    function setIdempotencyKeys() {
        $('input[name="idempotency_key"]').each(function () {
            $(this).val(config.idempotencyKey + '-' + Math.random().toString(36).substr(2, 6));
        });
    }

    function initTabs() {
        $('.gh-rfq-tab').on('click', function () {
            var tab = $(this).data('tab');
            $('.gh-rfq-tab').removeClass('gh-rfq-tab--active').attr('aria-selected', 'false');
            $(this).addClass('gh-rfq-tab--active').attr('aria-selected', 'true');
            $('.gh-rfq-tab-content').removeClass('gh-rfq-tab-content--active');
            $('[data-tab-content="' + tab + '"]').addClass('gh-rfq-tab-content--active');
        });
    }

    function initGeneralForm() {
        $('#gh-rfq-general-form').on('submit', function (e) {
            e.preventDefault();
            submitRFQ($(this), 'general');
        });
    }

    function initProductForm() {
        $('#gh-rfq-product-form').on('submit', function (e) {
            e.preventDefault();
            submitRFQ($(this), 'quote_list');
        });
    }

    function submitRFQ($form, type) {
        clearErrors($form);

        var customerSuffix = type === 'quote_list' ? '_product' : '';
        var company = $('#gh_rfq_company' + customerSuffix).val();
        var contactName = $('#gh_rfq_contact' + customerSuffix).val();
        var phone = $('#gh_rfq_phone' + customerSuffix).val();
        var email = $('#gh_rfq_email' + customerSuffix).val();
        var kvkk = $('#gh_rfq_kvkk' + customerSuffix).is(':checked');

        var valid = true;

        if (!company) {
            showFieldError($('#gh_rfq_company' + customerSuffix), config.i18n.requiredField);
            valid = false;
        }
        if (!contactName) {
            showFieldError($('#gh_rfq_contact' + customerSuffix), config.i18n.requiredField);
            valid = false;
        }
        if (!phone) {
            showFieldError($('#gh_rfq_phone' + customerSuffix), config.i18n.requiredField);
            valid = false;
        } else if (!validateTurkishPhone(phone)) {
            showFieldError($('#gh_rfq_phone' + customerSuffix), config.i18n.invalidPhone);
            valid = false;
        }
        if (!email) {
            showFieldError($('#gh_rfq_email' + customerSuffix), config.i18n.requiredField);
            valid = false;
        } else if (!validateEmail(email)) {
            showFieldError($('#gh_rfq_email' + customerSuffix), config.i18n.invalidEmail);
            valid = false;
        }
        if (!kvkk) {
            showFieldError($('#gh_rfq_kvkk' + customerSuffix), config.i18n.kvkkRequired);
            valid = false;
        }

        if (type === 'quote_list' && Object.keys(quoteListItems).length === 0) {
            showFormMessage($form, config.i18n.emptyQuoteList, 'error');
            valid = false;
        }

        if (!valid) {
            return;
        }

        var data = {
            type: type,
            idempotency_key: $form.find('input[name="idempotency_key"]').val(),
            customer: {
                company: company,
                contact_name: contactName,
                phone: phone,
                email: email,
                sector: $('#gh_rfq_sector' + customerSuffix).val() || ''
            },
            consent: {
                kvkk: kvkk,
                marketing: $('#gh_rfq_marketing' + customerSuffix).is(':checked')
            },
            website_url: $form.find('input[name="website_url"]').val()
        };

        if (type === 'general') {
            data.subject = $('#gh_rfq_subject').val() || '';
            data.message = $('#gh_rfq_message').val() || '';
        } else if (type === 'quote_list') {
            data.message = $('#gh_rfq_message_product').val() || '';
            data.items = [];
            $.each(quoteListItems, function (key, item) {
                data.items.push({
                    product_id: item.product_id,
                    variation_id: item.variation_id,
                    quantity: item.quantity,
                    sales_unit: item.sales_unit_key
                });
            });
        }

        var $btn = $form.find('.gh-rfq-form__submit');
        $btn.prop('disabled', true).text(config.i18n.submitting);

        $.ajax({
            url: config.restUrl + '/rfq/submit',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(data),
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            success: function (response) {
                if (response.success && response.reference) {
                    $('#gh-rfq-form-wrapper > .gh-rfq-tabs').hide();
                    $('.gh-rfq-tab-content').hide();
                    $('#gh_rfq_success_reference').text(response.reference);
                    $('#gh-rfq-success').removeAttr('hidden');

                    quoteListItems = {};
                    try {
                        sessionStorage.removeItem('gh_quote_list');
                    } catch (e) {}
                } else {
                    showFormMessage($form, config.i18n.error, 'error');
                    $btn.prop('disabled', false).text(config.i18n.submit);
                }
            },
            error: function (xhr) {
                var msg = config.i18n.error;
                if (xhr.responseJSON) {
                    if (xhr.responseJSON.errors) {
                        var firstError = Object.values(xhr.responseJSON.errors)[0];
                        msg = firstError;
                    } else if (xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    }
                }
                showFormMessage($form, msg, 'error');
                $btn.prop('disabled', false).text(config.i18n.submit);
            }
        });
    }

    function initProductSearch() {
        var $input = $('#gh_rfq_product_search');
        var $results = $('#gh_rfq_search_results');

        $input.on('input', function () {
            var query = $(this).val();
            clearTimeout(searchTimer);

            if (query.length < 2) {
                $results.empty();
                return;
            }

            searchTimer = setTimeout(function () {
                $.ajax({
                    url: config.restUrl.replace('guvenhijyen/v1', 'wc/v3') + '/products',
                    method: 'GET',
                    data: {
                        search: query,
                        per_page: 10,
                        status: 'publish',
                        _fields: 'id,name,sku,type,variations'
                    },
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('X-WP-Nonce', config.nonce);
                    },
                    success: function (products) {
                        if (!products || products.length === 0) {
                            $results.html('<ul class="gh-rfq-search-results__list"><li class="gh-rfq-search-results__item" style="cursor:default;color:#888;">' + config.i18n.noResults + '</li></ul>');
                            return;
                        }

                        var html = '<ul class="gh-rfq-search-results__list">';
                        $.each(products, function (i, product) {
                            html += '<li class="gh-rfq-search-results__item" data-product=\'' + JSON.stringify({
                                id: product.id,
                                name: product.name,
                                sku: product.sku,
                                type: product.type,
                                variations: product.variations || []
                            }).replace(/'/g, '&#39;') + '\'>';
                            html += '<span class="gh-rfq-search-results__item-name">' + escapeHtml(product.name) + '</span>';
                            if (product.sku) {
                                html += '<span class="gh-rfq-search-results__item-sku">(' + escapeHtml(product.sku) + ')</span>';
                            }
                            html += '</li>';
                        });
                        html += '</ul>';
                        $results.html(html);
                    }
                });
            }, 300);
        });

        $results.on('click', '.gh-rfq-search-results__item[data-product]', function () {
            var product = $(this).data('product');
            if (!product || !product.id) return;

            selectedProduct = product;
            $input.val('');
            $results.empty();
            showSelectedProduct(product);
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.gh-rfq-product-search').length) {
                $results.empty();
            }
        });
    }

    function showSelectedProduct(product) {
        $('#gh_rfq_selected_name').text(product.name);
        $('#gh_rfq_selected_sku').text(product.sku ? 'SKU: ' + product.sku : '');
        $('#gh_rfq_selected_product').removeAttr('hidden');

        if (product.type === 'variable' && product.variations && product.variations.length > 0) {
            loadVariations(product.id);
            $('#gh_rfq_variation_selector').removeAttr('hidden');
        } else {
            $('#gh_rfq_variation_selector').attr('hidden', true);
            $('#gh_rfq_variation').empty().append('<option value="">-</option>');
        }

        $('#gh_rfq_quantity').val(1);
        updateSalesUnit(null);
    }

    function loadVariations(productId) {
        var $select = $('#gh_rfq_variation');
        $select.empty().append('<option value="">' + config.i18n.searchProduct.replace('...', '') + '</option>');

        $.ajax({
            url: config.restUrl.replace('guvenhijyen/v1', 'wc/v3') + '/products/' + productId + '/variations',
            method: 'GET',
            data: { per_page: 100, _fields: 'id,sku,attributes' },
            beforeSend: function (xhr) {
                xhr.setRequestHeader('X-WP-Nonce', config.nonce);
            },
            success: function (variations) {
                $select.empty().append('<option value="">' + escapeHtml(config.i18n.selectVariation) + '</option>');
                $.each(variations, function (i, variation) {
                    var label = [];
                    $.each(variation.attributes, function (j, attr) {
                        label.push(attr.name + ': ' + attr.option);
                    });
                    var text = label.join(', ');
                    if (variation.sku) {
                        text += ' (' + variation.sku + ')';
                    }
                    $select.append('<option value="' + variation.id + '">' + escapeHtml(text) + '</option>');
                });
            }
        });
    }

    function updateSalesUnit(unit) {
        $('#gh_rfq_sales_unit').text(unit || '-');
    }

    function initAddToList() {
        $('#gh_rfq_add_to_list').on('click', function () {
            if (!selectedProduct) return;

            var productId = selectedProduct.id;
            var variationId = 0;

            if (selectedProduct.type === 'variable') {
                variationId = parseInt($('#gh_rfq_variation').val(), 10) || 0;
                if (variationId === 0) {
                    alert(config.i18n.selectVariation);
                    return;
                }
            }

            var quantity = parseInt($('#gh_rfq_quantity').val(), 10) || 1;
            if (quantity < 1) quantity = 1;

            var itemKey = productId + '_' + variationId;
            var variationText = '';
            if (variationId > 0) {
                variationText = $('#gh_rfq_variation option:selected').text();
            }

            if (quoteListItems[itemKey]) {
                quoteListItems[itemKey].quantity += quantity;
            } else {
                quoteListItems[itemKey] = {
                    item_key: itemKey,
                    product_id: productId,
                    variation_id: variationId,
                    quantity: quantity,
                    product_name: selectedProduct.name,
                    sku: selectedProduct.sku || '',
                    variation: variationText,
                    sales_unit_key: 'adet',
                    sales_unit_label: 'Adet'
                };
            }

            saveQuoteListToSession();
            renderQuoteList();

            selectedProduct = null;
            $('#gh_rfq_selected_product').attr('hidden', true);
            $('#gh_rfq_product_search').val('');
        });
    }

    function renderQuoteList() {
        var $container = $('#gh_rfq_quote_items');
        var keys = Object.keys(quoteListItems);
        $('#gh_rfq_list_count').text(keys.length);

        if (keys.length === 0) {
            $container.html('<p class="gh-rfq-quote-items__empty">' + config.i18n.emptyQuoteList + '</p>');
            return;
        }

        var html = '';
        $.each(quoteListItems, function (key, item) {
            html += '<div class="gh-rfq-quote-item" data-key="' + escapeAttr(key) + '">';
            html += '<div class="gh-rfq-quote-item__info">';
            html += '<span class="gh-rfq-quote-item__name">' + escapeHtml(item.product_name) + '</span>';
            if (item.variation) {
                html += '<span class="gh-rfq-quote-item__variation">' + escapeHtml(item.variation) + '</span>';
            }
            if (item.sku) {
                html += '<span class="gh-rfq-quote-item__variation">SKU: ' + escapeHtml(item.sku) + '</span>';
            }
            html += '</div>';
            html += '<div class="gh-rfq-quote-item__qty">';
            html += '<input type="number" value="' + item.quantity + '" min="1" step="1" data-key="' + escapeAttr(key) + '">';
            html += '</div>';
            html += '<span class="gh-rfq-quote-item__unit">' + escapeHtml(item.sales_unit_label) + '</span>';
            html += '<button type="button" class="gh-rfq-quote-item__remove" data-key="' + escapeAttr(key) + '" title="' + escapeAttr(config.i18n.productRemoved) + '">&times;</button>';
            html += '</div>';
        });

        $container.html(html);

        $container.find('.gh-rfq-quote-item__qty input').on('change', function () {
            var k = $(this).data('key');
            var newQty = parseInt($(this).val(), 10) || 1;
            if (newQty < 1) newQty = 1;
            if (quoteListItems[k]) {
                quoteListItems[k].quantity = newQty;
                saveQuoteListToSession();
            }
        });

        $container.find('.gh-rfq-quote-item__remove').on('click', function () {
            var k = $(this).data('key');
            delete quoteListItems[k];
            saveQuoteListToSession();
            renderQuoteList();
        });
    }

    function saveQuoteListToSession() {
        try {
            sessionStorage.setItem('gh_quote_list', JSON.stringify(quoteListItems));
        } catch (e) {}
    }

    function loadQuoteListFromSession() {
        try {
            var stored = sessionStorage.getItem('gh_quote_list');
            if (stored) {
                quoteListItems = JSON.parse(stored);
                renderQuoteList();
            }
        } catch (e) {}
    }

    function showFieldError($field, message) {
        $field.addClass('gh-rfq-form__input--error');
        $field.after('<div class="gh-rfq-form__error">' + escapeHtml(message) + '</div>');
    }

    function clearErrors($form) {
        $form.find('.gh-rfq-form__input--error').removeClass('gh-rfq-form__input--error');
        $form.find('.gh-rfq-form__error').remove();
        $form.find('.gh-rfq-form__messages').empty();
    }

    function showFormMessage($form, message, type) {
        var cls = type === 'error' ? 'gh-rfq-form__message--error' : 'gh-rfq-form__message--success';
        $form.find('.gh-rfq-form__messages').html('<div class="' + cls + '">' + escapeHtml(message) + '</div>');
    }

    function validateEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    function validateTurkishPhone(phone) {
        var digits = phone.replace(/[^0-9]/g, '');
        return /^(90|0)?[1-9][0-9]{9}$/.test(digits);
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return String(str).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    $(document).ready(init);
})(jQuery);

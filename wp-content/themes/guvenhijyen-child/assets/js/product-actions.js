(function () {
    'use strict';

    var productData = window.ghProduct || {};

    function initVariationSelector() {
        if (!productData.isVariable) return;

        var selects = document.querySelectorAll('.js-variation-select');
        if (selects.length === 0) return;

        var addToQuoteBtn = document.querySelector('.gh-single-product__actions .js-add-to-quote');
        var quickQuoteLink = document.querySelector('.js-quick-quote');
        var variationNotice = document.getElementById('variation-notice');

        function checkAllSelected() {
            var allSelected = true;
            var selectedValues = {};

            for (var i = 0; i < selects.length; i++) {
                if (!selects[i].value) {
                    allSelected = false;
                } else {
                    selectedValues[selects[i].getAttribute('data-attribute')] = selects[i].value;
                }
            }

            if (addToQuoteBtn) {
                addToQuoteBtn.disabled = !allSelected;
                if (allSelected) {
                    addToQuoteBtn.removeAttribute('data-requires-variation');
                } else {
                    addToQuoteBtn.setAttribute('data-requires-variation', 'true');
                }
            }

            if (quickQuoteLink) {
                if (allSelected) {
                    quickQuoteLink.removeAttribute('aria-disabled');
                    quickQuoteLink.removeAttribute('tabindex');
                    var url = new URL(quickQuoteLink.href, window.location.origin);
                    Object.keys(selectedValues).forEach(function (key) {
                        url.searchParams.set('attribute_' + key, selectedValues[key]);
                    });
                    quickQuoteLink.href = url.toString();
                } else {
                    quickQuoteLink.setAttribute('aria-disabled', 'true');
                    quickQuoteLink.setAttribute('tabindex', '-1');
                }
            }

            if (variationNotice) {
                variationNotice.hidden = allSelected;
            }

            if (allSelected) {
                resolveVariationId(selectedValues);
            }

            return allSelected;
        }

        function resolveVariationId(selectedValues) {
            if (!addToQuoteBtn) return;

            var params = new URLSearchParams();
            params.set('product_id', productData.id);
            Object.keys(selectedValues).forEach(function (key) {
                params.set('attribute_' + key, selectedValues[key]);
            });

            var restUrl = (window.ghQuoteList && window.ghQuoteList.restUrl) || '/wp-json/guvenhijyen/v1/';
            var xhr = new XMLHttpRequest();
            xhr.open('GET', restUrl + 'products/variation?' + params.toString(), true);
            var nonce = (window.ghQuoteList && window.ghQuoteList.nonce) || '';
            if (nonce) {
                xhr.setRequestHeader('X-WP-Nonce', nonce);
            }

            xhr.onload = function () {
                if (xhr.status === 200) {
                    try {
                        var res = JSON.parse(xhr.responseText);
                        if (res.variation_id) {
                            addToQuoteBtn.setAttribute('data-variation-id', res.variation_id);
                        }
                    } catch (e) {
                        // Variation resolution failed
                    }
                }
            };

            xhr.send();
        }

        for (var i = 0; i < selects.length; i++) {
            selects[i].addEventListener('change', checkAllSelected);
        }

        checkAllSelected();
    }

    function initSkuCopy() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.js-copy-sku');
            if (!btn) return;

            var sku = btn.getAttribute('data-sku');
            if (!sku) return;

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(sku).then(function () {
                    showCopyFeedback(btn);
                }).catch(function () {
                    fallbackCopy(sku, btn);
                });
            } else {
                fallbackCopy(sku, btn);
            }
        });
    }

    function fallbackCopy(text, btn) {
        var textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            showCopyFeedback(btn);
        } catch (e) {
            // Copy failed
        }
        document.body.removeChild(textarea);
    }

    function showCopyFeedback(btn) {
        var original = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(function () {
            btn.textContent = original;
        }, 1500);
    }

    function initQuickQuote() {
        document.addEventListener('click', function (e) {
            var link = e.target.closest('.js-quick-quote');
            if (!link) return;

            if (link.getAttribute('aria-disabled') === 'true') {
                e.preventDefault();
                var notice = document.getElementById('variation-notice');
                if (notice) {
                    notice.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return;
            }

            var quantityInput = document.querySelector('.js-product-quantity');
            if (quantityInput) {
                var url = new URL(link.href, window.location.origin);
                url.searchParams.set('quantity', quantityInput.value);
                link.href = url.toString();
            }
        });
    }

    function initWhatsApp() {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.js-whatsapp-btn');
            if (!btn || !productData.whatsapp) return;

            e.preventDefault();

            var message = productData.title + ' (' + productData.sku + ')';
            message += ' hakkında bilgi almak istiyorum.';
            message += '\n' + productData.url;

            var selects = document.querySelectorAll('.js-variation-select');
            if (selects.length > 0) {
                var variations = [];
                for (var i = 0; i < selects.length; i++) {
                    if (selects[i].value) {
                        var label = selects[i].closest('.gh-single-product__variation-row');
                        var labelText = label ? label.querySelector('label').textContent.trim() : selects[i].getAttribute('data-attribute');
                        var optionText = selects[i].options[selects[i].selectedIndex].text;
                        variations.push(labelText + ': ' + optionText);
                    }
                }
                if (variations.length > 0) {
                    message += '\n' + variations.join(', ');
                }
            }

            var quantityInput = document.querySelector('.js-product-quantity');
            if (quantityInput && parseInt(quantityInput.value, 10) > 1) {
                message += '\nMiktar: ' + quantityInput.value;
            }

            var number = productData.whatsapp.replace(/[^0-9]/g, '');
            var url = 'https://wa.me/' + number + '?text=' + encodeURIComponent(message);
            window.open(url, '_blank', 'noopener,noreferrer');
        });
    }

    function initAddToQuoteVariation() {
        document.addEventListener('gh:quote:add', function () {
            var selects = document.querySelectorAll('.js-variation-select');
            for (var i = 0; i < selects.length; i++) {
                selects[i].selectedIndex = 0;
            }

            var addToQuoteBtn = document.querySelector('.gh-single-product__actions .js-add-to-quote');
            if (addToQuoteBtn && productData.isVariable) {
                addToQuoteBtn.disabled = true;
                addToQuoteBtn.removeAttribute('data-variation-id');
            }

            var variationNotice = document.getElementById('variation-notice');
            if (variationNotice && productData.isVariable) {
                variationNotice.hidden = false;
            }
        });
    }

    initVariationSelector();
    initSkuCopy();
    initQuickQuote();
    initWhatsApp();
    initAddToQuoteVariation();
})();

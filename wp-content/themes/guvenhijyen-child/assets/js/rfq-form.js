(function () {
    'use strict';

    var form = document.getElementById('gh-rfq-form');
    if (!form) return;

    var config = window.ghRfqForm || {};
    var submitting = false;
    var idempotencyKey = '';

    function generateUUID() {
        if (window.crypto && window.crypto.randomUUID) {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = (Math.random() * 16) | 0;
            var v = c === 'x' ? r : (r & 0x3) | 0x8;
            return v.toString(16);
        });
    }

    function resetIdempotencyKey() {
        idempotencyKey = generateUUID();
        var keyField = form.querySelector('[name="idempotency_key"]');
        if (keyField) {
            keyField.value = idempotencyKey;
        }
    }

    resetIdempotencyKey();

    var validators = {
        required: function (value) {
            return value.trim().length > 0;
        },
        email: function (value) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
        },
        phone: function (value) {
            var cleaned = value.replace(/[\s\-().]/g, '');
            return /^\+?[0-9]{7,15}$/.test(cleaned);
        }
    };

    var errorMessages = {
        required: 'Bu alan zorunludur.',
        email: 'Geçerli bir e-posta adresi girin.',
        phone: 'Geçerli bir telefon numarası girin.'
    };

    function validateField(field) {
        var rules = (field.getAttribute('data-validate') || '').split(',').filter(Boolean);
        var value = field.value || '';
        var wrapper = field.closest('.gh-rfq-form__field');

        clearFieldError(wrapper);

        for (var i = 0; i < rules.length; i++) {
            var rule = rules[i].trim();
            if (validators[rule] && !validators[rule](value)) {
                showFieldError(wrapper, field, errorMessages[rule] || 'Invalid value.');
                return false;
            }
        }

        return true;
    }

    function showFieldError(wrapper, field, message) {
        if (!wrapper) return;
        wrapper.classList.add('gh-rfq-form__field--error');
        var errorEl = wrapper.querySelector('.gh-rfq-form__error');
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = 'gh-rfq-form__error';
            errorEl.setAttribute('role', 'alert');
            wrapper.appendChild(errorEl);
        }
        errorEl.textContent = message;
        field.setAttribute('aria-invalid', 'true');
        field.setAttribute('aria-describedby', errorEl.id || '');
    }

    function clearFieldError(wrapper) {
        if (!wrapper) return;
        wrapper.classList.remove('gh-rfq-form__field--error');
        var errorEl = wrapper.querySelector('.gh-rfq-form__error');
        if (errorEl) {
            errorEl.textContent = '';
        }
        var input = wrapper.querySelector('input, textarea, select');
        if (input) {
            input.removeAttribute('aria-invalid');
        }
    }

    function showMessage(type, text) {
        var container = document.getElementById('rfq-form-messages') || form;
        var existing = container.querySelector('.gh-rfq-form__message');
        if (existing) {
            existing.remove();
        }

        var msg = document.createElement('div');
        msg.className = 'gh-rfq-form__message gh-rfq-form__message--' + type;
        msg.setAttribute('role', 'status');
        msg.setAttribute('aria-live', 'polite');
        msg.textContent = text;

        if (container === form) {
            form.insertBefore(msg, form.firstChild);
        } else {
            container.appendChild(msg);
        }

        msg.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function validateForm() {
        var fields = form.querySelectorAll('[data-validate]');
        var firstInvalid = null;
        var valid = true;

        for (var i = 0; i < fields.length; i++) {
            if (!validateField(fields[i])) {
                valid = false;
                if (!firstInvalid) {
                    firstInvalid = fields[i];
                }
            }
        }

        if (firstInvalid) {
            firstInvalid.focus();
        }

        return valid;
    }

    function setLoading(loading) {
        var submitBtn = form.querySelector('[type="submit"]');
        if (!submitBtn) return;

        if (loading) {
            submitBtn.disabled = true;
            submitBtn.classList.add('is-loading');
        } else {
            submitBtn.disabled = false;
            submitBtn.classList.remove('is-loading');
        }
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        if (submitting) return;

        var honeypot = form.querySelector('[name="gh_website_url"]');
        if (honeypot && honeypot.value) {
            showMessage('success', 'Talebiniz alındı. En kısa sürede dönüş yapılacaktır.');
            return;
        }

        if (!validateForm()) {
            return;
        }

        submitting = true;
        setLoading(true);

        var formData = new FormData(form);
        var data = {};
        formData.forEach(function (value, key) {
            if (key === 'gh_website_url') return;
            data[key] = value;
        });

        data.idempotency_key = idempotencyKey;

        if (window.GHQuoteList) {
            data.quote_items = window.GHQuoteList.getItems();
        }

        var xhr = new XMLHttpRequest();
        xhr.open('POST', config.restUrl + 'rfq/submit', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        if (config.nonce) {
            xhr.setRequestHeader('X-WP-Nonce', config.nonce);
        }

        xhr.onload = function () {
            submitting = false;
            setLoading(false);

            if (xhr.status >= 200 && xhr.status < 300) {
                showMessage('success', 'Teklif talebiniz başarıyla gönderildi. En kısa sürede sizinle iletişime geçeceğiz.');
                form.reset();
                resetIdempotencyKey();
                if (window.GHQuoteList) {
                    window.GHQuoteList.clear();
                }
            } else {
                var errorText = 'Bir hata oluştu. Lütfen tekrar deneyin.';
                try {
                    var res = JSON.parse(xhr.responseText);
                    if (res.message) {
                        errorText = res.message;
                    }
                } catch (ex) {
                    // Use default error text
                }
                showMessage('error', errorText);
                resetIdempotencyKey();
            }
        };

        xhr.onerror = function () {
            submitting = false;
            setLoading(false);
            showMessage('error', 'Bağlantı hatası oluştu. Lütfen internet bağlantınızı kontrol edin.');
            resetIdempotencyKey();
        };

        xhr.send(JSON.stringify(data));
    });

    form.addEventListener('blur', function (e) {
        if (e.target.hasAttribute('data-validate')) {
            validateField(e.target);
        }
    }, true);

    var searchInput = form.querySelector('.js-product-search');
    if (searchInput && config.searchUrl) {
        var searchResults = document.createElement('div');
        searchResults.className = 'gh-product-search-results';
        searchResults.setAttribute('role', 'listbox');
        searchResults.hidden = true;
        searchInput.parentNode.style.position = 'relative';
        searchInput.parentNode.appendChild(searchResults);

        var searchTimeout = null;

        searchInput.setAttribute('role', 'combobox');
        searchInput.setAttribute('aria-expanded', 'false');
        searchInput.setAttribute('aria-autocomplete', 'list');
        searchInput.setAttribute('aria-controls', 'product-search-results');
        searchResults.id = 'product-search-results';

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            var query = searchInput.value.trim();

            if (query.length < 2) {
                searchResults.hidden = true;
                searchInput.setAttribute('aria-expanded', 'false');
                return;
            }

            searchTimeout = setTimeout(function () {
                var xhr = new XMLHttpRequest();
                xhr.open('GET', config.searchUrl + '?s=' + encodeURIComponent(query), true);
                if (config.nonce) {
                    xhr.setRequestHeader('X-WP-Nonce', config.nonce);
                }

                xhr.onload = function () {
                    if (xhr.status !== 200) return;

                    try {
                        var products = JSON.parse(xhr.responseText);
                        if (!Array.isArray(products) || products.length === 0) {
                            searchResults.hidden = true;
                            searchInput.setAttribute('aria-expanded', 'false');
                            return;
                        }

                        var html = '';
                        for (var i = 0; i < products.length; i++) {
                            var p = products[i];
                            html += '<div class="gh-product-search-item" role="option" tabindex="-1"';
                            html += ' data-product-id="' + p.id + '"';
                            html += ' data-product-name="' + escAttr(p.name) + '"';
                            html += ' data-sku="' + escAttr(p.sku || '') + '"';
                            html += '>';
                            html += '<strong>' + esc(p.name) + '</strong>';
                            if (p.sku) {
                                html += ' <code>' + esc(p.sku) + '</code>';
                            }
                            html += '</div>';
                        }

                        searchResults.innerHTML = html;
                        searchResults.hidden = false;
                        searchInput.setAttribute('aria-expanded', 'true');
                    } catch (ex) {
                        searchResults.hidden = true;
                    }
                };

                xhr.send();
            }, 300);
        });

        searchResults.addEventListener('click', function (e) {
            var item = e.target.closest('.gh-product-search-item');
            if (!item) return;

            if (window.GHQuoteList) {
                window.GHQuoteList.addItem({
                    product_id: item.getAttribute('data-product-id'),
                    product_name: item.getAttribute('data-product-name'),
                    sku: item.getAttribute('data-sku'),
                    quantity: 1
                });
            }

            searchInput.value = '';
            searchResults.hidden = true;
            searchInput.setAttribute('aria-expanded', 'false');
            searchInput.focus();
        });

        document.addEventListener('click', function (e) {
            if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
                searchResults.hidden = true;
                searchInput.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function esc(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }

    function escAttr(str) {
        return (str || '')
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }
})();

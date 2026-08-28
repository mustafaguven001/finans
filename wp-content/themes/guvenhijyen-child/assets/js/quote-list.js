(function () {
    'use strict';

    var STORAGE_KEY = 'gh_quote_list';

    var QuoteList = {
        _items: [],

        init: function () {
            this._load();
            this._updateIndicators();
            this._bindGlobalEvents();
        },

        _load: function () {
            try {
                var raw = localStorage.getItem(STORAGE_KEY);
                if (raw) {
                    var parsed = JSON.parse(raw);
                    if (Array.isArray(parsed)) {
                        this._items = parsed;
                    }
                }
            } catch (e) {
                this._items = [];
            }
        },

        _save: function () {
            try {
                localStorage.setItem(STORAGE_KEY, JSON.stringify(this._items));
            } catch (e) {
                // Storage quota exceeded or unavailable
            }
        },

        getItems: function () {
            return this._items.slice();
        },

        getCount: function () {
            return this._items.length;
        },

        findIndex: function (productId, variationId) {
            variationId = variationId || 0;
            for (var i = 0; i < this._items.length; i++) {
                if (
                    this._items[i].product_id === productId &&
                    (this._items[i].variation_id || 0) === variationId
                ) {
                    return i;
                }
            }
            return -1;
        },

        addItem: function (data) {
            if (!data || !data.product_id) return false;

            var productId = parseInt(data.product_id, 10);
            var variationId = parseInt(data.variation_id || 0, 10);
            var quantity = Math.max(1, parseInt(data.quantity || 1, 10));
            var salesUnit = data.sales_unit || '';

            var idx = this.findIndex(productId, variationId);
            if (idx !== -1) {
                this._items[idx].quantity += quantity;
            } else {
                this._items.push({
                    product_id: productId,
                    variation_id: variationId,
                    quantity: quantity,
                    sales_unit: salesUnit,
                    product_name: data.product_name || '',
                    sku: data.sku || '',
                    added_at: Date.now()
                });
            }

            this._save();
            this._updateIndicators();
            this._emit('gh:quote:add', { product_id: productId, variation_id: variationId });
            this._syncToServer();
            return true;
        },

        removeItem: function (productId, variationId) {
            var idx = this.findIndex(productId, variationId || 0);
            if (idx === -1) return false;

            this._items.splice(idx, 1);
            this._save();
            this._updateIndicators();
            this._emit('gh:quote:remove', { product_id: productId, variation_id: variationId || 0 });
            this._syncToServer();
            return true;
        },

        updateQuantity: function (productId, variationId, quantity) {
            var idx = this.findIndex(productId, variationId || 0);
            if (idx === -1) return false;

            quantity = Math.max(1, parseInt(quantity, 10));
            this._items[idx].quantity = quantity;
            this._save();
            this._emit('gh:quote:update', { product_id: productId, variation_id: variationId || 0, quantity: quantity });
            return true;
        },

        clear: function () {
            this._items = [];
            this._save();
            this._updateIndicators();
            this._emit('gh:quote:clear', {});
        },

        _updateIndicators: function () {
            var count = this.getCount();
            var indicators = document.querySelectorAll('.gh-quote-indicator__count');
            for (var i = 0; i < indicators.length; i++) {
                indicators[i].textContent = count;
                indicators[i].setAttribute('data-count', count);
            }
        },

        _emit: function (name, detail) {
            var event;
            try {
                event = new CustomEvent(name, { detail: detail, bubbles: true });
            } catch (e) {
                event = document.createEvent('CustomEvent');
                event.initCustomEvent(name, true, true, detail);
            }
            document.dispatchEvent(event);
        },

        _syncToServer: function () {
            if (!window.ghQuoteList || !window.ghQuoteList.restUrl || !window.ghQuoteList.nonce) {
                return;
            }

            var url = window.ghQuoteList.restUrl + 'quote-list/sync';
            var body = JSON.stringify({ items: this._items });

            if (navigator.sendBeacon) {
                var blob = new Blob([body], { type: 'application/json' });
                navigator.sendBeacon(url, blob);
            } else {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', url, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.setRequestHeader('X-WP-Nonce', window.ghQuoteList.nonce);
                xhr.send(body);
            }
        },

        _bindGlobalEvents: function () {
            var self = this;

            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.js-add-to-quote');
                if (!btn) return;

                e.preventDefault();

                if (btn.disabled || btn.classList.contains('is-disabled')) {
                    return;
                }

                var productId = btn.getAttribute('data-product-id');
                var variationId = btn.getAttribute('data-variation-id') || 0;

                if (btn.getAttribute('data-requires-variation') === 'true' && !variationId) {
                    return;
                }

                var quantityInput = document.querySelector('.js-product-quantity');
                var quantity = quantityInput ? parseInt(quantityInput.value, 10) : 1;

                var salesUnitEl = document.querySelector('.gh-single-product__sales-unit strong');
                var salesUnit = salesUnitEl ? salesUnitEl.textContent.trim() : '';

                var result = self.addItem({
                    product_id: productId,
                    variation_id: variationId,
                    quantity: quantity,
                    sales_unit: salesUnit,
                    product_name: btn.getAttribute('data-product-name') || '',
                    sku: btn.getAttribute('data-sku') || ''
                });

                if (result) {
                    var originalText = btn.textContent;
                    btn.textContent = btn.getAttribute('data-added-text') || 'Eklendi!';
                    btn.classList.add('is-loading');
                    setTimeout(function () {
                        btn.textContent = originalText;
                        btn.classList.remove('is-loading');
                    }, 1500);
                }
            });
        },

        renderList: function (container) {
            if (!container) return;

            var items = this.getItems();

            if (items.length === 0) {
                container.innerHTML = '<p>' + (container.getAttribute('data-empty-text') || 'No items in your quote list.') + '</p>';
                return;
            }

            var html = '<table class="gh-quote-table"><thead><tr>';
            html += '<th>Product</th><th>SKU</th><th>Qty</th><th></th>';
            html += '</tr></thead><tbody>';

            for (var i = 0; i < items.length; i++) {
                var item = items[i];
                html += '<tr data-product-id="' + item.product_id + '" data-variation-id="' + (item.variation_id || 0) + '">';
                html += '<td>' + this._esc(item.product_name) + '</td>';
                html += '<td><code>' + this._esc(item.sku) + '</code></td>';
                html += '<td><input type="number" class="js-quote-qty" value="' + item.quantity + '" min="1" style="width:70px"></td>';
                html += '<td><button type="button" class="js-quote-remove gh-btn gh-btn--outline gh-btn--sm">&#10005;</button></td>';
                html += '</tr>';
            }

            html += '</tbody></table>';
            container.innerHTML = html;

            var self = this;

            container.addEventListener('click', function (e) {
                var removeBtn = e.target.closest('.js-quote-remove');
                if (removeBtn) {
                    var row = removeBtn.closest('tr');
                    self.removeItem(
                        parseInt(row.getAttribute('data-product-id'), 10),
                        parseInt(row.getAttribute('data-variation-id'), 10) || 0
                    );
                    self.renderList(container);
                }
            });

            container.addEventListener('change', function (e) {
                var qtyInput = e.target.closest('.js-quote-qty');
                if (qtyInput) {
                    var row = qtyInput.closest('tr');
                    self.updateQuantity(
                        parseInt(row.getAttribute('data-product-id'), 10),
                        parseInt(row.getAttribute('data-variation-id'), 10) || 0,
                        parseInt(qtyInput.value, 10)
                    );
                }
            });
        },

        _esc: function (str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str || ''));
            return div.innerHTML;
        }
    };

    QuoteList.init();

    window.GHQuoteList = QuoteList;
})();

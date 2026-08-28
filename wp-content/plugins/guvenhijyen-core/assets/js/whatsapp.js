(function ($) {
    'use strict';

    if (typeof ghWhatsApp === 'undefined') {
        return;
    }

    $(document).on('click', '.gh-whatsapp-btn--variable', function (e) {
        e.preventDefault();

        var $btn = $(this);
        var productId = $btn.data('product-id');
        var number = $btn.data('whatsapp-number');
        var companyName = $btn.data('company-name');

        var $variationInput = $('input[name="variation_id"], .variation_id');
        var variationId = 0;

        if ($variationInput.length) {
            variationId = parseInt($variationInput.val(), 10) || 0;
        }

        var $form = $('form.variations_form');
        if (!variationId && $form.length) {
            variationId = parseInt($form.find('input[name="variation_id"]').val(), 10) || 0;
        }

        if (!variationId) {
            alert(ghWhatsApp.selectVariation);
            return;
        }

        var productName = '';
        var $title = $('h1.product_title, .product_title');
        if ($title.length) {
            productName = $title.first().text().trim();
        }

        var variationText = '';
        $form.find('.variations select').each(function () {
            var label = $(this).closest('tr, .value').siblings('th, .label').text().trim().replace(':', '');
            var value = $(this).find('option:selected').text().trim();
            if (label && value) {
                variationText += label + ': ' + value + '\n';
            }
        });

        var message = 'Merhaba,\n\n';
        message += 'Asagidaki urun hakkinda bilgi almak istiyorum:\n\n';
        message += 'Urun: ' + productName + '\n';
        if (variationText) {
            message += variationText;
        }
        message += '\nUrun Linki: ' + window.location.href;

        var url = 'https://wa.me/' + number + '?text=' + encodeURIComponent(message);
        window.open(url, '_blank', 'noopener,noreferrer');
    });
})(jQuery);

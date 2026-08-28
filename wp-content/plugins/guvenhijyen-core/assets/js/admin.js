(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initRFQStatusForms();
        initImportForm();
        initRedirectValidation();
    });

    function initRFQStatusForms() {
        var forms = document.querySelectorAll('.gh-rfq-status-form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                var status = form.querySelector('[name="gh_rfq_status"]');
                if (status && status.value === 'cancelled') {
                    if (!confirm('Bu teklif talebini iptal etmek istediğinize emin misiniz?')) {
                        e.preventDefault();
                    }
                }
            });
        });
    }

    function initImportForm() {
        var form = document.getElementById('gh-import-form');
        if (!form) return;

        var fileInput = form.querySelector('input[type="file"]');
        var modeSelect = form.querySelector('[name="gh_import_mode"]');
        var submitBtn = form.querySelector('[type="submit"]');

        if (fileInput) {
            fileInput.addEventListener('change', function() {
                var file = this.files[0];
                if (!file) return;

                var maxSize = 50 * 1024 * 1024;
                if (file.size > maxSize) {
                    alert('Dosya boyutu 50MB\'dan büyük olamaz.');
                    this.value = '';
                    return;
                }

                var ext = file.name.split('.').pop().toLowerCase();
                if (ext !== 'xlsx') {
                    alert('Yalnızca .xlsx dosyaları kabul edilir.');
                    this.value = '';
                    return;
                }
            });
        }

        form.addEventListener('submit', function(e) {
            if (!fileInput || !fileInput.files[0]) {
                e.preventDefault();
                alert('Lütfen bir XLSX dosyası seçin.');
                return;
            }

            if (modeSelect && modeSelect.value === 'import') {
                if (!confirm('Import işlemi veritabanında değişiklik yapacaktır. Devam etmek istiyor musunuz?')) {
                    e.preventDefault();
                    return;
                }
            }

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'İşleniyor...';
            }
        });
    }

    function initRedirectValidation() {
        var form = document.getElementById('gh-redirect-form');
        if (!form) return;

        form.addEventListener('submit', function(e) {
            var source = form.querySelector('[name="source_url"]');
            var target = form.querySelector('[name="target_url"]');

            if (source && target && source.value === target.value) {
                e.preventDefault();
                alert('Kaynak ve hedef URL aynı olamaz (yönlendirme döngüsü).');
                return;
            }

            if (source && !source.value.startsWith('/')) {
                e.preventDefault();
                alert('Kaynak URL \'/\' ile başlamalıdır.');
                return;
            }
        });
    }
})();

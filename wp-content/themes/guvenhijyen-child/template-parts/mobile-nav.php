<?php

defined('ABSPATH') || exit;

$teklif_page = get_page_by_path('teklif-iste');
$teklif_url = $teklif_page ? get_permalink($teklif_page) : '#';

?>
<div class="gh-mobile-nav" id="mobile-nav" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Mobile navigation', 'guvenhijyen'); ?>" hidden>
    <div class="gh-mobile-nav__overlay" aria-hidden="true"></div>

    <div class="gh-mobile-nav__panel">
        <div class="gh-mobile-nav__header">
            <span class="gh-mobile-nav__title"><?php esc_html_e('Menu', 'guvenhijyen'); ?></span>
            <button type="button" class="gh-mobile-nav__close" aria-label="<?php esc_attr_e('Close menu', 'guvenhijyen'); ?>">
                <svg aria-hidden="true" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <nav class="gh-mobile-nav__body" aria-label="<?php esc_attr_e('Mobile menu', 'guvenhijyen'); ?>">
            <?php
            wp_nav_menu([
                'theme_location' => 'mobile',
                'container'      => false,
                'menu_class'     => 'gh-mobile-nav__list',
                'depth'          => 3,
                'fallback_cb'    => static function (): void {
                    wp_nav_menu([
                        'theme_location' => 'primary',
                        'container'      => false,
                        'menu_class'     => 'gh-mobile-nav__list',
                        'depth'          => 3,
                        'fallback_cb'    => false,
                    ]);
                },
                'walker'         => class_exists('GH_Mobile_Nav_Walker') ? new GH_Mobile_Nav_Walker() : null,
            ]);
            ?>
        </nav>

        <div class="gh-mobile-nav__footer">
            <a href="<?php echo esc_url($teklif_url); ?>" class="gh-btn gh-btn--primary gh-btn--full">
                <?php esc_html_e('Teklif İste', 'guvenhijyen'); ?>
            </a>
        </div>
    </div>
</div>

<script>
(function() {
    var nav = document.getElementById('mobile-nav');
    var hamburger = document.querySelector('.gh-hamburger');
    var closeBtn = nav.querySelector('.gh-mobile-nav__close');
    var overlay = nav.querySelector('.gh-mobile-nav__overlay');
    var panel = nav.querySelector('.gh-mobile-nav__panel');
    var previousFocus = null;
    var focusableSelector = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

    function openNav() {
        previousFocus = document.activeElement;
        nav.hidden = false;
        nav.classList.add('is-open');
        hamburger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
        closeBtn.focus();
        document.addEventListener('keydown', handleKeyDown);
    }

    function closeNav() {
        nav.classList.remove('is-open');
        hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleKeyDown);

        var handler = function() {
            nav.hidden = true;
            panel.removeEventListener('transitionend', handler);
        };
        panel.addEventListener('transitionend', handler);

        if (previousFocus && previousFocus.focus) {
            previousFocus.focus();
        }
    }

    function handleKeyDown(e) {
        if (e.key === 'Escape') {
            e.preventDefault();
            closeNav();
            return;
        }

        if (e.key === 'Tab') {
            var focusable = panel.querySelectorAll(focusableSelector);
            if (focusable.length === 0) {
                e.preventDefault();
                return;
            }
            var first = focusable[0];
            var last = focusable[focusable.length - 1];
            if (e.shiftKey) {
                if (document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                }
            } else {
                if (document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        }
    }

    hamburger.addEventListener('click', function() {
        if (nav.classList.contains('is-open')) {
            closeNav();
        } else {
            openNav();
        }
    });

    closeBtn.addEventListener('click', closeNav);
    overlay.addEventListener('click', closeNav);

    var submenuToggles = nav.querySelectorAll('.menu-item-has-children');
    submenuToggles.forEach(function(item) {
        var link = item.querySelector(':scope > a');
        var submenu = item.querySelector(':scope > .sub-menu');
        if (!submenu) return;

        submenu.hidden = true;

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'gh-mobile-nav__expand';
        toggle.setAttribute('aria-expanded', 'false');
        toggle.setAttribute('aria-label', link ? link.textContent.trim() + ' submenu' : 'Submenu');
        toggle.innerHTML = '<svg aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>';

        link.parentNode.insertBefore(toggle, submenu);

        toggle.addEventListener('click', function() {
            var expanded = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!expanded));
            submenu.hidden = expanded;
            toggle.querySelector('svg').style.transform = expanded ? '' : 'rotate(180deg)';
        });
    });
})();
</script>

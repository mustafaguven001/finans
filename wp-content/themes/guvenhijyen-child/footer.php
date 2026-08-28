<?php

defined('ABSPATH') || exit;

$company = gh_get_company_all();
$schema = gh_get_company_schema();

?>
</main>

<footer class="gh-footer" role="contentinfo">
    <div class="gh-container">
        <div class="gh-footer__grid">
            <div class="gh-footer__col">
                <h2 class="gh-footer__heading"><?php echo esc_html($company['company_name'] ?: get_bloginfo('name')); ?></h2>
                <?php if ($company['address']) : ?>
                    <p><?php echo esc_html($company['address']); ?></p>
                <?php endif; ?>
                <?php if ($company['district'] || $company['city']) : ?>
                    <p><?php echo esc_html(trim($company['district'] . ' / ' . $company['city'], ' /')); ?></p>
                <?php endif; ?>
                <div class="gh-footer__social">
                    <?php if ($company['instagram_url']) : ?>
                        <a href="<?php echo esc_url($company['instagram_url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if ($company['facebook_url']) : ?>
                        <a href="<?php echo esc_url($company['facebook_url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="Facebook">
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if ($company['linkedin_url']) : ?>
                        <a href="<?php echo esc_url($company['linkedin_url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                        </a>
                    <?php endif; ?>
                    <?php if ($company['youtube_url']) : ?>
                        <a href="<?php echo esc_url($company['youtube_url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="YouTube">
                            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="gh-footer__col">
                <h2 class="gh-footer__heading"><?php esc_html_e('Quick Links', 'guvenhijyen'); ?></h2>
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer',
                    'container'      => false,
                    'menu_class'     => 'gh-footer__list',
                    'depth'          => 1,
                    'fallback_cb'    => false,
                ]);
                ?>
            </div>

            <div class="gh-footer__col">
                <h2 class="gh-footer__heading"><?php esc_html_e('Product Categories', 'guvenhijyen'); ?></h2>
                <ul class="gh-footer__list">
                    <?php
                    $footer_cats = get_terms([
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => true,
                        'parent'     => 0,
                        'number'     => 8,
                    ]);
                    if ($footer_cats && !is_wp_error($footer_cats)) :
                        foreach ($footer_cats as $cat) : ?>
                            <li>
                                <a href="<?php echo esc_url(get_term_link($cat)); ?>">
                                    <?php echo esc_html($cat->name); ?>
                                </a>
                            </li>
                        <?php endforeach;
                    endif;
                    ?>
                </ul>
            </div>

            <div class="gh-footer__col">
                <h2 class="gh-footer__heading"><?php esc_html_e('Contact', 'guvenhijyen'); ?></h2>
                <ul class="gh-footer__list">
                    <?php if ($company['phone']) : ?>
                        <li>
                            <a href="tel:<?php echo esc_attr(preg_replace('/[^0-9+]/', '', $company['phone'])); ?>">
                                <?php echo esc_html($company['phone']); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($company['email']) : ?>
                        <li>
                            <a href="mailto:<?php echo esc_attr($company['email']); ?>">
                                <?php echo esc_html($company['email']); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($company['whatsapp']) : ?>
                        <li>
                            <a href="<?php echo esc_url(gh_whatsapp_url()); ?>" target="_blank" rel="noopener noreferrer">
                                WhatsApp: <?php echo esc_html($company['whatsapp']); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                    <?php if ($company['address']) : ?>
                        <li><?php echo esc_html($company['address']); ?></li>
                    <?php endif; ?>
                    <?php if ($company['map_url']) : ?>
                        <li>
                            <a href="<?php echo esc_url($company['map_url']); ?>" target="_blank" rel="noopener noreferrer">
                                <?php esc_html_e('View on Map', 'guvenhijyen'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <div class="gh-footer__bottom">
            <p>&copy; <?php echo esc_html(date_i18n('Y')); ?> <?php echo esc_html($company['company_name'] ?: get_bloginfo('name')); ?>. <?php esc_html_e('All rights reserved.', 'guvenhijyen'); ?></p>
            <nav aria-label="<?php esc_attr_e('Legal links', 'guvenhijyen'); ?>">
                <?php
                $privacy_page = get_privacy_policy_url();
                if ($privacy_page) : ?>
                    <a href="<?php echo esc_url($privacy_page); ?>"><?php esc_html_e('KVKK / Privacy Policy', 'guvenhijyen'); ?></a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
</footer>

<button class="gh-back-to-top" type="button" aria-label="<?php esc_attr_e('Back to top', 'guvenhijyen'); ?>">
    <svg aria-hidden="true" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="18 15 12 9 6 15"/></svg>
</button>

<?php if (!empty($schema)) : ?>
<script type="application/ld+json"><?php echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<?php endif; ?>

<script>
(function() {
    var header = document.getElementById('site-header');
    var backToTop = document.querySelector('.gh-back-to-top');
    var topbar = document.querySelector('.gh-topbar');
    var stickyOffset = topbar ? topbar.offsetHeight : 0;
    var ticking = false;

    function onScroll() {
        if (!ticking) {
            window.requestAnimationFrame(function() {
                if (window.scrollY > stickyOffset) {
                    header.classList.add('is-sticky');
                    document.body.classList.add('has-sticky-header');
                } else {
                    header.classList.remove('is-sticky');
                    document.body.classList.remove('has-sticky-header');
                }
                if (backToTop) {
                    backToTop.classList.toggle('is-visible', window.scrollY > 400);
                }
                ticking = false;
            });
            ticking = true;
        }
    }

    window.addEventListener('scroll', onScroll, { passive: true });

    if (backToTop) {
        backToTop.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }
})();
</script>

<?php wp_footer(); ?>
</body>
</html>

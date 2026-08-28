<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Widget_Product_Grid extends \Elementor\Widget_Base {

    public function get_name(): string {
        return 'gh_product_grid';
    }

    public function get_title(): string {
        return 'Ürün Grid';
    }

    public function get_icon(): string {
        return 'eicon-products';
    }

    public function get_categories(): array {
        return [ 'guvenhijyen' ];
    }

    protected function register_controls(): void {
        $this->start_controls_section( 'content_section', [
            'label' => 'Sorgu',
            'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
        ] );

        $this->add_control( 'source', [
            'label'   => 'Kaynak',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => 'featured',
            'options' => [
                'featured' => 'Öne Çıkan',
                'recent'   => 'Son Eklenen',
                'category' => 'Kategori',
                'brand'    => 'Marka',
            ],
        ] );

        $this->add_control( 'category', [
            'label'     => 'Kategori Slug',
            'type'      => \Elementor\Controls_Manager::TEXT,
            'condition' => [ 'source' => 'category' ],
        ] );

        $this->add_control( 'brand', [
            'label'     => 'Marka Slug',
            'type'      => \Elementor\Controls_Manager::TEXT,
            'condition' => [ 'source' => 'brand' ],
        ] );

        $this->add_control( 'limit', [
            'label'   => 'Ürün Sayısı',
            'type'    => \Elementor\Controls_Manager::NUMBER,
            'default' => 8,
            'min'     => 1,
            'max'     => 24,
        ] );

        $this->add_control( 'columns', [
            'label'   => 'Sütun Sayısı',
            'type'    => \Elementor\Controls_Manager::SELECT,
            'default' => '4',
            'options' => [
                '2' => '2',
                '3' => '3',
                '4' => '4',
            ],
        ] );

        $this->add_control( 'heading', [
            'label'   => 'Başlık',
            'type'    => \Elementor\Controls_Manager::TEXT,
            'default' => '',
        ] );

        $this->end_controls_section();
    }

    protected function render(): void {
        $settings = $this->get_settings_for_display();

        $args = [
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => (int) $settings['limit'],
        ];

        switch ( $settings['source'] ) {
            case 'featured':
                $args['tax_query'] = [ [
                    'taxonomy' => 'product_visibility',
                    'field'    => 'name',
                    'terms'    => 'featured',
                ] ];
                break;

            case 'recent':
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
                break;

            case 'category':
                if ( ! empty( $settings['category'] ) ) {
                    $args['tax_query'] = [ [
                        'taxonomy' => 'product_cat',
                        'field'    => 'slug',
                        'terms'    => sanitize_title( $settings['category'] ),
                    ] ];
                }
                break;

            case 'brand':
                if ( ! empty( $settings['brand'] ) ) {
                    $args['tax_query'] = [ [
                        'taxonomy' => 'product_brand',
                        'field'    => 'slug',
                        'terms'    => sanitize_title( $settings['brand'] ),
                    ] ];
                }
                break;
        }

        $query = new WP_Query( $args );

        if ( ! $query->have_posts() ) {
            return;
        }

        if ( ! empty( $settings['heading'] ) ) {
            printf( '<h2 class="gh-section-heading">%s</h2>', esc_html( $settings['heading'] ) );
        }

        printf(
            '<div class="gh-product-grid" style="display:grid;grid-template-columns:repeat(%s,1fr);gap:20px;">',
            esc_attr( $settings['columns'] )
        );

        while ( $query->have_posts() ) {
            $query->the_post();
            $template = locate_template( 'template-parts/product-card.php' );
            if ( $template ) {
                include $template;
            } else {
                $this->render_fallback_card();
            }
        }

        echo '</div>';

        wp_reset_postdata();
    }

    private function render_fallback_card(): void {
        $product = wc_get_product( get_the_ID() );
        if ( ! $product ) {
            return;
        }

        ?>
        <div class="gh-product-card">
            <div class="gh-product-card__image">
                <a href="<?php echo esc_url( get_permalink() ); ?>">
                    <?php echo wp_kses_post( $product->get_image( 'woocommerce_thumbnail' ) ); ?>
                </a>
            </div>
            <div class="gh-product-card__body">
                <h3 class="gh-product-card__title">
                    <a href="<?php echo esc_url( get_permalink() ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
                </h3>
                <?php if ( $product->get_sku() ) : ?>
                <div class="gh-product-card__sku"><?php echo esc_html( $product->get_sku() ); ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}

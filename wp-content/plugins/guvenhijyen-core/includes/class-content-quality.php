<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Content_Quality {

    private static ?self $instance = null;

    private const QUALITY_STATES = [
        'unreviewed'    => 'İncelenmedi',
        'needs_rewrite' => 'Yeniden Yazılmalı',
        'approved'      => 'Onaylandı',
    ];

    public static function instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void {
        add_action( 'add_meta_boxes', [ $this, 'add_meta_box' ] );
        add_action( 'save_post_post', [ $this, 'save_meta' ] );
        add_filter( 'pre_get_posts', [ $this, 'filter_frontend_posts' ] );
        add_filter( 'manage_posts_columns', [ $this, 'add_admin_column' ] );
        add_action( 'manage_posts_custom_column', [ $this, 'render_admin_column' ], 10, 2 );
    }

    public function get_quality_status( int $post_id ): string {
        $status = get_post_meta( $post_id, '_gh_content_quality_status', true );
        return array_key_exists( $status, self::QUALITY_STATES ) ? $status : 'unreviewed';
    }

    public function set_quality_status( int $post_id, string $status ): bool {
        if ( ! array_key_exists( $status, self::QUALITY_STATES ) ) {
            return false;
        }
        update_post_meta( $post_id, '_gh_content_quality_status', $status );
        return true;
    }

    public function is_content_approved( int $post_id ): bool {
        return $this->get_quality_status( $post_id ) === 'approved';
    }

    public function add_meta_box(): void {
        add_meta_box(
            'gh_content_quality',
            'İçerik Kalite Durumu',
            [ $this, 'render_meta_box' ],
            'post',
            'side',
            'high'
        );
    }

    public function render_meta_box( \WP_Post $post ): void {
        wp_nonce_field( 'gh_content_quality_' . $post->ID, 'gh_content_quality_nonce' );
        $current = $this->get_quality_status( $post->ID );

        echo '<select name="gh_content_quality_status" style="width:100%">';
        foreach ( self::QUALITY_STATES as $key => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $key ),
                selected( $current, $key, false ),
                esc_html( $label )
            );
        }
        echo '</select>';

        if ( $current !== 'approved' && get_post_status( $post ) === 'publish' ) {
            echo '<p style="color:#d63638;margin-top:8px;"><strong>';
            echo esc_html__( 'Dikkat: Bu yazı yayında ancak içerik onaylanmadı. Frontend\'de görünmeyecek.', 'guvenhijyen' );
            echo '</strong></p>';
        }
    }

    public function save_meta( int $post_id ): void {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! isset( $_POST['gh_content_quality_nonce'] ) ||
             ! wp_verify_nonce( sanitize_key( $_POST['gh_content_quality_nonce'] ), 'gh_content_quality_' . $post_id ) ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['gh_content_quality_status'] ) ) {
            $status = sanitize_key( $_POST['gh_content_quality_status'] );
            $this->set_quality_status( $post_id, $status );
        }
    }

    public function filter_frontend_posts( \WP_Query $query ): void {
        if ( is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( ! $query->is_home() && ! $query->is_category() && ! $query->is_tag() && ! $query->is_archive() ) {
            return;
        }

        if ( $query->get( 'post_type' ) !== '' && $query->get( 'post_type' ) !== 'post' ) {
            return;
        }

        $meta_query = $query->get( 'meta_query', [] );
        if ( ! is_array( $meta_query ) ) {
            $meta_query = [];
        }

        $meta_query[] = [
            'key'   => '_gh_content_quality_status',
            'value' => 'approved',
        ];

        $query->set( 'meta_query', $meta_query );
    }

    public function add_admin_column( array $columns ): array {
        $new_columns = [];
        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;
            if ( $key === 'title' ) {
                $new_columns['gh_quality'] = 'Kalite';
            }
        }
        return $new_columns;
    }

    public function render_admin_column( string $column, int $post_id ): void {
        if ( $column !== 'gh_quality' ) {
            return;
        }

        $status = $this->get_quality_status( $post_id );
        $colors = [
            'unreviewed'    => '#dba617',
            'needs_rewrite' => '#d63638',
            'approved'      => '#00a32a',
        ];
        $color = $colors[ $status ] ?? '#666';
        $label = self::QUALITY_STATES[ $status ] ?? $status;

        printf(
            '<span style="color:%s;font-weight:600;">%s</span>',
            esc_attr( $color ),
            esc_html( $label )
        );
    }
}

<?php
/**
 * Güven Hijyen - Security Headers (must-use plugin for early loading)
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_action( 'send_headers', function () {
    if ( is_admin() ) {
        return;
    }

    header( 'X-Content-Type-Options: nosniff' );
    header( 'X-Frame-Options: SAMEORIGIN' );
    header( 'Referrer-Policy: strict-origin-when-cross-origin' );
    header( 'Permissions-Policy: camera=(), microphone=(), geolocation=()' );
    header( 'X-XSS-Protection: 0' );
} );

add_filter( 'wp_headers', function ( $headers ) {
    unset( $headers['X-Powered-By'] );
    return $headers;
} );

add_action( 'wp_head', function () {
    remove_action( 'wp_head', 'wp_generator' );
}, 1 );

remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );

add_filter( 'xmlrpc_enabled', '__return_false' );

add_filter( 'rest_authentication_errors', function ( $result ) {
    if ( true === $result || is_wp_error( $result ) ) {
        return $result;
    }

    $public_namespaces = [
        'guvenhijyen/v1/rfq/submit',
        'guvenhijyen/v1/products/search',
        'guvenhijyen/v1/quote-list',
    ];

    $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

    foreach ( $public_namespaces as $ns ) {
        if ( strpos( $request_uri, $ns ) !== false ) {
            return $result;
        }
    }

    if ( ! is_user_logged_in() ) {
        return $result;
    }

    return $result;
} );

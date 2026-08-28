<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class GH_Rate_Limiter {

    private string $prefix;
    private int $max_requests;
    private int $window_seconds;

    public function __construct( string $action, int $max_requests = 5, int $window_seconds = 3600 ) {
        $this->prefix         = 'gh_rl_' . $action . '_';
        $this->max_requests   = $max_requests;
        $this->window_seconds = $window_seconds;
    }

    public function is_allowed( string $identifier = '' ): bool {
        if ( empty( $identifier ) ) {
            $identifier = $this->get_client_ip();
        }

        $key   = $this->prefix . md5( $identifier );
        $count = (int) get_transient( $key );

        return $count < $this->max_requests;
    }

    public function record( string $identifier = '' ): void {
        if ( empty( $identifier ) ) {
            $identifier = $this->get_client_ip();
        }

        $key   = $this->prefix . md5( $identifier );
        $count = (int) get_transient( $key );

        set_transient( $key, $count + 1, $this->window_seconds );
    }

    public function get_remaining( string $identifier = '' ): int {
        if ( empty( $identifier ) ) {
            $identifier = $this->get_client_ip();
        }

        $key   = $this->prefix . md5( $identifier );
        $count = (int) get_transient( $key );

        return max( 0, $this->max_requests - $count );
    }

    private function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ( $headers as $header ) {
            if ( ! empty( $_SERVER[ $header ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}

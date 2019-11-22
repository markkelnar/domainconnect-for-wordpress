<?php

namespace WPE\Domainconnect;

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once plugin_dir_path( __FILE__ ) . 'src/wpengine/api/sycnhronous.php';

    function login( $args, $assoc_args ) {
		if ( defined('AUTH_API_WPENGINE_USERNAME') ) {
            $synchronous_api = new WpengineApiSynchronous();
    		if ( $synchronous_api->login() ) {
    			\WP_CLI::error( "Didn't login ");
	    	}
            \WP_CLI::success( sprintf( 'Login token %s', $synchronous_api->api_token ) );
        }
	}

    function domainconnect( $args, $assoc_args ) {
        $synchronous_api = new WpengineApiSynchronous();
		if ( ! $synchronous_api->login() ) {
			\WP_CLI::error( "Didn't login " );
		}

		$domain = $assoc_args['domain'];
		$ip = $assoc_args['ip'] ?: '127.0.0.1';
		$site = $assoc_args['site'] ?: 'foo';
		if ( ! $domain ) {
			\WP_CLI::error( 'You need to specify a domain. Ex. --domain=example.com' );
		}

		$dashboard_url = $synchronous_api->discovery( $domain, $ip, $site );
		if ( ! $dashboard_url ) {
			\WP_CLI::error( 'Domain does not support domain connect' );
		}

		$display_name = $synchronous_api->provider_display_name();
		\WP_CLI::success( sprintf( "Domain connect supported at %s. Open this in browser\n%s", $display_name, $dashboard_url ) );
	}

	\WP_CLI::add_command( 'login', __NAMESPACE__ . '\login' );
	\WP_CLI::add_command( 'domainconnect', __NAMESPACE__ . '\domainconnect' );
}

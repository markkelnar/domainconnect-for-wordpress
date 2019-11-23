<?php

namespace DomainconnectWP;

require_once plugin_dir_path( __FILE__ ) . 'src/discover/domain_discovery.php';
require_once plugin_dir_path( __FILE__ ) . 'src/discover/synchronous_provider.php';
require_once plugin_dir_path( __FILE__ ) . 'src/discover/template_exampleservice_domainconnect_org.php';
require_once plugin_dir_path( __FILE__ ) . 'src/wpengine/api/sycnhronous.php';
require_once plugin_dir_path( __FILE__ ) . 'src/wpengine/api/sycnhronous.php';

/**
 * Initialize
 */
function domainconnect_shortcode( $atts = [], $content = null, $tag = '' ) {
	$atts = normalize_attributes( $atts, $tag );

	// start output
	$o = '';

	$domain = get_domain_from_input( $atts );
	if ( $domain ) {
		$is_supported = false;

		// Decide to use local discovery or an api service provider
		if ( defined('AUTH_API_WPENGINE_USERNAME') ) {
			$is_supported = checkSupportedWpengine( $domain );
		} else {
			$dc = new DomainDiscovery( $domain );
			$dc->discover();
			$is_supported = (
				$dc->provider_supports_synchronous() &&
				checkSupportedExampleTemplate( $dc->get_provider_api() )
			);
		}

		if ( show_content( $atts, $is_supported ) ) {
			$o .= '<div class="domainconnect-box">';
			// default message if custom one is not specified
			if ( empty( $content ) ) {
				if ( $is_supported ) {
					$content = sprintf( '[domainconnect_url domain=%s /]', $domain );
					$o .= do_shortcode( $content );
				} else {
					$o .= 'Follow manual steps to setup DNS';
				}
			} else {
				// secure output by executing the_content filter hook on $content
				$content = apply_filters( 'the_content', $content );

				// run shortcode parser recursively
				$o .= do_shortcode( $content );
			}
			$o .= '</div>';
		}
	}

	return $o;
}

/**
 * Initialize
 */
function domainconnect_url_shortcode( $atts = [], $content = null, $tag = '' ) {
	$atts = normalize_attributes( $atts, $tag );

	// start output
	$o = '';

	$domain = get_domain_from_input( $atts );
	if ( $domain ) {
		$is_supported = false;

		// Decide to use local discovery or an api service provider
		if ( defined('AUTH_API_WPENGINE_USERNAME') ) {
			$is_supported = checkSupportedWpengine( $domain );
			if ( $is_supported ) {
				$link_for_customer = getUrlWPengineApi( $domain );
				$display_name = getDnsDisplayNameWpengine( $domain );
			}
		} else {
			$dc = new DomainDiscovery( $domain );
			$dc->discover();
			if ( $dc->provider_supports_synchronous() ) {
				$is_supported = checkSupportedExampleTemplate( $dc->get_provider_api() );
				if ( $is_supported ) {
					$link_for_customer = getUrlExampleTemplate( $domain, $dc->get_provider_dashboard_url() );
					$display_name = $dc->provider_display_name();
				}
			}
		}

		if ( $is_supported ) {
			// default message if custom one is not specified
			if ( empty( $content ) ) {
				$content = $display_name;
			}
			$o .= '<a href="' . esc_url( $link_for_customer ) . '" target="_new">';
			$o .= esc_html__( $content, 'domainconnect' );
			$o .= '</a>';
		}
	}

	return $o;
}

function checkSupportedExampleTemplate ( $url ) {
	$service_provider_id       = TemplateExampleServiceDomainConnectOrg::PROVIDER_ID;
	$service_provider_template = TemplateExampleServiceDomainConnectOrg::TEMPLATE_SERVICE_ID;
	$provider                  = new SynchronousProvider( $url );
	return $provider->query_template_support( $service_provider_id, $service_provider_template );
}

function getUrlExampleTemplate( $domain, $service_provider_dashboard_url ) {
	$ip = '1.2.3.4';
	$randomtext = 'hello';
	$synchronous_template = new TemplateExampleServiceDomainConnectOrg( $domain, $ip, $randomtext );

	return $synchronous_template->synchronous_dashboard_apply_url( $service_provider_dashboard_url );
}

function checkSupportedWpengine ( $domain ) {
	return !! getUrlWPengineApi( $domain );
}

function getDnsDisplayNameWpengine( $domain ) {
	$ip = '1.2.3.4';
	$site = 'mark';
	$service_provider = new WpengineApiSynchronous();
	$login = $service_provider->login();
	$service_provider->discovery( $domain, $ip, $site );
	return $service_provider->provider_display_name();
}

function getUrlWPengineApi( $domain ) {
	$ip = '1.2.3.4';
	$site = 'mark';
	$service_provider = new WpengineApiSynchronous();
	$service_provider->login();
	return $service_provider->discovery( $domain, $ip, $site );
}

/**
 * Override default attributes with user attributes
 * dest is the ip address or host to cname to
 */
function normalize_attributes( $atts, $tag ) {
	// normalize attribute keys, lowercase
	$atts = array_change_key_case( (array) $atts, CASE_LOWER );

	// override default attributes with user attributes
	// dest is the ip address or host to cname to
	$atts = shortcode_atts(
		[
			'domain' => '',
			'when'   => 'supported',
			'dest'   => '127.0.0.1',
		],
		$atts,
		$tag
	);
	return $atts;
}

/**
 * Look at attribute of when to show the content message
 */
function show_content( $atts, $supports_domainconnect ) {
	if ( $supports_domainconnect && 'supported' == $atts['when'] ) {
		return true;
	} else if ( ! $supports_domainconect && 'unsupported' == $atts['when'] ) {
		return true;
	}
}

/**
 * Return the domain from input.  Assume it's the root/apex domain
 * Discovery must work on the root domain (zone) only.
 */
function get_domain_from_input( $atts ) {
	return $atts['domain'] ?: '';
}


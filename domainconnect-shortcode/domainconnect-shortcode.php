<?php
/**
 * Plugin Name:     Domain Connect Shortcode
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     A way to expose easy domain setup links for domains that support domain connect
 * Author:          Mark Kelnar
 * Author URI:      YOUR SITE HERE
 * Text Domain:     domainconnect-shortcode
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Domainconnect_Shortcode
 */

namespace WPE\Domainconnect;

/**
 * [domainconnect] [domainconnect_url]
 */
function domainconnect_shortcodes_init() {
	require_once plugin_dir_path( __FILE__ ) . 'src/domain_discovery.php';
	require_once plugin_dir_path( __FILE__ ) . 'src/syncflow_dns_provider.php';
	require_once plugin_dir_path( __FILE__ ) . 'src/template_exampleservice_domainconnect_org.php';

	add_shortcode( 'domainconnect', __NAMESPACE__ . '\domainconnect_shortcode' );
	add_shortcode( 'domainconnect_url', __NAMESPACE__ . '\domainconnect_url_shortcode' );
}
add_action( 'init', __NAMESPACE__ . '\domainconnect_shortcodes_init' );

/**
 * Initialize
 */
function domainconnect_shortcode( $atts = [], $content = null, $tag = '' ) {
	$atts = normalize_attributes( $atts, $tag );

	// start output
	$o = '';

	// configurable settings, should come from WP options or environment
	$service_provider_id       = 'wpengine.com';
	$service_provider_template = 'arecord';
	$is_supported              = false;

	$domain = get_domain_from_input( $atts );
	if ( $domain ) {
		$dc = new DomainDiscovery( $domain );
		$dc->discover();
		if ( $dc->provider_supports_synchronous() ) {
			$provider     = new SyncflowDnsProvider( $dc->get_provider_api() );
			$is_supported = $provider->query_template_support( $service_provider_id, $service_provider_template );
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

	// configurable settings, should come from WP options or environment
	$service_provider_id       = 'wpengine.com';
	$service_provider_template = 'arecord';
	$is_supported              = false;

	$domain = get_domain_from_input( $atts );
	if ( $domain ) {
		$dc = new DomainDiscovery( $domain );
		$dc->discover();
		if ( $dc->provider_supports_synchronous() ) {
			$provider     = new SyncflowDnsProvider( $dc->get_provider_api() );
			$is_supported = $provider->query_template_support( $service_provider_id, $service_provider_template );
		}

		if ( $is_supported ) {
			$ip = '1.2.3.4';
			$randomtext = 'hello';
			$synchronous_template = new TemplateExampleServiceDomainConnectOrg( $domain, $ip, $randomtext );

			$link_for_customer = $synchronous_template->synchronous_dashboard_apply_url( $dc->get_provider_dashboard_url() );

			// default message if custom one is not specified
			if ( empty( $content ) ) {
				$content = $dc->provider_display_name();
			}
			$o .= '<a href="' . esc_url( $link_for_customer ) . '">';
			$o .= esc_html__( $content, 'domainconnect' );
			$o .= '</a>';
		}
	}

	return $o;
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

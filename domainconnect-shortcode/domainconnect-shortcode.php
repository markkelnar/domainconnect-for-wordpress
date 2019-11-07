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
 * [domainconnect]
 */
function domainconnect_shortcodes_init() {
	require_once plugin_dir_path( __FILE__ ) . 'src/domain_functions.php';
	require_once plugin_dir_path( __FILE__ ) . 'src/provider_functions.php';

	add_shortcode( 'domainconnect', __NAMESPACE__.'\domainconnect_shortcode' );
}
add_action( 'init', __NAMESPACE__.'\domainconnect_shortcodes_init' );

/**
 * Initialize
 */
function domainconnect_shortcode( $atts = [], $content = null, $tag = '' ) {
	// normalize attribute keys, lowercase
	$atts = array_change_key_case( (array) $atts, CASE_LOWER );

	// override default attributes with user attributes
	$domainconnect_atts = shortcode_atts(
		[
			'supported'   => 'Click to setup DNS with your domain provider.',
			'unsupported' => 'See our instructions to manually setup DNS for your domain',
			'dest'        => '127.0.0.1', # ip address or host to cname to
			'domain'      => 'domain.example.com',
		],
		$atts,
		$tag
	);

	// start output
	$o = '';

	// configurable settings, should come from WP options or environment
	$service_provider_id       = 'wpengine.com';
	$service_provider_template = 'arecord';

	$is_supported = false;

	$domain = get_domain_from_input($domainconnect_atts);
	if ( $domain ) {
		$dc = new DomainFunctions( $domain );
		$dc->discover();
		if ( $dc->provider_supports_synchronous() ) {
			$provider     = new ProviderFunctions( $dc->get_provider_api() );
			$is_supported = $provider->query_template_support( $service_provider_id, $service_provider_template );
		}
	}

	// start box
	$o .= '<div class="domainconnect-box">';

	if ( $dc && $is_supported ) {
		$link_for_customer = $dc->build_synchronous_dashboard_apply_url( $service_provider_id, $service_provider_template );
		$o                .= '<a href="' . esc_url( $link_for_customer ) . '">' .
			esc_html__( $domainconnect_atts['supported'], 'domainconnect' ) .
			'</a>';
	} else {
		$o .= '<p>' . esc_html__( $domainconnect_atts['unsupported'], 'domainconnect' ) . '</p>';
	}

	// enclosing tags
	if ( ! is_null( $content ) ) {
		// secure output by executing the_content filter hook on $content
		$o .= apply_filters( 'the_content', $content );

		// run shortcode parser recursively
		$o .= do_shortcode( $content );
	}

	// end box
	$o .= '</div>';

	// return output
	return $o;
}

/**
 * Return the domain from input.  Assume it's the root/apex domain
 * Discovery must work on the root domain (zone) only.
 */
function get_domain_from_input($attrs) {
	return $attrs['domain'] ?: '';
}

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

namespace Domainconnect;

// [domainconnect]
function domainconnect_shortcodes_init()
{
    require_once plugin_dir_path( __FILE__ ) . 'src/domain_functions.php';
    require_once plugin_dir_path( __FILE__ ) . 'src/provider_functions.php';

    add_shortcode('domainconnect', 'Domainconnect\domainconnect_shortcode');
}
add_action('init', 'Domainconnect\domainconnect_shortcodes_init' );

// TODO: the transient cached data should all be one array of data, not separately

/**
 * Initialize
 */
function domainconnect_shortcode($atts = [], $content = null, $tag = '')
{
    // normalize attribute keys, lowercase
    $atts = array_change_key_case((array)$atts, CASE_LOWER);

    // override default attributes with user attributes
    $domainconnect_atts = shortcode_atts([
                                 'button' => 'Easy DNS',
                                 'help' => 'Click to setup DNS with your domain provider.',
                                 'supported' => 'This domain is at a DNS provider that supports Domain Connect',
                                 'unsupported' => 'See our instructions to manually setup DNS for your domain',
                         ], $atts, $tag);

    // start output
    $o = '';

    // configurable settings, should come from WP options or environment
    $service_provider_id = 'wpengine.com';
    $service_provider_template = 'arecord';

    $is_supported = false;

    $domain = get_domain_from_input();
    if ( $domain ) {
        $dc = new DomainFunctions( $domain );
        $dc->discover();
        if ( $dc->provider_supports_synchronous() ) {
            $provider = new ProviderFunctions( $dc->get_provider_api() );
            $is_supported = $provider->query_template_support($service_provider_id, $service_provider_template);
        }
    }

    // start box
    $o .= '<div class="domainconnect-box">';

    if ( $is_supported ) {
        $o .= '<p>' . esc_html__($domainconnect_atts['supported'], 'domainconnect') . '</p>';
        $o .= "<p> $domain : " . $dc->provider_display_name() . "</p>";

        // button
        $link_for_customer = $dc->build_synchronous_dashboard_apply_url($service_provider_id, $service_provider_template);
        $o .= '<a href="' . esc_url($link_for_customer) . '">' .
            esc_html__($domainconnect_atts['button'], 'domainconnect') .
            '</a>';

        // help text
        $o .= '<p>' . esc_html__($domainconnect_atts['help'], 'domainconnect') . '</p>';
    } else {
        $o .= '<p>' . esc_html__($domainconnect_atts['unsupported'], 'domainconnect') . '</p>';
    }

    // enclosing tags
    if (!is_null($content)) {
        // secure output by executing the_content filter hook on $content
        $o .= apply_filters('the_content', $content);

        // run shortcode parser recursively
        $o .= do_shortcode($content);
    }

    // end box
    $o .= '</div>';

    // return output
    return $o;
}

// Return the domain from input.  Assume it's the root/apex domain
// Discovery must work on the root domain (zone) only.
function get_domain_from_input()
{
    return $_GET['domain'] ?: '';
}

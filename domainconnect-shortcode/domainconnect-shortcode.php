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

CONST WP_CACHE_24HR = 86400;

// [domainconnect]
function domainconnect_shortcodes_init()
{
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
    $provider_url = provider_discovery( $domain );
    if ( $provider_url &&
        get_provider_supports_synchronous( $provider_url, $domain ) &&
        query_template_support($provider_url, $domain, $service_provider_id, $service_provider_template)
    ) {
        $is_supported = true;
    }

    // start box
    $o .= '<div class="domainconnect-box">';

    if ( $is_supported ) {
        $o .= '<p>' . esc_html__($domainconnect_atts['supported'], 'domainconnect') . '</p>';
        $o .= "<p> $domain : " . get_provider_display_name( $provider_url, $domain ) . "</p>";

        // button
        $link_for_customer = build_synchronous_dashboard_apply_url($provider_url, $domain, $service_provider_id, $service_provider_template);
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

// return url to api for dns provider of this domain that supports domain connect.
function provider_discovery($domain)
{
    $cache_key = 'domainconnect_plugin_discovery_'. $domain;
    $provider_url = get_transient( $cache_key );
    if ( false === $provider_url ) {
        // dig TXT record for _domainconnect.$domain should be on the APEX of the domain
        $check_domain = '_domainconnect.' . $domain;

        $dns = dns_get_record($check_domain, DNS_TXT);
        if ( isset( $dns[0]['txt'] ) ) {
            $provider_url = $dns[0]['txt'];
        } else {
            // write something to cache
            $provider_url = 'not-supported';
        }

        set_transient( $cache_key, $provider_url, WP_CACHE_24HR );
    }

    return $provider_url;
}

function get_provider_display_name($provider_url, $domain)
{
    $provider_settings = get_provider_settings($provider_url, $domain);
    return $provider_settings['providerDisplayName'] ?: $provider_settings['providerName'];
}

function get_provider_supports_synchronous($provider_url, $domain)
{
    $provider_settings = get_provider_settings($provider_url, $domain);
    return isset ( $provider_settings['urlSyncUX'] );
}

function get_provider_dashboard_url($provider_url, $domain)
{
    $provider_settings = get_provider_settings($provider_url, $domain);
    return $provider_settings['urlSyncUX'] ?: false;
}

function get_provider_api($provider_url, $domain)
{
    $provider_settings = get_provider_settings($provider_url, $domain);
    return $provider_settings['urlAPI'] ?: false;
}

function get_provider_settings($provider_url, $domain)
{
    // https://{_domainconnect}/v2/{domain}/settings
    $url = sprintf(
        "https://%s/v2/%s/settings",
        $provider_url,
        $domain
    );

    $cache_key = 'domainconnect_plugin_provider_settings_'. $domain;
    $provider_settings = get_transient( $cache_key );
    if ( false === $provider_settings ) {
        $response = wp_remote_get( $url );
        $provider_settings = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $provider_settings ) {
            set_transient( $cache_key, $provider_settings, WP_CACHE_24HR );
        }
    }
    return $provider_settings;
}

function query_template_support($provider_url, $domain, $service_provider_id, $service_provider_template)
{
    // {urlAPI}/v2/domainTemplates/providers/{providerId}/services/{serviceId}
    $url = sprintf(
        '%s/v2/domainTemplates/providers/%s/services/%s',
        get_provider_api($provider_url, $domain),
        $service_provider_id,
        $service_provider_template
    );

    $cache_key = sprintf(
        "domainconnect_plugin_provider_template_support_%s_%s_%s",
        $domain,
        $service_provider_id,
        $service_provider_template
    );

    $provider_template_supported = get_transient( $cache_key );
    if ( false === $provider_template_supported ) {
        $response = wp_remote_get( $url );
        $provider_template_supported = ( wp_remote_retrieve_response_code( $response ) == 200 ) ? 1 : 0;

        set_transient( $cache_key, $provider_template_supported, WP_CACHE_24HR );
    }

    return (boolean)$provider_template_supported;
}

function build_synchronous_dashboard_apply_url($provider_url, $domain, $service_provider_id, $service_provider_template)
{
    // {urlSyncUX}/v2/domainTemplates/providers/{providerId}/services/{serviceId}/apply?[properties]
    $url = sprintf(
        '%s/v2/domainTemplates/providers/%s/services/%s/apply?',
        get_provider_dashboard_url($provider_url, $domain),
        $service_provider_id,
        $service_provider_template
    );
    return $url;
}

function example_domain_discovery()
{
    $success_dns_get_record = 
        array(
            array(
                'host' => '_domainconnect.foo.com',
                'ttl' => 600,
                'type' => 'TXT',
                'txt' => 'api.company.com/client/v4',
                'entries' => array('api.company.com/client/v4')
            )
        );
}

function example_provider_settings()
{
    $json = '{
            "providerId": "company.com",
            "providerName": "company",
            "providerDisplayName": "ComPany",
            "urlSyncUX": "https://dash.company.com",
            "urlAPI": "https://api.company.com/client/v4"
        }';
}
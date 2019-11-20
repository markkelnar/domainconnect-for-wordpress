<?php
/**
 * send POST request to https://auth.wpengine.io/v1/tokens with authorization header username/password
 * that is the creds from server
 *
 * use return token, cache it until expires.
 *
 * send to https://landmark.wpesvc.net/v1 or api gateway /domains/domain.com
 * and handle url
 *
 * {
 *     "uuid": "asdfghjk-af52-40bc-8c7e-936a09648a9b",
 *     "domain": "example.com",
 *     "root_domain": "example.com",
 *     "registrar": "GoDaddy",
 *     "sync": {
 *         "template_url": "https://godaddy.com/something/"
 *     }
 * }
 */

namespace WPE\Domainconnect;

/**
 *
 */
class WpengineApiSynchronous {

    public function __construct() {
        $this->auth_username = AUTH_API_WPENGINE_USERNAME;
        $this->auth_password = AUTH_API_WPENGINE_PASSWORD;
        $this->auth_token_url = defined('AUTH_API_WPENGINE_URL') ?
            AUTH_API_WPENGINE_URL : 'https://auth.wpengine.io/v1/tokens';
        $this->api_token = '';
        $this->service_url = defined('WPENGINE_DOMAINCONNECT_SERVICE_URL') ?
            WPENGINE_DOMAINCONNECT_SERVICE_URL : 'https://www.wpengineapi.com/v1/domainconnectdomains';
        $this->redirect_uri = define('WPENGINE_DOMAINCONNECT_REDIRECT_URL') ?
            WPENGINE_DOMAINCONNECT_REDIRECT_URL : 'https://my.wpengine.com/installs/%s/domains';

        $this->response = array();
	}

    public function login() {
        $url = $this->auth_token_url;
        $headers = array (
            'Authorization' => 'Basic ' . base64_encode( $this->auth_username . ':' . $this->auth_password )
        );
        $args = array(
            'headers' => $headers,
        );

        $response = wp_remote_post( $this->auth_token_url, $args );
        if ( is_wp_error($response) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( isset($body['token']) ) {
            $this->api_token = $body['token'];
            return true;
        }
        return false;
    }

    public function discovery( $domain, $ip, $site ) {
        // https://wpengineapi.com/domains/wpe-domaintest.com?ip=1.2.3.4&site=nacholibre&redirect_uri=https://my.wpengine.com/installs/nacholibre/domains
        $url = sprintf(
            '%s/%s',
            $this->service_url,
            $domain
        );
        $args = array(
            'ip' => $ip,
            'site' => $site,
            'redirect_uri' => sprintf( $this->redirect_uri, $site ),
        );

        $url = add_query_arg( $args, $url );
        $args = array(
            'header' => sprintf('Token %s', $this->api_token),
        );

        $response = wp_remote_post( $url, $args );
        $this->response = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset($this->response['sync']) ) {
            return $this->response['sync']['template_url'];
        }
        return false;
    }

    public function provider_display_name() {
        if ( isset( $this->response['sync']['registrar'] ) ) {
            return $this->response['sync']['registrar'];
        }
        return false;
    }
}

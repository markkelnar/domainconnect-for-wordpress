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

    const CACHE_GROUP ='domain-connect-domain-discovery';
    const CACHE_KEY_FOR_TOKEN = 'wpengine-api-token';

    public function __construct() {
        $this->auth_username = AUTH_API_WPENGINE_USERNAME;
        $this->auth_password = AUTH_API_WPENGINE_PASSWORD;
        $this->auth_token_url = defined('AUTH_API_WPENGINE_URL') ?
            AUTH_API_WPENGINE_URL : 'https://auth.wpengine.io/v1/tokens';
        $this->api_token = '';
        $this->service_url = defined('WPENGINE_DOMAINCONNECT_SERVICE_URL') ?
            WPENGINE_DOMAINCONNECT_SERVICE_URL : 'https://landmark.wpesvc.net/v1/domains';
        $this->redirect_uri = defined('WPENGINE_DOMAINCONNECT_REDIRECT_URL') ?
            WPENGINE_DOMAINCONNECT_REDIRECT_URL : 'https://my.wpengine.com/installs/%s/domains';

        $this->response = array();
	}

    public function login() {
        $this->api_token = wp_cache_get( self::CACHE_KEY_FOR_TOKEN, self::CACHE_GROUP );
        if ( ! $this->api_token ) {
            $args = array(
                'headers' => array (
                    'Authorization' => 'Basic ' . base64_encode( $this->auth_username . ':' . $this->auth_password )
                )
            );

            $response = wp_remote_post( $this->auth_token_url, $args );
            if ( is_wp_error($response) ) {
                return false;
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( ! isset($body['token']) ) {
                return false;
            }

            $this->api_token = $body['token'];
            $expire_on = strtotime( $body['expires_on'] ) - time();
            wp_cache_set( self::CACHE_KEY_FOR_TOKEN, $this->api_token, self::CACHE_GROUP, $expire_on );
        }
        return true;
    }

    // https://wpengineapi.com/domains/wpe-domaintest.com?ip=1.2.3.4&site=nacholibre&redirect_uri=https://my.wpengine.com/installs/nacholibre/domains
    public function discovery( $domain, $ip, $site ) {
        $this->response = wp_cache_get( $domain, self::CACHE_GROUP );
        if ( ! $this->response ) {
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
                'headers' => array (
                    'Authorization' => sprintf('Token %s', $this->api_token),
                )
            );

            $response = wp_remote_get( $url, $args );
            $this->response = json_decode( wp_remote_retrieve_body( $response ), true );
            wp_cache_set( $domain, $this->response, self::CACHE_GROUP, 86400 );
        }

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

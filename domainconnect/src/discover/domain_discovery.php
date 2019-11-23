<?php

namespace WPE\Domainconnect;

const CACHE_24HR = 86400;
const CACHE_GROUP = 'domain-connect-domain-discovery';

/**
 * [domainconnect]
 */
class DomainDiscovery {

	public function __construct( $domain ) {
		$this->domain            = $domain;
		$this->provider_url      = '';
		$this->provider_settings = '';
		// Cache per domain
		$this->cache_key         = $this->domain;
	}

	public function from_cache() {
		$data                    = wp_cache_get( $this->cache_key, CACHE_GROUP );
		if ( $data ) {
			$this->provider_url      = $data->provider_url;
			$this->provider_settings = $data->provider_settings;
		}
	}

	public function to_cache() {
		$data                    = new \stdClass();
		$data->domain            = $this->domain;
		$data->provider_url      = $this->provider_url;
		$data->provider_settings = $this->provider_settings;
		wp_cache_set( $this->cache_key, $data, CACHE_GROUP, CACHE_24HR );
	}

	public function discover() {
		$this->do_discovery();
		$this->get_provider_settings();
	}

	/**
	 * return url to api for dns provider of this domain that supports domain connect.
	 **/
	private function do_discovery() {
		$this->from_cache();
		if ( ! $this->provider_url ) {
			// dig TXT record for _domainconnect.$domain should be on the APEX of the domain
			$check_domain = '_domainconnect.' . $this->domain;

			$dns = dns_get_record( $check_domain, DNS_TXT );
			if ( isset( $dns[0]['txt'] ) ) {
				$this->provider_url = $dns[0]['txt'];
			} else {
				// write something to cache
				$this->provider_url = 'not-supported';
			}

			$this->to_cache();
		}

		return $this->provider_url;
	}

	private function get_provider_settings() {
		$this->from_cache();
		if ( ! $this->provider_settings ) {
			// https://{_domainconnect}/v2/{domain}/settings
			$url = sprintf(
				'https://%s/v2/%s/settings',
				$this->provider_url,
				$this->domain
			);

			$response                = wp_remote_get( $url );
			$this->provider_settings = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $this->provider_settings ) {
				$this->to_cache();
			}
		}

		return $this->provider_settings;
	}

	public function provider_display_name() {
		return $this->provider_settings['providerDisplayName'] ?: $this->provider_settings['providerName'];
	}

	public function provider_supports_synchronous() {
		return isset( $this->provider_settings['urlSyncUX'] );
	}

	public function get_provider_dashboard_url() {
		return $this->provider_settings['urlSyncUX'] ?: false;
	}

	public function get_provider_api() {
		return $this->provider_settings['urlAPI'] ?: false;
	}

	function example_domain_discovery() {
		$success_dns_get_record =
			array(
				array(
					'host'    => '_domainconnect.foo.com',
					'ttl'     => 600,
					'type'    => 'TXT',
					'txt'     => 'api.company.com/client/v4',
					'entries' => array( 'api.company.com/client/v4' ),
				),
			);
	}

	function example_provider_settings() {
		$json = '{
                "providerId": "company.com",
                "providerName": "company",
                "providerDisplayName": "ComPany",
                "urlSyncUX": "https://dash.company.com",
                "urlAPI": "https://api.company.com/client/v4"
            }';
	}
}

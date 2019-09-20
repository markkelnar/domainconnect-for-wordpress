<?php

namespace WPE\Domainconnect;

const CACHE_24HR = 86400;
const CACHE_GROUP = 'domain-connect-dnsprovider';

class ProviderFunctions {

	public function __construct( $provider_api_url ) {
		$this->api_url   = $provider_api_url;
		$this->cache_key = 'dnsprovider_' . $provider_api_url;
	}

	public function from_cache() {
		$data                     = wp_cache_get( $this->cache_key, CACHE_GROUP );
		if ( $data ) {
			$this->template_supported = $data->provider_url;
			$this->providers          = $data->service_providers;
		}
	}

	public function to_cache() {
		$data            = new \stdClass();
		$data->api_url   = $this->api_url;
		$data->service_providers = $this->providers;
		wp_cache_set( $this->cache_key, $data, CACHE_GROUP, CACHE_24HR );
	}

	public function query_template_support( $service_provider_id, $service_provider_template ) {
		// {urlAPI}/v2/domainTemplates/providers/{providerId}/services/{serviceId}
		$url = sprintf(
			'%s/v2/domainTemplates/providers/%s/services/%s',
			$this->api_url,
			$service_provider_id,
			$service_provider_template
		);

		if ( ! isset( $this->providers[ $service_provider_id ][ $service_provider_template ] ) &&
			false == (bool) $this->providers[ $service_provider_id ][ $service_provider_template ]
		) {
			$response = wp_remote_get( $url );
			$this->providers[ $service_provider_id ][ $service_provider_template ] = ( wp_remote_retrieve_response_code( $response ) == 200 ) ? 1 : 0;

			$this->to_cache();
		}

		return (bool) $this->providers[ $service_provider_id ][ $service_provider_template ];
	}
}

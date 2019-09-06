<?php

namespace Domainconnect;

const CACHE_24HR = 86400;

class ProviderFunctions
{
    public function __construct( $provider_api_url )
    {
        $this->api_url = $provider_api_url;
        $this->cache_key = 'domainconnect_plugin_provider_'. $provider_api_url;
    }

    public function from_cache()
    {
        $data = get_transient( $this->cache_key );
        $this->template_supported = $data->provider_url;
        $this->providers = $data->providers;
    }

    public function to_cache()
    {
        $data = new \stdClass;
        $data->api_url = $this->api_url;
        $data->providers = $this->providers;
        set_transient( $this->cache_key, $data, CACHE_24HR );
    }

    public function query_template_support($service_provider_id, $service_provider_template)
    {
        // {urlAPI}/v2/domainTemplates/providers/{providerId}/services/{serviceId}
        $url = sprintf(
            '%s/v2/domainTemplates/providers/%s/services/%s',
            $this->api_url,
            $service_provider_id,
            $service_provider_template
        );

        if ( ! isset( $this->providers[$service_provider_id][$service_provider_template] ) &&
            false == (boolean)$this->providers[$service_provider_id][$service_provider_template]
        ) {
            $response = wp_remote_get( $url );
            $this->providers[$service_provider_id][$service_provider_template] = ( wp_remote_retrieve_response_code( $response ) == 200 ) ? 1 : 0;

            $this->to_cache();
        }

        return (boolean)$this->providers[$service_provider_id][$service_provider_template];
    }
}
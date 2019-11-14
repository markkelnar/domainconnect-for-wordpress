<?php

namespace WPE\Domainconnect;

/**
 * To apply example template
 * https://github.com/Domain-Connect/Templates/blob/master/exampleservice.domainconnect.org.template1.json
 */
class TemplateExampleServiceDomainConnectOrg {

	const PROVIDER_ID         = 'exampleservice.domainconnect.org';
	const TEMPLATE_SERVICE_ID = 'template1';

	public function __construct( $domain, $ip, $text ) {
		$this->domain = $domain;
		$this->ip     = $ip;
		$this->text   = 'shm:1234567890' . $text;
	}

	/**
	 *  Synchronous workflow url to the dns provider.
	 */
	public function synchronous_dashboard_apply_url( $service_provider_dashboard_url ) {
		// {urlSyncUX}/v2/domainTemplates/providers/{providerId}/services/{serviceId}/apply?[properties]
		$url = sprintf(
			'%s/v2/domainTemplates/providers/%s/services/%s/apply?domain=%s&IP=%s&RANDOMTEXT=%s',
			$service_provider_dashboard_url,
			self::PROVIDER_ID,
			self::TEMPLATE_SERVICE_ID,
			$this->domain,
			$this->ip,
			$this->text
		);
		return $url;
	}

}

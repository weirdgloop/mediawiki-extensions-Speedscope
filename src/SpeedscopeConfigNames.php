<?php

namespace MediaWiki\Extension\Speedscope;

/**
 * Constants for the names of config options.
 */
class SpeedscopeConfigNames {

	private const PREFIX = 'Speedscope';

	public const ENDPOINT = self::PREFIX . 'Endpoint';
	public const ENVIRONMENT = self::PREFIX . 'Environment';
	public const EXCLUDED_ENTRY_POINTS = self::PREFIX . 'ExcludedEntryPoints';
	public const EXPOSE_CPU_INFO = self::PREFIX . 'ExposeCPUInfo';
	public const FORCED_PARAM = self::PREFIX . 'ForcedParam';
	public const LOG_TO_STATSD = self::PREFIX . 'LogToStatsd';
	public const PERIOD = self::PREFIX . 'Period';
	public const PUBLIC_ENDPOINT = self::PREFIX . 'PublicEndpoint';
	public const SAMPLING_RATES = self::PREFIX . 'SamplingRates';
	public const TOKEN = self::PREFIX . 'Token';

}

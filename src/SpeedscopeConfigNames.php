<?php

namespace MediaWiki\Extension\Speedscope;

class SpeedscopeConfigNames {

	private const PREFIX = 'Speedscope';

	public const ENDPOINT = self::PREFIX . 'Endpoint';
	public const ENVIRONMENT = self::PREFIX . 'Environment';
	public const EXCLUDED_ENTRY_POINTS = self::PREFIX . 'ExcludedEntryPoints';
	public const FORCED_PARAM = self::PREFIX . 'ForcedParam';
	public const PERIOD = self::PREFIX . 'Period';
	public const SAMPLING_ENVIRONMENTS = self::PREFIX . 'SamplingEnvironments';
	public const SAMPLING_RATE = self::PREFIX . 'SamplingRate';
	public const TOKEN = self::PREFIX . 'Token';

}

<?php

namespace MediaWiki\Extension\Speedscope;

use MediaWiki\Config\Config;

/**
 * Provides configuration values from globals.
 * This is necessary because we need this before we have access to the service container.
 */
class SpeedscopeConfig {

	public const DEFAULTS = [
		SpeedscopeConfigNames::ENVIRONMENT => 'prod',
		SpeedscopeConfigNames::EXCLUDED_ENTRY_POINTS => [ 'cli' ],
		SpeedscopeConfigNames::FORCED_PARAM => 'forceprofile',
		SpeedscopeConfigNames::PERIOD => [ 'forced' => 0.0001, 'sample' => 0.001 ],
		SpeedscopeConfigNames::SAMPLING_RATES => [ 'prod' => 0.01 ],
	];

	/**
	 * @param string $environment
	 * @param string[] $excludedEntryPoints
	 * @param string $forcedParam
	 * @param array{forced:float,sample:float} $period
	 * @param array<string,float> $samplingRates
	 */
	public function __construct(
		private readonly string $environment,
		private readonly array $excludedEntryPoints,
		private readonly string $forcedParam,
		private readonly array $period,
		private readonly array $samplingRates,
	) {
	}

	public static function newFromGlobals(): self {
		// @phan-suppress-next-line PhanParamTooFewUnpack
		return new self( ...array_map( static fn ( $c ) => $GLOBALS["wg$c"], array_keys( self::DEFAULTS ) ) );
	}

	public static function newFromConfig( Config $config ): self {
		// @phan-suppress-next-line PhanParamTooFewUnpack
		return new self( ...array_map(
			static fn ( $c ) => $config->get( $c ) ?? self::DEFAULTS[$c],
			array_keys( self::DEFAULTS )
		) );
	}

	public function getEnvironment(): string {
		return $this->environment;
	}

	public function getExcludedEntryPoints(): array {
		return $this->excludedEntryPoints;
	}

	public function getForcedParam(): string {
		return $this->forcedParam;
	}

	public function getForcedPeriod(): float {
		return $this->period['forced'];
	}

	public function getSamplePeriod(): float {
		return $this->period['sample'];
	}

	/**
	 * @return array<string,float>
	 */
	public function getSamplingRates(): array {
		return $this->samplingRates;
	}

}

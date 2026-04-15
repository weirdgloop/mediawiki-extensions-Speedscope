<?php

namespace MediaWiki\Extension\Speedscope;

/**
 * Provides configuration values from globals.
 * This is necessary because we need this before we have access to the service container.
 */
class SpeedscopeConfig {

	/**
	 * @param string $environment
	 * @param string[] $excludedEntryPoints
	 * @param string $forcedParam
	 * @param array $samplingRates
	 */
	private function __construct(
		private readonly string $environment,
		private readonly array $excludedEntryPoints,
		private readonly string $forcedParam,
		private readonly array $period,
		private readonly array $samplingRates,
	) {
	}

	public static function newFromGlobals(): self {
		global $wgSpeedscopeEnvironment, $wgSpeedscopeExcludedEntryPoints, $wgSpeedscopeForcedParam,
			   $wgSpeedscopePeriod, $wgSpeedscopeSamplingRates;

		// TODO enforce setting some of these options
		// TODO remove defaults from extension.json and use ??= here instead?
		return new self(
			environment: $wgSpeedscopeEnvironment ?? 'prod',
			excludedEntryPoints: $wgSpeedscopeExcludedEntryPoints ?? [ 'cli' ],
			forcedParam: $wgSpeedscopeForcedParam ?? 'forceprofile',
			period: $wgSpeedscopePeriod ?? [ 'forced' => 0.0001, 'sample' => 0.001 ],
			samplingRates: $wgSpeedscopeSamplingRates ?? [ 'prod' => '0.01' ],
		);
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

	public function getSamplingRates(): array {
		return $this->samplingRates;
	}

}

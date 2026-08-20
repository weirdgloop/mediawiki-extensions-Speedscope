<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Speedscope\Profiler\ISpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\Profiler\NoOpSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeLogger;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Extension\Speedscope\Statsd\ExcimerComponentExtractor;
use MediaWiki\Extension\Speedscope\Statsd\SpeedscopeStatsdLogger;
use MediaWiki\MediaWikiServices;

// @codeCoverageIgnoreStart

/**
 * @phpcs-require-sorted-array
 * Tested in ServiceWiringTest.php
 */
return [
	'Speedscope.ExcimerComponentExtractor' => static function (
		MediaWikiServices $services
	): ExcimerComponentExtractor {
		return new ExcimerComponentExtractor(
			new ServiceOptions( ExcimerComponentExtractor::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
		);
	},
	'Speedscope.Profile' => static function ( MediaWikiServices $services ): ?SpeedscopeProfile {
		return $services->getService( 'Speedscope.Profiler' )->getProfile();
	},
	'Speedscope.ProfileLogger' => static function ( MediaWikiServices $services ): SpeedscopeLogger {
		return new SpeedscopeLogger(
			new ServiceOptions( SpeedscopeLogger::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getHttpRequestFactory(),
		);
	},
	'Speedscope.Profiler' => static function ( MediaWikiServices $services ): ISpeedscopeProfiler {
		// This is hacky, but we need to instantiate the profiler before the service container is available.
		global $wgSpeedscopeProfiler;
		return $wgSpeedscopeProfiler ?? new NoOpSpeedscopeProfiler();
	},
	'Speedscope.StatsdLogger' => static function ( MediaWikiServices $services ): SpeedscopeStatsdLogger {
		return new SpeedscopeStatsdLogger(
			new ServiceOptions( SpeedscopeStatsdLogger::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getService( 'Speedscope.ExcimerComponentExtractor' ),
			$services->getStatsFactory()->withComponent( 'speedscope' ),
		);
	},
];

// @codeCoverageIgnoreEnd

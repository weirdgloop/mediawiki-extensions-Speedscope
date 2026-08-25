<?php

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Speedscope\Profiler\NoOpSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeLogger;
use MediaWiki\Extension\Speedscope\SpeedscopeProfilerManager;
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
	'Speedscope.ProfileLogger' => static function ( MediaWikiServices $services ): SpeedscopeLogger {
		return new SpeedscopeLogger(
			new ServiceOptions( SpeedscopeLogger::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getHttpRequestFactory(),
		);
	},
	'Speedscope.ProfilerManager' => static function ( MediaWikiServices $services ): SpeedscopeProfilerManager {
		global $wgSpeedscopeProfiler;
		return new SpeedscopeProfilerManager( $wgSpeedscopeProfiler ?? new NoOpSpeedscopeProfiler() );
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

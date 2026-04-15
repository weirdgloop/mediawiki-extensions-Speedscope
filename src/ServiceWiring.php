<?php

/**
 * @phpcs-require-sorted-array
 * Tested in ServiceWiringTest.php
 */

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Speedscope\Profiler\ISpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\Profiler\NoOpSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeLogger;
use MediaWiki\MediaWikiServices;

return [
	'Speedscope.ProfileLogger' => static function ( MediaWikiServices $services ): SpeedscopeLogger {
		return new SpeedscopeLogger(
			new ServiceOptions( SpeedscopeLogger::CONSTRUCTOR_OPTIONS, $services->getMainConfig() ),
			$services->getHttpRequestFactory(),
		);
	},
	'Speedscope.Profiler' => static function ( MediaWikiServices $services ): ISpeedscopeProfiler {
		global $wgSpeedscopeProfiler;
		return $wgSpeedscopeProfiler ?? new NoOpSpeedscopeProfiler();
	},
];

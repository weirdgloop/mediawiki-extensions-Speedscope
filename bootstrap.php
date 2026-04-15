<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entrypoint!' );
}

require_once __DIR__ . '/src/Profiler/ISpeedscopeProfiler.php';
require_once __DIR__ . '/src/Profiler/ExcimerSpeedscopeProfiler.php';
require_once __DIR__ . '/src/SpeedscopeConfig.php';

use MediaWiki\Extension\Speedscope\Profiler\ExcimerSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfig;

// The defaults are defined here instead of in extension.json as this code is run
// before the extension is loaded.
$wgSpeedscopeEnvironment ??= 'prod';
$wgSpeedscopeExcludedEntryPoints ??= [ 'cli' ];
$wgSpeedscopeForcedParam ??= 'forceprofile';
$wgSpeedscopePeriod ??= [ 'forced' => 0.0001, 'sample' => 0.001 ];
$wgSpeedscopeSamplingRates ??= [ 'prod' => '0.01' ];

$wgSpeedscopeProfiler = new ExcimerSpeedscopeProfiler( SpeedscopeConfig::newFromGlobals() );
$wgSpeedscopeProfiler->init();

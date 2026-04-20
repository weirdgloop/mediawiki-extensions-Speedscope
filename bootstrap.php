<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entrypoint!' );
}

require_once __DIR__ . '/src/Profiler/ISpeedscopeProfiler.php';
require_once __DIR__ . '/src/Profiler/ExcimerSpeedscopeProfiler.php';
require_once __DIR__ . '/src/SpeedscopeConfig.php';
require_once __DIR__ . '/src/SpeedscopeConfigNames.php';
require_once __DIR__ . '/src/SpeedscopeProfile.php';

use MediaWiki\Extension\Speedscope\Profiler\ExcimerSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfig;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;

// The defaults are defined here instead of in extension.json as this code is run
// before the extension is loaded.
$speedscopeConfigValues = [];
foreach ( [
	SpeedscopeConfigNames::ENVIRONMENT => 'prod',
	SpeedscopeConfigNames::EXCLUDED_ENTRY_POINTS => [ 'cli' ],
	SpeedscopeConfigNames::FORCED_PARAM => 'forceprofile',
	SpeedscopeConfigNames::PERIOD => [ 'forced' => 0.0001, 'sample' => 0.001 ],
	SpeedscopeConfigNames::SAMPLING_RATES => [ 'prod' => 0.01 ],
] as $name => $value ) {
	$GLOBALS["wg$name"] ??= $value;
	$speedscopeConfigValues[] = $GLOBALS["wg$name"];
}

global $wgSpeedscopeProfiler;
$wgSpeedscopeProfiler = new ExcimerSpeedscopeProfiler( new SpeedscopeConfig( ...$speedscopeConfigValues ) );
unset( $speedscopeConfigValues );
$wgSpeedscopeProfiler->init();

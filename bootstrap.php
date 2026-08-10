<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entrypoint!' );
}

require_once __DIR__ . '/src/Profiler/ISpeedscopeProfiler.php';
require_once __DIR__ . '/src/Profiler/AbstractSpeedscopeProfiler.php';
require_once __DIR__ . '/src/Profiler/Excimer/ExcimerSpeedscopeProfiler.php';
require_once __DIR__ . '/src/SpeedscopeConfig.php';
require_once __DIR__ . '/src/SpeedscopeConfigNames.php';
require_once __DIR__ . '/src/SpeedscopeProfile.php';

use MediaWiki\Extension\Speedscope\Profiler\Excimer\ExcimerSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfig;

// The defaults are defined here instead of in extension.json as this code is run
// before the extension is loaded.
$speedscopeConfigValues = [];
foreach ( SpeedscopeConfig::DEFAULTS as $name => $value ) {
	$GLOBALS["wg$name"] ??= $value;
	$speedscopeConfigValues[] = $GLOBALS["wg$name"];
}

global $wgSpeedscopeProfiler;
$wgSpeedscopeProfiler = new ExcimerSpeedscopeProfiler( new SpeedscopeConfig( ...$speedscopeConfigValues ) );
unset( $speedscopeConfigValues );
$wgSpeedscopeProfiler->init();

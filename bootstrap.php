<?php

if ( !defined( 'MEDIAWIKI' ) ) {
	die( 'Not an entrypoint!' );
}

require_once __DIR__ . '/src/Profiler/ISpeedscopeProfiler.php';
require_once __DIR__ . '/src/Profiler/ExcimerSpeedscopeProfiler.php';
require_once __DIR__ . '/src/SpeedscopeConfig.php';

use MediaWiki\Extension\Speedscope\Profiler\ExcimerSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfig;

$wgSpeedscopeProfiler = new ExcimerSpeedscopeProfiler( SpeedscopeConfig::newFromGlobals() );
$wgSpeedscopeProfiler->init();

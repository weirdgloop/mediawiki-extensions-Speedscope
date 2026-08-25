<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration;

use MediaWiki\Extension\Speedscope\Profiler\Excimer\ExcimerSpeedscopeProfiler;
use MediaWikiIntegrationTestCase;

/**
 * @coversNothing Files cannot be covered by PHPUnit
 */
class BootstrapTest extends MediaWikiIntegrationTestCase {

	private function requireBootstrapFile(): void {
		// phpcs:ignore MediaWiki.NamingConventions.ValidGlobalName.allowedPrefix
		global $IP;
		require_once "$IP/extensions/Speedscope/bootstrap.php";
	}

	/** @inheritDoc */
	protected function tearDown(): void {
		parent::tearDown();
		global $wgSpeedscopeEnvironment, $wgSpeedscopeExcludedEntryPoints, $wgSpeedscopeForcedParam,
			   $wgSpeedscopePeriod, $wgSpeedscopeSamplingRates, $wgSpeedscopeProfiler;
		$wgSpeedscopeEnvironment = null;
		$wgSpeedscopeExcludedEntryPoints = null;
		$wgSpeedscopeForcedParam = null;
		$wgSpeedscopePeriod = null;
		$wgSpeedscopeSamplingRates = null;
		$wgSpeedscopeProfiler = null;
	}

	public function testProfilerIsCreated() {
		$this->requireBootstrapFile();

		global $wgSpeedscopeProfiler;
		$this->assertInstanceOf( ExcimerSpeedscopeProfiler::class, $wgSpeedscopeProfiler );
	}

}

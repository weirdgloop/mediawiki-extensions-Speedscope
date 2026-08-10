<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration\Profiler;

use MediaWiki\Extension\Speedscope\Profiler\NoOpSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Extension\Speedscope\SpeedscopeProfilerManager;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Speedscope\Profiler\NoOpSpeedscopeProfiler
 */
class NoOpSpeedscopeProfilerTest extends MediaWikiIntegrationTestCase {

	public function testNoOpProfiler() {
		$profilerManager = $this->getServiceContainer()->getService( 'Speedscope.ProfilerManager' );
		/** @var SpeedscopeProfilerManager $profilerManager */
		$profiler = $profilerManager->getProfiler();
		$this->assertInstanceOf( NoOpSpeedscopeProfiler::class, $profiler );
		$this->assertNull( $profiler->getProfile() );
		$profiler->recordProfile( SpeedscopeProfile::CAUSE_SAMPLE );
		$profiler->stopRecording();
		$this->assertNull( $profiler->getProfile() );
	}

}

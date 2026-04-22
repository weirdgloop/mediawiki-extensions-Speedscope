<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration\Profiler;

use MediaWiki\Extension\Speedscope\Profiler\NoOpSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Speedscope\Profiler\NoOpSpeedscopeProfiler
 */
class NoOpSpeedscopeProfilerTest extends MediaWikiIntegrationTestCase {

	public function testNoOpProfiler() {
		$profiler = $this->getServiceContainer()->getService( 'Speedscope.Profiler' );
		$this->assertInstanceOf( NoOpSpeedscopeProfiler::class, $profiler );
		$this->assertNull( $profiler->getProfile() );
		$profiler->recordProfile( SpeedscopeProfile::CAUSE_SAMPLE );
		$profiler->stopRecording();
		$this->assertNull( $profiler->getProfile() );
	}

}

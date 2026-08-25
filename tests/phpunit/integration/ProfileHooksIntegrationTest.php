<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Speedscope\HookHandlers\ProfileHooks;
use MediaWiki\Extension\Speedscope\Profiler\ISpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Extension\Speedscope\SpeedscopeProfilerManager;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Speedscope\HookHandlers\ProfileHooks
 */
class ProfileHooksIntegrationTest extends MediaWikiIntegrationTestCase {

	public function testSendProfileHeader_SendsHeader() {
		$this->overrideProfile( new SpeedscopeProfile(
			'test',
			SpeedscopeProfile::CAUSE_SAMPLE,
			'test-id'
		) );
		ProfileHooks::sendProfileHeader();
		$this->assertEquals(
			'test-id',
			RequestContext::getMain()->getRequest()->response()->getHeader( 'Profile-Id' )
		);
	}

	public function testSendProfileHeader_PrintsProfileId() {
		$this->overrideProfile( new SpeedscopeProfile(
			'test',
			SpeedscopeProfile::CAUSE_FORCED_ENV,
			'test-id'
		) );
		ob_start();
		ProfileHooks::sendProfileHeader();
		$output = ob_get_clean();
		$this->assertEquals(
			"[Speedscope] Profile ID: test-id\n\n",
			$output
		);
	}

	public function testSendProfileHeader_NoProfile() {
		$this->overrideProfile( null );
		ProfileHooks::sendProfileHeader();
		$this->assertNull( RequestContext::getMain()->getRequest()->response()->getHeader( 'Profile-Id' ) );
	}

	protected function overrideProfile( ?SpeedscopeProfile $profile ): void {
		$profiler = $this->createNoOpMock( ISpeedscopeProfiler::class, [ 'getProfile' ] );
		$profiler->method( 'getProfile' )->willReturn( $profile );
		$manager = new SpeedscopeProfilerManager( $profiler );
		$this->setService( 'Speedscope.ProfilerManager', $manager );
	}

}

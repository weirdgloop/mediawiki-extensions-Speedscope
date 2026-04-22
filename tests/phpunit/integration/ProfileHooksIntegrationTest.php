<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Speedscope\HookHandlers\ProfileHooks;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Speedscope\HookHandlers\ProfileHooks
 */
class ProfileHooksIntegrationTest extends MediaWikiIntegrationTestCase {

	public function testSendProfileHeader_SendsHeader() {
		$this->setService( 'Speedscope.Profile', static fn () => new SpeedscopeProfile(
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

	public function testSendProfileHeader_NoProfile() {
		$this->setService( 'Speedscope.Profile', static fn () => null );
		ProfileHooks::sendProfileHeader();
		$this->assertNull( RequestContext::getMain()->getRequest()->response()->getHeader( 'Profile-Id' ) );
	}

}

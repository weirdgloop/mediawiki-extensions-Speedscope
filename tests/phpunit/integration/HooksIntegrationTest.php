<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Speedscope\Hooks;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Speedscope\Hooks
 */
class HooksIntegrationTest extends MediaWikiIntegrationTestCase {

	public function testSendProfileHeader_SendsHeader() {
		$this->setService( 'Speedscope.Profile', static fn () => new SpeedscopeProfile(
			'test',
			false,
			'test-id'
		) );
		Hooks::sendProfileHeader();
		$this->assertEquals(
			'test-id',
			RequestContext::getMain()->getRequest()->response()->getHeader( 'Profile-Id' )
		);
	}

	public function testSendProfileHeader_NoProfile() {
		$this->setService( 'Speedscope.Profile', static fn () => null );
		Hooks::sendProfileHeader();
		$this->assertNull( RequestContext::getMain()->getRequest()->response()->getHeader( 'Profile-Id' ) );
	}

}

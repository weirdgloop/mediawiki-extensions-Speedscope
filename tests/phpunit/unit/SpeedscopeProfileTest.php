<?php

namespace MediaWiki\Extension\Speedscope\Tests\Unit;

use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Speedscope\SpeedscopeProfile
 */
class SpeedscopeProfileTest extends MediaWikiUnitTestCase {

	public function testConstruct() {
		$profile = new SpeedscopeProfile( 'test-env', SpeedscopeProfile::CAUSE_FORCED_URL, 'test-id' );
		$this->assertEquals( 'test-env', $profile->getEnvironment() );
		$this->assertEquals( SpeedscopeProfile::CAUSE_FORCED_URL, $profile->getCause() );
		$this->assertEquals( 'test-id', $profile->getId() );
	}

	public function testGettersAndSetters() {
		$profile = new SpeedscopeProfile( 'test-env', SpeedscopeProfile::CAUSE_SAMPLE, 'test-id' );

		$this->assertNull( $profile->getData() );
		$data = [ 'test' => 'data' ];
		$profile->setData( $data );
		$this->assertEquals( $data, $profile->getData() );

		$this->assertNull( $profile->getParserReport() );
		$parserReport = [ 'test' => 'parserReport' ];
		$profile->setParserReport( $parserReport );
		$this->assertEquals( $parserReport, $profile->getParserReport() );

		$this->assertFalse( $profile->shouldStoreParserReport() );
		$profile->setStoreParserReport( true );
		$this->assertTrue( $profile->shouldStoreParserReport() );
	}

}

<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration;

use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeLogger;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\Speedscope\SpeedscopeLogger
 */
class SpeedscopeLoggerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return SpeedscopeLogger
	 */
	private function getProfileLogger() {
		return TestingAccessWrapper::newFromObject(
			$this->getServiceContainer()->getService( 'Speedscope.ProfileLogger' )
		);
	}

	public function testAppendAdditionalData() {
		$data = $this->getProfileLogger()->appendAdditionalData( [ 'foo' => 'bar' ], '/test/url' );
		$this->assertArrayNotHasKey( 'cpuinfo', $data );
		$this->assertEquals( '/test/url', $data['profiles'][0]['name'] );
		$this->assertIsFloat( $data['microtime'] );
		$this->assertEquals( wfHostname(), $data['hostname'] );
		$this->assertIsInt( $data['memory_peak_allocated_bytes'] );
		$this->assertEquals( 'bar', $data['foo'] );
	}

	public function testAppendAdditionalData_ExposeCpuInfo() {
		$this->overrideConfigValue( SpeedscopeConfigNames::EXPOSE_CPU_INFO, true );
		$data = $this->getProfileLogger()->appendAdditionalData( [ 'foo' => 'bar' ], '/test/url' );
		$this->assertIsString( $data['cpuinfo'] );
	}

}

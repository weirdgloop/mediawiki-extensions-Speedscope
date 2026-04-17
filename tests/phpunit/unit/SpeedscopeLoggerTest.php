<?php

namespace MediaWiki\Extension\Speedscope\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeLogger;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Speedscope\SpeedscopeLogger
 */
class SpeedscopeLoggerTest extends MediaWikiUnitTestCase {

	private function newLogger(
		array $configOverrides = [],
		?HttpRequestFactory $httpRequestFactory = null,
	): SpeedscopeLogger {
		$config = new HashConfig( $configOverrides + [
			SpeedscopeConfigNames::ENDPOINT => 'localhost:3000',
			SpeedscopeConfigNames::EXPOSE_CPU_INFO => false,
			SpeedscopeConfigNames::TOKEN => 'test-token',
		] );
		return new SpeedscopeLogger(
			new ServiceOptions( SpeedscopeLogger::CONSTRUCTOR_OPTIONS, $config ),
			$httpRequestFactory ?? $this->createMock( HttpRequestFactory::class ),
		);
	}

	private function newProfile( ?array $data ) {
		$profile = new SpeedscopeProfile( 'test', false, 'abc' );
		$profile->setData( $data );
		return $profile;
	}

	public function testLog_NoData() {
		$status = $this->newLogger()->log( $this->newProfile( null ) );
		$this->assertStatusError( 'speedscope-log-error-no-data', $status );
	}

	public function testLog_NoToken() {
		$status = $this->newLogger( [
			SpeedscopeConfigNames::TOKEN => null,
		] )->log( $this->newProfile( [ 'test' => 'data' ] ) );
		$this->assertStatusError( 'speedscope-log-error-no-token', $status );
	}

}

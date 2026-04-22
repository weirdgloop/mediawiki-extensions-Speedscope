<?php

namespace MediaWiki\Extension\Speedscope\Tests\Unit;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeLogger;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use StatusValue;
use Throwable;

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
		$profile = new SpeedscopeProfile( 'test', SpeedscopeProfile::CAUSE_SAMPLE, 'abc' );
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

	public function testLog_Success() {
		global $wgOverrideHostname;
		$originalHostname = $wgOverrideHostname;
		$wgOverrideHostname = 'test-hostname';

		$httpRequestFactory = $this->newMockHttpRequestFactory(
			requestBodyCallback: function ( $body ) {
				$speedscopeProfile = json_decode( $body->speedscopeData, associative: true );
				$this->assertEquals( 'data', $speedscopeProfile['test'] );
				$this->assertCount( 1, $speedscopeProfile['profiles'] );
				$this->assertIsString( $speedscopeProfile['profiles'][0]['name'] );
				$this->assertArrayNotHasKey( 'cpuinfo', $speedscopeProfile['profiles'] );
				$this->assertIsFloat( $speedscopeProfile['microtime'] );
				$this->assertEquals( 'test-hostname', $speedscopeProfile['hostname'] );
				$this->assertIsInt( $speedscopeProfile['memory_peak_allocated_bytes'] );
			},
			statusCode: 200,
		);
		$status = $this->newLogger( httpRequestFactory: $httpRequestFactory )
			->log( $this->newProfile( [ 'test' => 'data' ] ) );
		$this->assertStatusGood( $status );

		$wgOverrideHostname = $originalHostname;
	}

	public function testLog_BadResponseStatus() {
		$httpRequestFactory = $this->newMockHttpRequestFactory(
			statusCode: 502,
			responseBody: 'Test error'
		);
		$status = $this->newLogger( httpRequestFactory: $httpRequestFactory )
			->log( $this->newProfile( [ 'test' => 'data' ] ) );
		$this->assertStatusMessagesExactly(
			StatusValue::newFatal( 'speedscope-log-error-request-bad-status', 502, 'Test error' ),
			$status
		);
	}

	public function testLog_ExceptionThrown() {
		$httpRequestFactory = $this->newMockHttpRequestFactory(
			statusCode: 200,
			throwOnPost: new Exception( 'Test exception' )
		);
		$status = $this->newLogger( httpRequestFactory: $httpRequestFactory )
			->log( $this->newProfile( [ 'test' => 'data' ] ) );
		$this->assertStatusMessagesExactly(
			StatusValue::newFatal( 'speedscope-log-error-request-failed', 'Test exception' ),
			$status
		);
	}

	private function newMockHttpRequestFactory(
		int $statusCode,
		?callable $requestBodyCallback = null,
		string $expectedToken = 'test-token',
		string $expectedUrl = 'localhost:3000/log',
		?string $responseBody = null,
		?Throwable $throwOnPost = null,
	): HttpRequestFactory {
		$client = $this->createMock( Client::class );
		$client->expects( $this->once() )->method( 'post' )->willReturnCallback(
			function ( $url, $options ) use (
				$expectedUrl, $requestBodyCallback, $expectedToken, $statusCode, $responseBody, $throwOnPost
			) {
				$this->assertEquals( $expectedUrl, $url );
				$this->assertEquals( [
					'Authorization' => "Bearer $expectedToken",
					'Content-Encoding' => 'gzip',
					'Content-Type' => 'application/json',
				], $options[RequestOptions::HEADERS] );
				if ( $requestBodyCallback !== null ) {
					$requestBodyCallback( json_decode( gzdecode( $options[RequestOptions::BODY ] ) ) );
				}

				if ( $throwOnPost !== null ) {
					throw $throwOnPost;
				}

				$body = $this->createMock( StreamInterface::class );
				$body->method( 'getContents' )->willReturn( $responseBody ?? '' );

				$response = $this->createMock( ResponseInterface::class );
				$response->method( 'getStatusCode' )->willReturn( $statusCode );
				$response->method( 'getBody' )->willReturn( $body );
				return $response;
			}
		);
		$mock = $this->createMock( HttpRequestFactory::class );
		$mock->expects( $this->once() )->method( 'createGuzzleClient' )->willReturn( $client );
		return $mock;
	}

}

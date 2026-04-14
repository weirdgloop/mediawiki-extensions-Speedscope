<?php

namespace MediaWiki\Extension\Speedscope;

use GuzzleHttp\RequestOptions;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\MWExceptionHandler;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Language\RawMessage;
use StatusValue;
use Throwable;

class SpeedscopeLogger {

	public const CONSTRUCTOR_OPTIONS = [
		SpeedscopeConfigNames::ENDPOINT,
		SpeedscopeConfigNames::TOKEN,
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly HttpRequestFactory $httpRequestFactory,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function log( SpeedscopeProfile $profile ): StatusValue {
		if ( !$profile->hasData() === null ) {
			return StatusValue::newFatal( new RawMessage( 'Attempted to log profile without data!' ) );
		}

		$token = $this->options->get( SpeedscopeConfigNames::TOKEN );
		if ( $token === null ) {
			return StatusValue::newFatal( new RawMessage( 'No token set!' ) );
		}

		$requestUri = $_SERVER['REQUEST_URI'] ?? MW_ENTRY_POINT;
		$data = $this->appendAdditionalData( $profile->getData(), $requestUri );

		$context = RequestContext::getMain();
		$body = json_encode( [
			'id' => $profile->getId(),
			'wiki' => $wgDBname ?? 'unknown',
			'url' => $requestUri,
			'cfRay' => $context->getRequest()->getHeader( 'Cf-Ray' ) ?: 'unknown',
			'forced' => $profile->isForced(),
			'speedscopeData' => json_encode( $data ),
			'parserReport' => $profile->getParserReport() ? json_encode( $profile->getParserReport() ) : null,
			'environment' => $profile->getEnvironment(),
		] );

		$client = $this->httpRequestFactory->createGuzzleClient();
		$endpoint = $this->options->get( SpeedscopeConfigNames::ENDPOINT );
		$options = [
			RequestOptions::BODY => gzencode( $body ),
			RequestOptions::HEADERS => [
				'Authorization' => "Bearer $token",
				'Content-Encoding' => 'gzip',
				'Content-Type' => 'application/json',
			],
		];
		try {
			$response = $client->post( "$endpoint/log", $options );
		} catch ( Throwable $e ) {
			MWExceptionHandler::logException( $e );
			return StatusValue::newFatal( new RawMessage( $e->getMessage() ) );
		}

		if ( $response->getStatusCode() === 200 || $response->getStatusCode() === 201 ) {
			return StatusValue::newGood();
		}

		return StatusValue::newFatal( new RawMessage( $response->getBody() ) )
			->error( new RawMessage( 'Code: ' . $response->getStatusCode() ) );
	}

	private function appendAdditionalData( array $data, string $requestUri ): array {
		$data['profiles'][0]['name'] = $requestUri;
		$data['cpuinfo'] = file_get_contents( '/proc/stat' );
		$data['microtime'] = microtime( true );
		$data['hostname'] = wfHostname();
		$data['memory_peak_allocated_bytes'] = memory_get_peak_usage( true );
		return $data;
	}

}

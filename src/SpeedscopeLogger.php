<?php

namespace MediaWiki\Extension\Speedscope;

use GuzzleHttp\RequestOptions;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\MWExceptionHandler;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\WikiMap\WikiMap;
use StatusValue;
use Throwable;

/**
 * Service used to log speedscope profiles to the speedscope service.
 */
class SpeedscopeLogger {

	public const CONSTRUCTOR_OPTIONS = [
		SpeedscopeConfigNames::ENDPOINT,
		SpeedscopeConfigNames::EXPOSE_CPU_INFO,
		SpeedscopeConfigNames::TOKEN,
		MainConfigNames::HTTPProxy,
	];

	/**
	 * @internal Only for use in ServiceWiring.php.
	 */
	public function __construct(
		private readonly ServiceOptions $options,
		private readonly HttpRequestFactory $httpRequestFactory,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	/**
	 * Log a speedscope profile to the speedscope service.
	 */
	public function log( SpeedscopeProfile $profile ): StatusValue {
		$data = $profile->getData();
		if ( $data === null ) {
			return StatusValue::newFatal( 'speedscope-log-error-no-data' );
		}

		$token = $this->options->get( SpeedscopeConfigNames::TOKEN );
		if ( $token === null ) {
			return StatusValue::newFatal( 'speedscope-log-error-no-token' );
		}

		$requestUri = isset( $_SERVER['REQUEST_URI'] )
			? ( ( $_SERVER['HTTP_HOST'] ?? '' ) . $_SERVER['REQUEST_URI'] )
			: MW_ENTRY_POINT;
		$data = $this->appendAdditionalData( $data, $profile->getName() ?? $requestUri );

		$context = RequestContext::getMain();
		$body = json_encode( [
			'id' => $profile->getId(),
			'wiki' => WikiMap::getCurrentWikiId(),
			'url' => $requestUri,
			'cfRay' => $context->getRequest()->getHeader( 'Cf-Ray' ) ?: 'unknown',
			'forced' => $profile->isForced(),
			'speedscopeData' => json_encode( $data ),
			'parserReport' => $profile->getParserReport() ? json_encode( $profile->getParserReport() ) : null,
			'environment' => $profile->getEnvironment(),
		] );

		$client = $this->httpRequestFactory->createGuzzleClient( [
			'proxy' => $this->options->get( MainConfigNames::HTTPProxy ),
		] );
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
			return StatusValue::newFatal( 'speedscope-log-error-request-failed', $e->getMessage() );
		}

		if ( $response->getStatusCode() === 200 || $response->getStatusCode() === 201 ) {
			return StatusValue::newGood();
		}

		return StatusValue::newFatal(
			'speedscope-log-error-request-bad-status',
			$response->getStatusCode(),
			$response->getBody()->getContents(),
		);
	}

	/**
	 * Add some additional data to the profile.
	 * @param array<string, mixed> $data
	 * @param string $profileName
	 * @return array<string, mixed>
	 */
	private function appendAdditionalData( array $data, string $profileName ): array {
		$data['profiles'][0]['name'] = $profileName;
		if ( $this->options->get( SpeedscopeConfigNames::EXPOSE_CPU_INFO ) ) {
			$data['cpuinfo'] = file_get_contents( '/proc/stat' );
		}
		$data['microtime'] = microtime( true );
		$data['hostname'] = wfHostname();
		$data['memory_peak_allocated_bytes'] = memory_get_peak_usage( true );
		return $data;
	}

}

<?php

namespace MediaWiki\Extension\Speedscope;

use MediaWiki\Config\Config;
use MediaWiki\Parser\ParserOutput;

/**
 * This class holds the data for a speedscope profile, along with related metadata.
 */
class SpeedscopeProfile {

	public const CAUSE_FORCED_ENV = 'forced_env';
	public const CAUSE_FORCED_URL = 'forced_url';
	public const CAUSE_FORCED_PREVIEW = 'preview';
	public const CAUSE_SAMPLE = 'sample';

	private const FORCED_CAUSES = [
		self::CAUSE_FORCED_ENV,
		self::CAUSE_FORCED_URL,
		self::CAUSE_FORCED_PREVIEW,
	];

	/** @var array<string, mixed>|null */
	private ?array $data = null;
	/** @see ParserOutput::getLimitReportJSData() */
	private ?array $parserReport = null;
	/** @var bool Whether we should store the parser output for the current request */
	private bool $storeParserReport = false;

	/**
	 * @param string $environment The environment of the request, e.g. `prod` or `dev`
	 * @param string $cause The cause of this profile
	 * @param string $id The randomly generated ID of this profile
	 */
	public function __construct(
		private readonly string $environment,
		private readonly string $cause,
		private readonly string $id,
	) {
	}

	/**
	 * @return string One of the CAUSE_... constants
	 */
	public function getCause(): string {
		return $this->cause;
	}

	/**
	 * @return array<string, mixed>|null
	 * @see \ExcimerLog::getSpeedscopeData()
	 */
	public function getData(): ?array {
		return $this->data;
	}

	/**
	 * @param array<string, mixed>|null $data
	 * @see \ExcimerLog::getSpeedscopeData()
	 */
	public function setData( ?array $data ): void {
		$this->data = $data;
	}

	/**
	 * @return string The environment of the request, e.g. `prod` or `dev`
	 */
	public function getEnvironment(): string {
		return $this->environment;
	}

	/**
	 * @return bool Whether this profile was forced using the URL parameter, environment variable or preference
	 */
	public function isForced(): bool {
		return in_array( $this->cause, self::FORCED_CAUSES, true );
	}

	/**
	 * @return string The randomly generated ID of this profile
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * @return array|null The parser report for the current request, or null if there was no page view parse during
	 * this request.
	 * @see ParserOutput::getLimitReportJSData()
	 */
	public function getParserReport(): ?array {
		return $this->parserReport;
	}

	/**
	 * @param array|null $parserReport The parser report for the current request. This should be the result of
	 * {@see ParserOutput::getLimitReportJSData}.
	 */
	public function setParserReport( ?array $parserReport ): void {
		$this->parserReport = $parserReport;
	}

	/**
	 * @param bool $storeParserReport Whether we should store a parser report later in the request, if available.
	 */
	public function setStoreParserReport( bool $storeParserReport ): void {
		$this->storeParserReport = $storeParserReport;
	}

	/**
	 * @return bool Whether we should store the parser output for the current request
	 */
	public function shouldStoreParserReport(): bool {
		return $this->storeParserReport;
	}

	public function getURL( Config $config ): string {
		$publicEndpoint = $config->get( SpeedscopeConfigNames::PUBLIC_ENDPOINT ) ??
			$config->get( SpeedscopeConfigNames::ENDPOINT );
		return "$publicEndpoint/view/$this->id";
	}

}

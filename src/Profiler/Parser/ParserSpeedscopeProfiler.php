<?php

namespace MediaWiki\Extension\Speedscope\Profiler\Parser;

use MediaWiki\Extension\Speedscope\Profiler\AbstractSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeLogger;
use MediaWiki\Language\Language;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;
use MediaWiki\Page\PageReference;
use MediaWiki\Parser\IParserProfiler;
use MediaWiki\Parser\PPFrame_Hash;

/**
 * Generates a speedscope profile based on parser frame expansion timings.
 */
class ParserSpeedscopeProfiler extends AbstractSpeedscopeProfiler implements IParserProfiler {

	private bool $recording = false;
	private ParserProfileData $data;

	public function __construct(
		string $environment,
		private readonly ?PageReference $page,
		private readonly Language $language,
	) {
		parent::__construct( $environment );
	}

	/** @inheritDoc */
	protected function doRecordProfile(): void {
		$this->data = new ParserProfileData();
		$logger = LoggerFactory::getInstance( 'Speedscope' );

		if ( !method_exists( PPFrame_Hash::class, 'setProfiler' ) ) {
			$logger->error( 'Cannot record parser profile: PPFrame_Hash::setProfiler is missing!' );
			return;
		}

		$this->recording = true;
		PPFrame_Hash::setProfiler( $this );

		// Start the main frame for the parser
		$this->data->startFrame( (string)( $this->page ?? 'Parse' ) );
	}

	/** @inheritDoc */
	public function stopRecording(): void {
		if ( !$this->recording ) {
			return;
		}
		PPFrame_Hash::setProfiler( null );
		// End the main frame
		$this->data->endFrame();
		$this->recording = false;
		$this->profile->setData( $this->data->toArray() );

		$profileLogger = MediaWikiServices::getInstance()->getService( 'Speedscope.ProfileLogger' );
		/** @var SpeedscopeLogger $profileLogger */
		$status = $profileLogger->log( $this->profile );
		$this->logStatus( $status );
	}

	/** @inheritDoc */
	public function startFrame( string $type, array $bits ): void {
		if ( !$this->recording ) {
			return;
		}
		$frameName = match ( $type ) {
			'template' => $this->getTemplateFrameName( $bits['title']->getFirstChild() ),
			'tplarg' => '{{{' . $bits['title']->getFirstChild() . '}}}',
			'ext' => '<' . $bits['name']->getFirstChild() . '>',
			default => $type,
		};
		$this->data->startFrame( $frameName );
	}

	private function getTemplateFrameName( string $templateName ): string {
		if ( !str_starts_with( $templateName, '#' ) ) {
			$templateName = $this->language->getNsText( NS_TEMPLATE ) . ":$templateName";
		}
		return '{{' . $templateName . '}}';
	}

	/** @inheritDoc */
	public function endFrame(): void {
		if ( !$this->recording ) {
			return;
		}
		$this->data->endFrame();
	}
}

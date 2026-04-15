<?php

namespace MediaWiki\Extension\Speedscope;

use MediaWiki\Config\Config;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserBeforeInternalParseHook;
use MediaWiki\Output\Hook\BeforePageDisplayHook;

class Hooks implements BeforePageDisplayHook, OutputPageParserOutputHook, ParserBeforeInternalParseHook {

	public function __construct(
		private readonly Config $config,
		private readonly ?SpeedscopeProfile $profile,
	) {
	}

	/**
	 * For forced profiles, add JS config vars and the notification script to the output.
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		if ( !$this->profile?->isForced() ) {
			return;
		}
		$out->addJsConfigVars( [
			'speedscopeEndpoint' => $this->config->get( SpeedscopeConfigNames::ENDPOINT ),
			'speedscopeProfileId' => $this->profile->getId(),
		] );
		$out->addModules( 'ext.speedscope.notification' );
	}

	/**
	 * Retrieve the parser report from the parser output.
	 * @inheritDoc
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		if ( !$this->profile?->shouldStoreParserReport() ) {
			return;
		}
		$this->profile->setParserReport( $parserOutput->getLimitReportJSData() );
	}

	/**
	 * Detect if we should store the parser report.
	 * @inheritDoc
	 */
	public function onParserBeforeInternalParse( $parser, &$text, $stripState ): void {
		if ( !$this->profile ) {
			return;
		}
		if ( str_starts_with( ( $parser->getOptions()?->getRenderReason() ?? '' ), 'page_view' ) ) {
			$this->profile->setStoreParserReport( true );
		}
	}
}

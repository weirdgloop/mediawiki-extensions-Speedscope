<?php

namespace MediaWiki\Extension\Speedscope\HookHandlers;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Extension\Speedscope\SpeedscopeProfilerManager;
use MediaWiki\Hook\OutputPageParserOutputHook;
use MediaWiki\Hook\ParserBeforeInternalParseHook;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\Hook\BeforePageDisplayHook;

class ProfileHooks implements
	BeforePageDisplayHook,
	OutputPageParserOutputHook,
	ParserBeforeInternalParseHook
{

	public function __construct(
		private readonly Config $config,
		private readonly SpeedscopeProfilerManager $profilerManager,
	) {
	}

	/**
	 * For forced profiles, add JS config vars and the notification script to the output.
	 * @inheritDoc
	 */
	public function onBeforePageDisplay( $out, $skin ): void {
		$profile = $this->profilerManager->getProfile();
		if ( $profile?->getCause() !== SpeedscopeProfile::CAUSE_FORCED_URL ) {
			return;
		}
		// TODO use profile->getURL?
		$publicEndpoint = $this->config->get( SpeedscopeConfigNames::PUBLIC_ENDPOINT ) ??
			$this->config->get( SpeedscopeConfigNames::ENDPOINT );
		$out->addJsConfigVars( [
			'speedscopeEndpoint' => $publicEndpoint,
			'speedscopeProfileId' => $profile->getId(),
		] );
		$out->addModules( 'ext.speedscope.notification' );
	}

	/**
	 * Retrieve the parser report from the parser output.
	 * @inheritDoc
	 */
	public function onOutputPageParserOutput( $outputPage, $parserOutput ): void {
		$profile = $this->profilerManager->getProfile();
		if ( !$profile?->shouldStoreParserReport() ) {
			return;
		}
		$profile->setParserReport( $parserOutput->getLimitReportJSData() );
	}

	/**
	 * Detect if we should store the parser report.
	 * @inheritDoc
	 */
	public function onParserBeforeInternalParse( $parser, &$text, $stripState ): void {
		$profile = $this->profilerManager->getProfile();
		if ( !$profile ) {
			return;
		}
		if ( str_starts_with( ( $parser->getOptions()?->getRenderReason() ?? '' ), 'page_view' ) ) {
			$profile->setStoreParserReport( true );
		}
	}

	/**
	 * Send the profile ID via the header, or print it in CLI contexts.
	 * This is called as an extension function.
	 */
	public static function sendProfileHeader(): void {
		$profilerManager = MediaWikiServices::getInstance()->getService( 'Speedscope.ProfilerManager' );
		/** @var SpeedscopeProfilerManager $profilerManager */
		$profile = $profilerManager->getProfile();
		if ( !$profile ) {
			return;
		}

		if ( $profile->getCause() === SpeedscopeProfile::CAUSE_FORCED_ENV && MW_ENTRY_POINT === 'cli' ) {
			print "[Speedscope] Profile ID: {$profile->getId()}\n\n";
		}

		RequestContext::getMain()->getRequest()->response()->header( "Profile-Id: {$profile->getId()}" );
	}

}

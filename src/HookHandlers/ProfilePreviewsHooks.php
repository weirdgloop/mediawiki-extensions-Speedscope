<?php

namespace MediaWiki\Extension\Speedscope\HookHandlers;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Speedscope\Profiler\ISpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Hook\ParserBeforeInternalParseHook;
use MediaWiki\Hook\ParserLimitReportFormatHook;
use MediaWiki\Hook\ParserLimitReportPrepareHook;
use MediaWiki\Html\Html;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\Options\UserOptionsLookup;

class ProfilePreviewsHooks implements
	GetPreferencesHook,
	ParserBeforeInternalParseHook,
	ParserLimitReportFormatHook,
	ParserLimitReportPrepareHook
{

	private const LIMIT_REPORT_KEY = 'speedscope-profile';
	private const PREFERENCE_NAME = 'speedscope-profile-previews';

	public function __construct(
		private readonly Config $config,
		private readonly ISpeedscopeProfiler $profiler,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
	}


	/** @inheritDoc */
	public function onGetPreferences( $user, &$preferences ): void {
		$preferences[self::PREFERENCE_NAME] = [
			'type' => 'toggle',
			'label-message' => 'speedscope-profile-previews-label',
			'help-message' => 'speedscope-profile-previews-help',
			'section' => 'editing/developertools'
		];
	}

	/** @inheritDoc */
	public function onParserBeforeInternalParse( $parser, &$text, $stripState ): void {
		if ( $parser->getOptions()?->getRenderReason() !== 'page-preview' ) {
			return;
		}
		$user = RequestContext::getMain()->getUser();
		if ( !$this->userOptionsLookup->getBoolOption( $user, self::PREFERENCE_NAME ) ) {
			return;
		}
		if ( !$this->profiler->getProfile() ) {
			$this->profiler->recordProfile( SpeedscopeProfile::CAUSE_FORCED_PREVIEW );
			ProfileHooks::sendProfileHeader();
		}
		$parser->getOutput()->setLimitReportData(
			self::LIMIT_REPORT_KEY,
			$this->profiler->getProfile()->getURL( $this->config )
		);
	}

	/** @inheritDoc */
	public function onParserLimitReportFormat( $key, &$value, &$report, $isHTML, $localize ) {
		if ( !$isHTML || $key !== self::LIMIT_REPORT_KEY ) {
			return;
		}
		if ( !$this->profiler->getProfile()?->isForced() ) {
			return;
		}
		$report .= Html::rawElement(
			'tr',
			[],
			Html::element( 'th', [], 'Speedscope profile' ) .
			Html::rawElement( 'td', [], Html::element(
				'a',
				[
					'href' => $value,
					'target' => '_blank',
				],
				'View' // TODO
			)),
		);
	}

	/** @inheritDoc */
	public function onParserLimitReportPrepare( $parser, $output ): void {
		if ( $parser->getOptions()?->getRenderReason() !== 'page-preview' ) {
			return;
		}
		$profile = $this->profiler->getProfile();
		if ( $profile?->getCause() !== SpeedscopeProfile::CAUSE_FORCED_PREVIEW ) {
			return;
		}
		$this->profiler->stopRecording();
	}
}

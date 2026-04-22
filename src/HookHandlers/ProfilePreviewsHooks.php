<?php

namespace MediaWiki\Extension\Speedscope\HookHandlers;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Speedscope\Profiler\ISpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Hook\EditPageGetCheckboxesDefinitionHook;
use MediaWiki\Hook\ParserBeforeInternalParseHook;
use MediaWiki\Hook\ParserLimitReportFormatHook;
use MediaWiki\Hook\ParserLimitReportPrepareHook;
use MediaWiki\Html\Html;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\Options\UserOptionsLookup;

class ProfilePreviewsHooks implements
	EditPageGetCheckboxesDefinitionHook,
	GetPreferencesHook,
	ParserBeforeInternalParseHook,
	ParserLimitReportFormatHook,
	ParserLimitReportPrepareHook
{

	public const EXTENSION_DATA_KEY = 'speedscope-profile';
	public const LIMIT_REPORT_KEY = 'speedscope-profile';
	public const PREFERENCE_NAME = 'speedscope-profile-previews';

	public function __construct(
		private readonly Config $config,
		private readonly ISpeedscopeProfiler $profiler,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
	}

	/** @inheritDoc */
	public function onEditPageGetCheckboxesDefinition( $editpage, &$checkboxes ): void {
		if ( !$this->userOptionsLookup->getBoolOption( $editpage->getContext()->getUser(), self::PREFERENCE_NAME ) ) {
			return;
		}
		$checkboxes['wpProfilePreview'] = [
			'id' => 'wpProfilePreview',
			'default' => $editpage->getContext()->getRequest()->getCheck( 'wpProfilePreview' ),
			'title-message' => 'speedscope-editpage-profile-preview-title',
			'label-message' => 'speedscope-editpage-profile-preview-label',
		];
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

	/**
	 * Start recording a profile if a preview parse starts and the user preference is enabled.
	 * Also set the limit report and extension data entries.
	 * @inheritDoc
	 */
	public function onParserBeforeInternalParse( $parser, &$text, $stripState ): void {
		if ( $parser->getOptions()?->getRenderReason() !== 'page-preview' ) {
			return;
		}
		if ( !RequestContext::getMain()->getRequest()->getCheck( 'wpProfilePreview' ) ) {
			return;
		}
		$user = RequestContext::getMain()->getUser();
		if ( !$this->userOptionsLookup->getBoolOption( $user, self::PREFERENCE_NAME ) ) {
			return;
		}
		$id = $this->profiler->getProfile()?->getId() ?? bin2hex( random_bytes( 16 ) );
		$publicEndpoint = $this->config->get( SpeedscopeConfigNames::PUBLIC_ENDPOINT ) ??
			$this->config->get( SpeedscopeConfigNames::ENDPOINT );
		$url = "$publicEndpoint/view/$id";
		$parser->getOutput()->setLimitReportData( self::LIMIT_REPORT_KEY, $url );
		$parser->getOutput()->setExtensionData( self::EXTENSION_DATA_KEY, true );
		$parser->getOutput()->addWarningMsg( 'speedscope-editpage-profile-notice', $url );
		if ( !$this->profiler->getProfile() ) {
			$this->profiler->recordProfile( SpeedscopeProfile::CAUSE_FORCED_PREVIEW, $id );
			// @codeCoverageIgnoreStart
			if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
				ProfileHooks::sendProfileHeader();
			}
			// @codeCoverageIgnoreEnd
		}
	}

	/**
	 * Add a link to the profile viewer to the parser report.
	 * @inheritDoc
	 */
	public function onParserLimitReportFormat( $key, &$value, &$report, $isHTML, $localize ): void {
		if ( !$isHTML || $key !== self::LIMIT_REPORT_KEY ) {
			return;
		}
		if ( !$this->profiler->getProfile()?->isForced() ) {
			return;
		}

		$labelMsg = wfMessage( 'speedscope-parser-report-label' );
		$linkTextMsg = wfMessage( 'speedscope-parser-report-link-text' );
		if ( !$localize ) {
			$labelMsg->inLanguage( 'en' )->useDatabase( false );
			$linkTextMsg->inLanguage( 'en' )->useDatabase( false );
		}

		$header = Html::element( 'th', [], $labelMsg->text() );
		$data = Html::rawElement( 'td', [], Html::element(
			'a',
			[
				'href' => $value,
				'target' => '_blank',
			],
			$linkTextMsg->text()
		) );

		$report .= Html::rawElement( 'tr', [], $header . $data );
	}

	/**
	 * Stop the recording if a preview parse has just ended, and we're recording a preview profile.
	 * @inheritDoc
	 */
	public function onParserLimitReportPrepare( $parser, $output ): void {
		if ( $parser->getOptions()?->getRenderReason() !== 'page-preview' ) {
			return;
		}
		if ( !$parser->getOutput()?->getExtensionData( self::EXTENSION_DATA_KEY ) ) {
			// Make sure this is the exact parse that triggered the profile.
			return;
		}
		$parser->getOutput()->setExtensionData( self::EXTENSION_DATA_KEY, null );
		$profile = $this->profiler->getProfile();
		if ( $profile?->getCause() !== SpeedscopeProfile::CAUSE_FORCED_PREVIEW ) {
			return;
		}
		$this->profiler->stopRecording();
	}
}

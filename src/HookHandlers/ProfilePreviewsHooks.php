<?php

namespace MediaWiki\Extension\Speedscope\HookHandlers;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Speedscope\Profiler\ISpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Hook\EditPage__importFormDataHook;
use MediaWiki\Hook\EditPageBeforeEditButtonsHook;
use MediaWiki\Hook\EditPageGetCheckboxesDefinitionHook;
use MediaWiki\Hook\ParserBeforeInternalParseHook;
use MediaWiki\Hook\ParserLimitReportFormatHook;
use MediaWiki\Hook\ParserLimitReportPrepareHook;
use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\Options\UserOptionsLookup;
use OOUI\ButtonInputWidget;

class ProfilePreviewsHooks implements
	EditPage__importFormDataHook,
	EditPageBeforeEditButtonsHook,
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
	public function onEditPage__importFormData( $editpage, $request ) {
		if ( $request->getCheck( 'wpProfilePreview' ) ) {
			$editpage->preview = true;
			$editpage->save = false;
		}
	}

	/** @inheritDoc */
	public function onEditPageBeforeEditButtons( $editpage, &$buttons, &$tabindex ) {
		if ( !$this->userOptionsLookup->getBoolOption( $editpage->getContext()->getUser(), self::PREFERENCE_NAME ) ) {
			return;
		}
		$button = new ButtonInputWidget( [
			'name' => 'wpProfilePreview',
			'tabIndex' => ++$tabindex,
			'id' => 'wpProfilePreview',
			'inputId' => 'wpProfilePreview',
			'useInputTag' => true,
			'label' => $editpage->getContext()->msg( 'speedscope-editpage-profile-preview-label' )->text(),
			'infusable' => true,
			'type' => 'submit',
			// Allow previewing even when the form is in invalid state (T343585)
			'formNoValidate' => true,
			'title' => $editpage->getContext()->msg( 'speedscope-editpage-profile-preview-title' )->text(),
		] );

		$newButtons = [];
		foreach ( $buttons as $key => $value ) {
			$newButtons[$key] = $value;

			if ( $key === 'preview' ) {
				$newButtons['profilePreview'] = $button;
			}
		}

		$buttons = $newButtons;
	}

	/** @inheritDoc */
	public function onEditPageGetCheckboxesDefinition( $editpage, &$checkboxes ): void {
		if ( !$this->userOptionsLookup->getBoolOption( $editpage->getContext()->getUser(), self::PREFERENCE_NAME ) ) {
			return;
		}
//		$checkboxes['wpProfilePreview'] = [
//			'id' => 'wpProfilePreview',
//			'default' => $editpage->getContext()->getRequest()->getCheck( 'wpProfilePreview' ),
//			'title-message' => 'speedscope-editpage-profile-preview-title',
//			'label-message' => 'speedscope-editpage-profile-preview-label',
//		];
		$editpage->getContext()->getOutput()->addModules( 'ext.speedscope.edit' );
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
		if ( !in_array( $parser->getOptions()?->getRenderReason(), [ 'page-preview', 'api-parse' ] ) ) {
			return;
		}
		if ( !RequestContext::getMain()->getRequest()->getCheck( 'wpProfilePreview' ) ) {
			return;
		}
		$user = $parser->getUserIdentity();
		if ( !$this->userOptionsLookup->getBoolOption( $user, self::PREFERENCE_NAME ) ) {
			return;
		}
		$id = $this->profiler->getProfile()?->getId() ?? bin2hex( random_bytes( 16 ) );
		$publicEndpoint = $this->config->get( SpeedscopeConfigNames::PUBLIC_ENDPOINT ) ??
			$this->config->get( SpeedscopeConfigNames::ENDPOINT );
		$url = "$publicEndpoint/view/$id";
		$parser->getOutput()->setLimitReportData( self::LIMIT_REPORT_KEY, $url );
		$parser->getOutput()->setExtensionData( self::EXTENSION_DATA_KEY, true );
		// TODO insert a raw parameter with a link that opens in a new tab once we drop support for 1.45
		// (1.45 and below don't support raw parameters in warning messages emitted during previews)
		$parser->getOutput()->addWarningMsg(
			'speedscope-editpage-profile-notice',
			$url,
		);
		if ( !$this->profiler->getProfile() ) {
			$this->profiler->recordProfile( SpeedscopeProfile::CAUSE_FORCED_PREVIEW, $id );
			$this->profiler->getProfile()?->setName( $parser->msg(
				'speedscope-profile-name-preview',
				(string)$parser->getPage(),
			)->text() );
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
		if ( !in_array( $parser->getOptions()?->getRenderReason(), [ 'page-preview', 'api-parse' ] ) ) {
			return;
		}
		if ( !$output->getExtensionData( self::EXTENSION_DATA_KEY ) ) {
			// Make sure this is the exact parse that triggered the profile.
			return;
		}
		$output->setExtensionData( self::EXTENSION_DATA_KEY, null );
		$profile = $this->profiler->getProfile();
		if ( $profile?->getCause() !== SpeedscopeProfile::CAUSE_FORCED_PREVIEW ) {
			return;
		}
		$this->profiler->stopRecording();
	}
}

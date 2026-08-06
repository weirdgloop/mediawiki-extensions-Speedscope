<?php

namespace MediaWiki\Extension\Speedscope\HookHandlers;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Speedscope\Profiler\ExcimerSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\Profiler\Parser\ParserSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfig;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Extension\Speedscope\SpeedscopeProfilerManager;
use MediaWiki\Hook\EditPage__importFormDataHook;
use MediaWiki\Hook\EditPage__showEditForm_initialHook;
use MediaWiki\Hook\EditPageBeforeEditButtonsHook;
use MediaWiki\Hook\ParserBeforeInternalParseHook;
use MediaWiki\Hook\ParserLimitReportFormatHook;
use MediaWiki\Hook\ParserLimitReportPrepareHook;
use MediaWiki\Html\Html;
use MediaWiki\Message\Message;
use MediaWiki\Preferences\Hook\GetPreferencesHook;
use MediaWiki\User\Options\UserOptionsLookup;
use OOUI\ButtonInputWidget;
use OOUI\DropdownInputWidget;

class ProfilePreviewsHooks implements
	EditPage__importFormDataHook,
	EditPage__showEditForm_initialHook,
	EditPageBeforeEditButtonsHook,
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
		private readonly SpeedscopeProfilerManager $profilerManager,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
	}

	/** @inheritDoc */
	public function onEditPage__importFormData( $editpage, $request ): void {
		if ( $request->getCheck( 'wpProfilePreview' ) ) {
			$editpage->preview = true;
			$editpage->save = false;
		}
	}

	/** @inheritDoc */
	public function onEditPage__showEditForm_initial( $editor, $out ): void {
		$profile = $this->profilerManager->getProfile();
		if ( $profile?->getCause() !== SpeedscopeProfile::CAUSE_FORCED_PREVIEW ) {
			return;
		}
		$id = $profile->getId();
		$publicEndpoint = $this->config->get( SpeedscopeConfigNames::PUBLIC_ENDPOINT ) ??
			$this->config->get( SpeedscopeConfigNames::ENDPOINT );
		$url = "$publicEndpoint/view/$id";
		$out->addHTML( Html::noticeBox( $editor->getContext()->msg(
			'speedscope-editpage-profile-notice',
			Message::rawParam( Html::element(
				'a',
				[
					'href' => $url,
					'target' => '_blank',
				],
				$editor->getContext()->msg( 'speedscope-editpage-profile-link-label' )->text(),
			),
		) )->parse(), 'mw-speedscope-profile-notice' ) );
	}

	/** @inheritDoc */
	public function onEditPageBeforeEditButtons( $editpage, &$buttons, &$tabindex ): void {
		if ( !$this->userOptionsLookup->getBoolOption( $editpage->getContext()->getUser(), self::PREFERENCE_NAME ) ) {
			return;
		}
		$context = $editpage->getContext();
		$button = new ButtonInputWidget( [
			'name' => 'wpProfilePreview',
			'tabIndex' => ++$tabindex,
			'id' => 'wpProfilePreview',
			'inputId' => 'wpProfilePreview',
			'useInputTag' => true,
			'label' => $context->msg( 'speedscope-editpage-profile-preview-label' )->text(),
			'infusable' => true,
			'type' => 'submit',
			// Allow previewing even when the form is in invalid state (T343585)
			'formNoValidate' => true,
			'title' => $context->msg( 'speedscope-editpage-profile-preview-title' )->text(),
		] );
		if ( $this->config->get( SpeedscopeConfigNames::ENABLE_PARSER_PROFILER ) ) {
			$dropdown = new DropdownInputWidget( [
				'name' => 'wpProfileType',
				'inputId' => 'wpProfileType',
				'id' => 'mw-speedscope-profile-type-selector',
				'value' => 'parser',
				'infusable' => true,
				'options' => [
					[
						'data' => 'parser',
						'label' => $context->msg( 'speedscope-profile-type-parser' )->text(),
					],
					[
						'data' => 'php',
						'label' => $context->msg( 'speedscope-profile-type-php' )->text(),
					],
				]
			] );
			$buttons['profilePreview'] = Html::rawElement(
				'div',
				[
					'class' => 'mw-speedscope-profile-button-group'
				],
				$button . $dropdown,
			);
		} else {
			$buttons['profilePreview'] = $button;
		}

		$context->getOutput()->addJsConfigVars( [
			'speedscopeUseLivePreview' => $this->userOptionsLookup->getOption(
				$context->getUser(),
				'uselivepreview'
			),
		] );
		$context->getOutput()->addModules( 'ext.speedscope.edit' );
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
		if ( $this->profilerManager->getProfile()?->getCause() === SpeedscopeProfile::CAUSE_FORCED_PREVIEW ) {
			return;
		}
		$profileType = RequestContext::getMain()->getRequest()->getVal( 'wpProfileType' );
		if ( $this->config->get( SpeedscopeConfigNames::ENABLE_PARSER_PROFILER ) && $profileType !== 'php' ) {
			$environment = $this->config->get( SpeedscopeConfigNames::ENVIRONMENT );
			$profiler = new ParserSpeedscopeProfiler( $environment, $parser->getPage(), $parser->getTargetLanguage() );
		} else {
			$profiler = new ExcimerSpeedscopeProfiler( SpeedscopeConfig::newFromConfig( $this->config ) );
		}
		$this->profilerManager->setProfiler( $profiler );
		$profiler->recordProfile( SpeedscopeProfile::CAUSE_FORCED_PREVIEW );
		$profiler->getProfile()->setName( $parser->msg(
			'speedscope-profile-name-preview',
			(string)$parser->getPage(),
		)->text() );
		// @codeCoverageIgnoreStart
		if ( !defined( 'MW_PHPUNIT_TEST' ) ) {
			ProfileHooks::sendProfileHeader();
		}
		// @codeCoverageIgnoreEnd

		$publicEndpoint = $this->config->get( SpeedscopeConfigNames::PUBLIC_ENDPOINT ) ??
			$this->config->get( SpeedscopeConfigNames::ENDPOINT );
		$url = "$publicEndpoint/view/{$profiler->getProfile()->getId()}";

		$parser->getOutput()->setLimitReportData( self::LIMIT_REPORT_KEY, $url );
		$parser->getOutput()->setExtensionData( self::EXTENSION_DATA_KEY, true );
		if ( $parser->getOptions()->getRenderReason() === 'api-parse' ) {
			// Add a JS config var for the live preview script
			$parser->getOutput()->setJsConfigVar( 'speedscopeProfileUrl', $url );
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
		$profile = $this->profilerManager->getProfile();
		if ( !$profile?->isForced() ) {
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
		$profile = $this->profilerManager->getProfile();
		if ( $profile?->getCause() !== SpeedscopeProfile::CAUSE_FORCED_PREVIEW ) {
			return;
		}
		$this->profilerManager->getProfiler()->stopRecording();
	}
}

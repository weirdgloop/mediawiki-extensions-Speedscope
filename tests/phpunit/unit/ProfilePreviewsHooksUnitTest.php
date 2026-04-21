<?php

namespace MediaWiki\Extension\Speedscope\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks;
use MediaWiki\Extension\Speedscope\Profiler\ISpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\StripState;
use MediaWiki\User\User;
use MediaWiki\User\UserOptionsLookup;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks
 */
class ProfilePreviewsHooksUnitTest extends MediaWikiUnitTestCase {

	private function newHooks(
		?ISpeedscopeProfiler $profiler = null,
		bool $userOptionEnabled = true,
		array $configOverrides = []
	) {
		$config = new HashConfig( $configOverrides + [
			SpeedscopeConfigNames::ENDPOINT => 'localhost:3000',
			SpeedscopeConfigNames::PUBLIC_ENDPOINT => null,
		] );

		$user = $this->createNoOpMock( User::class );
		RequestContext::getMain()->setUser( $user );
		$userOptionsLookup = $this->createNoOpMock( UserOptionsLookup::class, [ 'getBoolOption' ] );
		$userOptionsLookup->method( 'getBoolOption' )
			->with( $user, ProfilePreviewsHooks::PREFERENCE_NAME )
			->willReturn( $userOptionEnabled );

		return new ProfilePreviewsHooks(
			$config,
			$profiler ?? $this->createMock( ISpeedscopeProfiler::class ),
			$userOptionsLookup
		);
	}

	public function testOnGetPreferences() {
		$user = $this->createNoOpMock( User::class );
		$preferences = [];
		$this->newHooks()->onGetPreferences( $user, $preferences );
		$this->assertArrayHasKey( ProfilePreviewsHooks::PREFERENCE_NAME, $preferences );
	}

	public function testOnParserBeforeInternalParse_WrongRenderReason() {
		$parser = $this->createNoOpMock( Parser::class, [ 'getOptions' ] );
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->expects( $this->once() )->method( 'getRenderReason' )->willReturn( 'page_view' );
		$parser->method( 'getOptions' )->willReturn( $parserOptions );
		$text = '';

		$this->newHooks()->onParserBeforeInternalParse( $parser, $text, $this->createNoOpMock( StripState::class ) );
	}

	public function testOnParserBeforeInternalParse_OptionDisabled() {
		$parser = $this->createNoOpMock( Parser::class, [ 'getOptions' ] );
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->expects( $this->once() )->method( 'getRenderReason' )->willReturn( 'page-preview' );
		$parser->method( 'getOptions' )->willReturn( $parserOptions );
		$text = '';

		$this->newHooks(
			userOptionEnabled: false
		)->onParserBeforeInternalParse( $parser, $text, $this->createNoOpMock( StripState::class ) );
	}

	public function testOnParserBeforeInternalParse_Success() {
		$parser = $this->createNoOpMock( Parser::class, [ 'getOptions', 'getOutput' ] );
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->expects( $this->once() )->method( 'getRenderReason' )->willReturn( 'page-preview' );
		$parser->method( 'getOptions' )->willReturn( $parserOptions );
		$parserOutput = $this->createMock( ParserOutput::class );
		$url = 'http://localhost:3000/view/test123';
		$parserOutput->expects( $this->once() )
			->method( 'setLimitReportData' )
			->with( ProfilePreviewsHooks::LIMIT_REPORT_KEY, $url );
		$parserOutput->expects( $this->once() )
			->method( 'setExtensionData' )
			->with( ProfilePreviewsHooks::EXTENSION_DATA_KEY, true );
		$parser->expects( $this->atLeastOnce() )->method( 'getOutput' )->willReturn( $parserOutput );

		$text = '';
		$createdProfile = false;
		$profiler = $this->createMock( ISpeedscopeProfiler::class );
		$profiler->expects( $this->once() )
			->method( 'recordProfile' )
			->with( SpeedscopeProfile::CAUSE_FORCED_PREVIEW )
			->willReturnCallback( static function () use ( &$createdProfile ) {
				$createdProfile = true;
			} );
		$profile = $this->createMock( SpeedscopeProfile::class );
		$profile->expects( $this->once() )->method( 'getURL' )->willReturn( $url );
		$profiler->expects( $this->atLeastOnce() )
			->method( 'getProfile' )
			->willReturnCallback( static function () use ( $profile, &$createdProfile ) {
				return $createdProfile ? $profile : null;
			} );

		$this->newHooks(
			profiler: $profiler,
			userOptionEnabled: true
		)->onParserBeforeInternalParse( $parser, $text, $this->createNoOpMock( StripState::class ) );
	}

}

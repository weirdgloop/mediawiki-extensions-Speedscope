<?php

namespace MediaWiki\Extension\Speedscope\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks;
use MediaWiki\Extension\Speedscope\Profiler\Excimer\ExcimerSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\Profiler\ISpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\Profiler\NoOpSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfig;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Extension\Speedscope\SpeedscopeProfilerManager;
use MediaWiki\Message\Message;
use MediaWiki\Page\PageIdentityValue;
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
		?SpeedscopeProfilerManager $profilerManager = null,
		?User $user = null,
		bool $userOptionEnabled = true,
		array $configOverrides = [],
	) {
		$config = new HashConfig( $configOverrides + [
			SpeedscopeConfigNames::ENDPOINT => 'localhost:3000',
			SpeedscopeConfigNames::PUBLIC_ENDPOINT => null,
			SpeedscopeConfigNames::ENABLE_PARSER_PROFILER => false,
		] + SpeedscopeConfig::DEFAULTS );

		$user ??= $this->createNoOpMock( User::class );
		RequestContext::getMain()->setUser( $user );
		$userOptionsLookup = $this->createNoOpMock( UserOptionsLookup::class, [ 'getBoolOption' ] );
		$userOptionsLookup->method( 'getBoolOption' )
			->with( $user, ProfilePreviewsHooks::PREFERENCE_NAME )
			->willReturn( $userOptionEnabled );

		$profilerManager ??= new SpeedscopeProfilerManager( $this->createNoOpMock( ISpeedscopeProfiler::class ) );

		return new ProfilePreviewsHooks(
			$config,
			$profilerManager,
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
		RequestContext::getMain()->getRequest()->setVal( 'wpProfilePreview', true );
		$parser = $this->createNoOpMock(
			Parser::class,
			[ 'getOptions', 'getOutput', 'getPage', 'msg' ]
		);
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->expects( $this->atLeastOnce() )->method( 'getRenderReason' )->willReturn( 'page-preview' );
		$parser->method( 'getOptions' )->willReturn( $parserOptions );
		$parserOutput = $this->createMock( ParserOutput::class );
		$parserOutput->expects( $this->once() )
			->method( 'setLimitReportData' )
			->willReturnCallback( function ( $key, $val ) {
				$this->assertEquals( ProfilePreviewsHooks::LIMIT_REPORT_KEY, $key );
				$this->assertStringContainsString( 'localhost:3000/view/', $val );
			} );
		$parserOutput->expects( $this->once() )
			->method( 'setExtensionData' )
			->with( ProfilePreviewsHooks::EXTENSION_DATA_KEY, true );
		$parser->expects( $this->atLeastOnce() )->method( 'getOutput' )->willReturn( $parserOutput );
		$parser->method( 'getPage' )->willReturn( PageIdentityValue::localReference( 0, __METHOD__ ) );
		$parser->method( 'msg' )->willReturn( $this->createMock( Message::class ) );

		$text = '';

		$profilerManager = new SpeedscopeProfilerManager( new NoOpSpeedscopeProfiler() );
		$hooks = $this->newHooks( profilerManager: $profilerManager );

		$hooks->onParserBeforeInternalParse(
			$parser,
			$text,
			$this->createNoOpMock( StripState::class )
		);

		// TODO test with parser profiler as well
		$this->assertInstanceOf( ExcimerSpeedscopeProfiler::class, $profilerManager->getProfiler() );
		$profile = $profilerManager->getProfile();
		$this->assertNotNull( $profile );
		$this->assertEquals( SpeedscopeProfile::CAUSE_FORCED_PREVIEW, $profile->getCause() );
	}

}

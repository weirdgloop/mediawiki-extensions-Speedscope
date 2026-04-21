<?php

namespace MediaWiki\Extension\Speedscope\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\Speedscope\HookHandlers\ProfileHooks;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\StripState;
use MediaWiki\Skin\Skin;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Speedscope\HookHandlers\ProfileHooks
 */
class ProfileHooksUnitTest extends MediaWikiUnitTestCase {

	private function newHooks( ?SpeedscopeProfile $profile, array $configOverrides = [] ): ProfileHooks {
		$config = new HashConfig( $configOverrides + [
			SpeedscopeConfigNames::ENDPOINT => 'localhost:3000',
			SpeedscopeConfigNames::PUBLIC_ENDPOINT => null,
		] );
		return new ProfileHooks( $config, $profile );
	}

	public function testOnBeforePageDisplay_Forced() {
		$hooks = $this->newHooks(
			new SpeedscopeProfile( 'test', SpeedscopeProfile::CAUSE_FORCED_URL, 'abc' ),
			[ SpeedscopeConfigNames::ENDPOINT => 'test-endpoint' ]
		);
		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->once() )->method( 'addModules' )->with( 'ext.speedscope.notification' );
		$out->expects( $this->once() )->method( 'addJsConfigVars' )->with( [
			'speedscopeEndpoint' => 'test-endpoint',
			'speedscopeProfileId' => 'abc',
		] );
		$skin = $this->createMock( Skin::class );
		$hooks->onBeforePageDisplay( $out, $skin );
	}

	public function testOnBeforePageDisplay_Forced_PublicEndpoint() {
		$hooks = $this->newHooks(
			new SpeedscopeProfile( 'test', SpeedscopeProfile::CAUSE_FORCED_URL, 'abc' ),
			[ SpeedscopeConfigNames::PUBLIC_ENDPOINT => 'test-public-endpoint' ]
		);
		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->once() )->method( 'addModules' )->with( 'ext.speedscope.notification' );
		$out->expects( $this->once() )->method( 'addJsConfigVars' )->with( [
			'speedscopeEndpoint' => 'test-public-endpoint',
			'speedscopeProfileId' => 'abc',
		] );
		$skin = $this->createMock( Skin::class );
		$hooks->onBeforePageDisplay( $out, $skin );
	}

	public function testOnBeforePageDisplay_NoProfile() {
		$hooks = $this->newHooks( null );
		$out = $this->createNoOpMock( OutputPage::class );
		$skin = $this->createNoOpMock( Skin::class );
		$hooks->onBeforePageDisplay( $out, $skin );
	}

	public function testOnBeforePageDisplay_NotForced() {
		$hooks = $this->newHooks( new SpeedscopeProfile( 'test', SpeedscopeProfile::CAUSE_SAMPLE, 'abc' ) );
		$out = $this->createNoOpMock( OutputPage::class );
		$skin = $this->createNoOpMock( Skin::class );
		$hooks->onBeforePageDisplay( $out, $skin );
	}

	public function testOnOutputPageParserOutput_ShouldStoreParserReport() {
		$profile = new SpeedscopeProfile( 'test', SpeedscopeProfile::CAUSE_SAMPLE, 'abc' );
		$profile->setStoreParserReport( true );
		$hooks = $this->newHooks( $profile );
		$out = $this->createNoOpMock( OutputPage::class );
		$parserOutput = $this->createMock( ParserOutput::class );
		$limitReport = [
			'test' => 'limit report',
			'123' => '456',
		];
		$parserOutput->expects( $this->once() )->method( 'getLimitReportJSData' )->willReturn( $limitReport );
		$hooks->onOutputPageParserOutput( $out, $parserOutput );
		$this->assertEquals( $limitReport, $profile->getParserReport() );
	}

	public function testOnOutputPageParserOutput_NoProfile() {
		$hooks = $this->newHooks( null );
		$out = $this->createNoOpMock( OutputPage::class );
		$parserOutput = $this->createNoOpMock( ParserOutput::class );
		$hooks->onOutputPageParserOutput( $out, $parserOutput );
	}

	public function testOnOutputPageParserOutput_ShouldNotStoreParserReport() {
		$profile = new SpeedscopeProfile( 'test', SpeedscopeProfile::CAUSE_SAMPLE, 'abc' );
		$profile->setStoreParserReport( false );
		$hooks = $this->newHooks( $profile );
		$out = $this->createNoOpMock( OutputPage::class );
		$parserOutput = $this->createNoOpMock( ParserOutput::class );
		$hooks->onOutputPageParserOutput( $out, $parserOutput );
		$this->assertNull( $profile->getParserReport() );
	}

	public function testOnParserBeforeInternalParse_ShouldStore_PageView() {
		$profile = new SpeedscopeProfile( 'test', SpeedscopeProfile::CAUSE_SAMPLE, 'abc' );
		$hooks = $this->newHooks( $profile );
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->expects( $this->once() )->method( 'getRenderReason' )->willReturn( 'page_view' );
		$parser = $this->createMock( Parser::class );
		$parser->expects( $this->once() )->method( 'getOptions' )->willReturn( $parserOptions );
		$stripState = $this->createNoOpMock( StripState::class );
		$text = '';
		$hooks->onParserBeforeInternalParse( $parser, $text, $stripState );
		$this->assertTrue( $profile->shouldStoreParserReport() );
	}

	public function testOnParserBeforeInternalParse_ShouldStore_PageViewOldId() {
		$profile = new SpeedscopeProfile( 'test', SpeedscopeProfile::CAUSE_SAMPLE, 'abc' );
		$hooks = $this->newHooks( $profile );
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->expects( $this->once() )->method( 'getRenderReason' )->willReturn( 'page_view_oldid' );
		$parser = $this->createMock( Parser::class );
		$parser->expects( $this->once() )->method( 'getOptions' )->willReturn( $parserOptions );
		$stripState = $this->createNoOpMock( StripState::class );
		$text = '';
		$hooks->onParserBeforeInternalParse( $parser, $text, $stripState );
		$this->assertTrue( $profile->shouldStoreParserReport() );
	}

	public function testOnParserBeforeInternalParse_NoParserOptions() {
		$profile = new SpeedscopeProfile( 'test', SpeedscopeProfile::CAUSE_SAMPLE, 'abc' );
		$hooks = $this->newHooks( $profile );
		$parser = $this->createMock( Parser::class );
		$parser->expects( $this->once() )->method( 'getOptions' )->willReturn( null );
		$stripState = $this->createNoOpMock( StripState::class );
		$text = '';
		$hooks->onParserBeforeInternalParse( $parser, $text, $stripState );
		$this->assertFalse( $profile->shouldStoreParserReport() );
	}

	public function testOnParserBeforeInternalParse_NoProfile() {
		$hooks = $this->newHooks( null );
		$parser = $this->createNoOpMock( Parser::class );
		$stripState = $this->createNoOpMock( StripState::class );
		$text = '';
		$hooks->onParserBeforeInternalParse( $parser, $text, $stripState );
	}

}

<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks;
use MediaWiki\Extension\Speedscope\Profiler\Excimer\ExcimerSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\Profiler\NoOpSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\Profiler\Parser\ParserSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Extension\Speedscope\SpeedscopeProfilerManager;
use MediaWiki\Page\Article;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Parser\PPFrame_Hash;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 */
class ProfilePreviewsHooksIntegrationTest extends MediaWikiIntegrationTestCase {

	protected function tearDown(): void {
		parent::tearDown();

		if ( method_exists( PPFrame_Hash::class, 'setProfiler' ) ) {
			PPFrame_Hash::setProfiler( null );
		}
	}

	/**
	 * @covers \MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks::onParserBeforeInternalParse
	 * @covers \MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks::onParserLimitReportFormat
	 * @covers \MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks::onParserLimitReportPrepare
	 *
	 * @dataProvider provideProfilerTypes
	 */
	public function testPreviewParseStartsProfileRecording(
		bool $parserProfilerEnabled,
		?string $wpProfileType,
		string $expectedProfilerClass,
	) {
		if ( $parserProfilerEnabled && !method_exists( PPFrame_Hash::class, 'setProfiler' ) ) {
			$this->markTestSkipped( 'Core patch not applied (could not find PPFrame_Hash::setProfiler())' );
		}

		$this->overrideConfigValues( [
			SpeedscopeConfigNames::ENDPOINT => 'http://localhost:3000',
			SpeedscopeConfigNames::ENVIRONMENT => 'prod',
			SpeedscopeConfigNames::ENABLE_PARSER_PROFILER => $parserProfilerEnabled,
		] );

		$profilerManager = $this->getServiceContainer()->get( 'Speedscope.ProfilerManager' );
		/** @var SpeedscopeProfilerManager $profilerManager $page */

		$this->assertNull( $profilerManager->getProfile() );
		$this->assertInstanceOf( NoOpSpeedscopeProfiler::class, $profilerManager->getProfiler() );

		$page = $this->getExistingTestPage( __METHOD__ );
		$context = new DerivativeContext( RequestContext::getMain() );
		$user = $this->getTestUser();
		$context->setUser( $user->getUser() );
		$context->setTitle( $page->getTitle() );
		$context->getOutput()->setTitle( $page->getTitle() );
		$context->getRequest()->setVal( 'wpProfilePreview', true );
		$context->getRequest()->setVal( 'wpProfileType', $wpProfileType );
		RequestContext::getMain()->setUser( $user->getUser() );
		$this->getServiceContainer()->getUserOptionsManager()->setOption(
			$user->getUserIdentity(),
			ProfilePreviewsHooks::PREFERENCE_NAME,
			true
		);
		$this->getServiceContainer()->getUserOptionsManager()->saveOptions( $user->getUserIdentity() );
		$editPage = new EditPage( Article::newFromWikiPage( $page, $context ) );
		[ 'parserOutput' => $parserOutput ] = TestingAccessWrapper::newFromObject( $editPage )
			->doPreviewParse( $page->getContent() );
		/** @var ParserOutput $parserOutput */

		$this->assertInstanceOf( $expectedProfilerClass, $profilerManager->getProfiler() );
		$profile = $profilerManager->getProfile();
		$this->assertNotNull( $profile );
		$this->assertEquals( SpeedscopeProfile::CAUSE_FORCED_PREVIEW, $profile->getCause() );

		$limitReport = EditPage::getPreviewLimitReport( $parserOutput );
		$this->assertStringContainsString( 'http://localhost:3000/view/', $limitReport );
	}

	public static function provideProfilerTypes(): array {
		return [
			[ true, null, ParserSpeedscopeProfiler::class ],
			[ false, null, ExcimerSpeedscopeProfiler::class ],
			[ true, 'php', ExcimerSpeedscopeProfiler::class ],
			[ false, 'parser', ExcimerSpeedscopeProfiler::class ],
			[ true, 'abcdefg', ParserSpeedscopeProfiler::class ],
			[ false, 'abcdefg', ExcimerSpeedscopeProfiler::class ],
		];
	}

}

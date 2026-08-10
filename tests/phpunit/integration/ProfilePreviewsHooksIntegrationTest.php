<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks;
use MediaWiki\Extension\Speedscope\Profiler\Excimer\ExcimerSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\Profiler\NoOpSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Extension\Speedscope\SpeedscopeProfilerManager;
use MediaWiki\Page\Article;
use MediaWiki\Parser\ParserOutput;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @group Database
 */
class ProfilePreviewsHooksIntegrationTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers \MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks::onParserBeforeInternalParse
	 * @covers \MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks::onParserLimitReportFormat
	 * @covers \MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks::onParserLimitReportPrepare
	 */
	public function testPreviewParseStartsProfileRecording() {
		$this->overrideConfigValues( [
			SpeedscopeConfigNames::ENDPOINT => 'http://localhost:3000',
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

		// TODO test with parser profiler as well
		$this->assertInstanceOf( ExcimerSpeedscopeProfiler::class, $profilerManager->getProfiler() );
		$profile = $profilerManager->getProfile();
		$this->assertNotNull( $profile );
		$this->assertEquals( SpeedscopeProfile::CAUSE_FORCED_PREVIEW, $profile->getCause() );

		$limitReport = EditPage::getPreviewLimitReport( $parserOutput );
		$this->assertStringContainsString( 'http://localhost:3000/view/', $limitReport );
	}

}

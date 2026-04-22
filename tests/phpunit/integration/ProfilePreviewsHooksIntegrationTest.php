<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration;

use MediaWiki\Context\DerivativeContext;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Extension\Speedscope\HookHandlers\ProfilePreviewsHooks;
use MediaWiki\Extension\Speedscope\Profiler\ISpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Page\Article;
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
			SpeedscopeConfigNames::ENDPOINT => 'http://localhost:3000'
		] );

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
		$this->setService( 'Speedscope.Profiler', function () {
			$recordingStarted = false;

			$mock = $this->createMock( ISpeedscopeProfiler::class );
			$mock->expects( $this->once() )
				->method( 'recordProfile' )
				->with( SpeedscopeProfile::CAUSE_FORCED_PREVIEW )
				->willReturnCallback( static function () use ( &$recordingStarted ) {
					$recordingStarted = true;
				} );
			$mock->expects( $this->once() )->method( 'stopRecording' );

			$profile = new SpeedscopeProfile(
				'test',
				SpeedscopeProfile::CAUSE_FORCED_PREVIEW,
				'integrationtest123'
			);
			$mock->method( 'getProfile' )
				->willReturnCallback( static function () use ( $profile, &$recordingStarted ) {
					return $recordingStarted ? $profile : null;
				} );

			return $mock;
		} );
		[ 'parserOutput' => $parserOutput ] = TestingAccessWrapper::newFromObject( $editPage )
			->doPreviewParse( $page->getContent() );

		$limitReport = EditPage::getPreviewLimitReport( $parserOutput );
		$this->assertStringContainsString( 'http://localhost:3000/view/', $limitReport );
	}

}

<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration\Profiler;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\Speedscope\Profiler\ExcimerSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfig;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Extension\Speedscope\SpeedscopeLogger;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Language\RawMessage;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use StatusValue;
use Wikimedia\ScopedCallback;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\Speedscope\Profiler\ExcimerSpeedscopeProfiler
 */
class ExcimerSpeedscopeProfilerTest extends MediaWikiIntegrationTestCase {

	/**
	 * @return ExcimerSpeedscopeProfiler
	 */
	private function newProfiler() {
		return TestingAccessWrapper::newFromObject( new ExcimerSpeedscopeProfiler(
			SpeedscopeConfig::newFromGlobals(),
		) );
	}

	protected function setUp(): void {
		parent::setUp();
		putenv( 'SPEEDSCOPE_FORCE_PROFILE=0' );
		$this->overrideConfigValues( [
			SpeedscopeConfigNames::ENVIRONMENT => 'test',
			SpeedscopeConfigNames::EXCLUDED_ENTRY_POINTS => [],
			SpeedscopeConfigNames::FORCED_PARAM => 'forceprofile',
			SpeedscopeConfigNames::PERIOD => [ 'forced' => 0.0001, 'sample' => 0.001 ],
			SpeedscopeConfigNames::SAMPLING_RATES => [ 'test' => 0 ],
		] );
	}

	private function forceProfile( string $param = 'forceprofile' ): ScopedCallback {
		// phpcs:ignore MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
		$_GET[$param] = 1;
		return new ScopedCallback( static function () use ( $param ) {
			// phpcs:ignore MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
			unset( $_GET[$param] );
		} );
	}

	public function testForcedViaEnv() {
		putenv( 'SPEEDSCOPE_FORCE_PROFILE=1' );
		$this->assertSame( SpeedscopeProfile::CAUSE_FORCED_ENV, $this->newProfiler()->isForced() );
	}

	public function testForcedViaParam() {
		$this->overrideConfigValue( SpeedscopeConfigNames::FORCED_PARAM, 'testprofile' );
		$sc = $this->forceProfile( 'testprofile' );

		$this->assertSame( SpeedscopeProfile::CAUSE_FORCED_URL, $this->newProfiler()->isForced() );
	}

	public function testNotForcedByDefault() {
		$this->assertNull( $this->newProfiler()->isForced() );
	}

	public function testCreateForcedProfile() {
		$sc = $this->forceProfile();
		$profiler = $this->newProfiler();
		$profiler->init();

		$profile = $profiler->getProfile();
		$this->assertNotNull( $profile );
		$this->assertTrue( $profile->isForced() );
	}

	public function testShouldSampleRequest_True() {
		$this->overrideConfigValues( [
			SpeedscopeConfigNames::SAMPLING_RATES => [ 'test' => 1 ],
		] );

		$this->assertTrue( $this->newProfiler()->shouldSampleRequest() );
	}

	public function testShouldSampleRequest_False_ExcludedEntryPoint() {
		$this->overrideConfigValues( [
			SpeedscopeConfigNames::SAMPLING_RATES => [ 'test' => 1 ],
			SpeedscopeConfigNames::EXCLUDED_ENTRY_POINTS => [ MW_ENTRY_POINT ],
		] );

		$this->assertFalse( $this->newProfiler()->shouldSampleRequest() );
	}

	public function testShouldSampleRequest_False_SamplingRate() {
		$this->overrideConfigValues( [
			SpeedscopeConfigNames::SAMPLING_RATES => [ 'test' => 0 ],
		] );

		$this->assertFalse( $this->newProfiler()->shouldSampleRequest() );
	}

	public function testDeferredUpdate() {
		$sc = $this->forceProfile();
		$sc2 = DeferredUpdates::preventOpportunisticUpdates();
		$profiler = $this->newProfiler();
		$profiler->forceDeferredUpdate = true;
		$profiler->init();

		// Disable the profile logger so we don't run more code than necessary
		$this->mockProfileLogger( StatusValue::newGood() );

		$this->assertNull( $profiler->getProfile()->getData() );
		$this->runDeferredUpdates();
		$this->assertIsArray( $profiler->getProfile()->getData() );
	}

	public function testDeferredUpdate_WithSendErrors() {
		$sc = $this->forceProfile();
		$sc2 = DeferredUpdates::preventOpportunisticUpdates();
		$profiler = $this->newProfiler();
		$profiler->forceDeferredUpdate = true;
		$profiler->init();

		// Disable the profile logger so we don't run more code than necessary
		$this->mockProfileLogger(
			StatusValue::newFatal( new RawMessage( 'Test Error' ) )
				->warning( new RawMessage( 'Test warning' ) )
		);

		$logger = $this->createMock( LoggerInterface::class );
		$logger->expects( $this->once() )->method( 'warning' )->with( 'Test warning' );
		$logger->expects( $this->once() )->method( 'error' )->with( 'Test Error' );
		$this->setLogger( 'Speedscope', $logger );

		$this->runDeferredUpdates();
	}

	private function mockProfileLogger( StatusValue $statusValue ): void {
		$this->overrideMwServices( services: [
			'Speedscope.ProfileLogger' => function () use ( $statusValue ) {
				$mock = $this->createMock( SpeedscopeLogger::class );
				$mock->expects( $this->once() )->method( 'log' )->willReturn( $statusValue );
				return $mock;
			},
		] );
	}

}

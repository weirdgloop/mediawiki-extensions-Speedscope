<?php

namespace MediaWiki\Extension\Speedscope\Profiler\Excimer;

use ExcimerProfiler;
use LogicException;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\Speedscope\Profiler\AbstractSpeedscopeProfiler;
use MediaWiki\Extension\Speedscope\SpeedscopeConfig;
use MediaWiki\Extension\Speedscope\SpeedscopeLogger;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Extension\Speedscope\Statsd\SpeedscopeStatsdLogger;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * This class allows generating a speedscope profile using excimer.
 * @see https://www.mediawiki.org/wiki/Excimer
 */
class ExcimerSpeedscopeProfiler extends AbstractSpeedscopeProfiler {

	private ExcimerProfiler $excimer;
	private bool $stopped = false;
	/** Currently only used in tests */
	private bool $forceDeferredUpdate = false;

	public function __construct(
		private readonly SpeedscopeConfig $config
	) {
		parent::__construct( $config->getEnvironment() );
	}

	/**
	 * Check if we should record a profile, and start the profiler if so.
	 * @internal Only for use in bootstrap.php.
	 */
	public function init(): void {
		// @codeCoverageIgnoreStart
		if ( !extension_loaded( 'excimer' ) ) {
			// This is already required in extension.json, but let's throw here instead of below when constructing
			// an ExcimerProfiler
			throw new LogicException( 'Excimer needs to be loaded to use the Speedscope extension!' );
		}
		// @codeCoverageIgnoreEnd

		$forced = $this->isForced();
		if ( $forced !== null ) {
			$this->recordProfile( $forced );
		} elseif ( $this->shouldSampleRequest() ) {
			$this->recordProfile( SpeedscopeProfile::CAUSE_SAMPLE );
		}
	}

	/**
	 * Record a profile using excimer, and schedule a shutdown function or a callable update to send it to the
	 * speedscope service.
	 * @inheritDoc
	 */
	public function doRecordProfile(): void {
		$this->excimer = new ExcimerProfiler();
		$period = $this->profile->isForced() ? $this->config->getForcedPeriod() : $this->config->getSamplePeriod();
		$this->excimer->setPeriod( $period );
		$this->excimer->setEventType( EXCIMER_REAL );
		$this->excimer->start();

		if ( MW_ENTRY_POINT === 'cli' && !$this->forceDeferredUpdate ) {
			register_shutdown_function( $this->send( ... ) );
		} else {
			DeferredUpdates::addCallableUpdate( $this->send( ... ) );
		}
	}

	/** @inheritDoc */
	public function stopRecording(): void {
		if ( $this->stopped ) {
			return;
		}
		$this->stopped = true;
		$this->excimer->stop();
	}

	/**
	 * Stop the profiler and send the profile to the speedscope service.
	 */
	private function send(): void {
		// @codeCoverageIgnoreStart
		if ( !MediaWikiServices::hasInstance() ) {
			// We probably don't want the profile if the service container isn't even ready yet
			wfLogWarning( 'Cannot send speedscope profile before the service container is initialized!' );
			return;
		}
		// @codeCoverageIgnoreEnd

		$this->stopRecording();
		$this->profile->setData( $this->excimer->getLog()->getSpeedscopeData() );

		$profileLogger = MediaWikiServices::getInstance()->getService( 'Speedscope.ProfileLogger' );
		/** @var SpeedscopeLogger $profileLogger */
		$status = $profileLogger->log( $this->profile );

		$this->logStatus( $status );

		if ( !$this->profile->isForced() ) {
			$statsdLogger = MediaWikiServices::getInstance()->getService( 'Speedscope.StatsdLogger' );
			/** @var SpeedscopeStatsdLogger $statsdLogger */
			$statsdLogger->processExcimerLog( $this->excimer->getLog() );
		}
	}

	/**
	 * @return bool Whether the current request should be sampled.
	 */
	private function shouldSampleRequest(): bool {
		$samplingRate = $this->config->getSamplingRates()[$this->config->getEnvironment()] ?? 0;
		return !in_array( MW_ENTRY_POINT, $this->config->getExcludedEntryPoints() )
			&& $samplingRate > 0
			&& mt_rand() / mt_getrandmax() < $samplingRate
			// phpcs:ignore MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
			&& !isset( $_POST['wpProfilePreview'] );
	}

	/**
	 * Checks Whether a profile is forced for this request, either via the configured parameter or the
	 * `SPEEDSCOPE_FORCE_PROFILE` environment variable.
	 * @return string|null One of the SpeedscopeProfile::CAUSE... constants
	 */
	private function isForced(): ?string {
		// phpcs:ignore MediaWiki.Usage.SuperGlobalsUsage.SuperGlobals
		if ( isset( $_GET[$this->config->getForcedParam()] ) ) {
			return SpeedscopeProfile::CAUSE_FORCED_URL;
		} elseif ( getenv( 'SPEEDSCOPE_FORCE_PROFILE' ) ) {
			return SpeedscopeProfile::CAUSE_FORCED_ENV;
		}
		return null;
	}

}

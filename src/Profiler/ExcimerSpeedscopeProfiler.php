<?php

namespace MediaWiki\Extension\Speedscope\Profiler;

use ExcimerProfiler;
use LogicException;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\Speedscope\SpeedscopeConfig;
use MediaWiki\Extension\Speedscope\SpeedscopeLogger;
use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MediaWikiServices;

/**
 * This class is used to initialize the profiler and store data before the service container is initialized.
 */
class ExcimerSpeedscopeProfiler implements ISpeedscopeProfiler {

	private ExcimerProfiler $excimer;
	private ?SpeedscopeProfile $profile = null;

	/**
	 * Only for use by bootstrap.php!
	 */
	public function __construct(
		private readonly SpeedscopeConfig $config,
	) {
	}

	/**
	 * Only for use by bootstrap.php!
	 */
	public function init(): void {
		if ( !extension_loaded( 'excimer' ) ) {
			// This is already required in extension.json, but let's throw here instead of below when constructing
			// an ExcimerProfiler
			throw new LogicException( 'Excimer needs to be loaded to use the Speedscope extension!' );
		}

		if ( $this->isForced() || $this->shouldSampleRequest() ) {
			$this->recordProfile();
		}
	}

	private function recordProfile(): void {
		// Lazy-autoload class
		require_once __DIR__ . '/../SpeedscopeProfile.php';

		$this->profile = new SpeedscopeProfile(
			environment: $this->config->getEnvironment(),
			forced: $this->isForced(),
			id: bin2hex( random_bytes( 16 ) )
		);

		$this->excimer = new ExcimerProfiler();
		$period = $this->profile->isForced() ? $this->config->getForcedPeriod() : $this->config->getSamplePeriod();
		$this->excimer->setPeriod( $period );
		$this->excimer->setEventType( EXCIMER_REAL );
		$this->excimer->start();

		if ( MW_ENTRY_POINT === 'cli' ) {
			register_shutdown_function( $this->send( ... ) );
		} else {
			DeferredUpdates::addCallableUpdate( $this->send( ... ) );
		}
	}

	private function send(): void {
		if ( !MediaWikiServices::hasInstance() ) {
			// We probably don't want the profile if the service container isn't even ready yet
			wfLogWarning( 'Cannot send speedscope profile before the service container is initialized!' );
			return;
		}

		$this->excimer->stop();
		$this->profile->setData( $this->excimer->getLog()->getSpeedscopeData() );

		$profileLogger = MediaWikiServices::getInstance()->getService( 'Speedscope.ProfileLogger' );
		/** @var SpeedscopeLogger $profileLogger */
		$status = $profileLogger->log( $this->profile );

		$logger = LoggerFactory::getInstance( 'Speedscope' );

		if ( $status->isGood() ) {
			$logger->debug( 'Successfully logged speedscope profile.' );
		} else {
			foreach ( $status->getMessages( 'warning' ) as $warning ) {
				$logger->warning( wfMessage( $warning )->text() );
			}
			foreach ( $status->getMessages( 'error' ) as $error ) {
				$logger->error( wfMessage( $error )->text() );
			}
		}
	}

	private function shouldSampleRequest(): bool {
		$samplingRate = $this->config->getSamplingRates()[$this->config->getEnvironment()] ?? 0;
		return !in_array( MW_ENTRY_POINT, $this->config->getExcludedEntryPoints() )
			&& $samplingRate > 0
			&& mt_rand() / mt_getrandmax() < $samplingRate;
	}

	private function isForced(): bool {
		return isset( $_GET[$this->config->getForcedParam()] ) || getenv( 'SPEEDSCOPE_FORCE_PROFILE' );
	}

	/**
	 * @inheritDoc
	 */
	public function getProfile(): ?SpeedscopeProfile {
		return $this->profile;
	}

}

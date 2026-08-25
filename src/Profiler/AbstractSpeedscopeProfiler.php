<?php

namespace MediaWiki\Extension\Speedscope\Profiler;

use MediaWiki\Extension\Speedscope\SpeedscopeProfile;
use MediaWiki\Logger\LoggerFactory;
use StatusValue;

abstract class AbstractSpeedscopeProfiler implements ISpeedscopeProfiler {

	protected ?SpeedscopeProfile $profile = null;

	public function __construct(
		protected readonly string $environment,
	) {
	}

	/** @inheritDoc */
	public function recordProfile( string $cause ): void {
		$this->profile = new SpeedscopeProfile(
			environment: $this->environment,
			cause: $cause,
			id: bin2hex( random_bytes( 16 ) )
		);
		$this->doRecordProfile();
	}

	/**
	 * Start recording a profile.
	 */
	abstract protected function doRecordProfile(): void;

	/** @inheritDoc */
	public function getProfile(): ?SpeedscopeProfile {
		return $this->profile;
	}

	protected function logStatus( StatusValue $status ): void {
		$logger = LoggerFactory::getInstance( 'Speedscope' );

		if ( $status->isGood() ) {
			$logger->debug( 'Successfully logged speedscope profile.' );
		} else {
			foreach ( $status->getMessages( 'warning' ) as $warning ) {
				$logger->warning( wfMessage( $warning )->inLanguage( 'en' )->text() );
			}
			foreach ( $status->getMessages( 'error' ) as $error ) {
				$logger->error( wfMessage( $error )->inLanguage( 'en' )->text() );
			}
		}
	}

}

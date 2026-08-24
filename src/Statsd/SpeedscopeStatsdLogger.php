<?php

namespace MediaWiki\Extension\Speedscope\Statsd;

use ExcimerLog;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Speedscope\SpeedscopeConfigNames;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Profiler\ProfilingContext;
use Wikimedia\Stats\StatsFactory;

/**
 * Based on https://github.com/wikimedia/operations-mediawiki-config/blob/9ad56271e7f4110b729f868d81432102d9a4524e/src/Profiler.php
 */
class SpeedscopeStatsdLogger {

	public const CONSTRUCTOR_OPTIONS = [
		SpeedscopeConfigNames::LOG_TO_STATSD,
	];

	public function __construct(
		private readonly ServiceOptions $options,
		private readonly ExcimerComponentExtractor $excimerComponentExtractor,
		private readonly StatsFactory $statsFactory,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	public function processExcimerLog( ExcimerLog $log ): void {
		$time = hrtime(true);
		if ( !$this->options->get( SpeedscopeConfigNames::LOG_TO_STATSD ) ) {
			return;
		}

		$requestMethod = RequestContext::getMain()->getRequest()->getMethod();
		if ( $requestMethod === '' ) {
			return;
		}

		$handler = ProfilingContext::singleton()->getHandlerMetricPrefix();
		if ( $handler === 'unknown' ) {
			return;
		}

		$logLines = explode( "\n", $log->formatCollapsed() );
		foreach ( $logLines as $line ) {
			if ( $line === '' ) {
				continue;
			}

			$this->statsFactory->getCounter( 'samples_total' )
				->setLabels( [
					'handler' => $handler,
					'request_method' => $requestMethod,
				] )
				->increment();

			$components = [];
			foreach ( explode( ';', $line ) as $function ) {
				$component = $this->excimerComponentExtractor->extractFromCaller( $function );
				if ( !isset( $components[$component] ) && $component !== null ) {
					$this->statsFactory->getCounter( 'samples_by_component_total' )
						->setLabels( [
							'handler' => $handler,
							'request_method' => $requestMethod,
							'component' => $component
						] )
						->increment();
					$components[$component] = true;
				}
			}

			$done = hrtime(true);

			$total = ( $done - $time ) / 1e6;
			LoggerFactory::getInstance( 'speedscope-statsd' )->info( "Took {$total}ms" );
		}
	}

}

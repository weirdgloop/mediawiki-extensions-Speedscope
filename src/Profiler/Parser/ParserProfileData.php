<?php

namespace MediaWiki\Extension\Speedscope\Profiler\Parser;

class ParserProfileData {

	private array $frames = [];
	private array $frameMap = [];
	private array $currentStack = [];
	private array $samples = [];
	private array $weights = [];

	private ?float $lastTimestamp = null;

	public function startFrame( string $name ): void {
		$now = (float)hrtime( true );

		if ( $this->currentStack ) {
			$this->recordSample( $now );
		}

		$this->lastTimestamp = $now;

		$frameIndex = $this->getOrAddFrame( $name );
		$this->currentStack[] = $frameIndex;
	}

	public function endFrame(): void {
		if ( !$this->currentStack ) {
			return;
		}

		$now = (float)hrtime( true );
		$this->recordSample( $now );
		array_pop( $this->currentStack );
		$this->lastTimestamp = $now;
	}

	private function recordSample( float $now ): void {
		if ( $this->lastTimestamp === null ) {
			return;
		}

		$duration = $now - $this->lastTimestamp;

		if ( $duration > 0 && $this->currentStack ) {
			$this->samples[] = $this->currentStack;
			$this->weights[] = $duration;
		}
	}

	private function getOrAddFrame( string $name ): int {
		if ( !isset( $this->frameMap[$name] ) ) {
			$index = count( $this->frames );
			$this->frames[] = [
				'name' => $name,
			];
			$this->frameMap[$name] = $index;
		}
		return $this->frameMap[$name];
	}

	/**
	 * @return array Speedscope data
	 */
	public function toArray(): array {
		return [
			'$schema' => 'https://www.speedscope.app/file-format-schema.json',
			'shared' => [
				'frames' => $this->frames,
			],
			'profiles' => [
				[
					// The name is set by SpeedscopeLogger
					'type' => 'sampled',
					'unit' => 'nanoseconds',
					'startValue' => 0,
					'endValue' => array_sum( $this->weights ),
					'samples' => $this->samples,
					'weights' => $this->weights,
				],
			],
		];
	}

}

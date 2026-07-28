<?php

namespace MediaWiki\Extension\Speedscope;

use MediaWiki\Extension\Speedscope\Profiler\ISpeedscopeProfiler;

class SpeedscopeProfilerManager {

	public function __construct(
		private ISpeedscopeProfiler $profiler,
	) {
	}

	public function getProfiler(): ISpeedscopeProfiler {
		return $this->profiler;
	}

	public function setProfiler( ISpeedscopeProfiler $profiler ): void {
		$this->profiler = $profiler;
	}

	public function getProfile(): ?SpeedscopeProfile {
		return $this->getProfiler()->getProfile();
	}

}

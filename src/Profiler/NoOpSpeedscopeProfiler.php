<?php

namespace MediaWiki\Extension\Speedscope\Profiler;

use MediaWiki\Extension\Speedscope\SpeedscopeProfile;

class NoOpSpeedscopeProfiler implements ISpeedscopeProfiler {

	/**
	 * @inheritDoc
	 */
	public function getProfile(): ?SpeedscopeProfile {
		return null;
	}

}

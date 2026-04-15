<?php

namespace MediaWiki\Extension\Speedscope\Profiler;

use MediaWiki\Extension\Speedscope\SpeedscopeProfile;

/**
 * A profiler implementation that doesn't do anything.
 * Used as a fallback in case $wgSpeedscopeProfiler is not defined
 * because bootstrap.php wasn't included.
 */
class NoOpSpeedscopeProfiler implements ISpeedscopeProfiler {

	/**
	 * @inheritDoc
	 */
	public function getProfile(): ?SpeedscopeProfile {
		return null;
	}

}

<?php

namespace MediaWiki\Extension\Speedscope\Profiler;

use MediaWiki\Extension\Speedscope\SpeedscopeProfile;

interface ISpeedscopeProfiler {

	/**
	 * @return SpeedscopeProfile|null The profile that's being recorded, or null if none is being recorded
	 */
	public function getProfile(): ?SpeedscopeProfile;

}

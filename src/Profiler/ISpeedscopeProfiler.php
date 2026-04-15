<?php

namespace MediaWiki\Extension\Speedscope\Profiler;

use MediaWiki\Extension\Speedscope\SpeedscopeProfile;

interface ISpeedscopeProfiler {

	/**
	 * @return SpeedscopeProfile|null The profile data, or null if non is being recorded
	 */
	public function getProfile(): ?SpeedscopeProfile;

}

<?php

namespace MediaWiki\Extension\Speedscope;

class NoOpSpeedscopeProfiler implements ISpeedscopeProfiler {

	/**
	 * @inheritDoc
	 */
	public function getProfile(): ?SpeedscopeProfile {
		return null;
	}

}

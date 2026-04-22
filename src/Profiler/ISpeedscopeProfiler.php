<?php

namespace MediaWiki\Extension\Speedscope\Profiler;

use MediaWiki\Extension\Speedscope\SpeedscopeProfile;

interface ISpeedscopeProfiler {

	/**
	 * @return SpeedscopeProfile|null The profile that's being recorded, or null if none is being recorded
	 */
	public function getProfile(): ?SpeedscopeProfile;

	/**
	 * Start recording a profile.
	 * @param string $cause One of the SpeedscopeProfile::CAUSE_... constants
	 */
	public function recordProfile( string $cause, ?string $id = null ): void;

	/**
	 * Stop recording the profile that's currently being recorded.
	 */
	public function stopRecording(): void;

}

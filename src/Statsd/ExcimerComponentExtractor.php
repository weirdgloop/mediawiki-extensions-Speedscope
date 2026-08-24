<?php

namespace MediaWiki\Extension\Speedscope\Statsd;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use ReflectionClass;
use ReflectionException;

/**
 * Based on https://github.com/wikimedia/operations-mediawiki-config/blob/9ad56271e7f4110b729f868d81432102d9a4524e/src/Profiler.php
 */
class ExcimerComponentExtractor {

	// The regex is not anchored at the end because there's a number after the top element in the stack
	private const CLASS_REGEX = '/^([a-zA-Z0-9_\\\\]+)::[a-zA-Z0-9_]+(?: \d+)?$/';

	public const CONSTRUCTOR_OPTIONS = [
		MainConfigNames::ExtensionDirectory,
		MainConfigNames::StyleDirectory,
	];

	private array $prefixes;
	private string $vendorPath;

	public function __construct(
		private readonly ServiceOptions $options,
	) {
		$this->options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );

		$installationPath = MW_INSTALL_PATH;
		$extensionDirectory = $this->options->get( MainConfigNames::ExtensionDirectory );
		$styleDirectory = $this->options->get( MainConfigNames::StyleDirectory );
		$this->prefixes = [
			"$extensionDirectory/" => 'ext_',
			"$styleDirectory/" => 'skin_',
			"$installationPath/includes/libs/" => 'lib_',
			"$installationPath/includes/" => 'core_',
			"$installationPath/vendor/wikimedia/" => 'lib_',
		];
		$this->vendorPath = "$installationPath/vendor/";
	}

	public function extractFromCaller( string $caller ): ?string {
		$matches = [];
		if ( !preg_match( self::CLASS_REGEX, $caller, $matches ) ) {
			return null;
		}

		try {
			$path = ( new ReflectionClass( $matches[1] ) )->getFileName();
			if ( $path === false ) {
				// Function from PHP core or an extension
				return 'other';
			}
			return $this->extractFromPath( $path );
		} catch ( ReflectionException ) {
			return 'unknown';
		}
	}

	private function extractFromPath( string $path ): string {
		foreach ( $this->prefixes as $directory => $prefix ) {
			if ( str_starts_with( $path, $directory ) ) {
				$pos = strpos( $path, '/', strlen( $directory ) );
				$name = ( $pos !== false )
					// Treat the relative subdirectory as the component. This ideally matches
					// the relative sub-namespace of Wikimedia\ and MediaWiki\ namespaced files.
					? substr( $path, strlen( $directory ), $pos - strlen( $directory ) )
					// Placeholder to use for files that should be moved to a subdirectory
					: 'other';

				return $prefix . str_replace( '.', '_', $name );
			}
		}

		if ( str_starts_with( $path, $this->vendorPath ) ) {
			return 'lib_other';
		}

		return 'other';
	}

}

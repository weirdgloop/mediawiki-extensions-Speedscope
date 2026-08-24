<?php

namespace MediaWiki\Extension\Speedscope\Tests\Unit;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Speedscope\Statsd\ExcimerComponentExtractor;
use MediaWiki\MainConfigNames;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Speedscope\Statsd\ExcimerComponentExtractor
 */
class ExcimerComponentExtractorTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideTestExtractFromCaller
	 */
	public function testExtractFromCaller( string $caller, ?string $expected ) {
		$extractor = new ExcimerComponentExtractor( new ServiceOptions(
			ExcimerComponentExtractor::CONSTRUCTOR_OPTIONS,
			[
				MainConfigNames::ExtensionDirectory => MW_INSTALL_PATH . '/extensions',
				MainConfigNames::StyleDirectory => MW_INSTALL_PATH . '/skins',
			],
		) );
		$this->assertSame( $expected, $extractor->extractFromCaller( $caller ) );
	}

	public static function provideTestExtractFromCaller(): array {
		return [
			[ '/var/www/html/w/index.php', null ],
			[ '/var/www/html/w/includes/WebStart.php', null ],
			[ '{closure:/var/www/html/w/includes/Setup.php(340)}', null ],
			[ 'Wikimedia\base_convert 1', null ],
			[ 'MediaWiki\MediaWikiServices::getInstance', 'core_other' ],
			[ 'MediaWiki\ResourceLoader\ResourceLoader::respond', 'core_ResourceLoader' ],
			[ 'MediaWiki\Language\Language::getJsData', 'core_language' ],
			[ 'Wikimedia\AtEase\AtEase::suppressWarnings 1', 'lib_at-ease' ],
		];
	}

}

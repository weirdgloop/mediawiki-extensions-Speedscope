<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration\Profiler;

use MediaWiki\Extension\Speedscope\Profiler\Parser\ParserProfileData;
use MediaWiki\Extension\Speedscope\Profiler\Parser\ParserSpeedscopeProfiler;
use MediaWiki\Page\PageIdentityValue;
use MediaWiki\Page\PageReference;
use MediaWiki\Page\PageReferenceValue;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\PPFrame_Hash;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \MediaWiki\Extension\Speedscope\Profiler\Parser\ParserSpeedscopeProfiler
 * @covers \MediaWiki\Extension\Speedscope\Profiler\Parser\ParserProfileData
 *
 * @group Database
 */
class ParserSpeedscopeProfilerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !method_exists( PPFrame_Hash::class, 'setProfiler' ) ) {
			$this->markTestSkipped( 'Core patch not applied (could not find PPFrame_Hash::setProfiler())' );
		} else {
			PPFrame_Hash::setProfiler( null );
			Parser::setProfiler( null );
		}
	}

	protected function tearDown(): void {
		parent::tearDown();

		if ( method_exists( PPFrame_Hash::class, 'setProfiler' ) ) {
			PPFrame_Hash::setProfiler( null );
			Parser::setProfiler( null );
		}
	}

	/**
	 * @return ParserSpeedscopeProfiler
	 */
	private function newProfiler( ?PageReference $page = null ) {
		return TestingAccessWrapper::newFromObject( new ParserSpeedscopeProfiler(
			'prod',
			$page ?? PageReferenceValue::localReference( NS_MAIN, 'Test Page' ),
			$this->getServiceContainer()->getLanguageFactory()->getLanguage( 'en' ),
		) );
	}

	public function testStartsAndEndsMainFrame() {
		$profiler = $this->newProfiler();
		$profiler->recordProfile( 'Test Profile' );
		$data = TestingAccessWrapper::newFromObject( $profiler->data );
		/** @var ParserProfileData $data */

		$this->assertCount( 1, $data->frames );
		$this->assertEquals( '[0:Test_Page]', $data->frames[0]['name'] );
		$this->assertEquals( [ 0 => 0 ], $data->currentStack );

		$profiler->stopRecording();
		$this->assertCount( 0, $data->currentStack );
		$this->assertCount( 1, $data->frameMap );
		$this->assertCount( 1, $data->frames );
		$this->assertCount( 1, $data->samples );
		$this->assertCount( 1, $data->weights );
	}

	public function testStoresParserFrames() {
		$template = PageIdentityValue::localIdentity( 1, NS_TEMPLATE, 'TestTemplate' . wfRandomString() );
		$this->editPage(
			$template,
			'{{int:editpage}}'
		);

		$page = PageReferenceValue::localReference( NS_MAIN, 'ParserProfileTest' );
		$profiler = $this->newProfiler( $page );
		$profiler->recordProfile( 'Test Profile' );
		$data = TestingAccessWrapper::newFromObject( $profiler->data );
		/** @var ParserProfileData $data */
		$this->assertCount( 1, $data->frames );
		$this->assertEquals( (string)$page, $data->frames[0]['name'] );
		$this->assertEquals( [ 0 ], $data->currentStack );

		$parser = $this->getServiceContainer()->getParserFactory()->getInstance();
		$parser->parse(
			'Test 123 {{' . $template->getDBkey() . '}}',
			$page,
			ParserOptions::newFromAnon(),
		);

		$profiler->stopRecording();

		$expectedFrames = [
			[ 'name' => (string)$page ],
			[ 'name' => '{{Template:' . $template->getDBkey() . '}}' ],
			// TODO fix this format
			[ 'name' => '{{Template:int:editpage}}' ],
			[ 'name' => 'Sanitizing HTML' ],
			[ 'name' => 'Tables' ],
			[ 'name' => 'Magic Words' ],
			[ 'name' => 'Internal Links' ],
			[ 'name' => 'External Links' ],
			[ 'name' => 'Magic Links' ],
			[ 'name' => 'Headings' ],
			[ 'name' => 'Block Levels' ],
			[ 'name' => 'Link holders' ],
			[ 'name' => 'Language Conversion' ],
			[ 'name' => 'TOC' ],
			[ 'name' => 'Tidying' ],
		];
		$this->assertEquals( $expectedFrames, $data->frames );
		$this->assertArrayEquals(
			array_column( $expectedFrames, 'name' ),
			array_keys( $data->frameMap ),
		);
		$this->assertCount( 0, $data->currentStack );
		$this->assertCount( 29, $data->samples );
		$this->assertCount( 29, $data->weights );
	}

	/** @dataProvider provideTestGetTemplateFrameName */
	public function testGetTemplateFrameName( string $templateName, string $expected ) {
		$this->assertEquals( $expected, $this->newProfiler()->getTemplateFrameName( $templateName ) );
	}

	public static function provideTestGetTemplateFrameName(): array {
		return [
			[ '#if', '{{#if}}' ],
			[ '#invoke:Test', '{{#invoke:Test}}' ],
			[ 'TestTemplate', '{{Template:TestTemplate}}' ],
			[ '#invoke: Test2 ', '{{#invoke: Test2}}' ]
		];
	}

}

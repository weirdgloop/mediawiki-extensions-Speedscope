<?php

namespace MediaWiki\Extension\Speedscope\Tests\Integration;

use MediaWikiIntegrationTestCase;

class ServiceWiringTest extends MediaWikiIntegrationTestCase {

	public function testServices() {
		// we manually loop over the services so the coverage is 100%
		// this wouldn't work with a data provider
		$services = require __DIR__ . '/../../../src/ServiceWiring.php';
		foreach ( array_keys( $services ) as $service ) {
			$this->getServiceContainer()->get( $service );
			$this->addToAssertionCount( 1 );
		}
	}

}

<?php

use PHPUnit\Framework\TestCase;

class EnvServerTest extends TestCase {

	public function testGetScheme() {
		$server = new EnvServer();
		$server->initialize(['HTTPS'=>'on']);
		$this->assertEquals($server->getScheme(),"https");

		$server->initialize(['HTTPS']);
		$this->assertEquals($server->getScheme(),"http");

		$server->initialize([]);
		$this->assertEquals($server->getScheme(),"http");
	}
}

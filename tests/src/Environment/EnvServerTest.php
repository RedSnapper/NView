<?php

class EnvServerTest extends PHPUnit_Framework_TestCase {

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

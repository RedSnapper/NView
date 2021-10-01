<?php

use PHPUnit\Framework\TestCase;

class NViewLoggerTest extends TestCase {

	public function testPushName() {
		$logger = new NViewLogger('Foo');
		$logger->pushName('Bar');
		$this->assertEquals('Bar',$logger->getName(),'->pushName() changes the channel name');
		$logger->pushName('Baz');
		$this->assertEquals('Baz',$logger->getName(),'->pushName() changes the channel name');
	}

	public function testPopName() {
		$logger = new NViewLogger('Foo');
		$logger->pushName('Bar');
		$logger->pushName('Baz');
		$logger->popName();
		$this->assertEquals('Bar',$logger->getName(),'->popName() pops the channel name');
		$logger->popName();
		$logger->popName();
		$this->assertEquals('Foo',$logger->getName(),'->popName() pops the channel name unless there are no more channels');
	}
}

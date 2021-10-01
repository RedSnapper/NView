<?php

use PHPUnit\Framework\TestCase;

class SphinxConnectionTest extends TestCase {

	public function testEsc() {
		$mysqli = $this->createMock('mysqli');
		$connection = new SphinxConnection($mysqli);
		$string = "@!";
		$connection->esc($string);
		$this->assertEquals($string,'\\\@\\\!','->esc() escapes the the provided string');
		$string = "\x00";
		$connection->esc($string);
		$this->assertEquals($string,"\\x00",'->esc() escapes the the provided string');
	}
}

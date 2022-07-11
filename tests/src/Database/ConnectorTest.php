<?php

use PHPUnit\Framework\TestCase;

class ConnectorTest extends TestCase {

	
	public function testOptionResolution() {
		$connector = new Connector;
		$connector->setDefaultOptions([0 => 'foo', 1 => 'bar']);
		$this->assertEquals([0 => 'baz', 1 => 'bar', 2 => 'boom'], $connector->getOptions(['options' => [0 => 'baz', 2 => 'boom']]));
	}

	public function mySqlConnectProvider() {
		return [
			['mysql:host=foo;dbname=bar', ['host' => 'foo', 'database' => 'bar', 'collation' => 'utf8_unicode_ci', 'default-character-set' => 'utf8']],
			['mysql:host=foo;port=111;dbname=bar', ['host' => 'foo', 'database' => 'bar', 'port' => 111, 'collation' => 'utf8_unicode_ci', 'default-character-set' => 'utf8']],
			['mysql:unix_socket=baz;dbname=bar', ['host' => 'foo', 'database' => 'bar', 'port' => 111, 'unix_socket' => 'baz', 'collation' => 'utf8_unicode_ci', 'default-character-set' => 'utf8']],
		];
	}

	public function mySqliConnectProvider() {
		return [
			[['host' => 'foo', 'database' => 'bar', 'collation' => 'utf8_unicode_ci', 'default-character-set' => 'utf8']],
			[['host' => 'foo', 'database' => 'bar', 'port' => 111, 'collation' => 'utf8_unicode_ci', 'default-character-set' => 'utf8']],
			[['host' => 'foo', 'database' => 'bar', 'port' => 111, 'unix_socket' => 'baz', 'collation' => 'utf8_unicode_ci', 'default-character-set' => 'utf8']],
		];
	}

	/**
	 * @dataProvider mySqlConnectProvider
	 */
	public function testMySqlConnectCallsCreateConnectionWithProperArguments($dsn, $config) {

		$connector = $this->getMockBuilder('MySqlConnector')
							->setConstructorArgs([$config])
							->setMethods(['createConnection','getOptions'])
							->getMock();
		$connection = $this->createMock('PDO');
		$statement = $this->createMock('PDOStatement');
		$connector->expects($this->once())->method('getOptions')->with($this->equalTo($config))->will($this->returnValue(['options']));
		$connector->expects($this->once())->method('createConnection')->with($this->equalTo($dsn), $this->equalTo($config), $this->equalTo(['options']))->will($this->returnValue($connection));
		$connection->expects($this->once())->method('prepare')->with('set names \'utf8\' collate \'utf8_unicode_ci\'')->willReturn($statement);
		$statement->expects($this->once())->method('execute');
		$connection->expects($this->any())->method('exec');
		$result = $connector->connect();
		$this->assertSame($result, $connection);
	}

	/**
	 * @dataProvider mySqliConnectProvider
	 */
	public function testMySqliConnectCallsCreateConnectionWithProperArguments($config) {

		$connector = $this->getMockBuilder('MySqliConnector')
			->setConstructorArgs([$config])
			->setMethods(['getOptions','setupConnection'])
			->getMock();
		$connection = $this->createMock('mysqli');
		$connector->expects($this->once())->method('setupConnection')->will($this->returnValue($connection));
		$connection->expects($this->once())->method('set_charset')->with('utf8')->willReturn(true);

		$result = $connector->connect();
		$this->assertSame($result, $connection);
	}
	
}
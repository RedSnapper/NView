<?php

class PDOConnectionTest extends PHPUnit_Framework_TestCase {

	public function testSelectProperlyCallsPDO()
	{
		$pdo = $this->getMockBuilder('DatabaseConnectionTestMockPDO')
			->setMethods(['prepare'])
			->getMock();

		$statement = $this->getMockBuilder('PDOStatement')
						->setMethods(['execute', 'fetchAll'])
						->getMock();
		$statement->expects($this->once())->method('execute')->with($this->equalTo(['foo' => 'bar']));
		$statement->expects($this->once())->method('fetchAll')->will($this->returnValue(['boom']));

		$pdo->expects($this->once())->method('prepare')->with('foo')->will($this->returnValue($statement));

		$mock = $this->getMockConnection(['prepareBindings'], $pdo);
		$mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['foo' => 'bar']))->will($this->returnValue(['foo' => 'bar']));
		$results = $mock->select('foo', ['foo' => 'bar']);
		$this->assertEquals(['boom'], $results);
//		$log = $mock->getQueryLog();
//		$this->assertEquals('foo', $log[0]['query']);
//		$this->assertEquals(['foo' => 'bar'], $log[0]['bindings']);
//		$this->assertTrue(is_numeric($log[0]['time']));
	}

	protected function getMockConnection($methods = [], $pdo = null) {
		$pdo = $pdo ?: $this->createMock('PDO');

		$connection = $this->getMockBuilder('PDOConnection')
			->setMethods($methods)
			->setConstructorArgs([$pdo])
			->getMock();
		return $connection;
	}
}
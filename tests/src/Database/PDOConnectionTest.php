<?php

use PHPUnit\Framework\TestCase;

class PDOConnectionTest extends TestCase {

	public function testSelectProperlyCallsPDO() {
		$pdo = $this->getMockBuilder('PDO')
			->setMethods(['prepare'])
			->disableOriginalConstructor()
			->getMock();

		$statement = $this->getMockBuilder('PDOStatement')
			->setMethods(['execute', 'fetchAll', 'bindValue'])
			->getMock();

		$statement->expects($this->once())->method('bindValue')->with($this->equalTo('foo'), $this->equalTo('bar'), $this->equalTo(PDO::PARAM_STR))->willReturn(true);
		$statement->expects($this->once())->method('execute');
		$statement->expects($this->once())->method('fetchAll')->will($this->returnValue(['boom']));

		$pdo->expects($this->once())->method('prepare')->with('foo')->will($this->returnValue($statement));

		$mock = $this->getMockConnection(['prepareBindings'], $pdo);
		$mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['foo' => 'bar']))->will($this->returnValue(['foo' => 'bar']));
		$results = $mock->select('foo', ['foo' => 'bar']);
		$this->assertEquals(['boom'], $results);
		$log = $mock->getQueryLog();
		$this->assertEquals('foo', $log[0]['query']);
		$this->assertEquals(['foo' => 'bar'], $log[0]['bindings']);
		$this->assertTrue(is_numeric($log[0]['time']));
	}

	public function testInsertCallsTheStatementMethod() {
		$connection = $this->getMockConnection(['statement']);
		$connection->expects($this->once())->method('statement')->with($this->equalTo('foo'), $this->equalTo(['bar']))->will($this->returnValue('baz'));
		$results = $connection->insert('foo', ['bar']);
		$this->assertEquals('baz', $results);
	}

	public function testUpdateCallsTheAffectingStatementMethod() {
		$connection = $this->getMockConnection(['affectingStatement']);
		$connection->expects($this->once())->method('affectingStatement')->with($this->equalTo('foo'), $this->equalTo(['bar']))->will($this->returnValue('baz'));
		$results = $connection->update('foo', ['bar']);
		$this->assertEquals('baz', $results);
	}

	public function testDeleteCallsTheAffectingStatementMethod() {
		$connection = $this->getMockConnection(['affectingStatement']);
		$connection->expects($this->once())->method('affectingStatement')->with($this->equalTo('foo'), $this->equalTo(['bar']))->will($this->returnValue('baz'));
		$results = $connection->delete('foo', ['bar']);
		$this->assertEquals('baz', $results);
	}

	public function testStatementProperlyCallsPDO() {
		$pdo = $this->getMockBuilder('PDO')
			->setMethods(['prepare'])
			->disableOriginalConstructor()
			->getMock();
		$statement = $this->getMockBuilder('PDOStatement')
			->setMethods(['execute', 'bindValue'])
			->getMock();

		$statement->expects($this->once())->method('bindValue')->with($this->equalTo('bar'), $this->equalTo(0), $this->equalTo(PDO::PARAM_INT))->willReturn(true);
		$statement->expects($this->once())->method('execute')->willReturn(true);
		$pdo->expects($this->once())->method('prepare')->with($this->equalTo('foo'))->will($this->returnValue($statement));
		$mock = $this->getMockConnection(['prepareBindings'], $pdo);
		$mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['bar']))->will($this->returnValue(['bar' => 0]));
		$results = $mock->statement('foo', ['bar']);
		$this->assertTrue($results);
		$log = $mock->getQueryLog();
		$this->assertEquals('foo', $log[0]['query']);
		$this->assertEquals(['bar'], $log[0]['bindings']);
		$this->assertTrue(is_numeric($log[0]['time']));
	}

	public function testAffectingStatementProperlyCallsPDO() {
		$pdo = $this->getMockBuilder('PDO')
			->setMethods(['prepare'])
			->disableOriginalConstructor()
			->getMock();
		$statement = $this->getMockBuilder('PDOStatement')
			->setMethods(['execute', 'rowCount', 'bindValue'])
			->getMock();
		$statement->expects($this->once())->method('bindValue')->with($this->equalTo('foo'), $this->equalTo('bar'), $this->equalTo(PDO::PARAM_STR))->willReturn(true);
		$statement->expects($this->once())->method('execute')->willReturn(true);
		$statement->expects($this->once())->method('rowCount')->will($this->returnValue(['boom']));
		$pdo->expects($this->once())->method('prepare')->with('foo')->will($this->returnValue($statement));
		$mock = $this->getMockConnection(['prepareBindings'], $pdo);
		$mock->expects($this->once())->method('prepareBindings')->with($this->equalTo(['foo' => 'bar']))->will($this->returnValue(['foo' => 'bar']));
		$results = $mock->update('foo', ['foo' => 'bar']);
		$this->assertEquals(['boom'], $results);
		$log = $mock->getQueryLog();
		$this->assertEquals('foo', $log[0]['query']);
		$this->assertEquals(['foo' => 'bar'], $log[0]['bindings']);
		$this->assertTrue(is_numeric($log[0]['time']));
	}

	public function testPretendOnlyLogsQueries() {
		$connection = $this->getMockConnection();
		$queries = $connection->pretend(function ($connection) {
			$connection->select('foo bar', ['baz']);
		});
		$this->assertEquals('foo bar', $queries[0]['query']);
		$this->assertEquals(['baz'], $queries[0]['bindings']);
	}

	public function testAlternateFetchModes() {
		$stmt = $this->createMock('PDOStatement');
		$stmt->expects($this->exactly(3))->method('fetchAll')->withConsecutive(
			[PDO::FETCH_ASSOC],
			[PDO::FETCH_COLUMN, 3, []],
			[PDO::FETCH_CLASS, 'stdClass', [1, 2, 3]]
		);
		$pdo = $this->createMock('PDO');
		$pdo->expects($this->any())->method('prepare')->will($this->returnValue($stmt));
		$connection = $this->getMockConnection(null, $pdo);
		$connection->setFetchMode(PDO::FETCH_ASSOC);
		$connection->select('SELECT * FROM foo');
		$connection->setFetchMode(PDO::FETCH_COLUMN, 3);
		$connection->select('SELECT * FROM foo');
		$connection->setFetchMode(PDO::FETCH_CLASS, 'stdClass', [1, 2, 3]);
		$connection->select('SELECT * FROM foo');
	}

	
	protected function getMockConnection($methods = null, $pdo = null) {
		$pdo = $pdo ?: $this->createMock('PDO');

		$connection = $this->getMockBuilder('PDOConnection')
			->setMethods($methods)
			->setConstructorArgs([$pdo])
			->getMock();
		$connection->enableQueryLog();
		return $connection;
	}


}
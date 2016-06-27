<?php

class SessionStoreTest extends PHPUnit_Framework_TestCase {

	public $session;

	public function setup() {

		$handler = $this->createMock(SessionHandlerInterface::class);

		$handler->method('read')->willReturn('foo|s:3:"bar";');
		$handler->method('write')->willReturn(true);
		$handler->method('close')->willReturn(true);
		$handler->method('open')->willReturn(true);
		$handler->method('destroy')->willReturn(true);
		$handler->method('gb')->willReturn(true);

		$env = new EnvSession();
		$this->session = new SessionStore($handler, $env);

	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSessionIsLoadedFromHandler() {
		$session = $this->session;

		$session->start();

		$this->assertEquals('bar', $session->get('foo'));
		$this->assertEquals('baz', $session->get('bar', 'baz'));
		$this->assertTrue($session->has('foo'));
		$this->assertFalse($session->has('bar'));
		$this->assertTrue($session->isStarted());
		$session->set('baz', 'boom');
		$this->assertTrue($session->has('baz'));
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSessionMigration() {
		$session = $this->session;
		$session->start();
		$oldId = $session->getId();
		$session->getHandler()->expects($this->never())->method('destroy');
		$this->assertTrue($session->migrate());
		$this->assertNotEquals($oldId, $session->getId());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSessionMigrationDestroy() {
		$session = $this->session;
		$session->start();
		$oldId = $session->getId();
		$session->getHandler()->expects($this->once())->method('destroy');
		$this->assertTrue($session->migrate(true));
		$this->assertNotEquals($oldId, $session->getId());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSessionInvalidate() {
		$session = $this->session;
		$session->start();
		$oldId = $session->getId();
		$session->set('foo', 'bar');
		$this->assertGreaterThan(0, count($session->all()));
		$session->flash('name', 'Taylor');
		$this->assertTrue($session->has('name'));
		$session->getHandler()->expects($this->once())->method('destroy')->with($oldId);
		$this->assertTrue($session->invalidate());
		$this->assertFalse($session->has('name'));
		$this->assertNotEquals($oldId, $session->getId());
		$this->assertCount(0, $session->all());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testSessionIsProperlySaved() {
		$session = $this->session;
		$session->start();
		$session->set('foo', 'bar');
		$session->flash('baz', 'boom');
		$session->getHandler()->expects($this->once())->method('write')->with(
			$this->getSessionId(),
			[
				'_token' => $session->token(),
				'foo' => 'bar',
				'baz' => 'boom',
				'flash.new' => [],
				'flash.old' => []
			]
		);
		$this->assertFalse($session->isStarted());
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testReplace() {
		$session = $this->session;
		$session->start();
		$session->set('foo', 'bar');
		$session->set('qu', 'ux');
		$session->replace(['foo' => 'baz']);
		$this->assertEquals('baz', $session->get('foo'));
		$this->assertEquals('ux', $session->get('qu'));
	}

	/**
	 * @runInSeparateProcess
	 */
	public function testRemove() {
		$session = $this->session;
		$session->start();
		$session->set('foo', 'bar');
		$pulled = $session->remove('foo');
		$this->assertFalse($session->has('foo'));
		$this->assertEquals('bar', $pulled);
	}
	
	/**
	 * @runInSeparateProcess
	 */
	public function testClear() {
		$session = $this->session;
		$session->start();
		$session->set('foo', 'bar');
		$session->clear();
		$this->assertFalse($session->has('foo'));
	}

}
<?php

class AbstractEnvironmentTest extends \PHPUnit_Framework_TestCase {
	public $env;

	public function setup() {
		$this->env = $this->getMockForAbstractClass(AbstractEnvironment::class);;
		$this->env->initialize(['foo' => 'bar','BIM'=>'baz','empty'=>'','EMPTIER'=>'','array'=>[],'false'=>false]);
	}

	public function testInitialize() {
		$env = $this->env;
		$this->assertEquals('bar', $env->get('foo'), '->initialize() takes an array of env parameters as its first argument');
	}

	public function testGet() {
		$env = $this->env;
		$this->assertEquals('bar', $env->get('foo'), '->get() gets the value of a parameter');
		$this->assertEquals('baz', $env->get('BIM'), '->get() gets the value of uppercase parameter');
		$this->assertEquals('default', $env->get('unknown', 'default'), '->get() returns second argument as default if a parameter is not defined');
		$this->assertNull($env->get('unknown'), '->get() returns null if not found');
	}

	public function testIGet() {
		$env = $this->env;
		$this->assertEquals('baz', $env->iget('bim'), '->iget() gets the value of a case insensitive parameter');
		$this->assertEquals('bar', $env->iget('FOO'), '->iget() gets the value of a case insensitive parameter');
		$this->assertEquals('default', $env->iget('unknown', 'default'), '->iget() returns second argument as default if a parameter is not defined');
		$this->assertNull($env->iget('unknown'), '->iget() returns null if not found');
	}

	public function testHas() {
		$env = $this->env;
		$this->assertTrue($env->has('foo'), '->has() returns true if a parameter is defined');
		$this->assertTrue($env->has('foo','BIM'), '->has() returns true if multiple parameters are defined');
		$this->assertFalse($env->has('foo','unknown'), '->has() returns false if one parameter is defined and another is not');
		$this->assertFalse($env->has('unknown'), '->has() return false if a parameter is not defined');
	}

	public function testIHas() {
		$env = $this->env;
		$this->assertTrue($env->ihas('bim'), '->ihas() returns true if a case insensitive parameter is defined');
		$this->assertTrue($env->ihas('FOO'), '->ihas() returns true if a case insensitive parameter is defined');
		$this->assertTrue($env->ihas('foo','bim'), '->ihas() returns true if multiple parameters are defined');
		$this->assertFalse($env->ihas('foo','unknown'), '->ihas() returns false if one parameter is defined and another is not');
		$this->assertFalse($env->ihas('unknown'), '->ihas() return false if a parameter is not defined');
	}

	public function testSig() {
		$env = $this->env;
		$this->assertTrue($env->sig('foo'), '->sig() returns true if a parameter is defined and is not empty');
		$this->assertFalse($env->sig('empty'), '->sig() returns false if a parameter is defined and empty');
		$this->assertTrue($env->sig('foo','BIM'), '->isig() returns true if multiple parameters are defined');
		$this->assertFalse($env->sig('foo','unknown'), '->isig() returns false if one parameter is defined and another is not');
		$this->assertFalse($env->sig('empty','foo'), '->isig() returns false if one parameter is defined and another is defined but empty');
		$this->assertFalse($env->sig('unknown'), '->sig() return false if a parameter is not defined');
		$this->assertTrue($env->sig('array'),'->sig() return true if the parameter is an array');
		$this->assertTrue($env->sig('false'),'->sig() return true if the parameter is an boolean');
	}

	public function testISig() {
		$env = $this->env;
		$this->assertTrue($env->isig('FOO'), '->isig() returns true if a case insensitive parameter is defined and is not empty');
		$this->assertFalse($env->isig('EMPTY'), '->sig() returns false if a case insensitive parameter is defined and empty');
		$this->assertFalse($env->isig('emptier'), '->sig() returns false if a case insensitive parameter is defined and empty');
		$this->assertTrue($env->isig('foo','bim'), '->isig() returns true if multiple case insensitive parameters are defined');
		$this->assertFalse($env->isig('foo','unknown'), '->isig() returns false if one case insensitive parameter is defined and another is not');
		$this->assertFalse($env->isig('empty','foo'), '->isig() returns false if one case insensitive parameter is defined and another is defined but empty');
		$this->assertFalse($env->isig('unknown'), '->isig() return false if a case insensitive parameter is not defined');
	}

	public function testAll() {
		$env = $this->getMockForAbstractClass(AbstractEnvironment::class);;
		$env->initialize(['foo' => 'bar']);
		$this->assertEquals(array('foo' => 'bar'), $env->all(), '->all() gets all the input');
	}


}
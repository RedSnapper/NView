<?php
namespace RS\NView;

abstract class AbstractFactory {
	protected $services;
	public function __debugInfo() {
		return [];
	}
	final public function __construct(Services $services) {
		$this->services = $services;
	}
	abstract public function create(...$i);
	public function cached(...$i) {
		return $this->create(...$i);
	}
}
<?php

Interface EnvironmentInterface {
	public function initialize(array $env);
	public function initializeByRef(array &$env);
	public function has($v);
	public function ihas($v);
	public function get($v,$d);
	public function iget($v,$d);
	public function sig($v);
	public function isig($v);
	public function remove($name);
	public function clear();
}

<?php

class EnvGet extends AbstractEnvironment implements EnvironmentInterface {
	public function __construct() {
		$this->initialize($_GET);
	}
}

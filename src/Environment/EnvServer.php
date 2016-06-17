<?php

class EnvServer extends AbstractEnvironment implements EnvironmentInterface {
	public function __construct() {
		$this->initialize($_SERVER);
	}
}

<?php

class EnvEnv extends AbstractEnvironment implements EnvironmentInterface {

	public function __construct() {
		$this->initialize($_ENV);
	}
}

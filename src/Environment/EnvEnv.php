<?php

class EnvEnv extends AbstractEnvironment implements EnvironmentInterface {
	/**
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		$this->initialize($_ENV);
	}
}

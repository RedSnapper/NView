<?php
namespace RS\NView\Environment;

class EnvEnv extends AbstractEnvironment implements EnvironmentInterface {
	/**
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		$this->initialize($_ENV);
	}
}

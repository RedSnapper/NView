<?php
namespace RS\NView\Environment;

class EnvGet extends AbstractEnvironment implements EnvironmentInterface {
	/**
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		$this->initialize($_GET);
	}
}

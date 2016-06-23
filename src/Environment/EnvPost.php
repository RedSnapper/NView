<?php

class EnvPost extends AbstractEnvironment implements EnvironmentInterface {
	/**
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		$this->initialize($_POST);
	}
}

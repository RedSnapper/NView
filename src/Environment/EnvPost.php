<?php

class EnvPost extends AbstractEnvironment implements EnvironmentInterface {
	public function __construct() {
		$this->initialize($_POST);
	}
}

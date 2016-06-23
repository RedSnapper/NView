<?php

class EnvServer extends AbstractEnvironment implements EnvironmentInterface {

	/**
	 * @codeCoverageIgnore
	 */
	public function __construct() {
		$this->initialize($_SERVER);
	}

	public function getScheme(){
		return ($this->sig('HTTPS') && $this->get("HTTPS") =='on') ? "https" : "http";
	}
}

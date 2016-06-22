<?php

mb_internal_encoding('UTF-8');

class Services extends \Dice\Dice {

	public function __construct() {
		//'*' represents the class being instantiated, not the class being requested.
		$this->addRule('*',['constructParams'=>[$this]]);
	}

	public function get($namedInterface, array $args = [], array $share = []) {
//		print("[$namedInterface]");
		return $this->create($namedInterface, $args, $share);
	}
}
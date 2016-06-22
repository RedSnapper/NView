<?php

mb_internal_encoding('UTF-8');

class Services extends \Dice\Dice {

	public function __construct() {
		//'*' represents the class being instantiated, not the class being requested.
		$dice = $this;
		$rule = [
			'substitutions' => [
				'Services' => ['instance' => function() use($dice){ return $dice;}]
			],
			'shared'=> true
		];
		$this->addRule('*',$rule);
	}

	public function get($namedInterface, array $args = [], array $share = []) {
		return $this->create($namedInterface, $args, $share);
	}
}
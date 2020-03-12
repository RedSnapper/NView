<?php
namespace RS\NView;

use Dice\Dice;

class Services extends Dice {

	public function __construct() {
		//'*' represents the class being instantiated, not the class being requested.
		$dice = $this;
		$rule = [
			'substitutions' => [
				'Services' => ['instance' => function() use($dice){ return $dice;}]
			]
		];
		$this->addRule('*',$rule);
	}

	public function get($namedInterface, array $args = [], array $share = []) {
		return $this->create($namedInterface, $args, $share);
	}
}

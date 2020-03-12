<?php
namespace RS\NView\Environment;
use RS\NView\AbstractFactory;

class UriFactory extends AbstractFactory {
	public function create(...$i) : Uri {
		return $this->services->get(Uri::class, $i);
	}
}
<?php

class UriFactory extends AbstractFactory {
	public function create(...$i) : Uri {
		return $this->services->get(Uri::class, $i);
	}
}
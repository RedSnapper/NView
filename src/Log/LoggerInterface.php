<?php

interface LoggerInterface extends Psr\Log\LoggerInterface {
	public function pushName($name);
	public function popName();
}


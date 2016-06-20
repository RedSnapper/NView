<?php

interface LoggerInterface extends Psr\Log\LoggerInterface {
	public function pushName($name);
	public function popName();
	public function pushLog($name,$level,$bubble,$formatter);
	public function popLog();
}


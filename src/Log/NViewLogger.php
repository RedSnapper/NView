<?php
mb_internal_encoding('UTF-8');
use Monolog\Logger;

class NViewLogger extends \Monolog\Logger
{
	private $stack;
	private $base_name;
	public function __construct($name = "Log", array $handlers = array(), array $processors = array())
	{
		$this->base_name = $name;
		$this->stack = array($name);
		parent::__construct($name,$handlers,$processors);
	}
	public function pushName($name = "Log") {
		$this->stack[] = $name;		// Push equivalent
		$this->name = $name;		//sets Logger channel;
	}
	public function popName() {
		$name = $this->base_name;
		if (count($this->stack) > 1) {
			array_pop($this->stack);
			$name = end($this->stack);
		}
		$this->name = $name; 		//resets Logger channel;
	}
}

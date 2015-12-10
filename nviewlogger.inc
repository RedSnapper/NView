<?php
mb_internal_encoding('UTF-8');
use Monolog\Logger;

class NViewLogger extends Logger
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
    	$stack[] = $name;
    	$this->name = $name;		//sets Logger channel;
    }
    public function popName() {
    	$name = $this->base_name;
    	if (count($this->stack) > 0) {
			$name = array_pop($this->stack);
		}
		$this->name = $name; 		//resets Logger channel;
    }
}

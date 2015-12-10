<?php
mb_internal_encoding('UTF-8');
use Monolog\Logger;
use Monolog\Handler;
use Monolog\Formatter;

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

class SQLHandler extends Handler\AbstractProcessingHandler
{
/*
 * Example usage
 * 		$log_sql = "insert low_priority into my_log (user,message,linkref,receiver,event,l_channel,l_level,l_message,l_date) VALUES (?,?,?,?,?,?,?,?,?)";
 *  	$log_stm = Settings::$sql->prepare($log_sql);
 * 		$log_handler = new SQLHandler($log_stm,"iisiissss");
 * 		Settings::$log->pushHandler($log_handler);
*/
	private $prepared_stmt;
	private $bind_types;

    public function setQuery($p_stmt = NULL,$b_types = NULL) {
    	$this->prepared_stmt = $p_stmt;
    	$this->bind_types = $b_types;
    }
  
    public function __construct($p_stmt=NULL, $b_types=NULL,$level = Logger::DEBUG,$bubble = true)
    {
    	$this->prepared_stmt = $p_stmt;
    	$this->bind_types = $b_types;
        parent::__construct($level, $bubble);
    }
    
    protected function write(array $params)
    {
	// with call_user_func_array, array params must be passed by reference..
    	$sql_params = $params['context'];
		$a_params[] = & $this->bind_types;
		$n = count($sql_params);
		for($i = 0; $i < $n; $i++) {
		  $a_params[] = & $sql_params[$i];
		}    
		$a_params[] = & $params['channel'];
		$a_params[] = & $params['level_name'];
		$a_params[] = & $params['message'];
		$a_params[] = & $params['datetime']['date'];
		call_user_func_array(array($this->prepared_stmt,'bind_param'),$a_params);
		$this->prepared_stmt->execute();
    }
    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new Formatter\NormalizerFormatter();
    }
}



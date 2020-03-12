<?php
namespace RS\NView\Log;
use Monolog\Logger;
use Monolog\Handler;
use Monolog\Formatter;

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
		$a_params[] = & $params['formatted']['channel'];
		$a_params[] = & $params['formatted']['level_name'];
		$a_params[] = & $params['formatted']['message'];
		$a_params[] = & $params['formatted']['datetime']; //Cannot use object of type DateTime as array in /websites/shared/dev/sqlhandler.inc on line 43
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



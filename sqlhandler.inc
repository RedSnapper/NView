<?php
mb_internal_encoding('UTF-8');
use Monolog\Logger;
use Monolog\Handler;

class SQLHandler extends Handler\AbstractProcessingHandler
{
	private $prepared_stmt;	//insert ignore into log_xx (a,b,c,d) values (?,?,?,?)'
	private $bind_types;	//insert ignore into log_xx (a,b,c,d) values (?,?,?,?)'

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
    	array_unshift($params,$this->bind_types);
    	$this->prepared_stmt = $p_stmt;
    	$this->bind_types = $b_types;
		call_user_func_array(array($this->prepared_stmt,'bind_param'),$params);
		$this->prepared_stmt->execute();
    }
    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new NormalizerFormatter();
    }
}



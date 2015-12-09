<?php
mb_internal_encoding('UTF-8');
use Monolog\Logger;
use Monolog\Handler;

class SQLHandler extends Handler\AbstractProcessingHandler
{
	private $prepared_stmt;	//insert ignore into log_xx (a,b,c,d) values (?,?,?,?)'
	private $bind_types;	//eg "siii" may use isdb (integer/string/double/blob)
	
    public function __construct($p_stmt=NULL, $b_types=NULL,$level = Logger::DEBUG,$bubble = true)
    {
    	$this->prepared_stmt = $p_stmt;
    	switch (strlen($b_types)) {
    		case 1: $this->prepared_stmt->bind_param($p0); break;
    		case 2: $this->prepared_stmt->bind_param($p0,$p1); break;
    		case 3: $this->prepared_stmt->bind_param($p0,$p1,$p2); break;
    		case 4: $this->prepared_stmt->bind_param($p0,$p1,$p2,$p3); break;
    		case 5: $this->prepared_stmt->bind_param($p0,$p1,$p2,$p3,$p4); break;
    		case 6: $this->prepared_stmt->bind_param($p0,$p1,$p2,$p3,$p4,$p5); break;
    		case 7: $this->prepared_stmt->bind_param($p0,$p1,$p2,$p3,$p4,$p5,$p6); break;
    		case 8: $this->prepared_stmt->bind_param($p0,$p1,$p2,$p3,$p4,$p5,$p6,$p7); break;
    		case 9: $this->prepared_stmt->bind_param($p0,$p1,$p2,$p3,$p4,$p5,$p6,$p7,$p8); break;
    	}
        parent::__construct($level, $bubble);
    }
    
    public function setQuery($p_stmt = NULL,$b_types = NULL) {
    	$this->prepared_stmt = $p_stmt;
    	$this->prepared_stmt = $b_types;
    }
    
    private function doQ0($p0) { $this->prepared_stmt->execute(); }
    private function doQ1($p0,$p1) { $this->prepared_stmt->execute(); }
    private function doQ2($p0,$p1,$p2) { $this->prepared_stmt->execute(); }
    private function doQ3($p0,$p1,$p2,$p3) { $this->prepared_stmt->execute(); }
    private function doQ4($p0,$p1,$p2,$p3,$p4) { $this->prepared_stmt->execute(); }
    private function doQ5($p0,$p1,$p2,$p3,$p4,$p5) { $this->prepared_stmt->execute(); }
    private function doQ6($p0,$p1,$p2,$p3,$p4,$p5,$p6) { $this->prepared_stmt->execute(); }
    private function doQ7($p0,$p1,$p2,$p3,$p4,$p5,$p6,$p7) { $this->prepared_stmt->execute(); }
    private function doQ8($p0,$p1,$p2,$p3,$p4,$p5,$p6,$p7,$p8) { $this->prepared_stmt->execute(); }
  
    protected function write(array $r)
    {
    	switch (count($r)) {
    		case 1: call_user_func_array(array($this,'doQ0'),$r); break;
    		case 2: call_user_func_array(array($this,'doQ1'),$r); break;
    		case 3: call_user_func_array(array($this,'doQ2'),$r); break;
    		case 4: call_user_func_array(array($this,'doQ3'),$r); break;
    		case 5: call_user_func_array(array($this,'doQ4'),$r); break;
    		case 6: call_user_func_array(array($this,'doQ5'),$r); break;
    		case 7: call_user_func_array(array($this,'doQ6'),$r); break;
    		case 8: call_user_func_array(array($this,'doQ7'),$r); break;
    		case 9: call_user_func_array(array($this,'doQ8'),$r); break;
    	}
    }
    /**
     * {@inheritDoc}
     */
    protected function getDefaultFormatter()
    {
        return new NormalizerFormatter();
    }
}



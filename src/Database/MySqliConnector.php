<?php

/**
 * User: paramdhaliwal
 * Date: 13/06/2016
 * Time: 10:32
 */
class MySqliConnector extends Connector implements ConnectorInterface {

	private $log;
	private $config = array();

	public function __construct(array $conf) {
		$this->config = $conf;

	}

	public function connect() {
		$config = $this->config;
		$sql = mysqli_init();
		if (isset($config['default_file'])) {
			//This currently doesn't work because mysqli_real_connect will default a no password to empty string...
			$sql = parse_ini_file($config['default_file']);
			mysqli_options($sql,MYSQLI_READ_DEFAULT_FILE,$config['default_file']);
			mysqli_real_connect($sql);
		} else {
			mysqli_real_connect($sql, @$config['host'], @$config['user'], @$config['password'], @$config['databpase']);
		}
		if ($sql->connect_error) {
			$err = 'SQL Connection Error (' . self::$sql->connect_errno . ') ' . $sql->connect_error;
//			$this->log->critical($err);
		} else {
			//Do any special setting of the connect here.
			$sql->set_charset($config['default-character-set']);
		}
		return $sql;
	}
}

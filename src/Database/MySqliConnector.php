<?php

class MySqliConnector extends Connector implements ConnectorInterface {

	private $log;
	private $config = array();

	public function __construct(array $conf) {
		$this->config = $conf;

	}

	public function connect() {
		$sql = $this->setupConnection();
		if ($sql->connect_error) {
			$err = 'SQL Connection Error (' . self::$sql->connect_errno . ') ' . $sql->connect_error;
//			$this->log->critical($err);
		} else {
			//Do any special setting of the connect here.
			$sql->set_charset($this->config['default-character-set']);
		}
		return $sql;
	}

	public function setupConnection() {
		$config = $this->config;
		$sql = mysqli_init();
		if (isset($config['default_file'])) {
			//This currently doesn't work because mysqli_real_connect will default a no password to empty string...
			$file = parse_ini_file($config['default_file']);
			mysqli_options($sql,MYSQLI_READ_DEFAULT_FILE,$file);
			mysqli_real_connect($sql);
		} else {
			mysqli_real_connect($sql, @$config['host'], @$config['user'], @$config['password'], @$config['database']);
		}

		return $sql;
	}
}

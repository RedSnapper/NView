<?php
mb_internal_encoding('UTF-8');

class Config {
	//this is really a 'fake' class which holds our common rules..
	//it's also used for legacy static bindings.
	private $s;

	public static $sql;
	public static $log;
	public function __construct(Services $s) {
		$this->s = $s;
		$this->setRules("PDO");
//	$this->legacy();
	}
	private function setRules($dbinterface) {
		$s = $this->s;
		$s->addRule('EnvironmentInterface',['shared' => true]);

		$server = $s->get('EnvServer');

		$s->addRule('LoggerInterface', [
			'instanceOf'      => "NViewLogger",
			'constructParams' => ["Log"],
			'shared'          => true
		]);

		$s->addRule('ConnectorInterface', [
			'instanceOf'      => "MySqlConnector",
			'constructParams' => [parse_ini_file($server->get("RS_SQLCONFIG_FILE"))],
			'shared'          => true
		]);
		$connector = $s->create('ConnectorInterface');

		$s->addRule('ConnectionInterface', [
			'instanceOf'      => "{$dbinterface}Connection",
			'constructParams' => [$connector->connect()],
			'shared'          => true
		]);

// This is to allow for backward compatibility to mysql.
		$s->addRule('MySqliConnector', [
			'constructParams' => [parse_ini_file($server->get("RS_SQLCONFIG_FILE"))]
		]);

		$connector = $s->create('MySqliConnector');
		$s->addRule('MySqliConnection', [
			'constructParams' => [$connector->connect()],
			'shared'          => true
		]);

		$sphinx = $s->create('MySqliConnector',[parse_ini_file($server->get("RS_SEARCH_CONFIG_FILE"))]);
		$s->addRule('SphinxConnection', [
			'constructParams' => [$sphinx->connect()],
			'shared'          => true
		]);
	}
	public function legacy() {
		$s = $this->s;
		static::$log = $s->get('LoggerInterface');
		static::$sql = $s->get('MySqliConnection');
	}

}


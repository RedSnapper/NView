<?php
mb_internal_encoding('UTF-8');

class Config {
	//this is really a 'fake' class which holds our common rules..
	//it's also used for legacy static bindings.
	protected $s;

	public function __construct(Services $s) {
		$this->s = $s;
		$this->setRules("PDO");
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
			'shared'          => false
		]);
		$connector = $s->create('ConnectorInterface');

		$s->addRule('ConnectionInterface', [
			'instanceOf'      => "{$dbinterface}Connection",
			'constructParams' => [$connector->connect()],
			'shared'          => true
		]);

		if($server->has('RS_SEARCH_CONFIG_FILE')){
			$sphinx = $s->create('MySqlConnector',[parse_ini_file($server->get("RS_SEARCH_CONFIG_FILE"))]);
			$s->addRule('SphinxConnection', [
				'constructParams' => [$sphinx->connect()],
				'shared'          => true
			]);
		}

	}


}


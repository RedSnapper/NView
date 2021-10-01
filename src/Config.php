<?php
mb_internal_encoding('UTF-8');

class Config {
	//this is really a 'fake' class which holds our common rules..
	//it's also used for legacy static bindings.
	public $s;

	public function __construct(Services $s) {
		$this->s = $s;
		$this->setRules("PDO");
	}

	private function setRules($dbinterface) {

        $this->s =  $this->s->addRule('EnvironmentInterface', ['shared' => true]);

        $this->s = $this->s->addRule('UriInterface', [
			'instanceOf' => "Uri",
			'shared' => false,
		]);

		$server = $this->s->get('EnvServer');

        $this->s = $this->s->addRule('LoggerInterface', [
			'instanceOf' => "NViewLogger",
			'constructParams' => ["Log"],
			'shared' => true,
//			'call' => [
//				['pushHandler',[['instance' => PDOLogHandler::class]]]
//			]
		]);

        $this->s = $this->s->addRule(PDOLogHandler::class,[
			'constructParams' => ["sio_log"],
			'shared' => true
		]);
		
		$configFilename = $server->get("SQL_CONFIG_FILE",$server->get("RS_SQLCONFIG_FILE"));
		$config = [];
		if (! empty($configFilename)) {
			$config =  parse_ini_file($configFilename);
		}

        $this->s =  $this->s->addRule('ConnectorInterface', [
			'instanceOf' => "MySqlConnector",
			'constructParams' => [$config],
			'shared' => false
		]);

		$connector = $this->s->create('ConnectorInterface');

        $this->s =  $this->s->addRule('ConnectionInterface', [
			'instanceOf' => "{$dbinterface}Connection",
			'constructParams' => [$connector->connect()],
			'shared' => true
		]);

        $this->s = $this->s->addRule('SessionHandlerInterface', [
			'shared' => true,
			'instanceOf' => DatabaseSessionHandler::class,
			'constructParams' => ['sio_session']
		]);

        $this->s = $this->s->addRule('SessionInterface', [
			'shared' => true,
			'instanceOf' => SessionStore::class,
			'call' => [
				['start']
			]
		]);

		if ($server->has('RS_SEARCH_CONFIG_FILE')) {
            $this->s = $this->s->addRule('SphinxConnection', [
				'constructParams' => [['instance'=>function() use($s,$server){
					$sphinx = $this->s->create('MySqliConnector', [parse_ini_file($server->get("RS_SEARCH_CONFIG_FILE"))]);
					return $sphinx->connect();
				}]],
				'shared' => true
			]);
		}
	}

}


<?php
namespace RS\NView;

class Config {
	//this is really a 'fake' class which holds our common rules..
	//it's also used for legacy static bindings.
	public $s;

	public function __construct(Services $s) {
		$this->s = $s;
		$this->setRules("PDO");
	}

	private function setRules($dbinterface) {
		$s = $this->s;
		$s->addRule('EnvironmentInterface', ['shared' => true]);

		$s->addRule('UriInterface', [
			'instanceOf' => "Uri",
			'shared' => false,
		]);

		$server = $s->get('EnvServer');

		$s->addRule('LoggerInterface', [
			'instanceOf' => "NViewLogger",
			'constructParams' => ["Log"],
			'shared' => true,
//			'call' => [
//				['pushHandler',[['instance' => PDOLogHandler::class]]]
//			]
		]);

		$s->addRule(PDOLogHandler::class,[
			'constructParams' => ["sio_log"],
			'shared' => true
		]);
		
		$configFilename = $server->get("SQL_CONFIG_FILE",$server->get("RS_SQLCONFIG_FILE"));
		$config = [];
		if (! empty($configFilename)) {
			$config =  parse_ini_file($configFilename);
		}

		$s->addRule('ConnectorInterface', [
			'instanceOf' => "MySqlConnector",
			'constructParams' => [$config],
			'shared' => false
		]);

		$connector = $s->create('ConnectorInterface');

		$s->addRule('ConnectionInterface', [
			'instanceOf' => "{$dbinterface}Connection",
			'constructParams' => [$connector->connect()],
			'shared' => true
		]);

		$s->addRule('SessionHandlerInterface', [
			'shared' => true,
			'instanceOf' => DatabaseSessionHandler::class,
			'constructParams' => ['sio_session']
		]);

		$s->addRule('SessionInterface', [
			'shared' => true,
			'instanceOf' => SessionStore::class,
			'call' => [
				['start']
			]
		]);


		if ($server->has('RS_SEARCH_CONFIG_FILE')) {
			$s->addRule('SphinxConnection', [
				'constructParams' => [['instance'=>function() use($s,$server){
					$sphinx = $s->create('MySqliConnector', [parse_ini_file($server->get("RS_SEARCH_CONFIG_FILE"))]);
					return $sphinx->connect();
				}]],
				'shared' => true
			]);
		}
	}

}


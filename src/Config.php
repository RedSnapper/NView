<?php
namespace RS\NView;

use RS\NView\Environment\{EnvironmentInterface, Uri, UriInterface, EnvServer};
use RS\NView\Log\{LoggerInterface, NViewLogger, PDOLogHandler};
use RS\NView\Database\{ConnectionInterface,
  ConnectorInterface,
  MySqlConnector,
  MySqliConnector,
  PDOConnection,
  SphinxConnection};
use SessionHandlerInterface;
use RS\NView\Session\{SessionInterface, SessionStore, DatabaseSessionHandler};

class Config {
	//this is really a 'fake' class which holds our common rules..
	//it's also used for legacy static bindings.
	public $s;

	public function __construct(Services $s) {
		$this->s = $s;
		$this->setRules(PDOConnection::class);
	}

	private function setRules($db_interface) {
		$s = $this->s;
		$s->addRule(EnvironmentInterface::class, ['shared' => true]);

		$s->addRule(UriInterface::class, [
			'instanceOf' => Uri::class,
			'shared' => false,
		]);

		$server = $s->get(EnvServer::class);

		$s->addRule(LoggerInterface::class, [
			'instanceOf' => NViewLogger::class,
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
		
		$configFilename = $server->get("SQL_CONFIG_FILE");
		$config = [];
		if (! empty($configFilename)) {
			$config =  parse_ini_file($configFilename);
		}

		$s->addRule(ConnectorInterface::class, [
			'instanceOf' => MySqlConnector::class,
			'constructParams' => [$config],
			'shared' => false
		]);

		$connector = $s->create(ConnectorInterface::class);

		$s->addRule(ConnectionInterface::class, [
			'instanceOf' => $db_interface,
			'constructParams' => [$connector->connect()],
			'shared' => true
		]);

		$s->addRule(SessionHandlerInterface::class, [
			'shared' => true,
			'instanceOf' => DatabaseSessionHandler::class,
			'constructParams' => ['sio_session']
		]);

		$s->addRule(SessionInterface::class, [
			'shared' => true,
			'instanceOf' => SessionStore::class,
			'call' => [
				['start']
			]
		]);


		if ($server->has('RS_SEARCH_CONFIG_FILE')) {
			$s->addRule(SphinxConnection::class, [
				'constructParams' => [['instance'=>function() use($s,$server){
					$sphinx = $s->create(MySqliConnector::class, [parse_ini_file($server->get("RS_SEARCH_CONFIG_FILE"))]);
					return $sphinx->connect();
				}]],
				'shared' => true
			]);
		}
	}

}


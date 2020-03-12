<?php
namespace RS\NView\Database;

/**
 * Created by PhpStorm.
 * User: paramdhaliwal
 * Date: 13/06/2016
 * Mysql PDO connector.
 */
class MySqlConnector extends Connector implements ConnectorInterface {
	private $config = array();

	public function __construct($conf) {
		$this->config = $conf;
	}

	/**
	 * Establish a database connection.
	 *
	 * @param  array $config
	 * @return \PDO
	 */
	public function connect() {
		$config = $this->config;
		$dsn = $this->getDsn($config);
		$options = $this->getOptions($config);

		// We need to grab the PDO options that should be used while making the brand
		// new connection instance. The PDO options control various aspects of the
		// connection's behavior, and some might be specified by the developers.
		$connection = $this->createConnection($dsn, $config, $options);
		if (isset($connection)) {
			if (isset($config['database'])) {
				$connection->exec("use `{$config['database']}`;");
			}

			$collation = @$config['collation'];

			// Next we will set the "names" and "collation" on the clients connections so
			// a correct character set will be used by this client. The collation also
			// is set on the server but needs to be set here on this client objects.
			$charset = $config['default-character-set'];

			$names = "set names '$charset'" .
				(!is_null($collation) ? " collate '$collation'" : '');

			$connection->prepare($names)->execute();

			// Next, we will check to see if a timezone has been specified in this config
			// and if it has we will issue a statement to modify the timezone with the
			// database. Setting this DB timezone is an optional configuration item.
			if (isset($config['timezone'])) {
				$connection->prepare(
					'set time_zone="' . $config['timezone'] . '"'
				)->execute();
			}
		}
		return $connection;
	}
	
	/**
	 * Create a DSN string from a configuration.
	 * Chooses socket or host/port based on the 'unix_socket' config value.
	 *
	 * @param  array $config
	 * @return string
	 */
	protected function getDsn(array $config) {
		return $this->configHasSocket($config) ? $this->getSocketDsn($config) : $this->getHostDsn($config);
	}

	/**
	 * Determine if the given configuration array has a UNIX socket value.
	 *
	 * @param  array $config
	 * @return bool
	 */
	protected function configHasSocket(array $config) {
		return isset($config['unix_socket']) && !empty($config['unix_socket']);
	}

	/**
	 * Get the DSN string for a socket configuration.
	 *
	 * @param  array $config
	 * @return string
	 */
	protected function getSocketDsn(array $config) {
		return "mysql:unix_socket={$config['unix_socket']};dbname={$config['database']}";
	}

	/**
	 * Get the DSN string for a host / port configuration.
	 * extract() — Import variables into the current symbol table from an array
	 *
*@param  array $config
	 * @return string
	 */
	protected function getHostDsn(array $config) {
		extract($config, EXTR_SKIP);
		if (isset($host)) {
			return isset($port)
				? "mysql:host={$host};port={$port};dbname={$database}"
				: "mysql:host={$host};dbname={$database}";
		} else {
			return isset($port)
				? "mysql:port={$port};dbname={$database}"
				: "mysql:dbname={$database}";
		}
	}
}
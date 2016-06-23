<?php

class DatabaseSessionHandler implements SessionHandlerInterface {

	/**
	 * The database connection instance.
	 *
	 * @var ConnectionInterface
	 */
	protected $connection;
	/**
	 * The name of the session table.
	 *
	 * @var string
	 */
	protected $table;


	/**
	 * Create a new database session handler instance.
	 *
	 * @param  ConnectionInterface $connection
	 * @param  string $table
	 * @return void
	 */
	public function __construct(ConnectionInterface $connection, $table) {
		$this->table = $table;
		$this->connection = $connection;
	}

	/**
	 * Initialize session
	 * @link http://php.net/manual/en/sessionhandlerinterface.open.php
	 * @param string $save_path The path where to store/retrieve the session.
	 * @param string $session_id The session id.
	 * @return bool <p>
	 * The return value (usually TRUE on success, FALSE on failure).
	 * Note this value is returned internally to PHP for processing.
	 * </p>
	 * @since 5.4.0
	 */
	public function open($save_path, $session_id) {
		return true;
	}

	/**
	 * Read session data
	 * @link http://php.net/manual/en/sessionhandlerinterface.read.php
	 * @param string $session_id The session id to read data for.
	 * @return string <p>
	 * Returns an encoded string of the read data.
	 * If nothing was read, it must return an empty string.
	 * Note this value is returned internally to PHP for processing.
	 * </p>
	 * @since 5.4.0
	 */
	public function read($sessionId) {
		$session = $this->connection->selectOne("select value from $this->table where id=:id",['id'=>$sessionId]);
		return $session ? $session->value : "";
	}

	/**
	 * Write session data
	 * @link http://php.net/manual/en/sessionhandlerinterface.write.php
	 * @param string $session_id The session id.
	 * @param string $session_data <p>
	 * The encoded session data. This data is the
	 * result of the PHP internally encoding
	 * the $_SESSION superglobal to a serialized
	 * string and passing it as this parameter.
	 * Please note sessions use an alternative serialization method.
	 * </p>
	 * @return bool <p>
	 * The return value (usually TRUE on success, FALSE on failure).
	 * Note this value is returned internally to PHP for processing.
	 * </p>
	 * @since 5.4.0
	 */
	public function write($session_id, $session_data) {
		return $this->connection->statement("replace into {$this->table} (id,value) values (:id,:data)",["id"=>$session_id,"data"=>$session_data]);
	}

	/**
	 * Close the session
	 * @link http://php.net/manual/en/sessionhandlerinterface.close.php
	 * @return bool <p>
	 * The return value (usually TRUE on success, FALSE on failure).
	 * Note this value is returned internally to PHP for processing.
	 * </p>
	 * @since 5.4.0
	 */
	public function close() {
		return true;
	}

	/**
	 * Destroy a session
	 * @link http://php.net/manual/en/sessionhandlerinterface.destroy.php
	 * @param string $session_id The session ID being destroyed.
	 * @return bool <p>
	 * The return value (usually TRUE on success, FALSE on failure).
	 * Note this value is returned internally to PHP for processing.
	 * </p>
	 * @since 5.4.0
	 */
	public function destroy($session_id) {
		return $this->connection->statement("delete from {$this->table} where id=:id",['id'=>$session_id]);
	}

	/**
	 * Cleanup old sessions
	 * @link http://php.net/manual/en/sessionhandlerinterface.gc.php
	 * @param int $maxlifetime <p>
	 * Sessions that have not updated for
	 * the last maxlifetime seconds will be removed.
	 * </p>
	 * @return bool <p>
	 * The return value (usually TRUE on success, FALSE on failure).
	 * Note this value is returned internally to PHP for processing.
	 * </p>
	 * @since 5.4.0
	 */
	public function gc($maxlifetime) {
		return $this->connection->statement("delete from {$this->table} where TIMESTAMPADD(SECOND,:lifetime,ts) < CURRENT_TIMESTAMP",["lifetime"=>$maxlifetime]);
	}

}
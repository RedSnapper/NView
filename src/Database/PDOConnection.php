<?php

/**
 * Class PDOConnection
 */
class PDOConnection implements ConnectionInterface {
	/**
	 * The active PDO connection.
	 *
	 * @var PDO
	 */
	protected $pdo;
	/**
	 * The default fetch mode of the connection.
	 *
	 * @var int
	 */
	protected $fetchMode = PDO::FETCH_OBJ;
	/**
	 * The argument for the fetch mode.
	 *
	 * @var mixed
	 */
	protected $fetchArgument;
	/**
	 * The constructor arguments for the PDO::FETCH_CLASS fetch mode.
	 *
	 * @var array
	 */
	protected $fetchConstructorArgument = [];
	/**
	 * All of the queries run against the connection.
	 *
	 * @var array
	 */
	protected $queryLog = [];
	/**
	 * Indicates whether queries are being logged.
	 *
	 * @var bool
	 */
	protected $loggingQueries = false;
	/**
	 * Indicates if the connection is in a "dry run".
	 *
	 * @var bool
	 */
	protected $pretending = false;

	/**
	 * Create a new database connection instance.
	 *
	 * @param  PDO
	 */

	public function __construct(\PDO $pdo = null) {
		// First we will setup the default properties. We keep track of the DB
		// name we are connected to since it is needed when some reflective
		// type commands are run such as checking whether a table exists.
		$this->pdo = $pdo;
	}

	public function close() {
		//method for closing pdo is to remove all references to it.
		$this->pdo = null;
	}

	/**
	 * Interface compatibility with Mysqli.
	 */
	public function getConnection() {
		print ("This is a PDO Connection. Use the other methods.");
	}

	/**
	 * Escape / quote a string for a query.
	 * PDO recommends AGAINST using this as PREPARE does the work for us.
	 * @param $s
	 * @return the string. (also escapes by reference).
	 * @deprecated
	 */
	public function esc(&$s) {
		if (!is_null($this->pdo)) {
			$s = $this->pdo->quote($s);
		}
		return $s;
	}

	/**
	 * Run a select statement and return a single result.
	 *
	 * @param  string $query
	 * @param  array $bindings
	 * @return mixed
	 */
	public function selectOne($query, $bindings = []) {
		$records = $this->select($query, $bindings);

		return count($records) > 0 ? reset($records) : null;
	}

	/**
	 * Run a select statement against the database.
	 *
	 * @param  string $query
	 * @param  array $bindings
	 * @param  bool $useReadPdo
	 * @return array
	 */
	public function select($query, $bindings = []) {
		return $this->run($query, $bindings, function ($me, $query, $bindings) {
			if ($me->pretending()) {
				return [];
			}

			// For select statements, we'll simply execute the query and return an array
			// of the database result set. Each element in the array will be a single
			// row from the database table, and will either be an array or objects.
			$statement = $this->getPdo()->prepare($query);

			$me->bindValues($statement, $me->prepareBindings($bindings));

			$statement->execute();

			$fetchArgument = $me->getFetchArgument();

			return isset($fetchArgument)
				? $statement->fetchAll($me->getFetchMode(), $fetchArgument, $me->getFetchConstructorArgument())
				: $statement->fetchAll($me->getFetchMode());
		});
	}

	/**
	 * Get the connection query log.
	 *
	 * @return array
	 */
	public function getQueryLog() {
		return $this->queryLog;
	}

	/**
	 * Enable the query log on the connection.
	 *
	 * @return void
	 */
	public function enableQueryLog() {
		$this->loggingQueries = true;
	}

	/**
	 * Log a query in the connection's query log.
	 *
	 * @param  string $query
	 * @param  array $bindings
	 * @param  float|null $time
	 * @return void
	 */
	public function logQuery($query, $bindings, $time = null) {
		if ($this->loggingQueries) {
			$realQuery = static::interpolateQuery($query,$bindings);
			$this->queryLog[] = compact('query', 'bindings', 'time','realQuery');
		}
	}

	/**
	 * Run a SQL statement and log its execution context.
	 *
	 * @param  string $query
	 * @param  array $bindings
	 * @param  \Closure $callback
	 * @return mixed
	 */
	protected function run($query, $bindings, Closure $callback) {
		$start = microtime(true);
		// Here we will run this query. If an exception occurs we'll determine if it was
		// caused by a connection that has been lost. If that is the cause, we'll try
		// to re-establish connection and re-run the query with a fresh connection.
		$result = $this->runQueryCallback($query, $bindings, $callback);

		// Once we have run the query we will calculate the time that it took to run and
		// then log the query, bindings, and execution time so we will report them on
		// the event that the developer needs them. We'll log time in milliseconds.
		$time = $this->getElapsedTime($start);
		$this->logQuery($query, $bindings, $time);

		return $result;
	}

	/*
	 * Run a select statement against the database and returns a generator.
	 *
	 * @param  string  $query
	 * @param  array  $bindings
	 * @param  bool  $useReadPdo
	 * @return \Generator
	 */

	/**
	 * Run a SQL statement.
	 *
	 * @param  string $query
	 * @param  array $bindings
	 * @param  \Closure $callback
	 * @return mixed
	 */
	protected function runQueryCallback($query, $bindings, Closure $callback) {
		// To execute the statement, we'll simply call the callback, which will actually
		// run the SQL against the PDO connection. Then we can calculate the time it
		// took to execute and log the query SQL, bindings and time in our memory.
		$result = $callback($this, $query, $bindings);
		return $result;
	}

	/**
	 * Get the current PDO connection.
	 *
	 * @return \PDO
	 */
	public function getPdo() {
		if ($this->pdo instanceof Closure) {
			return $this->pdo = call_user_func($this->pdo);
		}
		return $this->pdo;
	}

	/**
	 * @param       $query
	 * @param array $bindings
	 * @param bool $useReadPdo
	 * @return Generator
	 */
	public function cursor($query, $bindings = [], $useReadPdo = true) {
		$statement = $this->run($query, $bindings, function ($me, $query, $bindings) use ($useReadPdo) {
			if ($me->pretending()) {
				return [];
			}

			$statement = $this->getPdo()->prepare($query);

			if ($me->getFetchMode() === PDO::FETCH_CLASS) {
				$statement->setFetchMode($me->getFetchMode(), 'StdClass');
			} else {
				$statement->setFetchMode($me->getFetchMode());
			}

			$me->bindValues($statement, $me->prepareBindings($bindings));

			$statement->execute();

			return $statement;
		});

		while ($record = $statement->fetch()) {
			yield $record;
		}
	}

	/**
	 * Run an insert statement against the database.
	 *
	 * @param  string $query
	 * @param  array $bindings
	 * @return bool
	 */
	public function insert($query, $bindings = []) {
		return $this->statement($query, $bindings);
	}

	/**
	 * Execute an SQL statement and return the boolean result.
	 *
	 * @param  string $query
	 * @param  array $bindings
	 * @return bool
	 */
	public function statement($query, $bindings = []) {
		return $this->run($query, $bindings, function ($me, $query, $bindings) {
			if ($me->pretending()) {
				return true;
			}

			$statement = $this->getPdo()->prepare($query);

			$this->bindValues($statement, $me->prepareBindings($bindings));

			return $statement->execute();
		});
	}

	/**
	 * Bind values to their parameters in the given statement.
	 *
	 * @param  \PDOStatement $statement
	 * @param  array $bindings
	 * @return void
	 */
	public function bindValues($statement, $bindings) {
		foreach ($bindings as $key => $value) {
			$statement->bindValue(
				is_string($key) ? $key : $key + 1, $value,
				filter_var($value, FILTER_VALIDATE_FLOAT) !== false ? PDO::PARAM_INT : PDO::PARAM_STR
			);
		}
	}

	/**
	 * Run an update statement against the database.
	 *
	 * @param  string $query
	 * @param  array $bindings
	 * @return int
	 */
	public function update($query, $bindings = []) {
		return $this->affectingStatement($query, $bindings);
	}

	/**
	 * Run an SQL statement and get the number of rows affected.
	 *
	 * @param  string $query
	 * @param  array $bindings
	 * @return int
	 */
	public function affectingStatement($query, $bindings = []) {
		return $this->run($query, $bindings, function ($me, $query, $bindings) {
			if ($me->pretending()) {
				return 0;
			}

			// For update or delete statements, we want to get the number of rows affected
			// by the statement and return that back to the developer. We'll first need
			// to execute the statement and then we'll use PDO to fetch the affected.
			$statement = $me->getPdo()->prepare($query);

			$this->bindValues($statement, $me->prepareBindings($bindings));

			$statement->execute();

			return $statement->rowCount();
		});
	}

	/**
	 * Run a delete statement against the database.
	 *
	 * @param  string $query
	 * @param  array $bindings
	 * @return int
	 */
	public function delete($query, $bindings = []) {
		return $this->affectingStatement($query, $bindings);
	}

	/**
	 * Run a raw, unprepared query against the PDO connection.
	 *
	 * @param  string $query
	 * @return bool
	 */
	public function unprepared($query) {
		return $this->run($query, [], function ($me, $query) {
			if ($me->pretending()) {
				return true;
			}

			return (bool)$me->getPdo()->exec($query);
		});
	}

	/**
	 * Prepare the query bindings for execution.
	 *
	 * @param  array $bindings
	 * @return array
	 */
	public function prepareBindings(array $bindings) {

		foreach ($bindings as $key => $value) {
			// We need to transform all instances of DateTimeInterface into the actual
			// date string. Each query grammar maintains its own date string format
			// so we'll just ask the grammar for the format to get from the date.
			if ($value === false) {
				$bindings[$key] = 0;
			}
		}

		return $bindings;
	}

	/**
	 * Execute the given callback in "dry run" mode.
	 *
	 * @param  \Closure $callback
	 * @return array
	 */
	public function pretend(Closure $callback) {
		$loggingQueries = $this->loggingQueries;

		$this->enableQueryLog();

		$this->pretending = true;

		$this->queryLog = [];

		// Basically to make the database connection "pretend", we will just return
		// the default values for all the query methods, then we will return an
		// array of queries that were "executed" within the Closure callback.
		$callback($this);

		$this->pretending = false;

		$this->loggingQueries = $loggingQueries;

		return $this->queryLog;
	}

	/**
	 * Disconnect from the underlying PDO connection.
	 *
	 * @return void
	 */
	public function disconnect() {
		$this->setPdo(null);
	}

	/**
	 * Set the PDO connection.
	 *
	 * @param  \PDO|null $pdo
	 * @return $this
	 *
	 * @throws \RuntimeException
	 */
	public function setPdo($pdo) {
		$this->pdo = $pdo;
		return $this;
	}

	/**
	 * Determine if the connection in a "dry run".
	 *
	 * @return bool
	 */
	public function pretending() {
		return $this->pretending === true;
	}

	/**
	 * Get the default fetch mode for the connection.
	 *
	 * @return int
	 */
	public function getFetchMode() {
		return $this->fetchMode;
	}

	/**
	 * Set the default fetch mode for the connection, and optional arguments for the given fetch mode.
	 *
	 * @param  int $fetchMode
	 * @param  mixed $fetchArgument
	 * @param  array $fetchConstructorArgument
	 * @return int
	 */
	public function setFetchMode($fetchMode, $fetchArgument = null, array $fetchConstructorArgument = []) {
		$this->fetchMode = $fetchMode;
		$this->fetchArgument = $fetchArgument;
		$this->fetchConstructorArgument = $fetchConstructorArgument;
	}

	/**
	 * Get the fetch argument to be applied when selecting.
	 *
	 * @return mixed
	 */
	public function getFetchArgument() {
		return $this->fetchArgument;
	}

	/**
	 * Get custom constructor arguments for the PDO::FETCH_CLASS fetch mode.
	 *
	 * @return array
	 */
	public function getFetchConstructorArgument() {
		return $this->fetchConstructorArgument;
	}

	/**
	 * Get the elapsed time since a given starting point.
	 *
	 * @param  int $start
	 * @return float
	 */
	protected function getElapsedTime($start) {
		return round((microtime(true) - $start) * 1000, 2);
	}

	protected static function interpolateQuery($query, $params) {
		$keys = array();
		$newParams = array();
		# build a regular expression for each parameter
		foreach ($params as $key => $value) {
			if (is_string($key)) {
				$keys[] = '/:'.$key.'/';
				$newParams[$key] = "'$value'";
			} else {
				$keys[] = '/[?]/';
			}
		}
		$query = preg_replace($keys, $newParams, $query, 1, $count);

		return $query;
	}

}
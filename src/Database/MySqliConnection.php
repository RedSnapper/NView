<?php
namespace RS\NView\Database;

use mysqli;

/**
 * Created by PhpStorm.
 * User: ben
 * Date: 15/06/2016
 * Time: 09:53
 */
class MySqliConnection implements ConnectionInterface {
	/**
	 * @var mysqli
	 */
	private $sql;

	/**
	 * MySqliConnection constructor.
	 *
	 * @param mysqli|null $sql
	 */
	public function __construct(\mysqli $sql = null) {
		$this->sql = $sql;
	}

	/**
	 * closes the connection.
	 */
	public function close() {
		$this->sql->close();
	}

	/**
	 * @return mysqli|null
	 */
	public function getConnection() {
		return $this->sql;
	}

	/**
	 * @param $s
	 */
	public function esc(&$s) {
		if (!is_null($this->sql)) {
			$s = $this->sql->real_escape_string($s);
		}
		return $s;
	}

	/*
	 * The following honour the connection interface but are of no value in mysqli.
	 *
   */

	/**
	 * @param string $query
	 * @param array  $bindings
	 */
	public function select($query, $bindings = []) {
		print ("This is a mysqli connection. You can only use getConnection()");
	}

	/**
	 * Run an insert statement against the database.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return bool
	 */
	public function insert($query, $bindings = []) {
	}

	/**
	 * Run an update statement against the database.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return int
	 */
	public function update($query, $bindings = []) {
	}

	/**
	 * Run a delete statement against the database.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return int
	 */
	public function delete($query, $bindings = []) {
	}

	/**
	 * Execute an SQL statement and return the boolean result.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return bool
	 */
	public function statement($query, $bindings = []) {
	}

	/**
	 * Run an SQL statement and get the number of rows affected.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return int
	 */
	public function affectingStatement($query, $bindings = []) {
	}

	/**
	 * Prepare the query bindings for execution.
	 *
	 * @param  array $bindings
	 * @return array
	 */
	public function prepareBindings(array $bindings) {
	}

	/**
	 * Execute the given callback in "dry run" mode.
	 *
	 * @param  \Closure $callback
	 * @return array
	 */
	public function pretend(\Closure $callback) {
	}
}
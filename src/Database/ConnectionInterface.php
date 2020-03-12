<?php
namespace RS\NView\Database;

interface ConnectionInterface {

	/**
	 * @return void
	 */
	public function close();
	/*
	 * Escape.
	 */
	/**
	 * @param $s
	 * @return void
	 */
	public function esc(&$s);

	/*
	 * Provide access to the connection itself.
	 */
	/**
	 * @return mixed
	 */
	public function getConnection();

	/**
	 * Run a select statement against the database.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return array
	 */
	public function select($query, $bindings = []);

	/**
	 * Run an insert statement against the database.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return bool
	 */
	public function insert($query, $bindings = []);

	/**
	 * Run an update statement against the database.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return int
	 */
	public function update($query, $bindings = []);

	/**
	 * Run a delete statement against the database.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return int
	 */
	public function delete($query, $bindings = []);

	/**
	 * Execute an SQL statement and return the boolean result.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return bool
	 */
	public function statement($query, $bindings = []);

	/**
	 * Run an SQL statement and get the number of rows affected.
	 *
	 * @param  string $query
	 * @param  array  $bindings
	 * @return int
	 */
	public function affectingStatement($query, $bindings = []);

	/**
	 * Prepare the query bindings for execution.
	 *
	 * @param  array $bindings
	 * @return array
	 */
	public function prepareBindings(array $bindings);

	/**
	 * Execute the given callback in "dry run" mode.
	 *
	 * @param  \Closure $callback
	 * @return array
	 */
	public function pretend(Closure $callback);
}
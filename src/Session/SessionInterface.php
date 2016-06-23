<?php

interface SessionInterface {
	/**
	 * Starts the session storage.
	 *
	 * @return bool True if session started.
	 *
	 */
	public function start();

	/**
	 * Checks if an attribute is defined.
	 *
	 * @param string $name The attribute name
	 *
	 * @return bool true if the attribute is defined, false otherwise
	 */
	public function has($name);

	/**
	 * Returns an attribute.
	 *
	 * @param string $name The attribute name
	 * @param mixed $default The default value if not found.
	 *
	 * @return mixed
	 */
	public function get($name, $default = null);

	/**
	 * Sets an attribute.
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function set($name, $value);

	/**
	 * Returns attributes.
	 *
	 * @return array Attributes
	 */
	public function all();

	/**
	 * Removes an attribute.
	 *
	 * @param string $name
	 *
	 * @return mixed The removed value or null when it does not exist
	 */
	public function remove($name);

	/**
	 * Clears all attributes.
	 */
	public function clear();

	/**
	 * Checks if the session was started.
	 *
	 * @return bool
	 */
	public function isStarted();

	/**
	 * Migrates the current session to a new session id while maintaining all
	 * session attributes.
	 *
	 * @param bool $destroy  Whether to delete the old session or leave it to garbage collection.
	 * @param int  $lifetime Sets the cookie lifetime for the session cookie. A null value
	 *                       will leave the system settings unchanged, 0 sets the cookie
	 *                       to expire with browser session. Time is in seconds, and is
	 *                       not a Unix timestamp.
	 *
	 * @return bool True if session migrated, false if error.
	 */
	public function migrate($destroy = false, $lifetime = null);

	/** Invalidates the current session.
	 *
	 * Clears all session attributes and flashes and regenerates the
	 * session and deletes the old session from persistence.
	 *
	 * @param int $lifetime Sets the cookie lifetime for the session cookie. A null value
	 *                      will leave the system settings unchanged, 0 sets the cookie
	 *                      to expire with browser session. Time is in seconds, and is
	 *                      not a Unix timestamp.
	 *
	 * @return bool True if session invalidated, false if error.
	 */
	public function invalidate($lifetime = null);

}
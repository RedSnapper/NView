<?php

class SessionStore implements SessionInterface {

	/**
	 * @var bool
	 */
	protected $started = false;
	/**
	 * @var SessionHandlerInterface
	 */
	private $handler;
	/**
	 * @var EnvSession
	 */
	private $session;

	/**
	 * SessionStore constructor.
	 * @param SessionHandlerInterface $handler
	 * @param EnvSession $session
	 */
	public function __construct(SessionHandlerInterface $handler, EnvSession $session) {
		$this->handler = $handler;
		$this->session = $session;
	}

	/**
	 * Starts the session storage.
	 *
	 * @return bool True if session started.
	 *
	 */
	public function start() {
		if (!$this->started) {

			session_set_save_handler($this->handler,true);
			session_start();
			register_shutdown_function(array($this, 'shutdown'));
			$this->loadSession();
		}
		return $this->started;
	}

	/**
	 * Checks if an attribute is defined.
	 *
	 * @param string $name The attribute name
	 *
	 * @return bool true if the attribute is defined, false otherwise
	 */
	public function has($name) {
		return $this->session->has($name);
	}

	/**
	 * Returns an attribute.
	 *
	 * @param string $name The attribute name
	 * @param mixed $default The default value if not found.
	 *
	 * @return mixed
	 */
	public function get($name, $default = null) {
		return $this->session->get($name, $default);
	}

	/**
	 * Sets an attribute.
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function set($name, $value) {
		$this->session->set($name, $value);
	}

	/**
	 * Returns attributes.
	 *
	 * @return array Attributes
	 */
	public function all() {
		return $this->session->all();
	}

	/**
	 * Removes an attribute.
	 *
	 * @param string $name
	 *
	 * @return mixed The removed value or null when it does not exist
	 */
	public function remove($name) {
		$this->session->remove($name);
	}

	/**
	 * Clears all attributes.
	 */
	public function clear() {
		$this->session->clear();
	}

	/**
	 * Remove one or many items from the session.
	 *
	 * @param  string|array $keys
	 * @return void
	 */
	public function forget($keys) {
		$this->session->forget($keys);
	}

	/**
	 * Checks if the session was started.
	 *
	 * @return bool
	 */
	public function isStarted() {
		return $this->started;
	}

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
	public function invalidate($lifetime = null) {
		$this->clear();
		return $this->migrate(true, $lifetime);
	}

	/**
	 * Migrates the current session to a new session id while maintaining all
	 * session attributes.
	 *
	 * @param bool $destroy Whether to delete the old session or leave it to garbage collection.
	 * @param int $lifetime Sets the cookie lifetime for the session cookie. A null value
	 *                       will leave the system settings unchanged, 0 sets the cookie
	 *                       to expire with browser session. Time is in seconds, and is
	 *                       not a Unix timestamp.
	 *
	 * @return bool True if session migrated, false if error.
	 */
	public function migrate($destroy = false, $lifetime = null) {
		// Cannot regenerate the session ID for non-active sessions.
		if (\PHP_SESSION_ACTIVE !== session_status()) {
			return false;
		}
		if (null !== $lifetime) {
			ini_set('session.cookie_lifetime', $lifetime);
		}

		$isRegenerated = session_regenerate_id($destroy);

		$this->loadSession();
		return $isRegenerated;
	}

	/**
	 * Push a value onto a session array.
	 *
	 * @param  string $key
	 * @param  mixed $value
	 * @return void
	 */
	public function push($key, $value) {
		$array = $this->get($key, []);
		$array[] = $value;
		$this->set($key, $array);
	}

	/**
	 * Flash a key / value pair to the session.
	 *
	 * @param  string $key
	 * @param  mixed $value
	 * @return void
	 */
	public function flash($key, $value) {
		$this->set($key, $value);
		$this->push('flash.new', $key);
	}

	/**
	 * Get the underlying session handler implementation.
	 *
	 * @return \SessionHandlerInterface
	 */
	public function getHandler() {
		return $this->handler;
	}

	/**
	 * Gets the session ID.
	 *
	 * @return string
	 */
	public function getId() {
		return session_id();
	}

	/**
	 * Age the flash data for the session.
	 *
	 * @return void
	 */
	private function ageFlashData() {
		$this->forget($this->get('flash.old', []));
		$this->set('flash.old', $this->get('flash.new', []));
		$this->set('flash.new', []);
	}

	/**
	 * Load the session with attributes.
	 *
	 *
	 * @param array|null $session
	 */
	protected function loadSession($session = null) {

		if (is_null($session)) {
			$session = &$_SESSION;
		}
		$this->session->initializeByRef($session);
		$this->started = true;
		$this->closed = false;
	}

	public function shutdown() {
		$this->ageFlashData();
	}

}
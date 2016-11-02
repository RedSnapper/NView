<?php

class Uri implements UriInterface {
	private static $schemes = [
		'http' => 80,
		'https' => 443,
	];

	private static $charUnreserved = 'a-zA-Z0-9_\-\.~';
	private static $charSubDelims = '!\$&\'\(\)\*\+,;=';

	/** @var string Uri scheme. */
	private $scheme = '';

	/** @var string Uri user info. */
	private $userInfo = '';

	/** @var string Uri host. */
	private $host = '';

	/** @var int|null Uri port. */
	private $port;

	/** @var string Uri path. */
	private $path = '';

	/** @var string Uri query string. */
	private $query = '';

	/** @var string Uri fragment. */
	private $fragment = '';
	/**
	 * @var EnvServer
	 */
	private $server;
	/**
	 * @var LoggerInterface
	 */
	private $log;

	/**
	 * @param EnvServer $server
	 * @param LoggerInterface $log
	 * @param string $uri
	 */
	public function __construct($uri = null, EnvServer $server, LoggerInterface $log) {
		$this->server = $server;
		$this->log = $log;
		$uri = is_null($uri) ? $this->current() : $uri;
		$this->initialize($uri);
	}

	private function initialize($uri = null) {
		if (!is_null($uri)) {
			$parts = parse_url($uri);
			if ($parts === false) {
				$this->log->error("Unable to parse URI: $uri");
			} else {
				$this->applyParts($parts);
			}
		}
	}

	private function current() {
		$current = null;
		$server = $this->server;
		$path = $server->get("REQUEST_URI");
		$scheme = $server->getScheme();
		$host = $server->get("HTTP_HOST");
		if (!($host === "" && $path === "")) {
			$current = "{$scheme}://{$host}{$path}";
		}
		return $current;
	}

	public function __toString() {
		return self::createUriString(
			$this->scheme,
			$this->getAuthority(),
			$this->path,
			$this->query,
			$this->fragment
		);
	}

	public function getScheme() {
		return $this->scheme;
	}

	public function getAuthority() {
		if ($this->host == '') {
			return '';
		}

		$authority = $this->host;
		if ($this->userInfo != '') {
			$authority = $this->userInfo . '@' . $authority;
		}

		if ($this->port !== null) {
			$authority .= ':' . $this->port;
		}

		return $authority;
	}

	public function getUserInfo() {
		return $this->userInfo;
	}

	public function getHost() {
		return $this->host;
	}

	public function getPort() {
		return $this->port;
	}

	public function getPath() {
		return $this->path;
	}

	public function getQuery() {
		return $this->query;
	}

	public function getFragment() {
		return $this->fragment;
	}

	/**
	 * Gets the scheme and HTTP host.
	 * @return string The scheme and HTTP host
	 */
	public function getSchemeAndHost() {
		return $this->getScheme() . '://' . $this->getHost();
	}


	public function getDomain() {
		$domain_arr = explode('.',$this->getHost(), 2);
		if($domain_arr[0]=='www') {
			return $domain_arr[1];
		} else {
			return $this->getHost();
		}
	}

	public function withScheme($scheme) {
		$scheme = $this->filterScheme($scheme);

		if ($this->scheme === $scheme) {
			return $this;
		}

		$new = clone $this;
		$new->scheme = $scheme;
		$new->port = $new->filterPort($new->port);
		return $new;
	}

	public function withUserInfo($user, $password = null) {
		$info = $user;
		if ($password != '') {
			$info .= ':' . $password;
		}

		if ($this->userInfo === $info) {
			return $this;
		}

		$new = clone $this;
		$new->userInfo = $info;
		return $new;
	}

	public function withHost($host) {
		$host = $this->filterHost($host);

		if ($this->host === $host) {
			return $this;
		}

		$new = clone $this;
		$new->host = $host;
		return $new;
	}

	public function withPort($port) {
		$port = $this->filterPort($port);

		if ($this->port === $port) {
			return $this;
		}

		$new = clone $this;
		$new->port = $port;
		return $new;
	}

	public function withPath($path) {
		$path = $this->filterPath($path);

		if ($this->path === $path) {
			return $this;
		}

		$new = clone $this;
		$new->path = $path;
		return $new;
	}

	public function withQuery($query) {
		$query = $this->filterQueryAndFragment($query);

		if ($this->query === $query) {
			return $this;
		}

		$new = clone $this;
		$new->query = $query;
		return $new;
	}

	public function withFragment($fragment) {
		$fragment = $this->filterQueryAndFragment($fragment);

		if ($this->fragment === $fragment) {
			return $this;
		}

		$new = clone $this;
		$new->fragment = $fragment;
		return $new;
	}

	/**
	 * Does this url belong to this site
	 * @return bool
	 */
	public function isLocal() {
		$uri = (string)$this;
		$host = $this->getHost();
		$scheme = $this->getScheme();
		return ($uri != "")
		&& ($host == "" || $this->server->get("HTTP_HOST") == $host)
		&& ($scheme == "" || $this->server->getScheme() == $scheme);
	}

	public function getAbsoluteLink() : string {
		return self::createUriString(
			$this->host,
			$this->scheme,
			$this->path,
			$this->query,
			$this->fragment
		);
	}

	public function redirect() {
		$absolute = $this->getAbsoluteLink();
		header("location: $absolute");
	}

	public function getLink() {
		if ($this->isLocal()) {
			$host = "";
			$scheme = "";
		} else {
			$host = $this->host;
			$scheme = $this->scheme;
		}

		return self::createUriString(
			$scheme,
			$host,
			$this->path,
			$this->query,
			$this->fragment
		);
	}

	public function mergeQuery($query) {

		if ($query instanceof UriInterface) {
			$query = $query->getQuery();
		} else {
			$query = $this->filterQueryAndFragment($query);
		}
		parse_str($query, $newQ);
		parse_str($this->query, $oldQ);
		$newQ = array_merge($oldQ, $newQ);
		array_walk($newQ, function (&$val, $key) {
			$val = $val == "" ? urlencode($key) : urlencode($key) . "=" . urlencode($val);
		});

		$new = clone $this;
		$new->query = implode("&", $newQ);
		return $new;
	}

	/**
	 * Apply parse_url parts to a URI.
	 *
	 * @param array $parts Array of parse_url parts to apply.
	 */
	private function applyParts(array $parts) {
		$this->scheme = isset($parts['scheme'])
			? $this->filterScheme($parts['scheme'])
			: '';
		$this->userInfo = isset($parts['user']) ? $parts['user'] : '';
		$this->host = isset($parts['host'])
			? $this->filterHost($parts['host'])
			: '';
		$this->port = isset($parts['port'])
			? $this->filterPort($parts['port'])
			: null;
		$this->path = isset($parts['path'])
			? $this->filterPath($parts['path'])
			: '';
		$this->query = isset($parts['query'])
			? $this->filterQueryAndFragment($parts['query'])
			: '';
		$this->fragment = isset($parts['fragment'])
			? $this->filterQueryAndFragment($parts['fragment'])
			: '';
		if (isset($parts['pass'])) {
			$this->userInfo .= ':' . $parts['pass'];
		}
	}

	/**
	 * Create a URI string from its various parts
	 *
	 * @param string $scheme
	 * @param string $authority
	 * @param string $path
	 * @param string $query
	 * @param string $fragment
	 * @return string
	 */
	private static function createUriString($scheme, $authority, $path, $query, $fragment) {
		$uri = '';

		if ($scheme != '') {
			$uri .= $scheme . ':';
		}

		if ($authority != '') {
			$uri .= '//' . $authority;
		}

		if ($path != '') {
			if ($path[0] !== '/') {
				if ($authority != '') {
					// If the path is rootless and an authority is present, the path MUST be prefixed by "/"
					$path = '/' . $path;
				}
			} elseif (isset($path[1]) && $path[1] === '/') {
				if ($authority == '') {
					// If the path is starting with more than one "/" and no authority is present, the
					// starting slashes MUST be reduced to one.
					$path = '/' . ltrim($path, '/');
				}
			}

			$uri .= $path;
		}

		if ($query != '') {
			$uri .= '?' . $query;
		}

		if ($fragment != '') {
			$uri .= '#' . $fragment;
		}

		return $uri;
	}

	/**
	 * Is a given port non-standard for the current scheme?
	 *
	 * @param string $scheme
	 * @param int $port
	 *
	 * @return bool
	 */
	private static function isNonStandardPort($scheme, $port) {
		return !isset(self::$schemes[$scheme]) || $port !== self::$schemes[$scheme];
	}

	/**
	 * @param string $scheme
	 *
	 * @return string
	 *
	 * @throws \InvalidArgumentException If the scheme is invalid.
	 */
	private function filterScheme($scheme) {
		if (!is_string($scheme)) {
			throw new \InvalidArgumentException('Scheme must be a string');
		}

		return strtolower($scheme);
	}

	/**
	 * @param string $host
	 *
	 * @return string
	 *
	 * @throws \InvalidArgumentException If the host is invalid.
	 */
	private function filterHost($host) {
		if (!is_string($host)) {
			throw new \InvalidArgumentException('Host must be a string');
		}

		return strtolower($host);
	}

	/**
	 * @param int|null $port
	 *
	 * @return int|null
	 *
	 * @throws \InvalidArgumentException If the port is invalid.
	 */
	private function filterPort($port) {
		if ($port === null) {
			return null;
		}

		$port = (int)$port;
		if (1 > $port || 0xffff < $port) {
			throw new \InvalidArgumentException(
				sprintf('Invalid port: %d. Must be between 1 and 65535', $port)
			);
		}

		return self::isNonStandardPort($this->scheme, $port) ? $port : null;
	}

	/**
	 * Filters the path of a URI
	 *
	 * @param string $path
	 *
	 * @return string
	 *
	 * @throws \InvalidArgumentException If the path is invalid.
	 */
	private function filterPath($path) {
		if (!is_string($path)) {
			throw new \InvalidArgumentException('Path must be a string');
		}

		return preg_replace_callback(
			'/(?:[^' . self::$charUnreserved . self::$charSubDelims . '%:@\/]++|%(?![A-Fa-f0-9]{2}))/',
			[$this, 'rawurlencodeMatchZero'],
			$path
		);
	}

	/**
	 * Filters the query string or fragment of a URI.

*
	 * @param mixed $str
	 * @return string

	 */
	private function filterQueryAndFragment($str) {
		if (!is_string($str)) {
			$str = http_build_query($str);
		}
		return preg_replace_callback(
			'/(?:[^' . self::$charUnreserved . self::$charSubDelims . '%:@\/\?]++|%(?![A-Fa-f0-9]{2}))/',
			[$this, 'rawurlencodeMatchZero'],
			$str
		);
	}

	private function rawurlencodeMatchZero(array $match) {
		return rawurlencode($match[0]);
	}
}
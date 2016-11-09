<?php

interface UriInterface extends \Psr\Http\Message\UriInterface {

	/**
	 * Returns the uri suitable in embedding in a web page
	 * @return string
	 */
	public function getLink();

	/**
	 * Merge two query string together
	 * @param string |UriInterface $mixed
	 * @return mixed
	 */
	public function mergeQuery($mixed);

	/**
	 * Does this url belong to this site
	 * @return bool
	 */
	public function isLocal();

	/**
	 * Gets the domain for this uri
	 * @return string
	 */
	public function getDomain();

	/**
	 * Gets the scheme and HTTP host.
	 * @return string The scheme and HTTP host
	 */
	public function getSchemeAndHost();

	/**
	 * Returns the absolute-uri suitable in embedding in a web page
	 *
	 * @return string
	 */
	public function getAbsoluteLink() : string;

	/**
	 * sets a redirect based on the url.
	 *
	 * @return void
	 */
	public function redirect();

	/**
	 * removes a part of (or all a query value).
	 *
	 * @return void
	 */
	public function withoutQueryValue(string $key = null) : \UriInterface;


}
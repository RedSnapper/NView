<?php

interface UriInterface extends Psr\Http\Message\UriInterface {

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
}
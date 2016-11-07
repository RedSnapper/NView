<?php

class UriTest extends \PHPUnit_Framework_TestCase {

	public $log;
	public $server;

	public function setup() {
		$this->log = $this->createMock('NViewLogger');
		$this->server = $this->createMock(EnvServer::class);
		$this->server->method('getScheme')->willReturn('http');
	}

	//LoggerInterface
	const RFC3986_BASE = 'http://a/b/c/d;p?q';

	public function testParsesProvidedUri() {
		$uri = $this->getUri('https://user:pass@example.com:8080/path/123?q=abc#test');
		$this->assertSame('https', $uri->getScheme());
		$this->assertSame('user:pass@example.com:8080', $uri->getAuthority());
		$this->assertSame('user:pass', $uri->getUserInfo());
		$this->assertSame('example.com', $uri->getHost());
		$this->assertSame('example.com', $uri->getDomain());
		$this->assertSame(8080, $uri->getPort());
		$this->assertSame('/path/123', $uri->getPath());
		$this->assertSame('q=abc', $uri->getQuery());
		$this->assertSame('test', $uri->getFragment());
		$this->assertSame('https://example.com', $uri->getSchemeAndHost());
		$this->assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string)$uri);
	}

	public function testCanTransformAndRetrievePartsIndividually() {
		$uri = $this->getUri()
			->withScheme('https')
			->withUserInfo('user', 'pass')
			->withHost('example.com')
			->withPort(8080)
			->withPath('/path/123')
			->withQuery('q=abc')
			->withFragment('test');

		$this->assertSame('https', $uri->getScheme());
		$this->assertSame('user:pass@example.com:8080', $uri->getAuthority());
		$this->assertSame('user:pass', $uri->getUserInfo());
		$this->assertSame('example.com', $uri->getHost());
		$this->assertSame('example.com', $uri->getDomain());
		$this->assertSame(8080, $uri->getPort());
		$this->assertSame('/path/123', $uri->getPath());
		$this->assertSame('q=abc', $uri->getQuery());
		$this->assertSame('test', $uri->getFragment());
		$this->assertSame('https://example.com', $uri->getSchemeAndHost());
		$this->assertSame('https://user:pass@example.com:8080/path/123?q=abc#test', (string)$uri);
	}


	/**
	 * @dataProvider getInvalidUris
	 */
	public function testInvalidUrisLogError($invalidUri) {
		$log = $this->getMockBuilder('NViewLogger')
					->setMethods(['error'])
					->getMock();

		$log->expects($this->once())->method('error')->with("Unable to parse URI: $invalidUri");
		new Uri($invalidUri,$this->server,$log);

	}

	public function getInvalidUris() {
		return [
			// parse_url() requires the host component which makes sense for http(s)
			// but not when the scheme is not known or different. So '//' or '///' is
			// currently invalid as well but should not according to RFC 3986.
			['http://'],
			['urn://host:with:colon'], // host cannot contain ":"
		];
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Invalid port: 100000. Must be between 1 and 65535
	 */
	public function testPortMustBeValid() {
		$this->getUri()->withPort(100000);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 * @expectedExceptionMessage Invalid port: 0. Must be between 1 and 65535
	 */
	public function testWithPortCannotBeZero() {
		$this->getUri()->withPort(0);
	}

	public function testParseUriPortCannotBeZero() {
		$invalidPort = "//example.com:0";
		$log = $this->getMockBuilder('NViewLogger')
			->setMethods(['error'])
			->getMock();

		$log->expects($this->once())->method('error')->with("Unable to parse URI: $invalidPort");
		new Uri($invalidPort,$this->server,$log);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testSchemeMustHaveCorrectType() {
		$this->getUri()->withScheme([]);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testHostMustHaveCorrectType() {
		$this->getUri()->withHost([]);
	}

	/**
	 * @expectedException \InvalidArgumentException
	 */
	public function testPathMustHaveCorrectType() {
		$this->getUri()->withPath([]);
	}


	public function testCanParseFalseyUriParts() {
		$uri = $this->getUri('0://0:0@0/0?0#0');

		$this->assertSame('0', $uri->getScheme());
		$this->assertSame('0:0@0', $uri->getAuthority());
		$this->assertSame('0:0', $uri->getUserInfo());
		$this->assertSame('0', $uri->getHost());
		$this->assertSame('/0', $uri->getPath());
		$this->assertSame('0', $uri->getQuery());
		$this->assertSame('0', $uri->getFragment());
		$this->assertSame('0://0:0@0/0?0#0', (string)$uri);
	}

	public function testCanConstructFalseyUriParts() {
		$uri = $this->getUri()
			->withScheme('0')
			->withUserInfo('0', '0')
			->withHost('0')
			->withPath('/0')
			->withQuery('0')
			->withFragment('0');

		$this->assertSame('0', $uri->getScheme());
		$this->assertSame('0:0@0', $uri->getAuthority());
		$this->assertSame('0:0', $uri->getUserInfo());
		$this->assertSame('0', $uri->getHost());
		$this->assertSame('/0', $uri->getPath());
		$this->assertSame('0', $uri->getQuery());
		$this->assertSame('0', $uri->getFragment());
		$this->assertSame('0://0:0@0/0?0#0', (string)$uri);
	}

	public function testAddAndRemoveQueryValues() {
		$uri = $this->getUri("?a=b&c=d&e");
		$uri = Uri::withoutQueryValue($uri, 'c');
		$this->assertSame('a=b&e', $uri->getQuery());
		$uri = Uri::withoutQueryValue($uri, 'e');
		$this->assertSame('a=b', $uri->getQuery());
		$uri = Uri::withoutQueryValue($uri, 'a');
		$this->assertSame('', $uri->getQuery());
	}

	public function testWithoutQueryValueRemovesAllSameKeys() {
		$uri = $this->getUri("?a=b&c=d&a=e");
		$uri = Uri::withoutQueryValue($uri, 'a');
		$this->assertSame('c=d', $uri->getQuery());
	}

	public function testRemoveNonExistingQueryValue() {
		$uri = $this->getUri("?a=b");
		$uri = Uri::withoutQueryValue($uri, 'c');
		$this->assertSame('a=b', $uri->getQuery());
	}

	public function testWithoutQueryValueHandlesEncoding() {
		// It also tests that the case of the percent-encoding does not matter,
		// i.e. both lowercase "%3d" and uppercase "%5E" can be removed.
		$uri = ($this->getUri())->withQuery('E%3dmc%5E2=einstein&foo=bar');
		$uri = Uri::withoutQueryValue($uri, 'E=mc^2');
		$this->assertSame('foo=bar', $uri->getQuery(), 'Handles key in decoded form');
		$uri = ($this->getUri())->withQuery('E%3dmc%5E2=einstein&foo=bar');
		$uri = Uri::withoutQueryValue($uri, 'E%3Dmc%5e2');
		$this->assertSame('foo=bar', $uri->getQuery(), 'Handles key in encoded form');
	}

	public function testSchemeIsNormalizedToLowercase() {
		$uri = $this->getUri('HTTP://example.com');

		$this->assertSame('http', $uri->getScheme());
		$this->assertSame('http://example.com', (string)$uri);

		$uri = $this->getUri('//example.com')->withScheme('HTTP');

		$this->assertSame('http', $uri->getScheme());
		$this->assertSame('http://example.com', (string)$uri);
	}

	public function testHostIsNormalizedToLowercase() {
		$uri = $this->getUri('//eXaMpLe.CoM');

		$this->assertSame('example.com', $uri->getHost());
		$this->assertSame('//example.com', (string)$uri);

		$uri = $this->getUri()->withHost('eXaMpLe.CoM');

		$this->assertSame('example.com', $uri->getHost());
		$this->assertSame('//example.com', (string)$uri);
	}

	public function testPortIsNullIfStandardPortForScheme() {
		// HTTPS standard port
		$uri = $this->getUri('https://example.com:443');
		$this->assertNull($uri->getPort());
		$this->assertSame('example.com', $uri->getAuthority());

		$uri = $this->getUri('https://example.com')->withPort(443);
		$this->assertNull($uri->getPort());
		$this->assertSame('example.com', $uri->getAuthority());

		// HTTP standard port
		$uri = $this->getUri('http://example.com:80');
		$this->assertNull($uri->getPort());
		$this->assertSame('example.com', $uri->getAuthority());

		$uri = $this->getUri('http://example.com')->withPort(80);
		$this->assertNull($uri->getPort());
		$this->assertSame('example.com', $uri->getAuthority());
	}

	public function testPortIsReturnedIfSchemeUnknown() {
		$uri = $this->getUri('//example.com')->withPort(80);

		$this->assertSame(80, $uri->getPort());
		$this->assertSame('example.com:80', $uri->getAuthority());
	}

	public function testStandardPortIsNullIfSchemeChanges() {
		$uri = $this->getUri('http://example.com:443');
		$this->assertSame('http', $uri->getScheme());
		$this->assertSame(443, $uri->getPort());

		$uri = $uri->withScheme('https');
		$this->assertNull($uri->getPort());
	}

	public function testPortPassedAsStringIsCastedToInt() {
		$uri = $this->getUri('//example.com')->withPort('8080');

		$this->assertSame(8080, $uri->getPort(), 'Port is returned as integer');
		$this->assertSame('example.com:8080', $uri->getAuthority());
	}

	public function testPortCanBeRemoved() {
		$uri = $this->getUri('http://example.com:8080')->withPort(null);

		$this->assertNull($uri->getPort());
		$this->assertSame('http://example.com', (string)$uri);
	}

	public function testAuthorityWithUserInfoButWithoutHost() {
		$uri = $this->getUri()->withUserInfo('user', 'pass');

		$this->assertSame('user:pass', $uri->getUserInfo());
		$this->assertSame('', $uri->getAuthority());
	}

	public function uriComponentsEncodingProvider() {
		$unreserved = 'a-zA-Z0-9.-_~!$&\'()*+,;=:@';

		return [
			// Percent encode spaces
			['/pa th?q=va lue#frag ment', '/pa%20th', 'q=va%20lue', 'frag%20ment', '/pa%20th?q=va%20lue#frag%20ment'],
			// Percent encode multibyte
			//['/€?€#€', '/%E2%82%AC', '%E2%82%AC', '%E2%82%AC', '/%E2%82%AC?%E2%82%AC#%E2%82%AC'],
			// Don't encode something that's already encoded
			['/pa%20th?q=va%20lue#frag%20ment', '/pa%20th', 'q=va%20lue', 'frag%20ment', '/pa%20th?q=va%20lue#frag%20ment'],
			// Percent encode invalid percent encodings
			['/pa%2-th?q=va%2-lue#frag%2-ment', '/pa%252-th', 'q=va%252-lue', 'frag%252-ment', '/pa%252-th?q=va%252-lue#frag%252-ment'],
			// Don't encode path segments
			['/pa/th//two?q=va/lue#frag/ment', '/pa/th//two', 'q=va/lue', 'frag/ment', '/pa/th//two?q=va/lue#frag/ment'],
			// Don't encode unreserved chars or sub-delimiters
			["/$unreserved?$unreserved#$unreserved", "/$unreserved", $unreserved, $unreserved, "/$unreserved?$unreserved#$unreserved"],
			// Encoded unreserved chars are not decoded
			['/p%61th?q=v%61lue#fr%61gment', '/p%61th', 'q=v%61lue', 'fr%61gment', '/p%61th?q=v%61lue#fr%61gment'],
		];
	}

	/**
	 * @dataProvider uriComponentsEncodingProvider
	 */
	public function testUriComponentsGetEncodedProperly($input, $path, $query, $fragment, $output) {
		$uri = $this->getUri($input);
		$this->assertSame($path, $uri->getPath());
		$this->assertSame($query, $uri->getQuery());
		$this->assertSame($fragment, $uri->getFragment());
		$this->assertSame($output, (string)$uri);
	}

	public function testWithPathEncodesProperly() {
		$uri = $this->getUri()->withPath('/baz?#€/b%61r');
		// Query and fragment delimiters and multibyte chars are encoded.
		$this->assertSame('/baz%3F%23%E2%82%AC/b%61r', $uri->getPath());
		$this->assertSame('/baz%3F%23%E2%82%AC/b%61r', (string)$uri);
	}

	public function testWithQueryEncodesProperly() {
		$uri = $this->getUri()->withQuery('?=#&€=/&b%61r');
		// A query starting with a "?" is valid and must not be magically removed. Otherwise it would be impossible to
		// construct such an URI. Also the "?" and "/" does not need to be encoded in the query.
		$this->assertSame('?=%23&%E2%82%AC=/&b%61r', $uri->getQuery());
		$this->assertSame('??=%23&%E2%82%AC=/&b%61r', (string)$uri);
	}

	public function testWithFragmentEncodesProperly() {
		$uri = $this->getUri()->withFragment('#€?/b%61r');
		// A fragment starting with a "#" is valid and must not be magically removed. Otherwise it would be impossible to
		// construct such an URI. Also the "?" and "/" does not need to be encoded in the fragment.
		$this->assertSame('%23%E2%82%AC?/b%61r', $uri->getFragment());
		$this->assertSame('#%23%E2%82%AC?/b%61r', (string)$uri);
	}

	public function testAllowsForRelativeUri() {
		$uri = $this->getUri()->withPath('foo');
		$this->assertSame('foo', $uri->getPath());
		$this->assertSame('foo', (string)$uri);
	}

	public function testAddsSlashForRelativeUriStringWithHost() {
		// If the path is rootless and an authority is present, the path MUST
		// be prefixed by "/".
		$uri = $this->getUri()->withPath('foo')->withHost('example.com');
		$this->assertSame('foo', $uri->getPath());
		// concatenating a relative path with a host doesn't work: "//example.comfoo" would be wrong
		$this->assertSame('//example.com/foo', (string)$uri);
	}

	public function testRemoveExtraSlashesWihoutHost() {
		// If the path is starting with more than one "/" and no authority is
		// present, the starting slashes MUST be reduced to one.
		$uri = $this->getUri()->withPath('//foo');
		$this->assertSame('//foo', $uri->getPath());
		// URI "//foo" would be interpreted as network reference and thus change the original path to the host
		$this->assertSame('/foo', (string)$uri);
	}

	public function testDefaultReturnValuesOfGetters() {
		$uri = $this->getUri();

		$this->assertSame('', $uri->getScheme());
		$this->assertSame('', $uri->getAuthority());
		$this->assertSame('', $uri->getUserInfo());
		$this->assertSame('', $uri->getHost());
		$this->assertNull($uri->getPort());
		$this->assertSame('', $uri->getPath());
		$this->assertSame('', $uri->getQuery());
		$this->assertSame('', $uri->getFragment());
	}

	public function testSameUrlAndWithMethods() {
		$uri = $this->getUri('http://www.example.com/foo?foo=bar#bim');
		$this->assertSame($uri->withFragment('bim')->getFragment(),'bim');
		$this->assertSame($uri->withScheme('http')->getScheme(),'http');
		$this->assertSame($uri->withHost('www.example.com')->getHost(),'www.example.com');
		$this->assertSame($uri->withPath('/foo')->getPath(),'/foo');
		$this->assertSame($uri->withQuery('foo=bar')->getQuery(),'foo=bar');
		$this->assertSame($uri->withUserInfo('')->getUserInfo(''),'');
	}

	public function testGetDomain() {
		$uri = $this->getUri('https://user:pass@www.example.com:8080/path/123?q=abc#test');
		$this->assertSame('example.com', $uri->getDomain());
	}

	public function isLocalProvider() {
		return [
			["http",'www.redsnapper.net','/home?test=foo','http://www.redsnapper.net/home?test=foo#bar',true],
			["http",'www.redsnapper.net','/home?test=foo','/home?test=foo#bar',true],
			["http",'www.redsnapper.net',"",null,true],
			["http",'www.redsnapper.net',"","",false],
			["http",'www.redsnapper.net','/foobar?test=foo','http://www.google.net/home?test=foo#bar',false],
			["https",'www.redsnapper.net','/foobar?test=foo','https://www.redsnapper.net/home?test=foo#bar',true],
			["http",'www.redsnapper.net','/foobar?test=foo','https://www.redsnapper.net/home?test=foo#bar',false]
		];
	}

	/**
	 * @dataProvider isLocalProvider
	 */
	public function testIsLocal($https,$serverDomain,$requestUri,$url,$expected) {

		$server = $this->createMock('EnvServer');
		$map = [
			['REQUEST_URI',null, $requestUri],
			['HTTP_HOST',null, $serverDomain],
        ];
		$server->method('get')->will($this->returnValueMap($map));
		$server->method('getScheme')->willReturn($https);
		$uri = new Uri($url,$server,$this->log);
		$this->assertEquals($uri->isLocal(),$expected);

	}
	
	public function getLinkProvider() {
		return [
			["http",'www.redsnapper.net','/home?test=foo','http://www.redsnapper.net/home?test=foo#bar',"/home?test=foo#bar"],
			["http",'www.redsnapper.net','/home?test=foo','/home?test=foo#bar',"/home?test=foo#bar"],
			["http",'www.redsnapper.net',"",null,""],
			["http",'www.redsnapper.net',"","",""],
			["http",'www.redsnapper.net','/foobar?test=foo','http://www.google.net/home?test=foo#bar',"http://www.google.net/home?test=foo#bar"],
			["https",'www.redsnapper.net','/foobar?test=foo','https://www.redsnapper.net/home?test=foo#bar',"/home?test=foo#bar"],
			["http",'www.redsnapper.net','/foobar?test=foo','https://www.redsnapper.net/home?test=foo#bar',"https://www.redsnapper.net/home?test=foo#bar"]
		];
	}

	/**
	 * @dataProvider getLinkProvider
	 */
	public function testGetLink($https,$serverDomain,$requestUri,$url,$expected){
		$server = $this->createMock('EnvServer');
		$map = [
			['REQUEST_URI',null, $requestUri],
			['HTTP_HOST',null, $serverDomain],
		];
		$server->method('get')->will($this->returnValueMap($map));
		$server->method('getScheme')->willReturn($https);
		$uri = new Uri($url,$server,$this->log);
		$this->assertSame($uri->getLink(),$expected);
	}

	public function testMergeQuery(){

		$testuri = $this->getUri("http://www.example.com?foo=bar&baz=bim&bar=foo");
		$uri = $this->getUri("http://www.example.com?foo=bar&baz=bim");
		$uri = $uri->mergeQuery('bar=foo');
		$this->assertSame($uri->getQuery(),$testuri->getQuery());

		$uri = $this->getUri("http://www.example.com?foo=bim&baz=foo&bar=baz");
		$uri = $uri->mergeQuery('foo=bar&baz=bim&bar=foo');
		$this->assertSame($uri->getQuery(),$testuri->getQuery());

		$uri = $this->getUri("http://www.example.com?foo=baz&baz=bim");
		$uri2 = $this->getUri("http://www.example.com?foo=bar&bar=foo");

		$this->assertSame($uri->mergeQuery($uri2)->getQuery(),$testuri->getQuery());

	}


	public function testImmutability() {
		$uri = $this->getUri();
		$this->assertNotSame($uri, $uri->withScheme('https'));
		$this->assertNotSame($uri, $uri->withUserInfo('user', 'pass'));
		$this->assertNotSame($uri, $uri->withHost('example.com'));
		$this->assertNotSame($uri, $uri->withPort(8080));
		$this->assertNotSame($uri, $uri->withPath('/path/123'));
		$this->assertNotSame($uri, $uri->withQuery('q=abc'));
		$this->assertNotSame($uri, $uri->withFragment('test'));

	}

	protected function getUri($url=null) {
		return new Uri($url,$this->server,$this->log);
	}



}



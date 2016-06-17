<?php

/**
 * Class SphinxConnection
 */
class SphinxConnection extends MySqliConnection implements ConnectionInterface {

	public function esc(&$s) {
		if (mb_check_encoding($s)) {
			$from = array(
				'\\',
				'(',
				')',
				'|',
				'-',
				'!',
				'@',
				'~',
				'"',
				'&',
				'/',
				'^',
				'$',
				'=',
				"'",
				"\x00",
				"\n",
				"\r",
				"\x1a"
			);
			$to = array(
				'\\\\',
				'\\\(',
				'\\\)',
				'\\\|',
				'\\\-',
				'\\\!',
				'\\\@',
				'\\\~',
				'\\\"',
				'\\\&',
				'\\\/',
				'\\\^',
				'\\\$',
				'\\\=',
				"\\'",
				"\\x00",
				"\\n",
				"\\r",
				"\\x1a"
			);
			$s = str_replace($from, $to, $s);
		}
		return $s;
	}
}
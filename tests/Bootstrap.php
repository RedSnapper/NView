<?php

/**
 * Test bootstrap, for setting up autoloading
 *
 */
class Bootstrap {

	public static function init() {
		spl_autoload_register();
		require '../vendor/autoload.php';
	}
	
}

Bootstrap::init();